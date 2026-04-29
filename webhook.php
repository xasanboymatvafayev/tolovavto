<?php
require_once __DIR__ . '/config.php';
header("Content-Type: application/json");

// ============================================
// INPUT O'QISH
// ============================================
$raw = file_get_contents('php://input');
if (empty($raw) && !empty($_POST)) {
    $raw = json_encode($_POST);
}

// LOG (kerak bo'lmasa o'chirish mumkin)
$log  = date('Y-m-d H:i:s') . "\n";
$log .= "RAW:\n" . $raw . "\n\n===\n\n";
file_put_contents(__DIR__ . '/log.txt', $log, FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$plain      = $data['plain'] ?? '';
$html       = $data['html']  ?? '';
$body       = $plain . " " . strip_tags($html);
$subject    = $data['headers']['subject'] ?? '';
$from       = $data['envelope']['from']   ?? '';
$message_id = $data['headers']['message_id'] ?? $data['headers']['message-id'] ?? uniqid();

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id  = getenv('ADMIN_ID') ?: "6365371142";

// ============================================
// YORDAMCHI FUNKSIYALAR
// ============================================
function sendTg($text, $bot_token, $chat_id) {
    $ch = curl_init("https://api.telegram.org/bot$bot_token/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['chat_id'=>$chat_id,'text'=>$text,'parse_mode'=>'HTML']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_NOSIGNAL       => 1,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}

function sendWebhook($url, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_NOSIGNAL       => 1,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ============================================
// TASDIQLASH LINKI
// ============================================
$confirm_url = '';
preg_match_all('/https?:\/\/[^\s"\'<>]+/', $plain . " " . $html, $lm);
foreach ($lm[0] as $l) {
    if (stripos($l,'verify')!==false || stripos($l,'confirm')!==false ||
        stripos($l,'activate')!==false || stripos($l,'token')!==false) {
        $confirm_url = $l;
        sendTg("🔗 <b>Tasdiqlash linki:</b>\n$l", $bot_token, $admin_id);
        break;
    }
}

// ============================================
// SUMMA VA BALANS
// ============================================
$body_norm = preg_replace('/(\d)\s(\d{3})(?=[\s,.]|UZS|$)/', '$1$2', $body);

if (!preg_match(
    '/summa\s*:\s*([\d\s]+(?:[.,]\d+)?)\s*UZS.*?balans\s*:\s*([\d\s]+(?:[.,]\d+)?)\s*UZS/is',
    $body_norm, $sm
)) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'no payment found']);
    exit;
}

$amount  = (int)round((float)str_replace([',',' '],['.',''],$sm[1]));
$balance = (int)round((float)str_replace([',',' '],['.',''],$sm[2]));

if ($amount <= 0) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'zero amount']);
    exit;
}

// ============================================
// KARTA OXIRGI 4 RAQAM
// ============================================
$card_last = '****';
if (preg_match('/karta\s+\*+\s*(\d{4})/i', $body, $cm)) {
    $card_last = $cm[1];
}

// ============================================
// SANA
// ============================================
$op_date = date('d.m.Y H:i');
if (preg_match('/(\d{2}\.\d{2}\.\d{2})\s+(\d{2}:\d{2})/i', $body, $dm)) {
    $dp = explode('.', $dm[1]);
    $op_date = $dp[0] . '.' . $dp[1] . '.20' . $dp[2] . ' ' . $dm[2];
}

// ============================================
// TIP ANIQLASH
// ============================================
// Real email formatlar (log dan aniqlangan):
//
//   KIRIM:  "Perevod na kartu: OPENBANK HUMO UZCARD P2P, UZ"
//   CHIQIM: "Platezh: OPENBANK UZCARD POPOLN SCHETA, UZ"
//   CHIQIM: "Perevod na kartu: OPENBANK SCHET TO UZCARD, UZ"
//
// QOIDA:
//   P2P so'zi bor       → KIRIM
//   SCHET TO UZCARD     → CHIQIM (OpenBank → UzCard o'tkazma)
//   Platezh:            → CHIQIM
//   SPISANIE            → CHIQIM
//   ZACHISLENIE         → KIRIM
//   Perevod na kartu (P2P yo'q, SCHET TO yo'q) → KIRIM

$is_p2p      = (bool)preg_match('/HUMO\s+UZCARD\s+P2P|P2P\s+UZCARD|\bP2P\b/i', $body);
$is_schet_to = (bool)preg_match('/SCHET\s+TO\s+UZCARD/i', $body);
$is_platezh  = (bool)preg_match('/Platezh\s*:/i', $body);
$is_spisanie = (bool)preg_match('/SPISANIE/i', $body);
$is_zach     = (bool)preg_match('/ZACHISLENIE|zachisleno|postuplenie/i', $body);
$is_perevod  = (bool)preg_match('/Perevod\s+na\s+kartu\s*:/i', $body);

if ($is_p2p) {
    $type = 'credit';  // P2P kirim
} elseif ($is_schet_to) {
    $type = 'debit';   // OpenBank → UzCard — CHIQIM
} elseif ($is_platezh || $is_spisanie) {
    $type = 'debit';   // To'lov — CHIQIM
} elseif ($is_zach) {
    $type = 'credit';  // Hisobga tushirildi — KIRIM
} elseif ($is_perevod) {
    $type = 'credit';  // Boshqa perevod — KIRIM
} else {
    $type = 'debit';   // Noma'lum — xavfsiz tomon
}

// ============================================
// MERCHANT — KIMDAN / QAYERGA
// ============================================
// UzCard emailida haqiqiy yuboruvchi/qabul qiluvchi nomi yo'q.
// Faqat texnik so'zlar bor: OPENBANK, HUMO, UZCARD, P2P, SCHET TO va h.k.
// Shuning uchun har bir holat uchun mazmunli tavsif yozamiz.
//
// REAL FORMATLAR:
//   "Perevod na kartu: OPENBANK HUMO UZCARD P2P, UZ"   → HUMO kartadan P2P kirim
//   "Platezh: OPENBANK UZCARD POPOLN SCHETA, UZ"        → UzCard hisobni to'ldirish (chiqim)
//   "Perevod na kartu: OPENBANK SCHET TO UZCARD, UZ"    → UzCard → OpenBank o'tkazma (chiqim)
//   "ZACHISLENIE ..."                                    → Hisobga o'tkazildi (kirim)

if ($type === 'credit') {
    $merchant = "Kirim";
} else {
    $merchant = "Chiqim";
}

// Tip labellari
if ($type === 'credit') {
    if ($is_p2p)      { $type_text = "🟢 Kirim (P2P o'tkazma)";         $sign = "➕"; }
    elseif ($is_zach) { $type_text = "🟢 Kirim (Hisobga tushirildi)";   $sign = "➕"; }
    else              { $type_text = "🟢 Kirim (O'tkazma)";             $sign = "➕"; }
} else {
    if ($is_schet_to)    { $type_text = "🔴 Chiqim (OpenBank → UzCard)"; $sign = "➖"; }
    elseif ($is_platezh) { $type_text = "🔴 Chiqim (To'lov)";            $sign = "➖"; }
    elseif ($is_spisanie){ $type_text = "🔴 Chiqim (Hisobdan chiqarish)";}
    else                 { $type_text = "🔴 Chiqim";                     $sign = "➖"; }
}

// ============================================
// DUPLICATE TEKSHIRUV
// ============================================
$mid_esc = mysqli_real_escape_string($connect, $message_id);
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id='$mid_esc'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// shop_id ustuni yo'q bo'lsa qo'shish
$col_shop = mysqli_query($connect, "SHOW COLUMNS FROM payments LIKE 'shop_id'");
if (mysqli_num_rows($col_shop) == 0) {
    mysqli_query($connect, "ALTER TABLE payments ADD COLUMN shop_id varchar(50) DEFAULT NULL");
}

// ============================================
// KARTA ORQALI KASSANI ANIQLASH
// ============================================
$payment_shop_id = null;
if ($card_last !== '****') {
    $all_shops = mysqli_query($connect,
        "SELECT shop_id, card_number FROM shops WHERE card_number IS NOT NULL AND card_number != ''"
    );
    while ($sh = mysqli_fetch_assoc($all_shops)) {
        if (substr(preg_replace('/\s+/', '', $sh['card_number']), -4) === $card_last) {
            $payment_shop_id = $sh['shop_id'];
            break;
        }
    }
}
$payment_shop_esc = $payment_shop_id
    ? mysqli_real_escape_string($connect, $payment_shop_id)
    : null;

// ============================================
// DB GA SAQLASH
// ============================================
$ins = mysqli_query($connect,
    "INSERT INTO payments (message_id, amount, merchant, date, card_type, raw_message, status, created_at"
    . ($payment_shop_esc ? ", shop_id" : "") . ")
     VALUES (
       '$mid_esc', '$amount',
       '" . mysqli_real_escape_string($connect, $merchant) . "',
       '" . mysqli_real_escape_string($connect, $op_date) . "',
       '$type',
       '" . mysqli_real_escape_string($connect, mb_substr($body, 0, 500)) . "',
       'pending', NOW()" . ($payment_shop_esc ? ", '$payment_shop_esc'" : "") . "
     )"
);

if ($ins) {
    $new_payment_id = mysqli_insert_id($connect);
    $amt_fmt = number_format($amount, 0, '.', ' ');
    $bal_fmt = number_format($balance, 0, '.', ' ');

    // ============================================
    // KIRIM — checkout bilan bog'lash
    // ============================================
    if ($type === 'credit') {
        $expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $checkout_res = mysqli_query($connect,
            "SELECT * FROM checkout
             WHERE amount='$amount' AND status='pending' AND date > '$expire_time'
             ORDER BY date ASC LIMIT 1"
        );

        if ($checkout_res && mysqli_num_rows($checkout_res) > 0) {
            $crow        = mysqli_fetch_assoc($checkout_res);
            $checkout_id = $crow['id'];
            $ch_order    = $crow['order'];
            $ch_shop     = $crow['shop_id'];
            $ch_user     = $crow['user_id'] ?? null;

            // Checkout → paid
            $col_check = mysqli_query($connect, "SHOW COLUMNS FROM checkout LIKE 'paid_to_user'");
            if (mysqli_num_rows($col_check) > 0) {
                mysqli_query($connect, "UPDATE checkout SET status='paid', paid_to_user='1' WHERE id='$checkout_id'");
            } else {
                mysqli_query($connect, "UPDATE checkout SET status='paid' WHERE id='$checkout_id'");
            }

            // Payment → used
            mysqli_query($connect,
                "UPDATE payments SET status='used',
                 used_order='" . mysqli_real_escape_string($connect, $ch_order) . "',
                 shop_id='"    . mysqli_real_escape_string($connect, $ch_shop)  . "'
                 WHERE id='$new_payment_id'"
            );

            // Foydalanuvchi balansini oshirish + xabar
            if (!empty($ch_user)) {
                $user_esc = mysqli_real_escape_string($connect, $ch_user);
                $upd = mysqli_query($connect,
                    "UPDATE users SET balance=balance+$amount, deposit=deposit+$amount WHERE user_id='$user_esc'"
                );
                if ($upd && mysqli_affected_rows($connect) > 0) {
                    sendTg(
                        "✅ <b>To'lov tasdiqlandi!</b>\n\n" .
                        "💵 <b>" . number_format($amount, 0, '.', ' ') . " UZS</b> hisobingizga avtomatik qo'shildi!",
                        $bot_token, $ch_user
                    );
                }
            }

            // Shop balansi
            $shop_esc = mysqli_real_escape_string($connect, $ch_shop);
            mysqli_query($connect,
                "UPDATE shops SET shop_balance=shop_balance+$amount WHERE shop_id='$shop_esc'"
            );

            // Shop egasi va webhook
            $srow = mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT webhook_url, user_id, shop_name FROM shops WHERE shop_id='$shop_esc'"
            ));
            if ($srow) {
                $shop_owner_id  = $srow['user_id']     ?? null;
                $shop_wh_url    = $srow['webhook_url'] ?? null;
                $shop_name_show = $srow['shop_name']
                    ? base64_decode($srow['shop_name'])
                    : $ch_shop;

                if (!empty($shop_wh_url)) {
                    sendWebhook($shop_wh_url, [
                        'status'      => 'paid',
                        'order'       => $ch_order,
                        'amount'      => $amount,
                        'merchant'    => $merchant,
                        'date'        => $op_date,
                        'confirm_url' => $confirm_url,
                    ]);
                }

                if (!empty($shop_owner_id) && $shop_owner_id !== $ch_user) {
                    sendTg(
                        "💰 <b>Yangi to'lov!</b>\n" .
                        "🏪 Kassa: <b>" . htmlspecialchars($shop_name_show) . "</b>\n" .
                        "━━━━━━━━━━━━━━\n" .
                        "➕ Summa: <b>" . number_format($amount, 0, '.', ' ') . " UZS</b>\n" .
                        "🔖 Order: <code>$ch_order</code>\n" .
                        "🏦 Kimdan: <b>" . htmlspecialchars($merchant) . "</b>\n" .
                        "🕒 Vaqt: $op_date",
                        $bot_token, $shop_owner_id
                    );
                }
            }
        }
    }

    // ============================================
    // ADMIN XABARI
    // ============================================

    $msg  = "🔔 <b>UZCARD Bildirishnomasi</b>\n";
    $msg .= "$type_text\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "$sign Summa: <b>$amt_fmt UZS</b>\n";
    $msg .= "💳 Karta: **** $card_last\n";
    $msg .= "🕒 Vaqt: $op_date\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "💵 Qoldiq: <b>$bal_fmt UZS</b>";

    sendTg($msg, $bot_token, $admin_id);
}

http_response_code(200);
echo json_encode(['status' => 'success', 'amount' => $amount, 'type' => $type, 'merchant' => $merchant]);
?>

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

// LOG (vaqtinchalik — kerak bo'lmasa o'chirish mumkin)
$log  = date('Y-m-d H:i:s') . "\n";
$log .= "METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n";
$log .= "CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'yoq') . "\n";
$log .= "RAW_LENGTH: " . strlen($raw) . "\n";
$log .= "POST_KEYS: " . implode(', ', array_keys($_POST)) . "\n";
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
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON', 'raw_length' => strlen($raw)]);
    exit;
}

$plain      = $data['plain'] ?? '';
$html       = $data['html'] ?? '';
$body       = $plain . " " . strip_tags($html);
$subject    = $data['headers']['subject'] ?? '';
$from       = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message_id'] ?? $data['headers']['message-id'] ?? uniqid();

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id  = getenv('ADMIN_ID') ?: "6365371142";

// ============================================
// YORDAMCHI FUNKSIYALAR
// ============================================
function sendTelegramAsync($text, $bot_token, $chat_id) {
    $ch = curl_init("https://api.telegram.org/bot$bot_token/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => json_encode([
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => 'HTML'
        ]),
        CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT         => 5,
        CURLOPT_CONNECTTIMEOUT  => 2,
        CURLOPT_NOSIGNAL        => 1,
        CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_2_0,
        CURLOPT_TCP_NODELAY     => true,
        CURLOPT_DNS_CACHE_TIMEOUT => 600,
        CURLOPT_FORBID_REUSE    => false,
        CURLOPT_FRESH_CONNECT   => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function sendWebhookAsync($url, $payload_arr) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload_arr),
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
$search_in = $plain . " " . $html;
preg_match_all('/https?:\/\/[^\s"\'<>]+/', $search_in, $lm);
foreach ($lm[0] as $l) {
    if (stripos($l, 'verify') !== false || stripos($l, 'confirm') !== false ||
        stripos($l, 'activate') !== false || stripos($l, 'token') !== false ||
        stripos($l, 'email') !== false) {
        $confirm_url = $l;
        sendTelegramAsync("🔗 <b>Tasdiqlash linki:</b>\n$l", $bot_token, $admin_id);
        break;
    }
}

// ============================================
// SUMMA VA BALANS
// ============================================
// Minglik bo'shliqli formatni normallashtirish: "50 000" => "50000"
$body_normalized = preg_replace('/(\d)\s(\d{3})(?=[\s,.]|UZS|$)/', '$1$2', $body);

if (!preg_match('/summa\s*:\s*([\d\s]+(?:[.,]\d+)?)\s*UZS.*?balans\s*:\s*([\d\s]+(?:[.,]\d+)?)\s*UZS/is', $body_normalized, $sm)) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'no payment found']);
    exit;
}

$amount  = (int)round((float)str_replace([',', ' '], ['.', ''], $sm[1]));
$balance = (int)round((float)str_replace([',', ' '], ['.', ''], $sm[2]));

if ($amount <= 0) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'zero amount']);
    exit;
}

// Karta oxirgi 4 raqam
$card_last = '****';
if (preg_match('/karta\s+\*+(\d{4})/i', $body, $cm)) {
    $card_last = $cm[1];
}

// Sana
$op_date = date('d.m.Y H:i');
if (preg_match('/(\d{2}\.\d{2}\.\d{2})\s+(\d{2}:\d{2})/i', $body, $dm)) {
    $date_parts = explode('.', $dm[1]);
    $op_date = $date_parts[0] . '.' . $date_parts[1] . '.20' . $date_parts[2] . ' ' . $dm[2];
}

// ============================================
// TIP ANIQLASH
//
// KIRIM (pul KARTA ga tushdi):
//   "Perevod na kartu: OPENBANK HUMO UZCARD P2P" — P2P o'tkazma
//   "ZACHISLENIE / zachisleno / postuplenie"       — hisobga tushirildi
//   "Perevod na kartu:" — SCHET TO va P2P yo'q    — karta o'tkazmasi
//
// CHIQIM (pul KARTADAN ketdi):
//   "Perevod na kartu: OPENBANK SCHET TO UZCARD"  — OpenBank→UzCard
//   "Platezh: ... POPOLN SCHETA"                  — hisob to'ldirish
//   "Platezh:" yoki "SPISANIE"                    — to'lov/chiqarish
// ============================================

// Belgilar
$is_p2p         = (bool)preg_match('/HUMO\s+UZCARD\s+P2P|P2P\s+UZCARD/i', $body);
$is_zachislenie = (bool)preg_match('/ZACHISLENIE|zachisleno|postuplenie/i', $body);
$is_perevod     = (bool)preg_match('/Perevod\s+na\s+kartu\s*:/i', $body);
$is_schet_to    = (bool)preg_match('/SCHET\s+TO\s+UZCARD/i', $body);
$is_popoln_plat = (bool)preg_match('/Platezh.*POPOLN\s+SCHETA/i', $body);
$is_platezh     = (bool)preg_match('/Platezh\s*:/i', $body);
$is_spisanie    = (bool)preg_match('/SPISANIE/i', $body);

// Avval KIRIM belgilarini tekshir (P2P, zachislenie — aniq kirim)
if ($is_p2p) {
    $type      = 'credit';
    $type_text = "🟢 Kirim (P2P o'tkazma)";
    $sign      = "➕";
} elseif ($is_zachislenie) {
    $type      = 'credit';
    $type_text = "🟢 Kirim (Hisobga tushirildi)";
    $sign      = "➕";
} elseif ($is_schet_to) {
    // "Perevod na kartu: OPENBANK SCHET TO UZCARD" — CHIQIM
    $type      = 'debit';
    $type_text = "🔴 Chiqim (OpenBank → UzCard o'tkazma)";
    $sign      = "➖";
} elseif ($is_platezh && $is_popoln_plat) {
    $type      = 'debit';
    $type_text = "🔴 Chiqim (o'tkazma)";
    $sign      = "➖";
} elseif ($is_platezh || $is_spisanie) {
    $type      = 'debit';
    $type_text = $is_spisanie ? "🔴 Chiqim (Hisobdan chiqarish)" : "🔴 Chiqim (To'lov)";
    $sign      = "➖";
} elseif ($is_perevod) {
    // Perevod na kartu — SCHET TO yo'q, P2P yo'q => KIRIM
    $type      = 'credit';
    $type_text = "🟢 Kirim (Karta o'tkazmasi)";
    $sign      = "➕";
} else {
    // Default
    $type      = 'debit';
    $type_text = "🔴 Chiqim (To'lov)";
    $sign      = "➖";
}

// ============================================
// MERCHANT ANIQLASH
// Real nom: "Platezh: OPENBANK UZCARD POPOLN SCHETA, UZ"
//           "Perevod na kartu: OPENBANK HUMO UZCARD P2P, UZ"
// OPENBANK — bu faqat bank nomi, uni olib tashlaymiz
// Qolgan qism — merchant/qabul qiluvchi
// ============================================
$merchant = '';

// Texnik so'zlarni tozalovchi funksiya
function cleanMerchant($raw) {
    $remove = [
        '/\bOPENBANK\b/i',
        '/\bHUMO\s+UZCARD\s+P2P\b/i',
        '/\bUZCARD\s+P2P\b/i',
        '/\bP2P\b/i',
        '/\bSCHET\s+TO\s+UZCARD\b/i',
        '/\bUZCARD\s+POPOLN\s+SCHETA\b/i',
        '/\bPOPOLN\s+SCHETA\b/i',
        '/\bONLINE\s+TRANSFER\b/i',
        '/\bUZCARD\s+ONLINE\b/i',
        '/\bUZCARD\b/i',
        '/\bHUMO\b/i',
    ];
    $cleaned = preg_replace($remove, '', $raw);
    $cleaned = trim(preg_replace('/\s{2,}/', ' ', $cleaned));
    $cleaned = trim($cleaned, ', ');
    return $cleaned;
}

// 1. "Platezh: <MERCHANT>, UZ"
if (preg_match('/Platezh\s*:\s*([^,\n]+?)(?:\s*,\s*[A-Z]{2})?\s*(?:,\s*\d|\s+summa\s*:|$)/i', $body, $mm)) {
    $cleaned = cleanMerchant(trim($mm[1]));
    if (!empty($cleaned)) $merchant = $cleaned;
}

// 2. "Perevod na kartu: <MERCHANT>, UZ"
if (empty($merchant) && $is_perevod) {
    if (preg_match('/Perevod\s+na\s+kartu\s*:\s*([^,\n]+?)(?:\s*,\s*[A-Z]{2})?\s*(?:,\s*\d|\s+summa\s*:|$)/i', $body, $mm)) {
        $cleaned = cleanMerchant(trim($mm[1]));
        if (!empty($cleaned)) $merchant = $cleaned;
    }
}

// 3. ZACHISLENIE keyin kelgan nom
if (empty($merchant)) {
    if (preg_match('/(?:ZACHISLENIE|POPOLN\s+SCHETA)\s+([A-Z][A-Z0-9\s]{2,30}?)(?:\s+summa|\s*,|\s*$)/i', $body, $mm)) {
        $cleaned = cleanMerchant(trim($mm[1]));
        if (!empty($cleaned)) $merchant = $cleaned;
    }
}

// 4. Fallback — tip asosida
if (empty($merchant)) {
    if ($is_schet_to)           $merchant = "UzCard o'tkazma";
    elseif ($is_p2p)            $merchant = "P2P o'tkazma";
    elseif ($type === 'credit') $merchant = "Noma'lum (kirim)";
    else                        $merchant = "Noma'lum (chiqim)";
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

// Karta oxirgi 4 raqami orqali kassani aniqlash
$payment_shop_id = null;
if ($card_last !== '****') {
    $all_shops = mysqli_query($connect,
        "SELECT shop_id, card_number FROM shops WHERE card_number IS NOT NULL AND card_number != ''"
    );
    while ($sh = mysqli_fetch_assoc($all_shops)) {
        $clean_card = preg_replace('/\s+/', '', $sh['card_number']);
        if (substr($clean_card, -4) === $card_last) {
            $payment_shop_id = $sh['shop_id'];
            break;
        }
    }
}
$payment_shop_esc = $payment_shop_id ? mysqli_real_escape_string($connect, $payment_shop_id) : null;

// ============================================
// DB GA SAQLASH
// ============================================
$ins = mysqli_query($connect, "INSERT INTO payments
(message_id, amount, merchant, date, card_type, raw_message, status, created_at"
. ($payment_shop_esc ? ", shop_id" : "") . ")
VALUES (
'" . $mid_esc . "',
'" . $amount . "',
'" . mysqli_real_escape_string($connect, $merchant) . "',
'" . mysqli_real_escape_string($connect, $op_date) . "',
'" . $type . "',
'" . mysqli_real_escape_string($connect, mb_substr($body, 0, 500)) . "',
'pending',
NOW()" . ($payment_shop_esc ? ", '$payment_shop_esc'" : "") . "
)");

if ($ins) {
    $new_payment_id = mysqli_insert_id($connect);
    $amt_fmt = number_format($amount, 0, '.', ' ');
    $bal_fmt = number_format($balance, 0, '.', ' ');

    // ============================================
    // KIRIM — checkout bilan bog'lash + BALANS
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

            $col_check = mysqli_query($connect, "SHOW COLUMNS FROM checkout LIKE 'paid_to_user'");
            if (mysqli_num_rows($col_check) > 0) {
                mysqli_query($connect, "UPDATE checkout SET status='paid', paid_to_user='1' WHERE id='$checkout_id'");
            } else {
                mysqli_query($connect, "UPDATE checkout SET status='paid' WHERE id='$checkout_id'");
            }

            mysqli_query($connect,
                "UPDATE payments SET status='used', used_order='" . mysqli_real_escape_string($connect, $ch_order) . "', shop_id='" . mysqli_real_escape_string($connect, $ch_shop) . "' WHERE id='$new_payment_id'"
            );

            if (!empty($ch_user)) {
                $user_esc = mysqli_real_escape_string($connect, $ch_user);
                $upd = mysqli_query($connect,
                    "UPDATE users SET balance=balance+$amount, deposit=deposit+$amount WHERE user_id='$user_esc'"
                );
                if ($upd && mysqli_affected_rows($connect) > 0) {
                    sendTelegramAsync(
                        "✅ <b>To'lov tasdiqlandi!</b>\n\n💵 <b>" . number_format($amount, 0, '.', ' ') . "</b> so'm hisobingizga avtomatik qo'shildi!",
                        $bot_token,
                        $ch_user
                    );
                }
            }

            $shop_esc = mysqli_real_escape_string($connect, $ch_shop);
            mysqli_query($connect,
                "UPDATE shops SET shop_balance=shop_balance+$amount WHERE shop_id='$shop_esc'"
            );

            $shops_row = mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT webhook_url, user_id, shop_name FROM shops WHERE shop_id='$shop_esc'"
            ));

            if ($shops_row) {
                $shop_owner_id  = $shops_row['user_id']  ?? null;
                $shop_wh_url    = $shops_row['webhook_url'] ?? null;
                $shop_name_raw  = $shops_row['shop_name'] ?? '';
                $shop_name_show = $shop_name_raw ? base64_decode($shop_name_raw) : $ch_shop;

                if (!empty($shop_wh_url)) {
                    sendWebhookAsync($shop_wh_url, [
                        'status'      => 'paid',
                        'order'       => $ch_order,
                        'amount'      => $amount,
                        'merchant'    => $merchant,
                        'date'        => $op_date,
                        'confirm_url' => $confirm_url,
                    ]);
                }

                if (!empty($shop_owner_id) && $shop_owner_id !== $ch_user) {
                    $owner_msg  = "💰 <b>Yangi to'lov!</b>\n";
                    $owner_msg .= "🏪 Kassa: <b>" . htmlspecialchars($shop_name_show) . "</b>\n";
                    $owner_msg .= "━━━━━━━━━━━━━━\n";
                    $owner_msg .= "➕ Summa: <b>" . number_format($amount, 0, '.', ' ') . " UZS</b>\n";
                    $owner_msg .= "🔖 Order: <code>$ch_order</code>\n";
                    $owner_msg .= "🏪 Kimdan: <b>$merchant</b>\n";
                    $owner_msg .= "🕒 Vaqt: $op_date";
                    sendTelegramAsync($owner_msg, $bot_token, $shop_owner_id);
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
    $msg .= "🏪 " . ($type === 'credit' ? "Kimdan" : "Qayerga") . ": <b>" . htmlspecialchars($merchant) . "</b>\n";
    $msg .= "🕒 Vaqt: $op_date\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "💵 Qoldiq: <b>$bal_fmt UZS</b>";

    sendTelegramAsync($msg, $bot_token, $admin_id);
}

http_response_code(200);
echo json_encode(['status' => 'success', 'amount' => $amount, 'type' => $type, 'merchant' => $merchant]);

// Email provayderiga darhol 200 qaytarib, qolgan ishni background da qilamiz
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

// ============================================
// BARCHA ISHLAR TUGADI
// ============================================
?>

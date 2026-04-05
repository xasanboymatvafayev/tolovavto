<?php
require_once __DIR__ . '/config.php';
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$plain      = $data['plain'] ?? '';
$html       = $data['html'] ?? '';
$body       = $plain . " " . strip_tags($html);
$subject    = $data['headers']['subject'] ?? '';
$from       = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message-id'] ?? uniqid();

// Debug log
file_put_contents(__DIR__ . "/debug_email.txt",
    "=== " . date('Y-m-d H:i:s') . " ===\nFROM: $from\nSUBJECT: $subject\nBODY:\n$body\n\n",
    FILE_APPEND
);

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id  = getenv('ADMIN_ID') ?: "6365371142";

// ==================================================
// ASYNC (fire-and-forget) curl — javobni kutmaydi
// ==================================================
function sendTelegramAsync($text, $bot_token, $chat_id) {
    $ch = curl_init("https://api.telegram.org/bot$bot_token/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => 'HTML'
        ]),
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_NOSIGNAL       => 1,
    ]);
    curl_exec($ch);
    curl_close($ch);
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

// Tasdiqlash linklar
preg_match_all('/https?:\/\/[^\s"\'<>]+/', $body, $lm);
foreach ($lm[0] as $l) {
    if (stripos($l,'verify')!==false || stripos($l,'confirm')!==false ||
        stripos($l,'activate')!==false || stripos($l,'token')!==false) {
        sendTelegramAsync("🔗 <b>Tasdiqlash linki:</b>\n$l", $bot_token, $admin_id);
        break;
    }
}

// ============================================
// UZCARD EMAIL PARSE
// ============================================

if (!preg_match('/summa:([\d]+(?:[.,]\d+)?)\s*UZS\s+balans:([\d]+(?:[.,]\d+)?)\s*UZS/i', $body, $sm)) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'no payment found']);
    exit;
}

$amount  = (int)round((float)str_replace(',', '.', $sm[1]));
$balance = (int)round((float)str_replace(',', '.', $sm[2]));

if ($amount <= 0) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'zero amount']);
    exit;
}

$card_last = '****';
if (preg_match('/karta\s+\*+(\d{4})/i', $body, $cm)) {
    $card_last = $cm[1];
}

$op_date = date('d.m.Y H:i');
if (preg_match('/(\d{2}\.\d{2}\.\d{2})\s+(\d{2}:\d{2})/i', $body, $dm)) {
    $date_parts = explode('.', $dm[1]);
    $op_date = $date_parts[0] . '.' . $date_parts[1] . '.20' . $date_parts[2] . ' ' . $dm[2];
}

$type      = 'debit';
$type_text = "🔴 Chiqim (To'lov)";
$sign      = "➖";
$merchant  = "Noma'lum";

if      (preg_match('/Platezh:/i', $body))              { $type='debit';  $type_text="🔴 Chiqim (To'lov)";             $sign="➖"; }
elseif  (preg_match('/Perevod\s+na\s+kartu:/i', $body)) { $type='credit'; $type_text="🟢 Kirim (O'tkazma olindi)";     $sign="➕"; }
elseif  (preg_match('/POPOLN\s+SCHETA/i', $body))       { $type='credit'; $type_text="🟢 Kirim (Hisob to'ldirildi)";  $sign="➕"; }
elseif  (preg_match('/SPISANIE/i', $body))               { $type='debit';  $type_text="🔴 Chiqim (Hisobdan chiqarish)",$sign="➖"; }
elseif  (preg_match('/ZACHISLENIE/i', $body))            { $type='credit'; $type_text="🟢 Kirim (Hisobga tushirildi)"; $sign="➕"; }

if (preg_match('/(?:Platezh|Perevod na kartu|Perevod):\s*([^,]+(?:,[^,]+)?),\s*(?:UZ|KZ|RU)/i', $body, $mm)) {
    $raw_merchant = trim($mm[1]);
    $raw_merchant = preg_replace('/\s*UZCARD\s*POPOLN\s*SCHETA/i', '', $raw_merchant);
    $raw_merchant = preg_replace('/\s*SCHET\s*TO\s*UZCARD/i', '', $raw_merchant);
    $merchant = trim($raw_merchant) ?: 'OPENBANK';
}
if ($merchant == "Noma'lum" && preg_match('/POPOLN\s+SCHETA/i', $body)) {
    $merchant = "UzCard hisobni to'ldirish";
}

// Duplicate tekshiruv
$mid_esc = mysqli_real_escape_string($connect, $message_id);
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id='$mid_esc'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// DB ga saqlash
$ins = mysqli_query($connect, "INSERT INTO payments
(message_id, amount, merchant, date, card_type, raw_message, status, created_at)
VALUES (
'$mid_esc',
'$amount',
'" . mysqli_real_escape_string($connect, $merchant) . "',
'" . mysqli_real_escape_string($connect, $op_date) . "',
'$type',
'" . mysqli_real_escape_string($connect, mb_substr($body, 0, 500)) . "',
'pending',
NOW()
)");

if ($ins) {
    $new_payment_id = mysqli_insert_id($connect);
    $amt_fmt = number_format($amount, 0, '.', ' ');
    $bal_fmt = number_format($balance, 0, '.', ' ');

    // ============================================
    // KIRIM bo'lsa — checkout bilan bog'lash + BALANS QO'SHISH
    // ============================================
    if ($type === 'credit') {
        $expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $checkout_res = mysqli_query($connect,
            "SELECT * FROM checkout
             WHERE amount='$amount' AND status='pending' AND date > '$expire_time'
             ORDER BY date ASC LIMIT 1"
        );

        if ($checkout_res && mysqli_num_rows($checkout_res) > 0) {
            $crow         = mysqli_fetch_assoc($checkout_res);
            $checkout_id  = $crow['id'];
            $ch_order     = $crow['order'];
            $ch_shop      = $crow['shop_id'];
            $ch_user      = $crow['user_id'] ?? null;

            // FIX: paid_to_user ustuni mavjudligini tekshir
            $col_check = mysqli_query($connect, "SHOW COLUMNS FROM checkout LIKE 'paid_to_user'");
            if (mysqli_num_rows($col_check) > 0) {
                mysqli_query($connect, "UPDATE checkout SET status='paid', paid_to_user='1' WHERE id='$checkout_id'");
            } else {
                mysqli_query($connect, "UPDATE checkout SET status='paid' WHERE id='$checkout_id'");
            }

            // Payment ni used qilish
            mysqli_query($connect,
                "UPDATE payments SET status='used', used_order='" . mysqli_real_escape_string($connect, $ch_order) . "' WHERE id='$new_payment_id'"
            );

            // === BALANS QO'SHISH (FIX: har doim user_id bo'lsa qo'shish) ===
            if (!empty($ch_user)) {
                $user_esc = mysqli_real_escape_string($connect, $ch_user);
                $upd = mysqli_query($connect,
                    "UPDATE users SET balance=balance+$amount, deposit=deposit+$amount WHERE user_id='$user_esc'"
                );
                // Foydalanuvchiga xabar (async, kutmaydi)
                if ($upd && mysqli_affected_rows($connect) > 0) {
                    $bot_local = getenv('TELEGRAM_BOT_TOKEN');
                    if ($bot_local) {
                        sendTelegramAsync(
                            "✅ <b>To'lov tasdiqlandi!</b>\n\n💵 <b>" . number_format($amount, 0, '.', ' ') . "</b> so'm hisobingizga avtomatik qo'shildi!",
                            $bot_local,
                            $ch_user
                        );
                    }
                }
            }

            // Do'kon balansini oshirish
            $shop_esc = mysqli_real_escape_string($connect, $ch_shop);
            mysqli_query($connect,
                "UPDATE shops SET shop_balance=shop_balance+$amount WHERE shop_id='$shop_esc'"
            );
            // Do'kon webhook (async, kutmaydi)
            $shops_row = mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT webhook_url FROM shops WHERE shop_id='$shop_esc'"
            ));
            if ($shops_row && !empty($shops_row['webhook_url'])) {
                sendWebhookAsync($shops_row['webhook_url'], [
                    'status'   => 'paid',
                    'order'    => $ch_order,
                    'amount'   => $amount,
                    'merchant' => $merchant,
                    'date'     => $op_date,
                ]);
            }
        }
    }

    // Admin Telegram xabar (async)
    $msg  = "🔔 <b>UZCARD Bildirishnomasi</b>\n";
    $msg .= "$type_text\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "$sign Summa: <b>$amt_fmt UZS</b>\n";
    $msg .= "💳 Karta: **** $card_last\n";
    $msg .= "🏪 " . ($type === 'credit' ? "Kimdan" : "Qayerga") . ": <b>$merchant</b>\n";
    $msg .= "🕒 Vaqt: $op_date\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "💵 Qoldiq: <b>$bal_fmt UZS</b>";

    sendTelegramAsync($msg, $bot_token, $admin_id);
}

http_response_code(200);
echo json_encode(['status' => 'success', 'amount' => $amount, 'type' => $type, 'merchant' => $merchant]);
?>

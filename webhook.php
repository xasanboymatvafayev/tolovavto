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

function sendTelegram($text, $bot_token, $chat_id) {
    $ch = curl_init("https://api.telegram.org/bot$bot_token/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML'
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Tasdiqlash linklar
preg_match_all('/https?:\/\/[^\s"\'<>]+/', $body, $lm);
foreach ($lm[0] as $l) {
    if (stripos($l,'verify')!==false || stripos($l,'confirm')!==false ||
        stripos($l,'activate')!==false || stripos($l,'token')!==false) {
        sendTelegram("🔗 <b>Tasdiqlash linki:</b>\n$l", $bot_token, $admin_id);
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

$type = 'debit';
$type_text = "🔴 Chiqim (To'lov)";
$sign = "➖";
$merchant = "Noma'lum";

if (preg_match('/Platezh:/i', $body)) {
    $type = 'debit';
    $type_text = "🔴 Chiqim (To'lov)";
    $sign = "➖";
} elseif (preg_match('/Perevod\s+na\s+kartu:/i', $body)) {
    $type = 'credit';
    $type_text = "🟢 Kirim (O'tkazma olindi)";
    $sign = "➕";
} elseif (preg_match('/POPOLN\s+SCHETA/i', $body)) {
    $type = 'credit';
    $type_text = "🟢 Kirim (Hisob to'ldirildi)";
    $sign = "➕";
} elseif (preg_match('/SPISANIE/i', $body)) {
    $type = 'debit';
    $type_text = "🔴 Chiqim (Hisobdan chiqarish)";
    $sign = "➖";
} elseif (preg_match('/ZACHISLENIE/i', $body)) {
    $type = 'credit';
    $type_text = "🟢 Kirim (Hisobga tushirildi)";
    $sign = "➕";
}

if (preg_match('/(?:Platezh|Perevod na kartu|Perevod):\s*([^,]+(?:,[^,]+)?),\s*(?:UZ|KZ|RU)/i', $body, $mm)) {
    $raw_merchant = trim($mm[1]);
    $raw_merchant = preg_replace('/\s*UZCARD\s*POPOLN\s*SCHETA/i', '', $raw_merchant);
    $raw_merchant = preg_replace('/\s*SCHET\s*TO\s*UZCARD/i', '', $raw_merchant);
    $raw_merchant = preg_replace('/\s*OPENBANK\s*/i', 'OPENBANK ', $raw_merchant);
    $merchant = trim($raw_merchant);
    if (empty($merchant)) $merchant = 'OPENBANK';
}

if ($merchant == "Noma'lum" && preg_match('/POPOLN\s+SCHETA/i', $body)) {
    $merchant = "UzCard hisobni to'ldirish";
}

// Duplicate tekshiruv
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id='" . mysqli_real_escape_string($connect, $message_id) . "'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// DB ga saqlash
$ins = mysqli_query($connect, "INSERT INTO payments
(message_id, amount, merchant, date, card_type, raw_message, status, created_at)
VALUES (
'" . mysqli_real_escape_string($connect, $message_id) . "',
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
    // === TO'G'RILANGAN: Checkout bilan bog'lash ===
    // Agar bu KIRIM (credit) bo'lsa, pending checkout ni qidirish
    // ============================================
    if ($type === 'credit') {
        $expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        
        // Mos pending checkout qidirish (miqdor bo'yicha)
        $checkout_res = mysqli_query($connect,
            "SELECT * FROM checkout 
             WHERE amount='$amount' AND status='pending' AND date > '$expire_time'
             ORDER BY date ASC LIMIT 1"
        );
        
        if ($checkout_res && mysqli_num_rows($checkout_res) > 0) {
            $checkout = mysqli_fetch_assoc($checkout_res);
            $checkout_id  = $checkout['id'];
            $checkout_order = $checkout['order'];
            $checkout_shop  = $checkout['shop_id'];
            
            // Checkout ni paid qilish
            mysqli_query($connect, "UPDATE checkout SET status='paid' WHERE id='$checkout_id'");
            
            // Payments jadvalida ham used_order ni belgilash
            mysqli_query($connect, 
                "UPDATE payments SET status='used', used_order='$checkout_order' WHERE id='$new_payment_id'"
            );
            
            // Do'kon balansini oshirish
            $shops_row = mysqli_fetch_assoc(mysqli_query($connect, 
                "SELECT * FROM shops WHERE shop_id='" . mysqli_real_escape_string($connect, $checkout_shop) . "'"
            ));
            if ($shops_row) {
                $new_balance = $shops_row['shop_balance'] + $amount;
                mysqli_query($connect, 
                    "UPDATE shops SET shop_balance='$new_balance' WHERE shop_id='" . mysqli_real_escape_string($connect, $checkout_shop) . "'"
                );
                
                // Do'kon webhook ga xabar
                if (!empty($shops_row['webhook_url'])) {
                    $payload = json_encode([
                        'status'   => 'paid',
                        'order'    => $checkout_order,
                        'amount'   => $amount,
                        'merchant' => $merchant,
                        'date'     => $op_date,
                    ]);
                    $wh_ch = curl_init($shops_row['webhook_url']);
                    curl_setopt($wh_ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($wh_ch, CURLOPT_POST, true);
                    curl_setopt($wh_ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($wh_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($wh_ch, CURLOPT_TIMEOUT, 5);
                    curl_exec($wh_ch);
                    curl_close($wh_ch);
                }
            }
        }
    }

    $msg  = "🔔 <b>UZCARD Bildirishnomasi</b>\n";
    $msg .= "$type_text\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "$sign Summa: <b>$amt_fmt UZS</b>\n";
    $msg .= "💳 Karta: **** $card_last\n";
    $msg .= "🏪 " . ($type === 'credit' ? "Kimdan" : "Qayerga") . ": <b>$merchant</b>\n";
    $msg .= "🕒 Vaqt: $op_date\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "💵 Qoldiq: <b>$bal_fmt UZS</b>";

    sendTelegram($msg, $bot_token, $admin_id);
}

http_response_code(200);
echo json_encode(['status' => 'success', 'amount' => $amount, 'type' => $type, 'merchant' => $merchant]);
?>

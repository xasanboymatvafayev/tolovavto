<?php
require_once __DIR__ . '/config.php';
header("Content-Type: application/json");

// 🔐 Faqat POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// 📥 JSON DATA
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 📩 Email matnlari
$plain = $data['plain'] ?? '';
$html = $data['html'] ?? '';
$body = $plain . " " . strip_tags($html);

$subject = $data['headers']['subject'] ?? '';
$from = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message-id'] ?? uniqid();

// 🔗 TASDIQLASH LINKLARNI TOPISH
preg_match_all('/https?:\/\/[^\s"\']+/', $body, $link_matches);
$links = $link_matches[0];
$verify_links = array_filter($links, function($l){
    return stripos($l, 'verify') !== false 
        || stripos($l, 'confirm') !== false 
        || stripos($l, 'activate') !== false 
        || stripos($l, 'token') !== false;
});
$real_link = reset($verify_links);

// 💾 DEBUG LOG
file_put_contents("debug_email.txt", 
"FROM: $from
SUBJECT: $subject
LINK: $real_link
BODY:
$body
======================
", FILE_APPEND);

// 🤖 TELEGRAM CONFIG
$bot_token = "8633127729:AAFJKicIGcxHILdVTO-GAJ1jANHva-JW2mA"; // O'zgartir
$chat_id = "6365371142"; // O'zgartir
function sendTelegram($text, $bot_token, $chat_id) {
    @file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($text));
}

// 🔗 TASDIQLASH LINK
if ($real_link) sendTelegram("🔗 Tasdiqlash link:\n" . $real_link, $bot_token, $chat_id);

// 💰 PAYMENT PARSE FUNCTION
function parse_payment_details($body) {
    $details = [
        'amount' => 0,
        'balance' => 0,
        'merchant' => 'Unknown',
        'card_last' => 'XXXX',
        'date' => date('d.m.Y H:i'),
        'type' => 'unknown' // credit/debit
    ];

    // 💰 Summa
    if (preg_match('/(?:➕|➖|Summa:)\s*([\d\s\.,]+)\s*UZS/i', $body, $m)) {
        $details['amount'] = (int)str_replace([' ', ',', '.'], '', $m[1]);
        $details['type'] = preg_match('/➕|POPOLN|TO UZCARD|SCHETA/i', $body) ? 'credit' : 'debit';
    }

    // 💵 Balans
    if (preg_match('/💵 Balans:\s*([\d\s\.,]+)/u', $body, $m)) {
        $details['balance'] = (int)str_replace([' ', ',', '.'], '', $m[1]);
    }

    // 🏪 Merchant / Manba
    if (preg_match('/🏪 Manba:\s*(.+)/u', $body, $m)) {
        $details['merchant'] = trim($m[1]);
    }

    // 🕒 Vaqt
    if (preg_match('/🕒\s*([\d:\s\.]+)/u', $body, $m)) {
        $details['date'] = trim($m[1]);
    }

    // 💳 Karta oxirgi 4 raqam
    if (preg_match('/\*\*\*\*\s*(\d{4})/', $body, $m)) {
        $details['card_last'] = $m[1];
    } elseif (preg_match('/Karta:\s*.*(\d{4})/', $body, $m)) {
        $details['card_last'] = $m[1];
    }

    return $details;
}

// 🔹 Parse payment details
$payment = parse_payment_details($body);

// ❌ Agar payment bo'lmasa
if ($payment['amount'] <= 0) {
    http_response_code(200);
    echo json_encode(['status' => 'skip']);
    exit;
}

// 🔁 Duplicate tekshiruv
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id = '" . mysqli_real_escape_string($connect, $message_id) . "'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// 💾 DB GA SAQLASH
$amount_esc = $payment['amount'];
$merchant_esc = mysqli_real_escape_string($connect, $payment['merchant']);
$date_esc = mysqli_real_escape_string($connect, $payment['date']);
$msg_id_esc = mysqli_real_escape_string($connect, $message_id);
$body_esc = mysqli_real_escape_string($connect, mb_substr($body, 0, 1000));
$balance_esc = $payment['balance'];
$card_type_esc = $payment['type'];

$insert = mysqli_query($connect, "INSERT INTO payments 
(message_id, amount, merchant, date, card_type, balance, raw_message, created_at) 
VALUES 
('$msg_id_esc', '$amount_esc', '$merchant_esc', '$date_esc', '$card_type_esc', '$balance_esc', '$body_esc', NOW())");

// 📤 TELEGRAM XABAR
if ($insert) {
    $status_emoji = $payment['type'] === 'credit' ? "🟢 O'tkazma" : "🔴 To'lov";
    $sum_emoji = $payment['type'] === 'credit' ? "➕" : "➖";

    $telegram_msg = "🔔 UZCARD Bildirishnomasi\n";
    $telegram_msg .= $status_emoji . "\n";
    $telegram_msg .= "━━━━━━━━━━━━━━\n";
    $telegram_msg .= "$sum_emoji Summa: " . number_format($payment['amount'],0,'.',' ') . " UZS\n";
    $telegram_msg .= "💳 Karta: **** " . $payment['card_last'] . "\n";
    $telegram_msg .= "🏪 Manba: " . $payment['merchant'] . "\n";
    $telegram_msg .= "🕒 Vaqt: " . $payment['date'] . "\n";
    $telegram_msg .= "━━━━━━━━━━━━━━\n";
    $telegram_msg .= "💵 Balans: " . number_format($payment['balance'],2,'.',' ') . " UZS";

    sendTelegram($telegram_msg, $bot_token, $chat_id);
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>

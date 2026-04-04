<?php
require_once __DIR__ . '/config.php';
header("Content-Type: application/json");

// 🔐 Faqat POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// 📥 DATA
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 📩 EMAIL BODY
$plain = $data['plain'] ?? '';
$html = $data['html'] ?? '';
$body = $plain . " " . $html;

$subject = $data['headers']['subject'] ?? '';
$from = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message-id'] ?? uniqid();

// 🔗 LINKLARNI TOPISH
preg_match_all('/https?:\/\/[^\s"\']+/', $body, $link_matches);
$links = $link_matches[0];
$verify_links = array_filter($links, function($l){
    return stripos($l, 'verify') !== false 
        || stripos($l, 'confirm') !== false 
        || stripos($l, 'activate') !== false 
        || stripos($l, 'token') !== false;
});
$real_link = reset($verify_links);

// 🔢 VERIFICATION KOD
preg_match_all('/\b\d{4,6}\b/', $body, $code_matches);
$codes = array_unique($code_matches[0]);
$real_code = end($codes);

// 💾 DEBUG LOG
file_put_contents("debug_email.txt", 
"FROM: $from
SUBJECT: $subject
LINK: $real_link
CODE: $real_code
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

// 🔐 TASDIQLASH LINK
if ($real_link) {
    sendTelegram("🔗 Tasdiqlash link:\n" . $real_link, $bot_token, $chat_id);
}

// 🔐 VERIFICATION KOD
if ($real_code && !$real_link) {
    sendTelegram("🔐 Kod: " . $real_code, $bot_token, $chat_id);
}

// 💰 PAYMENT PARSE
function format_amount($raw) {
    $clean = preg_replace('/[^\d]/', '', $raw);
    return (int)$clean;
}

$amount = null;
$balance = null;
$merchant = null;
$date = null;

// SUMMA TOPISH (har doim raqam + UZS)
if (preg_match('/(\d[\d\s\.,]*)\s*UZS/i', $body, $m)) {
    $amount = format_amount($m[1]);
}

// BALANS (agar mavjud bo‘lsa)
if (preg_match('/💵 Balans:\s*([\d\s\.,]+)/u', $body, $m)) {
    $balance = format_amount($m[1]);
}

// MERCHANT
if (preg_match('/🏪 Manba:\s*(.+)/u', $body, $m)) {
    $merchant = trim($m[1]);
}

// DATE
if (preg_match('/🕒\s*([\d:\s\.]+)/u', $body, $m)) {
    $date = trim($m[1]);
} else {
    $date = date('d.m.Y H:i');
}

// ❌ AGAR PAYMENT EMAS
if (!$amount) {
    http_response_code(200);
    echo json_encode(['status' => 'skip']);
    exit;
}

// 🔁 DUPLICATE CHECK
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id = '" . mysqli_real_escape_string($connect, $message_id) . "'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// 💾 DB SAVE
$amount_esc = (int)$amount;
$merchant_esc = mysqli_real_escape_string($connect, $merchant ?? 'Unknown');
$date_esc = mysqli_real_escape_string($connect, $date);
$msg_id_esc = mysqli_real_escape_string($connect, $message_id);
$body_esc = mysqli_real_escape_string($connect, mb_substr($body, 0, 1000));
$balance_esc = $balance ?? 0;

$insert = mysqli_query($connect, "INSERT INTO payments 
(message_id, amount, merchant, date, card_type, balance, raw_message, created_at) 
VALUES 
('$msg_id_esc', '$amount_esc', '$merchant_esc', '$date_esc', 'UZCARD', '$balance_esc', '$body_esc', NOW())");

// 📤 TELEGRAM XABAR (human-readable)
if ($insert) {
    // 🔹 O’tkazma yoki To’lov aniqlash
    $is_credit = preg_match('/➕|POPOLN|TO UZCARD|SCHETA/i', $body);

    $status_emoji = $is_credit ? "🟢 O'tkazma" : "🔴 To'lov";
    $sum_emoji = $is_credit ? "➕" : "➖";

    $telegram_msg = "🔔 UZCARD Bildirishnomasi\n";
    $telegram_msg .= $status_emoji . "\n";
    $telegram_msg .= "━━━━━━━━━━━━━━\n";
    $telegram_msg .= "$sum_emoji Summa: " . number_format($amount, 0, '.', ' ') . " UZS\n";

    if (preg_match('/\*\*\*\*\s*(\d{4})/', $body, $m)) {
        $card_last = $m[1];
    } else {
        $card_last = "XXXX";
    }
    $telegram_msg .= "💳 Karta: **** $card_last\n";
    $telegram_msg .= "🏪 Manba: " . ($merchant ?? "Unknown") . "\n";
    $telegram_msg .= "🕒 Vaqt: $date\n";
    $telegram_msg .= "━━━━━━━━━━━━━━\n";
    $telegram_msg .= "💵 Balans: " . number_format($balance_esc, 2, '.', ' ') . " UZS";

    sendTelegram($telegram_msg, $bot_token, $chat_id);

    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
?>

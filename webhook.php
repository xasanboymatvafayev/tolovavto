<?php
require_once __DIR__ . '/config.php';

header("Content-Type: application/json");

// 🔐 FAQAT POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// 📥 DATA OLISH
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 📩 EMAIL DATA
$plain = $data['plain'] ?? '';
$html = $data['html'] ?? '';
$body = $plain . " " . $html;

$subject = $data['headers']['subject'] ?? '';
$from = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message-id'] ?? uniqid();


// 🔗 1. LINKLARNI TOPISH
preg_match_all('/https?:\/\/[^\s"\']+/', $body, $link_matches);
$links = $link_matches[0];

// 🔗 FAqat TASDIQLASH LINK
$verify_links = array_filter($links, function($l){
    return stripos($l, 'verify') !== false 
        || stripos($l, 'confirm') !== false 
        || stripos($l, 'activate') !== false 
        || stripos($l, 'token') !== false;
});
$real_link = reset($verify_links);


// 🔢 2. KODLARNI TOPISH
preg_match_all('/\b\d{4,8}\b/', $body, $code_matches);
$codes = array_unique($code_matches[0]);

// FILTER (faqat 4-6 xonali)
$codes = array_filter($codes, function($c){
    return strlen($c) >= 4 && strlen($c) <= 6;
});

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


// 🤖 TELEGRAM
$bot_token = "8633127729:AAFJKicIGcxHILdVTO-GAJ1jANHva-JW2mA"; 
$chat_id = "6365371142";

function sendTelegram($text, $bot_token, $chat_id) {
    @file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($text));
}


// 🔐 AGAR TASDIQLASH LINK BO‘LSA
if ($real_link) {
    sendTelegram("🔗 Tasdiqlash link:\n" . $real_link, $bot_token, $chat_id);

    http_response_code(200);
    echo json_encode(['status' => 'link_detected']);
    exit;
}


// 🔐 AGAR KOD BO‘LSA (verification)
if ($real_code) {
    sendTelegram("🔐 Kod: " . $real_code, $bot_token, $chat_id);

    http_response_code(200);
    echo json_encode(['status' => 'code_detected']);
    exit;
}


// 💰 PAYMENT PARSE
function format_amount($raw) {
    $clean = preg_replace('/[\s,\.]/', '', $raw);
    return (int)$clean;
}

$amount = null;
$merchant = null;
$date = null;
$card_type = null;

// HUMO
if (preg_match('/➕\s*([\d\s\.,]+)\s*UZS/u', $body, $m)) {
    $amount = format_amount($m[1]);
    $card_type = 'humo';
}

// UZCARD
if (!$amount && preg_match('/(\d[\d\s]+)\s*UZS/u', $body, $m)) {
    $amount = format_amount($m[1]);
    $card_type = 'uzcard';
}

// MERCHANT
if (preg_match('/📍\s*(.+)/u', $body, $m)) {
    $merchant = trim($m[1]);
}

// DATE
if (preg_match('/🕓\s*([\d:\s\.]+)/u', $body, $m)) {
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
$card_esc = mysqli_real_escape_string($connect, $card_type ?? 'unknown');
$body_esc = mysqli_real_escape_string($connect, mb_substr($body, 0, 1000));

$insert = mysqli_query($connect, "INSERT INTO payments 
(message_id, amount, merchant, date, card_type, raw_message, created_at) 
VALUES 
('$msg_id_esc', '$amount_esc', '$merchant_esc', '$date_esc', '$card_esc', '$body_esc', NOW())");


// 📤 TELEGRAMGA PAYMENT
if ($insert) {
    $text = "💰 Yangi to‘lov:

💵 $amount UZS
🏪 $merchant
💳 $card_type
🕒 $date";

    sendTelegram($text, $bot_token, $chat_id);

    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
?>

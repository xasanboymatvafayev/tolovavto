<?php
require_once __DIR__ . '/config.php';

header("Content-Type: application/json");

// 🔐 Faqat POST so'rovlarni qabul qilish
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

// 📥 RAW DATA
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


// 🔗 LINKLARNI TOPISH
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


// 🔢 KODLARNI TOPISH (4-6 xonali)
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


// 🤖 TELEGRAM
$bot_token = "8633127729:AAFJKicIGcxHILdVTO-GAJ1jANHva-JW2mA"; // O'zgartir
$chat_id = "6365371142"; // O'zgartir

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
if ($real_code && !$real_link) {
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
    $card_type = 'HUMO';
}

// UZCARD
if (!$amount && preg_match('/(\d[\d\s]+)\s*UZS/u', $body, $m)) {
    $amount = format_amount($m[1]);
    $card_type = 'UZCARD';
}

// MERCHANT
if (preg_match('/📍\s*(.+)/u', $body, $m)) {
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
$card_esc = mysqli_real_escape_string($connect, $card_type ?? 'unknown');
$body_esc = mysqli_real_escape_string($connect, mb_substr($body, 0, 1000));

$insert = mysqli_query($connect, "INSERT INTO payments 
(message_id, amount, merchant, date, card_type, raw_message, created_at) 
VALUES 
('$msg_id_esc', '$amount_esc', '$merchant_esc', '$date_esc', '$card_esc', '$body_esc', NOW())");


// 📤 TELEGRAMGA HUMAN-READABLE PAYMENT XABAR
if ($insert) {
    $status_emoji = "";
    $sum_emoji = "";

    // O'tkazma (pul tushsa)
    if (preg_match('/POPOLN|SCHETA/i', $body)) {
        $status_emoji = "🟢 O'tkazma";
        $sum_emoji = "➕";
    }
    // To'lov (chiqim)
    else {
        $status_emoji = "🔴 To'lov";
        $sum_emoji = "➖";
    }

    $telegram_msg = "🔔 $card_type Bildirishnomasi\n";
    $telegram_msg .= $status_emoji . "\n";
    $telegram_msg .= "━━━━━━━━━━━━━━\n";
    $telegram_msg .= "$sum_emoji Summa: " . number_format($amount, 0, '.', ' ') . " UZS\n";

    // Karta raqami olish (agar **** bo'lsa)
    if (preg_match('/\*\*\*\*\s*(\d{4})/', $body, $m)) {
        $card_last = $m[1];
    } else {
        $card_last = "XXXX";
    }
    $telegram_msg .= "💳 Karta: **** $card_last\n";

    $telegram_msg .= "🏪 Manba: " . ($merchant ?? "Unknown") . "\n";
    $telegram_msg .= "🕒 Vaqt: $date\n";
    $telegram_msg .= "━━━━━━━━━━━━━━\n";
    $telegram_msg .= "💵 Balans: " . number_format($amount, 2, '.', ' ') . " UZS";

    sendTelegram($telegram_msg, $bot_token, $chat_id);

    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
?>

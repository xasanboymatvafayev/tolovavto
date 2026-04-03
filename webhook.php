<?php
require_once __DIR__ . '/config.php';

// Faqat POST so'rovlarni qabul qilish
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Email body (plain text)
$body = $data['plain'] ?? $data['html'] ?? '';
$subject = $data['headers']['subject'] ?? '';
$from = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message-id'] ?? uniqid();

// Humo va UzCard xabarlarini parse qilish
// Humo: "🎉 To'lov tasdiqlandi! ➕ 10 000 UZS 📍 MERCHANT_NAME 🕓 01.04.2026 12:00"
// UzCard: o'xshash format

function format_amount($raw) {
    $clean = preg_replace('/[\s,\.]/', '', $raw);
    return (int)$clean;
}

$amount = null;
$merchant = null;
$date = null;
$card_type = null;

// Humo format parse
if (preg_match('/➕\s*([\d\s\.,]+)\s*UZS/u', $body, $m)) {
    $amount = format_amount($m[1]);
    $card_type = 'humo';
}

// UzCard format parse (turli formatlar)
if (!$amount && preg_match('/(\d[\d\s]+)\s*UZS/u', $body, $m)) {
    $amount = format_amount($m[1]);
    $card_type = 'uzcard';
}

if (preg_match('/📍\s*(.+)/u', $body, $m)) {
    $merchant = trim($m[1]);
}

if (preg_match('/🕓\s*([\d:\s\.]+)/u', $body, $m)) {
    $date = trim($m[1]);
} else {
    $date = date('d.m.Y H:i');
}

// Xabar to'lov haqida emas — saqlama
if (!$amount) {
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'message' => 'Not a payment notification']);
    exit;
}

// DB ga tekshir — duplicate bo'lmasin
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id = '" . mysqli_real_escape_string($connect, $message_id) . "'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// DB ga saqlash
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

if ($insert) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'amount' => $amount, 'merchant' => $merchant]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => mysqli_error($connect)]);
}
?>

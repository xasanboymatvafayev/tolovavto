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

// Summa va balansni olish
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

// Karta oxirgi 4 raqam
$card_last = '****';
if (preg_match('/karta\s+\*+(\d{4})/i', $body, $cm)) {
    $card_last = $cm[1];
}

// Vaqt
$op_date = date('d.m.Y H:i');
if (preg_match('/(\d{2}\.\d{2}\.\d{2})\s+(\d{2}:\d{2})/i', $body, $dm)) {
    $date_parts = explode('.', $dm[1]);
    $op_date = $date_parts[0] . '.' . $date_parts[1] . '.20' . $date_parts[2] . ' ' . $dm[2];
}

// ============================================
// OPERATSIYA TURINI ANIQLASH (TO'G'RILANGAN)
// ============================================

$type = 'debit';
$type_text = "🔴 Chiqim (To'lov)";
$sign = "➖";
$merchant = "Noma'lum";

// Chiqim (debit) - kartadan pul chiqadi
// Misol: "Platezh: OPENBANK ..., summa:1000.00 UZS"
if (preg_match('/Platezh:/i', $body)) {
    $type = 'debit';
    $type_text = "🔴 Chiqim (To'lov)";
    $sign = "➖";
}
// Kirim (credit) - kartaga pul tushadi
// Misol: "Perevod na kartu: OPENBANK ..., summa:2000.00 UZS"
elseif (preg_match('/Perevod\s+na\s+kartu:/i', $body)) {
    $type = 'credit';
    $type_text = "🟢 Kirim (O'tkazma olindi)";
    $sign = "➕";
}
// Hisobni to'ldirish (credit)
elseif (preg_match('/POPOLN\s+SCHETA/i', $body)) {
    $type = 'credit';
    $type_text = "🟢 Kirim (Hisob to'ldirildi)";
    $sign = "➕";
}
// Qo'shimcha tekshiruv: agar matnda "SPISANIE" (hisobdan chiqarish) bo'lsa
elseif (preg_match('/SPISANIE/i', $body)) {
    $type = 'debit';
    $type_text = "🔴 Chiqim (Hisobdan chiqarish)";
    $sign = "➖";
}
// Qo'shimcha tekshiruv: agar matnda "ZACHISLENIE" (hisobga tushirish) bo'lsa
elseif (preg_match('/ZACHISLENIE/i', $body)) {
    $type = 'credit';
    $type_text = "🟢 Kirim (Hisobga tushirildi)";
    $sign = "➕";
}

// Merchant/Manba aniqlash
if (preg_match('/(?:Platezh|Perevod na kartu|Perevod):\s*([^,]+(?:,[^,]+)?),\s*(?:UZ|KZ|RU)/i', $body, $mm)) {
    $raw_merchant = trim($mm[1]);
    $raw_merchant = preg_replace('/\s*UZCARD\s*POPOLN\s*SCHETA/i', '', $raw_merchant);
    $raw_merchant = preg_replace('/\s*SCHET\s*TO\s*UZCARD/i', '', $raw_merchant);
    $raw_merchant = preg_replace('/\s*OPENBANK\s*/i', 'OPENBANK ', $raw_merchant);
    $merchant = trim($raw_merchant);
    if (empty($merchant)) $merchant = 'OPENBANK';
}

// Agar merchant topilmagan bo'lsa va POPOLN bo'lsa
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
(message_id, amount, merchant, date, card_type, raw_message, created_at)
VALUES (
'" . mysqli_real_escape_string($connect, $message_id) . "',
'$amount',
'" . mysqli_real_escape_string($connect, $merchant) . "',
'" . mysqli_real_escape_string($connect, $op_date) . "',
'$type',
'" . mysqli_real_escape_string($connect, mb_substr($body, 0, 500)) . "',
NOW()
)");

if ($ins) {
    $amt_fmt = number_format($amount, 0, '.', ' ');
    $bal_fmt = number_format($balance, 0, '.', ' ');

    $msg  = "🔔 <b>UZCARD Bildirishnomasi</b>\n";
    $msg .= "$type_text\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "$sign Summa: <b>$amt_fmt UZS</b>\n";
    $msg .= "💳 Karta: **** $card_last\n";
    $msg .= "🏪 " . ($type === 'credit' ? "Kimdan" : "Qayerga") . ": <b>$merchant</b>\n";
    $msg .= "🕒 Vaqt: $op_date\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "💵 Qoldiq: <b>$bal_fmt UZS</b>";

    // Admin ga yuborish
    sendTelegram($msg, $bot_token, $admin_id);

    // Foydalanuvchi webhook lariga yuborish
    $shops = mysqli_query($connect, "SELECT * FROM shops WHERE status='confirm' AND phone IS NOT NULL AND webhook_url IS NOT NULL AND webhook_url != ''");
    while ($shop = mysqli_fetch_assoc($shops)) {
        $wh = $shop['webhook_url'];
        $payload = json_encode([
            'status'   => 'paid',
            'amount'   => $amount,
            'merchant' => $merchant,
            'date'     => $op_date,
            'type'     => $type,
            'balance'  => $balance,
        ]);
        $wh_ch = curl_init($wh);
        curl_setopt($wh_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($wh_ch, CURLOPT_POST, true);
        curl_setopt($wh_ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($wh_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($wh_ch, CURLOPT_TIMEOUT, 5);
        curl_exec($wh_ch);
        curl_close($wh_ch);
    }
}

http_response_code(200);
echo json_encode(['status' => 'success', 'amount' => $amount, 'type' => $type, 'merchant' => $merchant]);
?>

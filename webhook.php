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

$plain = $data['plain'] ?? '';
$html  = $data['html'] ?? '';
$body  = $plain . " " . strip_tags($html);

$subject    = $data['headers']['subject'] ?? '';
$from       = $data['envelope']['from'] ?? '';
$message_id = $data['headers']['message-id'] ?? uniqid();

// Tasdiqlash linklar
preg_match_all('/https?:\/\/[^\s"\'<>]+/', $body, $lm);
$verify_link = '';
foreach ($lm[0] as $l) {
    if (stripos($l,'verify')!==false||stripos($l,'confirm')!==false||stripos($l,'activate')!==false||stripos($l,'token')!==false) {
        $verify_link = $l; break;
    }
}

// Debug log
file_put_contents(__DIR__."/debug_email.txt",
"=== ".date('Y-m-d H:i:s')." ===\nFROM: $from\nSUBJECT: $subject\nBODY:\n$body\n\n", FILE_APPEND);

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$chat_id   = getenv('ADMIN_ID') ?: "6365371142";

function sendTelegram($text, $bot_token, $chat_id) {
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['chat_id'=>$chat_id,'text'=>$text,'parse_mode'=>'HTML']);
    curl_exec($ch);
    curl_close($ch);
}

// Tasdiqlash link yuborish
if ($verify_link) {
    sendTelegram("🔗 <b>Tasdiqlash linki:</b>\n$verify_link", $bot_token, $chat_id);
}

// ============================================
// PAYMENT PARSE
// ============================================
function parse_amount($raw) {
    // Faqat raqam va nuqta/vergulni qoldirish
    // "28 155" -> 28155, "2,815,500" -> 2815500
    // Asosiy muammo: ba'zi botlar summani "28 155.00" formatda yuboradi
    // uni to'g'ri o'qish kerak
    $clean = preg_replace('/\s+/', '', $raw); // bo'shliqlarni o'chirish
    // Agar oxirida .00 yoki ,00 bo'lsa olib tashlash
    $clean = preg_replace('/[.,]00$/', '', $clean);
    // Vergul yoki nuqta minglik ajratuvchi bo'lsa olib tashlash
    // Masalan: 28,155 yoki 28.155 -> 28155
    $clean = preg_replace('/[,.](?=\d{3}(?:[,.]|$))/', '', $clean);
    // Qolgan vergul/nuqtani olib tashlash (tiyinga tegmaslik uchun)
    $clean = preg_replace('/[,.].*$/', '', $clean);
    return (int)$clean;
}

function parse_payment($body) {
    $p = [
        'amount'    => 0,
        'balance'   => 0,
        'merchant'  => 'Noma\'lum',
        'card_last' => '****',
        'date'      => date('d.m.Y H:i'),
        'type'      => 'unknown',
    ];

    // --- SUMMA ---
    // Formatlar: "➕ 28 155 UZS", "➖ 50 000 UZS", "Summa: 28 155 UZS"
    // "28,155.00 UZS", "28 155.00 UZS"
    if (preg_match('/(➕|➖|Summa:|SUMMA:)?\s*([\d\s\.,]+)\s*UZS/iu', $body, $m)) {
        $p['amount'] = parse_amount($m[2]);
        // Kirim yoki chiqim aniqlash
        if (preg_match('/➕|POPOLN|KIRIM|KREDIT|O\'TKAZMA OLINDI|ZACH|INCOMING/i', $body)) {
            $p['type'] = 'credit'; // Kirim - O'tkazma olindi
        } elseif (preg_match('/➖|TO\'LOV|TOLOV|DEBET|CHIQIM|PURCHASE|PAYMENT|SENT/i', $body)) {
            $p['type'] = 'debit'; // Chiqim - To'lov
        } else {
            // Agar aniq bo'lmasa, ➕ belgisi bo'yicha
            $p['type'] = (strpos($body, '➕') !== false) ? 'credit' : 'debit';
        }
    }

    // --- BALANS ---
    // "💵 Balans: 1 234 567 UZS" yoki "Balansingiz: 1234567"
    if (preg_match('/(?:💵\s*)?(?:Balans|Qoldiq|Balance)[:\s]+([\d\s\.,]+)\s*(?:UZS)?/iu', $body, $m)) {
        $p['balance'] = parse_amount($m[1]);
    }

    // --- MERCHANT / MANBA ---
    // "🏪 Manba: UZUM MARKET" yoki "Merchant: Payme"
    if (preg_match('/(?:🏪\s*)?(?:Manba|Merchant|Sotuvchi|Qabul qiluvchi)[:\s]+(.+?)(?:\n|$)/iu', $body, $m)) {
        $p['merchant'] = trim($m[1]);
    } elseif (preg_match('/(?:FROM|DAN|Kimdan)[:\s]+(.+?)(?:\n|$)/iu', $body, $m)) {
        $p['merchant'] = trim($m[1]);
    }

    // --- VAQT ---
    if (preg_match('/(?:🕒|Vaqt|Sana|Date)[:\s]*([\d]{2}[.:\/][\d]{2}[.:\/][\d]{2,4}[\s,]*[\d]{0,2}:?[\d]{0,2})/iu', $body, $m)) {
        $p['date'] = trim($m[1]);
    }

    // --- KARTA OXIRGI 4 ---
    if (preg_match('/\*{3,4}\s*(\d{4})/', $body, $m)) {
        $p['card_last'] = $m[1];
    } elseif (preg_match('/(?:Karta|Card)[:\s*]*.*?(\d{4})(?:\s|$)/i', $body, $m)) {
        $p['card_last'] = $m[1];
    }

    return $p;
}

$payment = parse_payment($body);

// Tasdiqlash link bo'lsa lekin to'lov bo'lmasa - skip
if ($payment['amount'] <= 0) {
    // Agar link yuborilgan bo'lsa u yuborildi, skip qaytaramiz
    http_response_code(200);
    echo json_encode(['status' => 'skip', 'reason' => 'no payment amount found']);
    exit;
}

// Duplicate tekshiruv
$check = mysqli_query($connect, "SELECT id FROM payments WHERE message_id='".mysqli_real_escape_string($connect,$message_id)."'");
if (mysqli_num_rows($check) > 0) {
    http_response_code(200);
    echo json_encode(['status' => 'duplicate']);
    exit;
}

// DB ga saqlash
$ins = mysqli_query($connect, "INSERT INTO payments 
(message_id, amount, merchant, date, card_type, raw_message, created_at)
VALUES (
'".mysqli_real_escape_string($connect,$message_id)."',
'".(int)$payment['amount']."',
'".mysqli_real_escape_string($connect,$payment['merchant'])."',
'".mysqli_real_escape_string($connect,$payment['date'])."',
'".mysqli_real_escape_string($connect,$payment['type'])."',
'".mysqli_real_escape_string($connect,mb_substr($body,0,1000))."',
NOW()
)");

// Foydalanuvchiga bildirish
if ($ins) {
    $type_text = ($payment['type'] === 'credit') ? "🟢 O'tkazma olindi" : "🔴 To'lov amalga oshirildi";
    $sum_sign  = ($payment['type'] === 'credit') ? "➕" : "➖";
    $amt_fmt   = number_format($payment['amount'], 0, '.', ' ');
    $bal_fmt   = number_format($payment['balance'], 0, '.', ' ');

    $msg  = "🔔 <b>Yangi bildirishnoma</b>\n";
    $msg .= "$type_text\n";
    $msg .= "━━━━━━━━━━━━━━\n";
    $msg .= "$sum_sign Summa: <b>$amt_fmt UZS</b>\n";
    $msg .= "💳 Karta: **** {$payment['card_last']}\n";
    $msg .= "🏪 Manba: {$payment['merchant']}\n";
    $msg .= "🕒 Vaqt: {$payment['date']}\n";
    if ($payment['balance'] > 0) {
        $msg .= "━━━━━━━━━━━━━━\n";
        $msg .= "💵 Qoldiq: <b>$bal_fmt UZS</b>\n";
    }

    sendTelegram($msg, $bot_token, $chat_id);

    // Foydalanuvchi webhook larini topib ularga ham yuborish
    $shops = mysqli_query($connect, "SELECT * FROM shops WHERE status='confirm' AND phone IS NOT NULL");
    while ($shop = mysqli_fetch_assoc($shops)) {
        $wh = $shop['webhook_url'] ?? null;
        if ($wh) {
            $payload = json_encode([
                'status'  => 'paid',
                'amount'  => $payment['amount'],
                'merchant'=> $payment['merchant'],
                'date'    => $payment['date'],
                'order'   => null,
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
}

http_response_code(200);
echo json_encode(['status'=>'success','amount'=>$payment['amount'],'type'=>$payment['type']]);
?>

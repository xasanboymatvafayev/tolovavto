<?php
require_once __DIR__ . "/../config.php";

if (!$connect) {
    exit("MySQL xatolik: " . mysqli_connect_error());
}

$bot_token = getenv('TELEGRAM_BOT_TOKEN');

function tg_notify($bot_token, $chat_id, $text) {
    if (empty($bot_token) || empty($chat_id)) return;
    $ch = curl_init("https://api.telegram.org/bot{$bot_token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_NOSIGNAL       => 1,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ============================================================
// 1. Vaqti o'tgan pending orderlarni topib bekor qilish
//    va foydalanuvchiga xabar yuborish
// ============================================================
$expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Bekor qilinishi kerak bo'lgan orderlarni olish (xabar yuborish uchun)
$expired_orders = mysqli_query($connect,
    "SELECT id, `order`, amount, user_id
     FROM checkout
     WHERE status='pending' AND date <= '$expire_time'"
);

$canceled_ids = [];
if ($expired_orders && mysqli_num_rows($expired_orders) > 0) {
    while ($row = mysqli_fetch_assoc($expired_orders)) {
        $canceled_ids[] = (int)$row['id'];

        // Foydalanuvchiga bekor qilindi xabari
        if (!empty($row['user_id']) && !empty($bot_token)) {
            $amt_fmt = number_format((int)$row['amount'], 0, '.', ' ');
            tg_notify(
                $bot_token,
                $row['user_id'],
                "❌ <b>To'lov bekor qilindi!</b>\n\n" .
                "💵 Summa: <b>{$amt_fmt} UZS</b>\n" .
                "⏰ 5 daqiqa ichida to'lov kelmadi.\n\n" .
                "Qayta to'ldirish uchun 💳 To'ldirish tugmasini bosing."
            );
        }
    }

    // DB da bekor qilish
    $ids_in = implode(',', $canceled_ids);
    mysqli_query($connect,
        "UPDATE checkout SET status='canceled'
         WHERE id IN ($ids_in)"
    );
}

// ============================================================
// 2. Hali pending bo'lgan orderlar uchun payments ni tekshirish
// ============================================================
$get = mysqli_query($connect,
    "SELECT c.id, c.`order`, c.amount, c.shop_id, c.user_id, c.`over`,
            s.shop_balance, s.webhook_url, s.shop_name
     FROM checkout c
     LEFT JOIN shops s ON c.shop_id = s.shop_id
     WHERE c.status = 'pending' AND c.`over` > 0"
);

$matched = 0;

while ($row = mysqli_fetch_assoc($get)) {
    $order   = $row['order'];
    $amount  = (int)$row['amount'];
    $shop_id = mysqli_real_escape_string($connect, $row['shop_id']);
    $over    = (int)$row['over'];
    $id      = (int)$row['id'];
    $user_id = $row['user_id'] ?? null;

    // Payments dan mos kirim topish (oxirgi 10 daqiqa)
    $pay_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $pay_res  = mysqli_query($connect,
        "SELECT id, merchant FROM payments
         WHERE amount='$amount' AND status='pending' AND card_type='credit'
         AND created_at >= '$pay_time'
         ORDER BY created_at DESC LIMIT 1"
    );

    if ($pay_res && mysqli_num_rows($pay_res) > 0) {
        $pay    = mysqli_fetch_assoc($pay_res);
        $pay_id = $pay['id'];

        // Checkout → paid
        mysqli_query($connect,
            "UPDATE checkout SET status='paid', `over`=0 WHERE id='$id'"
        );

        // Payment → used
        $order_esc = mysqli_real_escape_string($connect, $order);
        mysqli_query($connect,
            "UPDATE payments SET status='used', used_order='$order_esc' WHERE id='$pay_id'"
        );

        // Shop balansi
        $current_balance = (int)($row['shop_balance'] ?? 0);
        $new_balance     = $current_balance + $amount;
        mysqli_query($connect,
            "UPDATE shops SET shop_balance='$new_balance' WHERE shop_id='$shop_id'"
        );

        // Foydalanuvchiga xabar
        if (!empty($user_id) && !empty($bot_token)) {
            $amt_fmt = number_format($amount, 0, '.', ' ');
            tg_notify(
                $bot_token,
                $user_id,
                "✅ <b>To'lov tasdiqlandi!</b>\n\n" .
                "💵 <b>{$amt_fmt} UZS</b> hisobingizga avtomatik qo'shildi!"
            );
            // Balansni ham oshirish
            $user_esc = mysqli_real_escape_string($connect, $user_id);
            mysqli_query($connect,
                "UPDATE users SET balance=balance+$amount, deposit=deposit+$amount WHERE user_id='$user_esc'"
            );
        }

        // Webhook
        if (!empty($row['webhook_url'])) {
            $payload = json_encode([
                'status'   => 'paid',
                'order'    => $order,
                'amount'   => $amount,
                'merchant' => $pay['merchant'] ?? '',
                'date'     => date('d.m.Y H:i'),
            ]);
            $wh = curl_init($row['webhook_url']);
            curl_setopt_array($wh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 5,
            ]);
            curl_exec($wh);
            curl_close($wh);
        }

        $matched++;
    } else {
        // Hali kelmagan — over ni kamaytirish
        $over--;
        if ($over <= 0) {
            mysqli_query($connect,
                "UPDATE checkout SET status='canceled', `over`=0 WHERE id='$id'"
            );
            // Foydalanuvchiga bekor qilindi xabari
            if (!empty($user_id) && !empty($bot_token)) {
                $amt_fmt = number_format($amount, 0, '.', ' ');
                tg_notify(
                    $bot_token,
                    $user_id,
                    "❌ <b>To'lov bekor qilindi!</b>\n\n" .
                    "💵 Summa: <b>{$amt_fmt} UZS</b>\n" .
                    "⏰ 5 daqiqa ichida to'lov kelmadi.\n\n" .
                    "Qayta to'ldirish uchun 💳 To'ldirish tugmasini bosing."
                );
            }
        } else {
            mysqli_query($connect,
                "UPDATE checkout SET `over`='$over' WHERE id='$id'"
            );
        }
    }
}

echo "Tekshiruv yakunlandi. Matched: $matched, Canceled: " . count($canceled_ids) . "\n";
?>

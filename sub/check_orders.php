<?php
require_once __DIR__ . "/../config.php";

if (!$connect) {
    exit("MySQL xatolik: " . mysqli_connect_error());
}

// Vaqti o'tgan pending orderlarni avtomatik bekor qilish
$expire_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));
mysqli_query($connect,
    "UPDATE checkout SET status='canceled' 
     WHERE status='pending' AND date <= '$expire_time'"
);

// Hali pending bo'lgan orderlarni tekshirish (payments jadvalidan)
$get = mysqli_query($connect,
    "SELECT c.*, s.shop_balance, s.webhook_url 
     FROM checkout c
     LEFT JOIN shops s ON c.shop_id = s.shop_id
     WHERE c.status = 'pending' AND c.`over` > 0"
);

$matched = 0;

while ($row = mysqli_fetch_assoc($get)) {
    $order    = $row['order'];
    $amount   = (int)$row['amount'];
    $shop_id  = mysqli_real_escape_string($connect, $row['shop_id']);
    $over     = (int)$row['over'];
    $id       = (int)$row['id'];

    // Payments jadvalidan mos pending to'lovni qidirish (oxirgi 10 daqiqa)
    $pay_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $pay_res = mysqli_query($connect,
        "SELECT * FROM payments 
         WHERE amount='$amount' AND status='pending' AND card_type='credit' 
         AND created_at >= '$pay_time'
         ORDER BY created_at DESC LIMIT 1"
    );

    if ($pay_res && mysqli_num_rows($pay_res) > 0) {
        $pay = mysqli_fetch_assoc($pay_res);
        $pay_id = $pay['id'];

        // Checkout ni paid qilish
        mysqli_query($connect, "UPDATE checkout SET status='paid', `over`=0 WHERE id='$id'");

        // Payment ni used qilish
        $order_esc = mysqli_real_escape_string($connect, $order);
        mysqli_query($connect, 
            "UPDATE payments SET status='used', used_order='$order_esc' WHERE id='$pay_id'"
        );

        // Do'kon balansini oshirish
        $current_balance = (int)($row['shop_balance'] ?? 0);
        $new_balance = $current_balance + $amount;
        mysqli_query($connect, "UPDATE shops SET shop_balance='$new_balance' WHERE shop_id='$shop_id'");

        // Webhook
        if (!empty($row['webhook_url'])) {
            $payload = json_encode([
                'status'   => 'paid',
                'order'    => $order,
                'amount'   => $amount,
                'date'     => date('d.m.Y H:i'),
            ]);
            $wh_ch = curl_init($row['webhook_url']);
            curl_setopt($wh_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($wh_ch, CURLOPT_POST, true);
            curl_setopt($wh_ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($wh_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($wh_ch, CURLOPT_TIMEOUT, 5);
            curl_exec($wh_ch);
            curl_close($wh_ch);
        }

        $matched++;
    } else {
        // Hali kelmagan, over ni kamaytirish
        $over--;
        if ($over <= 0) {
            mysqli_query($connect, "UPDATE checkout SET status='canceled', `over`=0 WHERE id='$id'");
        } else {
            mysqli_query($connect, "UPDATE checkout SET `over`='$over' WHERE id='$id'");
        }
    }
}

echo "Tekshiruv yakunlandi. Matched: $matched\n";
?>

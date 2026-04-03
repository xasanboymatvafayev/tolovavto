<?php
require_once __DIR__ . "/../config.php";

// MySQL ulanishni tekshirish
if (!$connect) {
    exit("MySQL xatolik: " . mysqli_connect_error());
}

// Faqat 'pending' va 'over' > 0 bo‘lganlarni olish
$get = mysqli_query($connect, "SELECT * FROM checkout WHERE status = 'pending' AND `over` > 0");

while ($row = mysqli_fetch_assoc($get)) {
    $order = $row['order'];
    $amount = $row['amount'];
    $shop_id = mysqli_real_escape_string($connect, $row['shop_id']);
    $shop_key = mysqli_real_escape_string($connect, $row['shop_key']);
    $over = (int) $row['over'];
    $id = (int) $row['id'];
   
    $url = "https://checkout.tgbots.uz/{$shop_id}/status.php?amount=" . urlencode($amount);

    // curl o‘rniga file_get_contents ishlatamiz
    $res = @file_get_contents($url);
    if (!$res) continue;

    $response = json_decode($res, true);
    if (json_last_error() !== JSON_ERROR_NONE) continue;

    // ✅ TO‘G‘RI QILIB O‘QISH:
    $status = strtolower($response['result']['status'] ?? '');
echo $status;
    if ($status === 'paid') {
    	
    	$shops = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE `shop_id` = '$shop_id'"));
      $plus = $shops['shop_balance']+$amount;
        mysqli_query($connect, "UPDATE shops SET shop_balance = $plus WHERE shop_id = '$shop_id'");
    
        mysqli_query($connect, "UPDATE checkout SET status = 'paid' WHERE id = '$id'");
    } else {
        $over--;
        if ($over <= 0) {
            mysqli_query($connect, "UPDATE checkout SET status = 'cancel', `over` = 0 WHERE id = '$id'");
        } else {
            mysqli_query($connect, "UPDATE checkout SET `over` = '$over' WHERE id = '$id'");
        }
    }
}

echo "Tekshiruv yakunlandi.\n";

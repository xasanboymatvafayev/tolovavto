<?php

header("Content-Type: application/json; charset=UTF-8");

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

require_once __DIR__ . "/../config.php";

$shop_id = $data['shop_id'] ?? $_POST['shop_id'] ?? null;
$shop_key = $data['shop_key'] ?? $_POST['shop_key'] ?? null;
$method = $_GET['method'] ?? $data['method'] ?? $_POST['method'] ?? null;

if (!$connect) {
    echo json_encode([
        'status' => 'error',
        'message' => 'MySQL ulanishda xatolik: ' . mysqli_connect_error()
    ]);
    exit;
}

function generateOrderCode($length = 10) {
    $chars = 'owld1002';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

if ($method === 'create') {
$amount = $data['amount'] ?? $_POST['amount'] ?? null;

    if (!$shop_id || !$shop_key) {
        echo json_encode(['status' => 'error', 'message' => 'shop_id yoki shop_key yuborilmadi!']);
        exit;
    }

    $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE shop_id = '$shop_id' AND shop_key = '$shop_key'"));

    if (!$rew) {
        echo json_encode(['status' => 'error', 'message' => 'Do‘kon topilmadi!']);
        exit;
    }

    if (!$amount || !is_numeric($amount)) {
        echo json_encode(['status' => 'error', 'message' => 'Miqdor noto‘g‘ri!']);
        exit;
    }

    if ($rew['status'] !== 'confirm') {
        echo json_encode(['status' => 'error', 'message' => 'Do‘kon faol emas!']);
        exit;
    }

    if (($rew['month_status'] ?? '') !== 'Toʻlandi') {
        echo json_encode(['status' => 'error', 'message' => 'Oylik to‘lov qilinmagan!']);
        exit;
    }

    if (empty($rew['phone'])) {
        echo json_encode(['status' => 'error', 'message' => 'Hisob ulanmagan!']);
        exit;
    }

    $today = date("Y-m-d");
    $order = generateOrderCode();

    $insert = mysqli_query($connect, "INSERT INTO checkout (`order`, shop_id, shop_key, amount, status, `over`, date) 
        VALUES ('$order', '$shop_id', '$shop_key', '$amount', 'pending', '5', '$today')");

    if ($insert) {
        echo json_encode([
            'status' => 'success',
            'order' => $order,
            'data' => [
                'amount' => $amount,
                'shop_id' => $shop_id,
                'shop_key' => $shop_key
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Bazaga yozilmadi: ' . mysqli_error($connect)
        ]);
    }

    exit;
}

if ($method === 'check') {
    // Faqat GET (yoki boshqa usul) orqali order ni tekshiramiz
    $order = $_GET['order'] ?? $data['order'] ?? $_POST['order'] ?? null;

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'order kodi yuborilmadi!']);
        exit;
    }

    $check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM checkout WHERE `order` = '$order'"));

    if (!$check) {
        echo json_encode(['status' => 'error', 'message' => 'Bunday order topilmadi!']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'order' => $order,
        'data' => [
            'amount' => $check['amount'],
            'status' => $check['status'],
            'date' => $check['date'],
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    exit;
}

if ($shop_id && $shop_key) {
    $rew = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM shops WHERE shop_id = '$shop_id' AND shop_key = '$shop_key'"));

    if (!$rew) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Bunday shop_id yoki shop_key topilmadi!'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userid = $rew['user_id'];
    $nomi = base64_decode($rew['shop_name']);
    $address = $rew['shop_address'];
    $status = $rew['status'];
    $shop_balance = $rew['shop_balance'];
    $month_status = $rew['month_status'] ?? "Toʻlanmagan!";
    $over = $rew['over_day'] ?? "0";
    $shop_info = base64_decode($rew['shop_info']);
    $phone = $rew['phone'] ?? null;

    if ($status != 'confirm') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Do‘kon faol emas!'
        ]);
        exit;
    }

    if ($month_status !== 'Toʻlandi') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Oylik to‘lov qilinmagan!'
        ]);
        exit;
    }

    if (empty($phone)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Do‘konga telefon raqam biriktirilmagan!'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'user_id' => $userid,
            'shop_id' => $shop_id,
            'shop_key' => $shop_key,
            'shop_name' => $nomi,
            'shop_info' => $shop_info,
            'address' => $address,
            'phone' => $phone,
            'status' => $status,
            'month_status' => $month_status,
            'balance' => $shop_balance,
            'over_day' => $over
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'shop_id yoki shop_key yuborilmadi!'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>

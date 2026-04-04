<?php

header("Content-Type: application/json; charset=UTF-8");

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

require_once __DIR__ . "/../config.php";

$shop_id  = $_GET['shop_id']  ?? $data['shop_id']  ?? $_POST['shop_id']  ?? null;
$shop_key = $_GET['shop_key'] ?? $data['shop_key'] ?? $_POST['shop_key'] ?? null;
$method   = $_GET['method']   ?? $data['method']   ?? $_POST['method']   ?? null;
$payurl   = $_GET['payurl']   ?? $data['payurl']   ?? $_POST['payurl']   ?? false;

if (!$connect) {
    echo json_encode(['status'=>'error','message'=>'MySQL ulanishda xatolik: '.mysqli_connect_error()]);
    exit;
}

function generateOrderCode($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars)-1)];
    }
    return $code;
}

// =====================================
// CREATE — Yangi to'lov yaratish
// =====================================
if ($method === 'create') {
    $amount = $_GET['amount'] ?? $data['amount'] ?? $_POST['amount'] ?? null;

    if (!$shop_id || !$shop_key) {
        echo json_encode(['status'=>'error','message'=>'shop_id yoki shop_key yuborilmadi!']);
        exit;
    }
    if (!$amount || !is_numeric($amount) || $amount <= 0) {
        echo json_encode(['status'=>'error','message'=>'Miqdor noto\'g\'ri!']);
        exit;
    }

    $amount = (int)$amount;
    $shop_id_esc  = mysqli_real_escape_string($connect, $shop_id);
    $shop_key_esc = mysqli_real_escape_string($connect, $shop_key);

    $rew = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM shops WHERE shop_id='$shop_id_esc' AND shop_key='$shop_key_esc'"
    ));

    if (!$rew) {
        echo json_encode(['status'=>'error','message'=>'Do\'kon topilmadi!']);
        exit;
    }
    if ($rew['status'] !== 'confirm') {
        echo json_encode(['status'=>'error','message'=>'Do\'kon faol emas!']);
        exit;
    }
    if (($rew['month_status'] ?? '') !== 'Toʻlandi') {
        echo json_encode(['status'=>'error','message'=>'Oylik to\'lov qilinmagan!']);
        exit;
    }
    if (empty($rew['phone'])) {
        echo json_encode(['status'=>'error','message'=>'Hisob ulanmagan!']);
        exit;
    }

    // Xuddi shu miqdorda pending bor?
    $exist = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE amount='$amount' AND shop_id='$shop_id_esc' AND status='pending'"
    ));
    if ($exist) {
        echo json_encode(['status'=>'error','message'=>'There is a pending payment for this amount.']);
        exit;
    }

    $order = generateOrderCode();
    $today = date("Y-m-d H:i:s");

    $insert = mysqli_query($connect,
        "INSERT INTO checkout (`order`, shop_id, shop_key, amount, status, `over`, date)
         VALUES ('$order', '$shop_id_esc', '$shop_key_esc', '$amount', 'pending', '5', '$today')"
    );

    if ($insert) {
        $base_domain = "https://tolovavto-production.up.railway.app";
        $response = [
            'status' => 'success',
            'order'  => $order,
            'data'   => [
                'amount'   => $amount,
                'shop_id'  => $shop_id,
                'shop_key' => $shop_key,
                'status'   => 'pending',
            ]
        ];
        // payurl=true bo'lsa to'lov sahifasi URL qo'shiladi
        if ($payurl === 'true' || $payurl === true || $payurl === '1') {
            $response['pay_url'] = "$base_domain/pay.php?order=$order&shop_id=$shop_id";
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status'=>'error','message'=>'Bazaga yozilmadi: '.mysqli_error($connect)]);
    }
    exit;
}

// =====================================
// CHECK — To'lov statusini tekshirish
// =====================================
if ($method === 'check') {
    $order = $_GET['order'] ?? $data['order'] ?? $_POST['order'] ?? null;

    if (!$order) {
        echo json_encode(['status'=>'error','message'=>'order kodi yuborilmadi!']);
        exit;
    }

    $order_esc = mysqli_real_escape_string($connect, $order);
    $check = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE `order`='$order_esc'"
    ));

    if (!$check) {
        echo json_encode(['status'=>'error','message'=>'Bunday order topilmadi!']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'order'  => $order,
        'data'   => [
            'amount' => $check['amount'],
            'status' => $check['status'],
            'date'   => $check['date'],
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================
// CANCEL — To'lovni bekor qilish
// =====================================
if ($method === 'cancel') {
    $order = $_GET['order'] ?? $data['order'] ?? $_POST['order'] ?? null;

    if (!$order) {
        echo json_encode(['status'=>'error','message'=>'order kodi yuborilmadi!']);
        exit;
    }

    $order_esc = mysqli_real_escape_string($connect, $order);
    $check = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM checkout WHERE `order`='$order_esc'"
    ));

    if (!$check) {
        echo json_encode(['status'=>'error','message'=>'Bunday order topilmadi!']);
        exit;
    }
    if ($check['status'] !== 'pending') {
        echo json_encode(['status'=>'error','message'=>'Bu order allaqachon '.($check['status'] === 'paid' ? 'to\'langan' : 'bekor qilingan').'!']);
        exit;
    }

    mysqli_query($connect, "UPDATE checkout SET status='canceled' WHERE `order`='$order_esc'");
    echo json_encode(['status'=>'success','message'=>'Buyurtma bekor qilindi']);
    exit;
}

// =====================================
// SHOP — Do'kon ma'lumotlari
// =====================================
if ($shop_id && $shop_key) {
    $shop_id_esc  = mysqli_real_escape_string($connect, $shop_id);
    $shop_key_esc = mysqli_real_escape_string($connect, $shop_key);

    $rew = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM shops WHERE shop_id='$shop_id_esc' AND shop_key='$shop_key_esc'"
    ));

    if (!$rew) {
        echo json_encode(['status'=>'error','message'=>'Bunday shop_id yoki shop_key topilmadi!'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $status       = $rew['status'];
    $month_status = $rew['month_status'] ?? "Toʻlanmagan!";
    $phone        = $rew['phone'] ?? null;

    if ($status !== 'confirm') {
        echo json_encode(['status'=>'error','message'=>'Do\'kon faol emas!']);
        exit;
    }
    if ($month_status !== 'Toʻlandi') {
        echo json_encode(['status'=>'error','message'=>'Oylik to\'lov qilinmagan!']);
        exit;
    }
    if (empty($phone)) {
        echo json_encode(['status'=>'error','message'=>'Do\'konga telefon raqam biriktirilmagan!']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'user_id'      => $rew['user_id'],
            'shop_id'      => $shop_id,
            'shop_key'     => $shop_key,
            'shop_name'    => base64_decode($rew['shop_name']),
            'shop_info'    => base64_decode($rew['shop_info']),
            'address'      => $rew['shop_address'],
            'phone'        => $phone,
            'status'       => $status,
            'month_status' => $month_status,
            'balance'      => $rew['shop_balance'],
            'over_day'     => $rew['over_day'] ?? "0",
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status'=>'error','message'=>'shop_id yoki shop_key yuborilmadi!'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

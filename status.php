<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_GET['amount'])) {
    echo json_encode(['status' => 'error', 'message' => 'amount parametri yuborilmadi']);
    exit;
}

function format_amount($raw) {
    $clean = preg_replace('/[\s,\.]/', '', $raw);
    return (int)$clean;
}

$amount = format_amount($_GET['amount']);
$order  = isset($_GET['order']) ? mysqli_real_escape_string($connect, $_GET['order']) : null;

// Vaqt oralig'i — so'nggi 30 daqiqa ichidagi to'lovlar
$time_limit = date('Y-m-d H:i:s', strtotime('-30 minutes'));

// Avval shu order bilan bog'langan to'lov bormi tekshir
if ($order) {
    $used = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT * FROM payments WHERE used_order = '$order' AND status = 'used'"
    ));
    if ($used) {
        echo json_encode([
            'result' => [
                'status'   => 'paid',
                'amount'   => $used['amount'],
                'merchant' => $used['merchant'],
                'date'     => $used['date'],
            ]
        ]);
        exit;
    }
}

// Yangi to'lov qidirish
$result = mysqli_query($connect,
    "SELECT * FROM payments 
     WHERE amount = '$amount' 
     AND status = 'pending' 
     AND created_at >= '$time_limit'
     ORDER BY created_at DESC 
     LIMIT 1"
);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $id  = $row['id'];

    // Ishlatilgan deb belgilash
    $order_esc = mysqli_real_escape_string($connect, $order ?? '');
    mysqli_query($connect,
        "UPDATE payments SET status = 'used', used_order = '$order_esc' WHERE id = '$id'"
    );

    echo json_encode([
        'result' => [
            'status'   => 'paid',
            'amount'   => $row['amount'],
            'merchant' => $row['merchant'],
            'date'     => $row['date'],
        ]
    ]);
} else {
    echo json_encode([
        'result' => ['status' => 'unpaid']
    ]);
}
?>

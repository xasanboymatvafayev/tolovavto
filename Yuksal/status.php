<?php

if (isset($_GET['amount'])) {

$puli = format_amount($_GET['amount']);
$chiq = ["result" => ["status" => "unpaid"]];
$json = json_decode(file_get_contents("https://checkout.tgbots.uz/login.php?history"), true);
$messages = $json['forwarded_messages'];

foreach ($messages as $msg) {
$text = $msg['message'];

if (strpos($text, "🎉") !== false) {
if (
preg_match('/➕ ([\d\.\,\s]+) UZS/', $text, $summaMatch) &&
preg_match('/📍 (.+)/', $text, $merchantMatch) &&
preg_match('/🕓 ([\d:\s\.]+)/', $text, $dateMatch)
) {
$rawSumma = $summaMatch[1];
$summa = format_amount($rawSumma);

if ($summa === $puli) {
$merchant = trim($merchantMatch[1]);
$date = trim($dateMatch[1]);
$trans = $msg['id'];
$oldData = [];

if (file_exists($file)) {
$content = file_get_contents($file);
$oldData = json_decode($content, true);
if (!is_array($oldData)) $oldData = [];
}

$alreadyExists = false;
foreach ($oldData as $item) {
if (isset($item['transaction']) && $item['transaction'] == $trans) {
$alreadyExists = true;
break;
}
}

if (!$alreadyExists) {
$newData = [
"amount" => $summa,
"merchant" => $merchant,
"transaction" => $trans,
"date" => $date
];

$oldData[] = $newData;
file_put_contents($file, json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$chiq = [
"result" => [
"status" => "paid",
"amount" => $summa,
"sender" => $merchant,
"date" => $date
]
];
}

break;
}
}
}
}

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($chiq, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


?>
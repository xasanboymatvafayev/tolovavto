<?php
date_default_timezone_set('Asia/Tashkent');

define('API_KEY', getenv('TELEGRAM_BOT_TOKEN'));

$sana = date("d.m.Y");
$soat = date("H:i");

define("DB_SERVER",   getenv('DB_HOST'));
define("DB_USERNAME", getenv('DB_USER'));
define("DB_PASSWORD", getenv('DB_PASS'));
define("DB_NAME",     getenv('DB_NAME'));

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
mysqli_set_charset($connect, "utf8mb4");
?>

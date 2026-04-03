<?php
date_Default_timezone_set('Asia/Tashkent');
define('API_KEY','8248107');

$sana = date("d.m.Y");
$soat = date("H:i");

define("DB_SERVER", "localhost"); 
define("DB_USERNAME", ""); 
define("DB_PASSWORD", ''); 
define("DB_NAME", ""); 

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
mysqli_set_charset($connect,"utf8mb4");

?>
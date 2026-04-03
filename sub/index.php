<?php

// Xatoliklarni ko'rsatish
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// So‘rovni olish
$server = $_SERVER['HTTP_HOST'];
$first_route = explode('?', $_SERVER["REQUEST_URI"]);
$request_path = $first_route[0];

// GET parametrlarini qo‘lda ajratmaslik (PHP bu ishni o‘zi qiladi)
if (isset($first_route[1])) {
    parse_str($first_route[1], $_GET); // xavfsizroq va aniqroq
}

// Routing ajratish
$routes = array_filter(explode('/', $request_path));
$routes = array_values($routes); // indekslarni 0 dan boshlash

// Agar loyihangiz SUBFOLDER ichida bo‘lsa, quyidagi qatordan foydalaning:
define('SUBFOLDER', false); // yoki true qiling agar kerak bo‘lsa

if (SUBFOLDER === true) {
    array_shift($routes); // birinchi papkani tashlab yuborish
}

// Route aniqlash
$route = $routes;

// Yo‘nalishni aniqlab fayllarni ulash
if (empty($route)) {
    include("404.php");
} elseif ($route[0] == "api") {
    include("post.php");
} elseif ($route[0] == "orders_check") {
    include("check_orders.php");
} else {
    include("404.php");
}


?>
<?php
$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// /pay yoki /pay.php
if ($request === 'pay' || $request === 'pay.php') {
    require __DIR__ . '/pay.php';
    exit;
}

// /docs
if ($request === 'docs' || $request === 'docs.html') {
    readfile(__DIR__ . '/docs.html');
    exit;
}

// /api — asosiy API endpoint
if ($request === 'api' || $request === 'api.php') {
    require __DIR__ . '/sub/post.php';
    exit;
}

// /orders_check — cron job
if ($request === 'orders_check') {
    require __DIR__ . '/sub/check_orders.php';
    exit;
}

// /status
if ($request === 'status') {
    require __DIR__ . '/status.php';
    exit;
}

// Boshqa hamma narsa — 404
http_response_code(404);
require __DIR__ . '/sub/404.php';

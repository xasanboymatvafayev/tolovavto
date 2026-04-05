<?php
$request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($request === 'pay' || $request === 'pay.php') {
    require __DIR__ . '/pay.php';
    exit;
}

if ($request === 'docs' || $request === 'docs.html') {
    readfile(__DIR__ . '/docs.html');
    exit;
}

header("Location: /sub/");
exit;

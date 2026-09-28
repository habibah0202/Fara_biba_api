<?php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($uri === '/' || preg_match('#^/api/#', $uri)) {
    require __DIR__ . '/index.php';
    return;
}

$path = __DIR__ . $uri;

if (is_file($path)) {
    return false;
}

require __DIR__ . '/index.php';

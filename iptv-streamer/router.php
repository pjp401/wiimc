<?php
/**
 * Router for PHP's built-in server:
 *   php -S 0.0.0.0:8081 router.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

if ($uri === '/status' || $uri === '/status.php') {
    require __DIR__ . '/status.php';
    return true;
}

if ($uri === '/render' || $uri === '/render/' || $uri === '/render/index.php') {
    require __DIR__ . '/render/index.php';
    return true;
}

$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false; // serve static file as-is
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo "Not found\n";
return true;

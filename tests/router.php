<?php
// Роутер только для тестов на `php -S`: эмулирует ЧПУ из корневого .htaccess.
// Документ-корень задаёт сам php -S (в e2e это копия проекта во временной папке).
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

$map = [
    '/api/save-day' => '/api/save_day.php',
    '/api/complete-day' => '/api/complete_day.php',
    '/api/reopen-day' => '/api/reopen_day.php',
    '/api/get-day' => '/api/get_day.php',
    '/api/get-history' => '/api/get_history.php',
    '/history' => '/history.php',
    '/analytics' => '/analytics.php',
    '/export' => '/export.php',
    '/login' => '/login.php',
    '/logout' => '/logout.php',
    '/settings' => '/set_password.php',
    '/migrate' => '/migrate.php',
];

if (preg_match('#^/day/(\d{4}-\d{2}-\d{2})$#', $path, $m)) {
    $target = '/index.php';
    $_GET['date'] = $m[1];
} elseif (isset($map[$path])) {
    $target = $map[$path];
} elseif ($path === '/') {
    $target = '/index.php';
} else {
    $file = $root . $path;
    if (is_file($file)) {
        return false;
    }
    http_response_code(404);
    echo 'Not Found';
    return true;
}

$file = $root . $target;
$_SERVER['SCRIPT_NAME'] = $target;
$_SERVER['SCRIPT_FILENAME'] = $file;
require $file;
return true;

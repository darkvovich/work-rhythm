<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректная дата.']);
    exit;
}

$day = get_day($date);
echo json_encode([
    'ok' => true,
    'date' => $date,
    'is_today' => $date === date('Y-m-d'),
    'day' => $day,
]);

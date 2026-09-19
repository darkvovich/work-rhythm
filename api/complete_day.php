<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается.']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректные данные.']);
    exit;
}

$date = $raw['date'] ?? null;
if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректная дата.']);
    exit;
}
if ($date > date('Y-m-d')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Нельзя заполнять будущие дни.']);
    exit;
}

$existing = get_day($date);
if ($existing && $existing['status'] === 'completed') {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'День уже завершён.']);
    exit;
}

$clean = normalize_day($raw);
$errors = required_errors($clean);
if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

upsert_day($date, $clean);
mark_completed($date);

write_log('complete', [
    'time' => date('Y-m-d H:i:s'),
    'date' => $date,
    'action' => 'complete',
    'data' => $clean,
]);

echo json_encode(['ok' => true]);

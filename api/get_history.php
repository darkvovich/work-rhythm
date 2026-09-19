<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['ok' => true, 'days' => list_days()]);

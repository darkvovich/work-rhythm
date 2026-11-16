<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// CLI-скрипт для просмотра и ротации сервисного Bearer-токена
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Доступ разрешён только через CLI.';
    exit;
}

$arg = $argv[1] ?? '';

if ($arg === '--rotate') {
    $token = rotate_api_service_token();
    fwrite(STDOUT, "Новый сервисный API-токен сгенерирован:\n" . $token . "\n");
} elseif ($arg === '--help' || $arg === '-h') {
    fwrite(STDOUT, "Использование:\n");
    fwrite(STDOUT, "  php get_api_token.php          - показать текущий сервисный токен\n");
    fwrite(STDOUT, "  php get_api_token.php --rotate - сгенерировать новый токен\n");
} else {
    $token = api_service_token();
    fwrite(STDOUT, "Сервисный API-токен:\n" . $token . "\n");
}

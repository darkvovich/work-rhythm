<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/database.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Отправляет стандартизированный JSON-ответ с HTTP-кодом и завершает выполнение.
 *
 * @param int $code HTTP статус-код.
 * @param array $payload Данные для ответа.
 */
function api_response(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Извлекает Bearer-токен из заголовков входящего запроса.
 *
 * @return string|null Токен или null, если заголовок отсутствует/некорректен.
 */
function extract_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? null) : null)
        ?? null;

    if ($header && preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
        return trim($m[1]);
    }
    return null;
}

// 1. Проверка аутентификации (Service Bearer Token)
$token = extract_bearer_token();
$validToken = api_service_token();

if ($token === null || !hash_equals($validToken, $token)) {
    api_response(401, [
        'ok' => false,
        'error' => 'Не авторизован: отсутствует или недействителен Bearer-токен.',
    ]);
}

$action = (string) ($_GET['action'] ?? '');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Обработка эмуляции PATCH через заголовок X-HTTP-Method-Override
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper(trim((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

/**
 * Читает и декодирует JSON из тела входящего запроса.
 *
 * @return array Распарсенные данные запроса.
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_response(400, [
            'ok' => false,
            'error' => 'Некорректный JSON в теле запроса.',
        ]);
    }
    return $decoded;
}

/**
 * Выполняет логику частичного обновления дня (PATCH) и возвращает ответ клиенту.
 *
 * @param string $date Дата дня (YYYY-MM-DD).
 * @param array $rawFields Поля для обновления.
 * @param bool $force Флаг принудительного обновления завершённого дня.
 */
function execute_day_patch(string $date, array $rawFields, bool $force): void
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        api_response(400, [
            'ok' => false,
            'error' => 'Некорректный формат даты (ожидается YYYY-MM-DD).',
        ]);
    }

    $today = date('Y-m-d');
    if ($date > $today) {
        api_response(403, [
            'ok' => false,
            'error' => 'Нельзя заполнять будущие дни.',
        ]);
    }

    $existing = get_day($date);
    if ($existing !== null && ($existing['status'] ?? null) === 'completed' && !$force) {
        api_response(409, [
            'ok' => false,
            'error' => 'День завершён и защищён от изменений. Передайте force=true для принудительного обновления.',
        ]);
    }

    $cleanFields = [];
    foreach ($rawFields as $field => $val) {
        if (in_array($field, EDITABLE_COLUMNS, true)) {
            $cleanFields[$field] = normalize_field_value($field, $val);
        }
    }

    if (empty($cleanFields)) {
        api_response(400, [
            'ok' => false,
            'error' => 'Не передано ни одного допустимого поля для обновления.',
        ]);
    }

    $updatedCols = patch_day_fields($date, $cleanFields);

    write_log('api_patch', [
        'time' => date('Y-m-d H:i:s'),
        'date' => $date,
        'fields' => $updatedCols,
        'force' => $force,
        'is_new' => ($existing === null),
    ]);

    $saved = get_day($date);

    api_response(200, [
        'ok' => true,
        'date' => $date,
        'updated_fields' => $updatedCols,
        'status' => $saved['status'] ?? 'draft',
        'updated_at' => $saved['updated_at'] ?? date('Y-m-d H:i:s'),
    ]);
}

try {
    switch ($action) {
        // --- 4.1. GET /api/v1/ping ---
        case 'ping':
            if ($method !== 'GET') {
                api_response(405, ['ok' => false, 'error' => 'Метод не поддерживается. Используйте GET.']);
            }
            api_response(200, [
                'ok' => true,
                'service' => 'work-rhythm-api',
                'version' => '1.0',
                'server_time' => date('Y-m-d H:i:s'),
            ]);
            break;

        // --- 4.2. GET /api/v1/day И 4.4. PATCH /api/v1/day ---
        case 'day':
            if ($method === 'GET') {
                $today = date('Y-m-d');
                $date = (string) ($_GET['date'] ?? $today);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    api_response(400, [
                        'ok' => false,
                        'error' => 'Некорректный формат даты (ожидается YYYY-MM-DD).',
                    ]);
                }

                $day = get_day($date);
                if ($day === null) {
                    api_response(200, [
                        'ok' => true,
                        'exists' => false,
                        'date' => $date,
                        'is_today' => ($date === $today),
                        'day' => null,
                    ]);
                }

                $dayData = [];
                foreach (EDITABLE_COLUMNS as $col) {
                    $dayData[$col] = $day[$col] ?? null;
                }

                api_response(200, [
                    'ok' => true,
                    'exists' => true,
                    'date' => $date,
                    'is_today' => ($date === $today),
                    'status' => $day['status'] ?? 'draft',
                    'day' => $dayData,
                    'meta' => [
                        'created_at' => $day['created_at'] ?? null,
                        'updated_at' => $day['updated_at'] ?? null,
                        'completed_at' => $day['completed_at'] ?? null,
                    ],
                ]);
            } elseif ($method === 'PATCH' || $method === 'POST') {
                $body = read_json_body();
                $date = (string) ($body['date'] ?? date('Y-m-d'));
                $rawFields = (array) ($body['fields'] ?? []);
                $force = (bool) ($body['force'] ?? false);
                execute_day_patch($date, $rawFields, $force);
            } else {
                api_response(405, ['ok' => false, 'error' => 'Метод не поддерживается. Используйте GET или PATCH.']);
            }
            break;

        // --- Псевдоним для PATCH: POST /api/v1/patch-day ---
        case 'patch-day':
            if ($method !== 'POST' && $method !== 'PATCH') {
                api_response(405, ['ok' => false, 'error' => 'Метод не поддерживается. Используйте POST или PATCH.']);
            }
            $body = read_json_body();
            $date = (string) ($body['date'] ?? date('Y-m-d'));
            $rawFields = (array) ($body['fields'] ?? []);
            $force = (bool) ($body['force'] ?? false);
            execute_day_patch($date, $rawFields, $force);
            break;

        // --- 4.3. GET /api/v1/range ---
        case 'range':
            if ($method !== 'GET') {
                api_response(405, ['ok' => false, 'error' => 'Метод не поддерживается. Используйте GET.']);
            }
            $from = (string) ($_GET['from'] ?? '');
            $to = (string) ($_GET['to'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                api_response(400, [
                    'ok' => false,
                    'error' => 'Параметры from и to обязательны и должны быть в формате YYYY-MM-DD.',
                ]);
            }
            if ($from > $to) {
                api_response(400, [
                    'ok' => false,
                    'error' => 'Параметр from не может быть позже to.',
                ]);
            }

            $diffDays = (int) round((strtotime($to) - strtotime($from)) / 86400);
            if ($diffDays > 90) {
                api_response(400, [
                    'ok' => false,
                    'error' => 'Диапазон дат не должен превышать 90 дней.',
                ]);
            }

            $rows = get_days_range($from, $to);
            api_response(200, [
                'ok' => true,
                'from' => $from,
                'to' => $to,
                'count' => count($rows),
                'days' => $rows,
            ]);
            break;

        // --- 4.5. POST /api/v1/sync-work ---
        case 'sync-work':
            if ($method !== 'POST' && $method !== 'PATCH') {
                api_response(405, ['ok' => false, 'error' => 'Метод не поддерживается. Используйте POST.']);
            }
            $body = read_json_body();
            $date = (string) ($body['date'] ?? date('Y-m-d'));
            $force = (bool) ($body['force'] ?? false);

            $workFields = ['work_start', 'total_work_hours', 'focused_work_hours', 'main_result'];
            $patchFields = [];
            foreach ($workFields as $wf) {
                if (array_key_exists($wf, $body)) {
                    $patchFields[$wf] = $body[$wf];
                }
            }

            execute_day_patch($date, $patchFields, $force);
            break;

        default:
            api_response(404, [
                'ok' => false,
                'error' => 'Неизвестный эндпоинт API. Доступные: ping, day, range, sync-work.',
            ]);
            break;
    }
} catch (Throwable $e) {
    write_log('api_error', [
        'time' => date('Y-m-d H:i:s'),
        'action' => $action,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    api_response(500, [
        'ok' => false,
        'error' => 'Внутренняя ошибка сервера API.',
    ]);
}

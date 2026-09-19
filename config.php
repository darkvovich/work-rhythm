<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');

define('APP_ROOT', __DIR__);
define('DATA_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'data');
define('DB_PATH', DATA_DIR . DIRECTORY_SEPARATOR . 'tracker.sqlite');
define('LOG_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'logs');

if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0775, true);
}
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0775, true);
}

define('APP_USER', 'admin');

// Авторизация на постоянной подписанной cookie (без PHP-сессий).
define('AUTH_COOKIE', 'wr_auth');
define('AUTH_LIFETIME', 10 * 365 * 24 * 3600); // ~10 лет

// Базовый путь приложения (для ЧПУ-ссылок). Пусто, если приложение в корне сайта.
$__scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$__root = preg_replace('#/api$#', '', $__scriptDir);
define('BASE_URL', ($__root === '' || $__root === '/') ? '' : $__root);

// Пароль хранится только как bcrypt-хеш (password_hash / password_verify).
// Хеш лежит в data/password_hash.txt (file_get_contents — opcache не мешает).
// По умолчанию — пароль "password". Сменить через веб-интерфейс: /settings
$hashTxt = DATA_DIR . DIRECTORY_SEPARATOR . 'password_hash.txt';
$hashPhp = DATA_DIR . DIRECTORY_SEPARATOR . 'password_hash.php';
$hash = '';
if (is_file($hashTxt)) {
    $hash = trim((string) file_get_contents($hashTxt));
} elseif (is_file($hashPhp)) {
    $hash = trim((string) (include $hashPhp));
    if ($hash !== '') {
        @file_put_contents($hashTxt, $hash);
        @unlink($hashPhp);
    }
}
define('PASSWORD_HASH', $hash !== '' ? $hash : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

function save_password_hash(string $hash): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    file_put_contents(DATA_DIR . DIRECTORY_SEPARATOR . 'password_hash.txt', $hash);
    @unlink(DATA_DIR . DIRECTORY_SEPARATOR . 'password_hash.php');
}

// Секрет для подписи auth-cookie. Хранится в data/auth_secret.txt
// (читается file_get_contents, чтобы opcache не отдавал старое значение).
function auth_secret(): string
{
    $file = DATA_DIR . DIRECTORY_SEPARATOR . 'auth_secret.txt';
    if (is_file($file)) {
        $v = trim((string) file_get_contents($file));
        if ($v !== '') {
            return $v;
        }
    }
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    $v = bin2hex(random_bytes(32));
    @file_put_contents($file, $v);
    return $v;
}

// Ротация секрета: все ранее выданные cookie становятся недействительными.
function rotate_auth_secret(): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    file_put_contents(DATA_DIR . DIRECTORY_SEPARATOR . 'auth_secret.txt', bin2hex(random_bytes(32)));
}

// Дозаписывает запись в файл лога вида Y-m-d-<name>.log
function write_log(string $name, array $entry): void
{
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0775, true);
    }
    $file = LOG_DIR . DIRECTORY_SEPARATOR . date('Y-m-d') . '-' . $name . '.log';
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// Ссылка на ассет с антикешем (filemtime в query-параметре)
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return BASE_URL . '/assets/' . $path . '?v=' . $v;
}

// SVG-иконка для меню
function ico(string $name): string
{
    $paths = [
        'day' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'history' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'analytics' => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'export' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

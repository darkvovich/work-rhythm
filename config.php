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

/**
 * Возвращает сервисный Bearer-токен для Machine-to-Machine REST API.
 * Если файл data/api_token.txt отсутствует, генерирует криптостойкий 64-символьный hex-токен.
 *
 * @return string Сервисный API-токен.
 */
function api_service_token(): string
{
    $file = DATA_DIR . DIRECTORY_SEPARATOR . 'api_token.txt';
    if (is_file($file)) {
        $token = trim((string) @file_get_contents($file));
        if ($token !== '') {
            return $token;
        }
    }
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    $token = bin2hex(random_bytes(32));
    @file_put_contents($file, $token);
    @chmod($file, 0600);
    return $token;
}

/**
 * Принудительно перегенерирует сервисный Bearer-токен API и сохраняет в data/api_token.txt.
 *
 * @return string Новый 64-символьный hex-токен.
 */
function rotate_api_service_token(): string
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }
    $file = DATA_DIR . DIRECTORY_SEPARATOR . 'api_token.txt';
    $token = bin2hex(random_bytes(32));
    file_put_contents($file, $token);
    @chmod($file, 0600);
    return $token;
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
        'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'auto' => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * Возвращает CSS-класс темы для тега <html> на основе cookie wr_theme.
 *
 * @return string Класс темы: 'theme-dark', 'theme-light' или пустая строка для auto.
 */
function theme_html_class(): string
{
    $theme = $_COOKIE['wr_theme'] ?? 'auto';
    if ($theme === 'dark') {
        return 'theme-dark';
    }
    if ($theme === 'light') {
        return 'theme-light';
    }
    return '';
}

/**
 * Возвращает инлайн-скрипт для применения темы до рендера страницы (предотвращение мигания белым).
 *
 * @return string HTML-тег <script> с кодом проверки localStorage и prefers-color-scheme.
 */
function theme_inline_script(): string
{
    return '<script>'
        . '(function(){'
        . 'try{'
        . 'var t=localStorage.getItem("wr_theme")||"auto";'
        . 'var m=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches;'
        . 'if(t==="dark"||(t==="auto"&&m)){document.documentElement.classList.add("theme-dark");}'
        . 'else if(t==="light"){document.documentElement.classList.add("theme-light");}'
        . '}catch(e){}'
        . '})();'
        . '</script>';
}

/**
 * Возвращает HTML-разметку кнопок переключения темы (Светлая / Авто / Тёмная).
 *
 * @return string HTML-разметка блока переключателя.
 */
function theme_switch_html(): string
{
    return '<div class="theme-switch" role="group" aria-label="Выбор темы">'
        . '<button type="button" class="theme-btn" data-theme-val="light" title="Светлая тема" aria-label="Светлая тема">' . ico('sun') . '<span class="theme-label">Светлая</span></button>'
        . '<button type="button" class="theme-btn" data-theme-val="auto" title="Системная тема" aria-label="Системная тема">' . ico('auto') . '<span class="theme-label">Авто</span></button>'
        . '<button type="button" class="theme-btn" data-theme-val="dark" title="Тёмная тема" aria-label="Тёмная тема">' . ico('moon') . '<span class="theme-label">Тёмная</span></button>'
        . '</div>';
}

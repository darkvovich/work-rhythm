<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function b64url_encode(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64url_decode(string $s): string
{
    $pad = (4 - strlen($s) % 4) % 4;
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', $pad));
}

function auth_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

// Подписанный токен: base64(payload) . base64url(HMAC-SHA256)
function make_auth_token(): string
{
    $payload = json_encode(['u' => APP_USER, 'exp' => time() + AUTH_LIFETIME], JSON_UNESCAPED_UNICODE);
    $body = b64url_encode((string) $payload);
    $sig = hash_hmac('sha256', $body, auth_secret(), true);
    return $body . '.' . b64url_encode($sig);
}

function verify_auth_token(string $token): bool
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    [$body, $sig] = $parts;

    $expected = hash_hmac('sha256', $body, auth_secret(), true);
    $given = b64url_decode($sig);
    if ($given === '' || !hash_equals($expected, $given)) {
        return false;
    }

    $data = json_decode(b64url_decode($body), true);
    if (!is_array($data)) {
        return false;
    }
    if (($data['u'] ?? null) !== APP_USER) {
        return false;
    }
    if (!isset($data['exp']) || (int) $data['exp'] < time()) {
        return false;
    }
    return true;
}

function is_logged_in(): bool
{
    $token = $_COOKIE[AUTH_COOKIE] ?? '';
    return is_string($token) && $token !== '' && verify_auth_token($token);
}

function set_auth_cookie(string $token): void
{
    setcookie(AUTH_COOKIE, $token, [
        'expires' => time() + AUTH_LIFETIME,
        'path' => '/',
        'secure' => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_user(): void
{
    set_auth_cookie(make_auth_token());
}

function clear_auth_cookie(): void
{
    setcookie(AUTH_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}

function do_logout(): void
{
    clear_auth_cookie();
}

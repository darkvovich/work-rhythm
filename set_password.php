<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Режим командной строки: php set_password.php <новый пароль>
if (PHP_SAPI === 'cli') {
    $password = $argv[1] ?? null;
    if ($password === null || $password === '') {
        fwrite(STDOUT, "Использование: php set_password.php <новый пароль>\n");
        exit(1);
    }
    save_password_hash(password_hash($password, PASSWORD_DEFAULT));
    rotate_auth_secret();
    fwrite(STDOUT, "Пароль обновлён.\n");
    exit;
}

require_login();

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($new) < 4) {
        $error = 'Пароль должен быть не короче 4 символов.';
    } elseif ($new !== $confirm) {
        $error = 'Пароли не совпадают.';
    } else {
        save_password_hash(password_hash($new, PASSWORD_DEFAULT));
        rotate_auth_secret();
        login_user();
        $message = 'Пароль обновлён.';
    }
}

$active = 'settings';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Пароль — Рабочий ритм</title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/icon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32.png">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
<meta name="theme-color" content="#20242b">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
<div class="app">
<aside>
  <div class="logo">Рабочий ритм</div>
  <nav class="nav">
    <a href="<?= BASE_URL ?>/"><?= ico('day') ?><span class="nav-label">Сегодня</span></a>
    <a href="<?= BASE_URL ?>/history"><?= ico('history') ?><span class="nav-label">История</span></a>
    <a href="<?= BASE_URL ?>/analytics"><?= ico('analytics') ?><span class="nav-label">Аналитика</span></a>
    <a href="<?= BASE_URL ?>/export"><?= ico('export') ?><span class="nav-label">Экспорт</span></a>
  </nav>
</aside>

<main>
<div class="top"><div><h1>Пароль</h1><div class="muted">Смена пароля для входа</div></div></div>

<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section">
  <div class="sh"><h2>Новый пароль</h2><p>Пароль хранится только в виде хеша (password_hash)</p></div>
  <div class="fields">
    <div class="full">
      <form method="post" action="<?= BASE_URL ?>/settings">
        <label class="title" for="new_password">Новый пароль</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
        <label class="title" for="confirm_password">Повторите пароль</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
        <button type="submit" class="btn primary" style="margin-top:18px">Сохранить пароль</button>
      </form>
    </div>
  </div>
</div>
<footer class="footer">
  <a href="<?= BASE_URL ?>/settings">Смена пароля</a>
  <a href="<?= BASE_URL ?>/logout">Выйти</a>
</footer>
</main>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>

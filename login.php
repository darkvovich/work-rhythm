<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if ($user === APP_USER && password_verify($pass, PASSWORD_HASH)) {
        login_user();
        header('Location: ' . BASE_URL . '/');
        exit;
    }
    $error = 'Неверный логин или пароль.';
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — Рабочий ритм</title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/icon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32.png">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
<meta name="theme-color" content="#20242b">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="login-body">
<div class="login-card">
  <div class="logo">Рабочий ритм</div>
  <p class="muted">Войдите, чтобы заполнять и анализировать свой день.</p>
  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="<?= htmlspecialchars(BASE_URL) ?>/login">
    <label class="title" for="username">Логин</label>
    <input type="text" id="username" name="username" autocomplete="username" required autofocus>
    <label class="title" for="password">Пароль</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit" class="btn primary login-btn">Войти</button>
  </form>
</div>
</body>
</html>

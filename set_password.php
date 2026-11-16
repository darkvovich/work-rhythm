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
$tokenMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'password');
    if ($action === 'rotate_token') {
        rotate_api_service_token();
        $tokenMessage = 'Сервисный API-токен успешно обновлён.';
    } else {
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
}

$apiToken = api_service_token();

$active = 'settings';
?>
<!doctype html>
<html lang="ru"<?= theme_html_class() !== '' ? ' class="' . theme_html_class() . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Настройки — Рабочий ритм</title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/icon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32.png">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
<link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
<meta name="theme-color" content="#20242b">
<?= theme_inline_script() ?>
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
  <?= theme_switch_html() ?>
</aside>

<main>
<div class="top"><div><h1>Настройки</h1><div class="muted">Пароль и интеграции</div></div></div>

<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section">
  <div class="sh"><h2>Новый пароль</h2><p>Пароль хранится только в виде хеша (password_hash)</p></div>
  <div class="fields">
    <div class="full">
      <form method="post" action="<?= BASE_URL ?>/settings">
        <input type="hidden" name="action" value="password">
        <label class="title" for="new_password">Новый пароль</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
        <label class="title" for="confirm_password">Повторите пароль</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
        <button type="submit" class="btn primary" style="margin-top:18px">Сохранить пароль</button>
      </form>
    </div>
  </div>
</div>

<div class="section">
  <div class="sh">
    <h2>Сервисный REST API (v1)</h2>
    <p>Токен для межсервисной интеграции (CRM of Everything, Weeek, Telegram-боты)</p>
  </div>
  <div class="fields">
    <div class="full">
      <?php if ($tokenMessage): ?><div class="alert success"><?= htmlspecialchars($tokenMessage) ?></div><?php endif; ?>
      <label class="title" for="api_token">Bearer Token (Заголовок Authorization: Bearer &lt;TOKEN&gt;)</label>
      <div style="display:flex;gap:8px;align-items:center">
        <input type="text" id="api_token" value="<?= htmlspecialchars($apiToken) ?>" readonly style="font-family:monospace;font-size:13px">
        <button type="button" class="btn" id="copy-token-btn" style="white-space:nowrap">Копировать</button>
      </div>
      <form method="post" action="<?= BASE_URL ?>/settings" style="margin-top:14px" onsubmit="return confirm('Сгенерировать новый сервисный токен? Старый токен перестанет действовать.');">
        <input type="hidden" name="action" value="rotate_token">
        <button type="submit" class="btn">Перегенерировать токен</button>
      </form>
    </div>
  </div>
</div>

<footer class="footer">
  <a href="<?= BASE_URL ?>/settings">Настройки</a>
  <a href="<?= BASE_URL ?>/logout">Выйти</a>
</footer>
</main>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
document.getElementById('copy-token-btn')?.addEventListener('click', function () {
  var inp = document.getElementById('api_token');
  if (inp) {
    inp.select();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(inp.value).then(function () {
        var b = document.getElementById('copy-token-btn');
        b.textContent = 'Скопировано!';
        setTimeout(function () { b.textContent = 'Копировать'; }, 2000);
      });
    }
  }
});
</script>
</body>
</html>

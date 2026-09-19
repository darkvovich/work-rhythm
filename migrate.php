<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

require_login();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = migrate_schema(db());
}

$missing = missing_columns(db());

$backups = [];
$backupDir = DATA_DIR . DIRECTORY_SEPARATOR . 'backups';
foreach (glob($backupDir . DIRECTORY_SEPARATOR . 'tracker-*.sqlite') ?: [] as $f) {
    $backups[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
}
usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);

function fmt_size(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' МБ';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' КБ';
    return $bytes . ' Б';
}

$active = 'settings';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Миграция БД — Рабочий ритм</title>
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
<div class="top">
  <div><h1>Миграция базы данных</h1><div class="muted">Обновление схемы (только добавление колонок)</div></div>
</div>

<?php if ($result !== null): ?>
  <?php if ($result['status'] === 'ok'): ?>
    <div class="alert success">
      Миграция выполнена. Добавлены колонки:
      <?= htmlspecialchars(implode(', ', $result['added'])) ?>.
      <?php if ($result['backup']): ?>
        Бэкап: <b><?= htmlspecialchars(basename($result['backup'])) ?></b>.
      <?php endif; ?>
    </div>
  <?php elseif ($result['status'] === 'up-to-date'): ?>
    <div class="alert info">Изменений не требуется — схема уже актуальна.</div>
  <?php else: ?>
    <div class="alert error">Миграция прервана: не удалось создать бэкап. Данные не изменялись.</div>
  <?php endif; ?>
<?php endif; ?>

<div class="section">
  <div class="sh"><h2>Состояние схемы</h2><p>Таблица days</p></div>
  <div class="fields">
    <div class="full">
      <?php if ($missing): ?>
        <div class="alert warn nomargin">
          Требуется миграция: не хватает <?= count($missing) ?> колонок —
          <?= htmlspecialchars(implode(', ', array_keys($missing))) ?>.
        </div>
        <form method="post" action="<?= BASE_URL ?>/migrate" class="migrate-form">
          <button type="submit" class="btn primary">Выполнить миграцию</button>
        </form>
      <?php else: ?>
        <div class="alert success nomargin">Схема актуальна, миграция не нужна.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Бэкапы</h2><p>Хранятся последние 10; восстановление — вручную</p></div>
  <?php if ($backups): ?>
  <div class="tablewrap">
    <table>
      <thead><tr><th>Файл</th><th>Размер</th><th>Дата</th></tr></thead>
      <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['name']) ?></td>
          <td><?= htmlspecialchars(fmt_size($b['size'])) ?></td>
          <td><?= htmlspecialchars(date('Y-m-d H:i', $b['time'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div class="fields"><div class="muted">Бэкапов пока нет.</div></div>
  <?php endif; ?>
  <div class="disclaimer">
    Чтобы восстановить: остановите сайт, скопируйте нужный файл из
    <b>data/backups/</b> в <b>data/tracker.sqlite</b> (перезаписав), затем обновите страницу.
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

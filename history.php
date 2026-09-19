<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

require_login();

$days = list_days();
$active = 'history';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>История — Рабочий ритм</title>
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
    <a href="<?= BASE_URL ?>/history" class="active"><?= ico('history') ?><span class="nav-label">История</span></a>
    <a href="<?= BASE_URL ?>/analytics"><?= ico('analytics') ?><span class="nav-label">Аналитика</span></a>
    <a href="<?= BASE_URL ?>/export"><?= ico('export') ?><span class="nav-label">Экспорт</span></a>
  </nav>
</aside>

<main>
<div class="top">
  <div><h1>История</h1><div class="muted">Все дни эксперимента</div></div>
</div>

<?php if (!$days): ?>
<div class="section"><div class="fields"><div class="muted">Пока нет записей. Заполните первый день на вкладке «Сегодня».</div></div></div>
<?php else: ?>
<div class="tablewrap">
<table>
  <thead>
    <tr>
      <th>Дата</th>
      <th>Тип дня</th>
      <th>Сон</th>
      <th>Энергия утром</th>
      <th>Готовность</th>
      <th>Фокус</th>
      <th>Результат</th>
      <th>Энергия вечером</th>
      <th>Завтра</th>
      <th>Статус</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($days as $d): ?>
    <tr>
      <td><a href="<?= BASE_URL ?>/day/<?= htmlspecialchars($d['date']) ?>"><?= htmlspecialchars(fmt_date($d['date'])) ?></a></td>
      <td><?= htmlspecialchars(day_type_label($d['day_type'])) ?></td>
      <td><?= htmlspecialchars(fmt_hours($d['sleep_hours'])) ?><?= $d['sleep_quality'] ? ' · ' . $d['sleep_quality'] . '/5' : '' ?></td>
      <td><?= htmlspecialchars(fmt_scale($d['morning_energy'])) ?></td>
      <td><?= htmlspecialchars(fmt_scale($d['day_readiness'])) ?></td>
      <td><?= htmlspecialchars(fmt_hours($d['focused_work_hours'])) ?></td>
      <td><?= htmlspecialchars(fmt_scale($d['work_result'])) ?></td>
      <td><?= htmlspecialchars(fmt_scale($d['evening_energy'])) ?></td>
      <td><?= htmlspecialchars(fmt_scale($d['tomorrow_motivation'])) ?></td>
      <td><?= $d['status'] === 'completed' ? '<span class="badge">Завершён</span>' : '<span class="badge gray">Черновик</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<footer class="footer">
  <a href="<?= BASE_URL ?>/settings">Смена пароля</a>
  <a href="<?= BASE_URL ?>/logout">Выйти</a>
</footer>
</main>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

require_login();

$days = list_days();
$completed = array_values(array_filter($days, fn($d) => $d['status'] === 'completed'));
$workDays = array_values(array_filter($completed, fn($d) => $d['day_type'] === 'work'));

usort($workDays, fn($a, $b) => strcmp($a['date'], $b['date']));
$completedSorted = $completed;
usort($completedSorted, fn($a, $b) => strcmp($a['date'], $b['date']));

function avg(array $rows, string $f): ?float
{
    $vals = [];
    foreach ($rows as $r) {
        if (($r[$f] ?? null) !== null && $r[$f] !== '') $vals[] = (float) $r[$f];
    }
    return $vals ? array_sum($vals) / count($vals) : null;
}

function sum(array $rows, string $f): ?float
{
    $total = 0.0;
    $any = false;
    foreach ($rows as $r) {
        if (($r[$f] ?? null) !== null && $r[$f] !== '') {
            $total += (float) $r[$f];
            $any = true;
        }
    }
    return $any ? $total : null;
}

function pairs(array $rows, string $a, string $b): array
{
    $xs = [];
    $ys = [];
    foreach ($rows as $r) {
        if (($r[$a] ?? null) !== null && ($r[$b] ?? null) !== null && $r[$a] !== '' && $r[$b] !== '') {
            $xs[] = (float) $r[$a];
            $ys[] = (float) $r[$b];
        }
    }
    return [$xs, $ys];
}

function pearson(array $xs, array $ys): ?float
{
    $n = count($xs);
    if ($n !== count($ys) || $n < 3) return null;
    $mx = array_sum($xs) / $n;
    $my = array_sum($ys) / $n;
    $num = 0;
    $dx = 0;
    $dy = 0;
    for ($i = 0; $i < $n; $i++) {
        $a = $xs[$i] - $mx;
        $b = $ys[$i] - $my;
        $num += $a * $b;
        $dx += $a * $a;
        $dy += $b * $b;
    }
    if ($dx == 0 || $dy == 0) return null;
    return $num / sqrt($dx * $dy);
}

function fmt_num($v, int $dec = 1): string
{
    if ($v === null) return '—';
    return number_format((float) $v, $dec, '.', ' ');
}

// --- Метрики ---
$metrics = [
    ['Средний фокус', 'work', 'focused_work_hours', 'h'],
    ['Средняя результативность', 'work', 'work_result', '/ 5'],
    ['Средний сон', 'all', 'sleep_hours', 'h'],
    ['Энергия утром', 'all', 'morning_energy', '/ 5'],
    ['Энергия вечером', 'all', 'evening_energy', '/ 5'],
    ['Готовность работать завтра', 'all', 'tomorrow_motivation', '/ 5'],
    ['Работа над будущим, итого', 'sum', 'future_work_hours', 'h'],
];

// --- Числовые корреляции ---
$correlationDefs = [
    ['Сон → энергия утром', 'sleep_hours', 'morning_energy', $completedSorted],
    ['Энергия утром → результативность', 'morning_energy', 'work_result', $workDays],
    ['Готовность к дню → результативность', 'day_readiness', 'work_result', $workDays],
    ['Готовность к дню → фокус (часы)', 'day_readiness', 'focused_work_hours', $workDays],
    ['Фокус (часы) → результативность', 'focused_work_hours', 'work_result', $workDays],
    ['Результативность → энергия вечером', 'work_result', 'evening_energy', $workDays],
    ['Энергия вечером → готовность работать завтра', 'evening_energy', 'tomorrow_motivation', $completedSorted],
    ['Время на будущее → готовность работать завтра', 'future_work_hours', 'tomorrow_motivation', $completedSorted],
    ['Значимость для будущего → готовность работать завтра', 'future_work_importance', 'tomorrow_motivation', $completedSorted],
];

$correlations = [];
foreach ($correlationDefs as [$title, $a, $b, $rows]) {
    [$xs, $ys] = pairs($rows, $a, $b);
    $correlations[] = [
        'title' => $title,
        'r' => pearson($xs, $ys),
        'n' => count($xs),
    ];
}

function group_compare(string $yesLabel, string $noLabel, string $field, string $metric, array $rows): array
{
    $a = [];
    $b = [];
    foreach ($rows as $r) {
        $v = $r[$metric] ?? null;
        if ($v === null || $v === '') continue;
        $g = $r[$field];
        if ((string) $g === '1') $a[] = (float) $v;
        elseif ((string) $g === '0') $b[] = (float) $v;
    }
    return [
        'yesLabel' => $yesLabel,
        'noLabel' => $noLabel,
        'yesAvg' => $a ? array_sum($a) / count($a) : null,
        'noAvg' => $b ? array_sum($b) / count($b) : null,
        'yesN' => count($a),
        'noN' => count($b),
    ];
}

// --- Данные для графиков ---
$chartData = [
    'workTrend' => [
        'labels' => array_map(fn($d) => fmt_date($d['date']), $workDays),
        'focused' => array_map(fn($d) => $d['focused_work_hours'] !== null ? (float) $d['focused_work_hours'] : null, $workDays),
        'result' => array_map(fn($d) => $d['work_result'] !== null ? (int) $d['work_result'] : null, $workDays),
    ],
    'sleepTrend' => [
        'labels' => array_map(fn($d) => fmt_date($d['date']), $completedSorted),
        'sleep' => array_map(fn($d) => $d['sleep_hours'] !== null ? (float) $d['sleep_hours'] : null, $completedSorted),
        'morning' => array_map(fn($d) => $d['morning_energy'] !== null ? (int) $d['morning_energy'] : null, $completedSorted),
    ],
    'workFuture' => [
        'labels' => array_map(fn($d) => fmt_date($d['date']), $completedSorted),
        'work' => array_map(fn($d) => $d['focused_work_hours'] !== null ? (float) $d['focused_work_hours'] : null, $completedSorted),
        'future' => array_map(fn($d) => $d['future_work_hours'] !== null ? (float) $d['future_work_hours'] : null, $completedSorted),
    ],
];

$leftHome = group_compare('Выходил из дома', 'Не выходил', 'left_home', 'work_result', $workDays);
$chartData['leftHome'] = ['labels' => [$leftHome['yesLabel'], $leftHome['noLabel']], 'values' => [$leftHome['yesAvg'], $leftHome['noAvg']]];

$exercise = group_compare('Был спорт', 'Без спорта', 'exercise', 'work_result', $workDays);
$chartData['exercise'] = ['labels' => [$exercise['yesLabel'], $exercise['noLabel']], 'values' => [$exercise['yesAvg'], $exercise['noAvg']]];

$exerciseEvening = group_compare('Был спорт', 'Без спорта', 'exercise', 'evening_energy', $completedSorted);
$exerciseMorning = group_compare('Был спорт', 'Без спорта', 'exercise', 'morning_energy', $completedSorted);

$timeOutsideLabels = [];
$timeOutsideValues = [];
foreach (TIME_OUTSIDE_VALUES as $bucket) {
    $vals = [];
    foreach ($workDays as $r) {
        if ($r['time_outside'] === $bucket && $r['work_result'] !== null && $r['work_result'] !== '') {
            $vals[] = (float) $r['work_result'];
        }
    }
    $timeOutsideLabels[] = time_outside_label($bucket);
    $timeOutsideValues[] = $vals ? array_sum($vals) / count($vals) : null;
}
$chartData['timeOutside'] = ['labels' => $timeOutsideLabels, 'values' => $timeOutsideValues];

[$ex, $ey] = pairs($completedSorted, 'evening_energy', 'tomorrow_motivation');
$scatter = [];
for ($i = 0; $i < count($ex); $i++) $scatter[] = ['x' => $ex[$i], 'y' => $ey[$i]];
$chartData['eveningTomorrow'] = ['points' => $scatter];

$hasData = count($completedSorted) > 0;
$active = 'analytics';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Аналитика — Рабочий ритм</title>
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
    <a href="<?= BASE_URL ?>/analytics" class="active"><?= ico('analytics') ?><span class="nav-label">Аналитика</span></a>
    <a href="<?= BASE_URL ?>/export"><?= ico('export') ?><span class="nav-label">Экспорт</span></a>
  </nav>
</aside>

<main>
<div class="top">
  <div><h1>Аналитика</h1><div class="muted">Связи и закономерности по накопленным данным</div></div>
</div>

<?php if (!$hasData): ?>
<div class="section"><div class="fields"><div class="muted">Недостаточно данных. Завершайте дни — и здесь появятся графики и связи.</div></div></div>
<?php else: ?>

<div class="cards">
<?php foreach ($metrics as [$label, $scope, $field, $unit]):
    $rows = $scope === 'work' ? $workDays : $completedSorted;
    $v = $scope === 'sum' ? sum($completedSorted, $field) : avg($rows, $field);
?>
  <div class="metric"><small><?= htmlspecialchars($label) ?></small><strong><?= htmlspecialchars(fmt_num($v)) ?> <?= htmlspecialchars($unit) ?></strong></div>
<?php endforeach; ?>
</div>

<div class="section">
  <div class="sh"><h2>Текущая работа и работа над будущим</h2><p>Это разные категории, они не суммируются в одну продуктивность</p></div>
  <div class="chart"><canvas id="chart-workfuture"></canvas></div>
</div>

<div class="section">
  <div class="sh"><h2>Работа по дням</h2><p>Фокусные часы и результативность</p></div>
  <div class="chart"><canvas id="chart-work"></canvas></div>
</div>

<div class="section">
  <div class="sh"><h2>Сон и энергия утром</h2><p>Часы сна и утренняя энергия по дням</p></div>
  <div class="chart"><canvas id="chart-sleep"></canvas></div>
</div>

<div class="grid2">
  <div class="section">
    <div class="sh"><h2>Выход из дома → результативность</h2><p>Средний результат работы</p></div>
    <div class="chart"><canvas id="chart-lefthome"></canvas></div>
  </div>
  <div class="section">
    <div class="sh"><h2>Спорт → результативность</h2><p>Средний результат работы</p></div>
    <div class="chart"><canvas id="chart-exercise"></canvas></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Время вне дома → результативность</h2><p>Средний результат работы по длительности выхода</p></div>
  <div class="chart"><canvas id="chart-timeoutside"></canvas></div>
</div>

<div class="section">
  <div class="sh"><h2>Энергия вечером → готовность работать завтра</h2><p>Каждая точка — один день</p></div>
  <div class="chart"><canvas id="chart-eveningtomorrow"></canvas></div>
</div>

<div class="section">
  <div class="sh"><h2>Связи</h2><p>Корреляция по числовым показателям</p></div>
  <div class="corr-list">
    <?php foreach ($correlations as $c): ?>
    <div class="corr-row">
      <span class="corr-title"><?= htmlspecialchars($c['title']) ?></span>
      <span class="corr-val"><?= $c['r'] === null ? 'мало данных' : 'r = ' . fmt_num($c['r'], 2) . ' · N=' . $c['n'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="disclaimer">Корреляция показывает связь, но не причинность. Это повод для размышлений, а не выводы.</div>
</div>

<div class="section">
  <div class="sh"><h2>Различия по группам</h2><p>Средние значения в разных условиях</p></div>
  <div class="corr-list">
    <?php
    $groups = [
        ['Выход из дома', $leftHome['yesLabel'], $leftHome['noLabel'], $leftHome['yesAvg'], $leftHome['noAvg'], 'результат'],
        ['Спорт', $exercise['yesLabel'], $exercise['noLabel'], $exercise['yesAvg'], $exercise['noAvg'], 'результат'],
        ['Спорт', $exerciseEvening['yesLabel'], $exerciseEvening['noLabel'], $exerciseEvening['yesAvg'], $exerciseEvening['noAvg'], 'энергия вечером'],
        ['Спорт', $exerciseMorning['yesLabel'], $exerciseMorning['noLabel'], $exerciseMorning['yesAvg'], $exerciseMorning['noAvg'], 'энергия утром'],
    ];
    foreach ($groups as [$dim, $yesL, $noL, $yesA, $noA, $metric]):
    ?>
    <div class="corr-row">
      <span class="corr-title"><?= htmlspecialchars($dim) ?> → <?= htmlspecialchars($metric) ?></span>
      <span class="corr-val"><?= htmlspecialchars($yesL) ?>: <?= fmt_num($yesA) ?> · <?= htmlspecialchars($noL) ?>: <?= fmt_num($noA) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endif; ?>
<footer class="footer">
  <a href="<?= BASE_URL ?>/settings">Смена пароля</a>
  <a href="<?= BASE_URL ?>/logout">Выйти</a>
</footer>
</main>
</div>

<script>window.ANALYTICS = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/analytics.js') ?>"></script>
</body>
</html>

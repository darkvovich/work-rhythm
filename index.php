<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

require_login();

$today = date('Y-m-d');
$date = (string) ($_GET['date'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}
if ($date > $today) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$isToday = $date === $today;
$day = get_day($date) ?? [];
if (($day['day_type'] ?? null) === null) {
    $day['day_type'] = 'work';
}
if (($day['future_work'] ?? null) === null) {
    $day['future_work'] = 0;
}
if (($day['dayoff_worked'] ?? null) === null) {
    $day['dayoff_worked'] = 0;
}
$readonly = (($day['status'] ?? null) === 'completed');

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

function split_hours($value): array
{
    if ($value === null || $value === '') {
        return ['', ''];
    }
    $h = (float) $value;
    $whole = (int) floor($h);
    $mins = (int) round(($h - floor($h)) * 60);
    if ($mins === 60) { $whole++; $mins = 0; }
    return [(string) $whole, (string) $mins];
}

[$sleepHoursPart, $sleepMinutesPart] = split_hours($day['sleep_hours'] ?? null);
[$fwHoursPart, $fwMinutesPart] = split_hours($day['future_work_hours'] ?? null);

// --- Презентационные хелперы ---
function val(array $day, string $k): string
{
    return isset($day[$k]) && $day[$k] !== null ? (string) $day[$k] : '';
}
function is_checked(array $day, string $k, $v): bool
{
    return isset($day[$k]) && $day[$k] !== null && (string) $day[$k] === (string) $v;
}
function reset_button(string $name, bool $dis): string
{
    return $dis ? '' : '<button type="button" class="reset-btn" data-reset="' . $name . '" title="Сбросить" aria-label="Сбросить">×</button>';
}
function scale_html(string $name, array $day, bool $dis): string
{
    $s = '<div class="scale choices">';
    for ($i = 1; $i <= 5; $i++) {
        $c = is_checked($day, $name, $i) ? ' checked' : '';
        $d = $dis ? ' disabled' : '';
        $s .= '<label class="choice"><input type="radio" name="' . $name . '" value="' . $i . '"' . $c . $d . '><span>' . $i . '</span></label>';
    }
    $s .= '</div>';
    return '<div class="scale-row">' . $s . reset_button($name, $dis) . '</div>';
}
function choice_html(string $name, array $opts, array $day, bool $dis, bool $reset = true): string
{
    $s = '<div class="choices">';
    foreach ($opts as $v => $lbl) {
        $c = is_checked($day, $name, $v) ? ' checked' : '';
        $d = $dis ? ' disabled' : '';
        $s .= '<label class="choice"><input type="radio" name="' . $name . '" value="' . htmlspecialchars((string) $v) . '"' . $c . $d . '><span>' . htmlspecialchars($lbl) . '</span></label>';
    }
    return $s . ($reset ? reset_button($name, $dis) : '') . '</div>';
}
function input_html(string $name, string $type, array $day, bool $dis, string $placeholder = '', string $step = '', ?string $value = null): string
{
    $v = htmlspecialchars($value ?? val($day, $name));
    $d = $dis ? ' disabled' : '';
    $st = $step !== '' ? ' step="' . $step . '"' : '';
    return '<input type="' . $type . '" name="' . $name . '" value="' . $v . '" placeholder="' . htmlspecialchars($placeholder) . '"' . $st . $d . '>';
}
function title_html(string $label, array $tip = []): string
{
    $t = '';
    if ($tip) {
        $t .= '<span class="info" tabindex="0" role="button" aria-label="Подсказка">i</span>';
        $t .= '<span class="tip-pop">';
        $t .= '<span class="tip-q">' . htmlspecialchars($tip['q']) . '</span>';
        foreach ([1, 3, 5] as $k) {
            $t .= '<span class="tip-line"><b>' . $k . '</b> — ' . htmlspecialchars($tip[$k]) . '</span>';
        }
        if (!empty($tip['note'])) {
            $t .= '<span class="tip-note">' . htmlspecialchars($tip['note']) . '</span>';
        }
        $t .= '</span>';
    }
    return '<label class="title">' . htmlspecialchars($label) . $t . '</label>';
}
function select_html(string $name, array $opts, array $day, bool $dis, string $placeholder = 'Выбрать…'): string
{
    $s = '<select name="' . $name . '"' . ($dis ? ' disabled' : '') . '>';
    $s .= '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    foreach ($opts as $v) {
        $c = is_checked($day, $name, $v) ? ' selected' : '';
        $s .= '<option value="' . htmlspecialchars($v) . '"' . $c . '>' . htmlspecialchars($v) . '</option>';
    }
    return $s . '</select>';
}

$dayTypeOpts = ['work' => 'Рабочий день', 'day_off' => 'Выходной по плану'];
$yesNoOpts = [1 => 'Да', 0 => 'Нет'];
$timeOutsideOpts = [
    'less_30' => 'Менее 30 минут',
    '30_60' => '30 минут–1 час',
    '1_3' => '1–3 часа',
    'more_3' => 'Более 3 часов',
];
$youtubeOpts = [
    'none' => 'Не было',
    'lt30' => 'Менее 30 минут',
    '30_60' => '30–60 минут',
    '1_2' => '1–2 часа',
    'gt2' => 'Более 2 часов',
];
$exerciseTypeOpts = ['Бег', 'Силовая', 'Йога', 'Велосипед', 'Плавание', 'Прогулка', 'Другое'];
$dipActionOpts = ['Прогулка', 'Спорт', 'Отдых', 'YouTube', 'Другое'];
$distractionOpts = ['Усталость', 'Тревога', 'Скука', 'YouTube', 'Сообщения', 'Другое'];
$stopReasonOpts = ['План выполнен', 'Устал', 'Потерял концентрацию', 'Стало скучно', 'YouTube', 'Тревога — надо ещё работать', 'Спорт', 'Семья / друзья', 'Другое'];

$tips = [
    'sleep_quality' => [
        'q' => 'Как ты оцениваешь качество сна?',
        1 => 'очень плохой сон: плохо спал, часто просыпался, сон не восстановил.',
        3 => 'обычный сон: без особых проблем.',
        5 => 'очень хороший сон: глубокий/непрерывный, хорошо восстановился.',
    ],
    'morning_energy' => [
        'q' => 'Сколько у тебя сейчас физического и психического ресурса?',
        1 => 'разбит, хочется лежать, обычные дела даются тяжело.',
        3 => 'нормальный ресурс, могу заниматься обычными делами и работать.',
        5 => 'много сил, чувствую себя заряженным.',
        'note' => 'Энергия ≠ желание работать.',
    ],
    'day_readiness' => [
        'q' => 'Насколько ты уже перешёл из состояния сна/отдыха в состояние начавшегося дня?',
        1 => 'день ещё фактически не начался: лежу, пижама, не привёл себя в порядок, остаюсь в «домашнем режиме».',
        3 => 'нормально подготовился к дню и могу его начинать.',
        5 => 'полностью собран, приведён в порядок и ощущаю явный старт дня.',
        'note' => 'Это не оценка желания работать и не оценка энергии.',
    ],
    'focus_quality' => [
        'q' => 'Насколько хорошо ты удерживал внимание на работе?',
        1 => 'постоянно отвлекался и с трудом удерживал внимание.',
        3 => 'обычный фокус, периодически отвлекался.',
        5 => 'глубокая концентрация, долго удерживал внимание на задаче.',
    ],
    'work_result' => [
        'q' => 'Насколько полезный результат ты получил относительно затраченного рабочего времени?',
        1 => 'почти ничего полезного не сделал.',
        3 => 'нормальный рабочий результат.',
        5 => 'сделал очень много важного / существенно продвинулся.',
        'note' => 'Это не количество часов.',
    ],
    'evening_energy' => [
        'q' => 'Сколько ресурса у тебя осталось к концу дня?',
        1 => 'полностью выжат.',
        3 => 'обычная усталость после дня.',
        5 => 'много энергии, чувствую себя свежим и могу ещё заниматься делами.',
    ],
    'tomorrow_motivation' => [
        'q' => 'Если завтра обычный рабочий день, насколько тебе хочется и легко представить, что ты снова будешь работать?',
        1 => 'совсем не хочется, мысль о работе отталкивает.',
        3 => 'нейтрально: надо будет — буду работать.',
        5 => 'хочется продолжить, есть интерес/предвкушение.',
        'note' => 'Это не энергия и не усталость.',
    ],
    'future_work_importance' => [
        'q' => 'Насколько это было значимо для будущего?',
        1 => 'почти незначимо.',
        3 => 'полезно и имеет смысл.',
        5 => 'очень важный шаг для будущего.',
    ],
];

$active = 'day';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Сегодня — Рабочий ритм</title>
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
    <a href="<?= BASE_URL ?>/" class="active"><?= ico('day') ?><span class="nav-label">Сегодня</span></a>
    <a href="<?= BASE_URL ?>/history"><?= ico('history') ?><span class="nav-label">История</span></a>
    <a href="<?= BASE_URL ?>/analytics"><?= ico('analytics') ?><span class="nav-label">Аналитика</span></a>
    <a href="<?= BASE_URL ?>/export"><?= ico('export') ?><span class="nav-label">Экспорт</span></a>
  </nav>
</aside>

<main>
<?= migration_banner() ?>
<div class="top">
  <div>
    <h1>Дневник</h1>
    <div class="muted"><?= htmlspecialchars(fmt_date_full($date)) ?></div>
  </div>
  <div class="top-right">
    <div class="date">
      <a class="date-btn" href="<?= BASE_URL ?>/day/<?= $prevDate ?>" title="Предыдущий день" aria-label="Предыдущий день">←</a>
      <span><?= htmlspecialchars(fmt_date_short($date)) ?></span>
      <?php if ($date < $today): ?>
        <a class="date-btn" href="<?= BASE_URL ?>/day/<?= $nextDate ?>" title="Следующий день" aria-label="Следующий день">→</a>
      <?php else: ?>
        <button class="date-btn" disabled title="Сегодня" aria-label="Следующий день">→</button>
      <?php endif; ?>
    </div>
    <?php if (!$isToday): ?><a class="btn" href="<?= BASE_URL ?>/">Сегодня</a><?php endif; ?>
  </div>
</div>

<?php if (($day['status'] ?? null) === 'completed'): ?>
  <div class="alert success">Этот день завершён и доступен только для просмотра.</div>
<?php elseif (!$isToday): ?>
  <div class="alert info">Вы заполняете прошедший день.</div>
<?php endif; ?>

<?php if (!$readonly): ?>
<div class="progress">
  <div class="prow"><b>Заполнение дня</b><span class="muted" id="progress-label"></span></div>
  <div class="bar"><i id="progress-bar" style="width:0%"></i></div>
</div>
<?php endif; ?>

<form id="day-form" autocomplete="off" <?= $readonly ? 'class="readonly"' : '' ?>>

<div class="section">
  <div class="sh"><h2>Тип дня</h2><p>Какой сегодня день?</p></div>
  <div class="fields">
    <div class="full"><?= choice_html('day_type', $dayTypeOpts, $day, $readonly, false) ?></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Сон</h2><p>Как начался день</p></div>
  <div class="fields">
    <div><label class="title">Во сколько лёг</label><?= input_html('bed_time', 'time', $day, $readonly) ?></div>
    <div><label class="title">Во сколько проснулся</label><?= input_html('wake_time', 'time', $day, $readonly) ?></div>
    <div>
      <label class="title">Сон по смарт-часам</label>
      <div class="sleep-inline">
        <div class="sleep-field">
          <input type="number" name="sleep_hours" min="0" max="24" step="1" placeholder="0" <?= $readonly ? 'disabled' : '' ?> value="<?= htmlspecialchars($sleepHoursPart) ?>">
          <span class="unit">часов</span>
        </div>
        <div class="sleep-field">
          <input type="number" name="sleep_minutes" min="0" max="59" step="1" placeholder="0" <?= $readonly ? 'disabled' : '' ?> value="<?= htmlspecialchars($sleepMinutesPart) ?>">
          <span class="unit">минут</span>
        </div>
      </div>
    </div>
    <div><?= title_html('Качество сна', $tips['sleep_quality']) ?><?= scale_html('sleep_quality', $day, $readonly) ?></div>
    <div class="full sleep-calc" id="sleep-calc"></div>
    <div><?= title_html('Энергия утром', $tips['morning_energy']) ?><?= scale_html('morning_energy', $day, $readonly) ?></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Состояние</h2><p>Готовность к полноценному дню</p></div>
  <div class="fields">
    <div class="full"><?= title_html('Готовность к дню', $tips['day_readiness']) ?><?= scale_html('day_readiness', $day, $readonly) ?></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Выход из дома</h2><p>Был ли вне дома и как долго</p></div>
  <div class="fields">
    <div><label class="title">Выходил из дома?</label><?= choice_html('left_home', $yesNoOpts, $day, $readonly) ?></div>
    <div data-show="left_home:1"><label class="title">Время вне дома</label><?= choice_html('time_outside', $timeOutsideOpts, $day, $readonly) ?></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Спорт</h2><p>Была ли физическая нагрузка</p></div>
  <div class="fields">
    <div><label class="title">Был спорт?</label><?= choice_html('exercise', $yesNoOpts, $day, $readonly) ?></div>
    <div data-show="exercise:1"><label class="title">Тип</label><?= select_html('exercise_type', $exerciseTypeOpts, $day, $readonly) ?></div>
    <div data-show="exercise:1"><label class="title">Длительность, минут</label><?= input_html('exercise_duration', 'number', $day, $readonly, 'минут', '1') ?></div>
    <div data-show="exercise:1" class="full"><label class="title">Интенсивность</label><?= scale_html('exercise_intensity', $day, $readonly) ?></div>
  </div>
</div>

<div class="section" data-show="day_type:work">
  <div class="sh"><h2>Работа</h2><p>Не только часы, но и результат</p></div>
  <div class="fields">
    <div><label class="title">Время начала работы</label><?= input_html('work_start', 'time', $day, $readonly) ?></div>
    <div><label class="title">Сфокусированная работа, часов</label><?= input_html('focused_work_hours', 'number', $day, $readonly, 'часов', '0.25') ?></div>
    <div><label class="title">Общее рабочее время, часов</label><?= input_html('total_work_hours', 'number', $day, $readonly, 'часов', '0.25') ?></div>
    <div><?= title_html('Результативность работы', $tips['work_result']) ?><?= scale_html('work_result', $day, $readonly) ?></div>
    <div><?= title_html('Качество фокуса', $tips['focus_quality']) ?><?= scale_html('focus_quality', $day, $readonly) ?></div>
    <div><label class="title">YouTube / развлечения</label><?= choice_html('youtube_time', $youtubeOpts, $day, $readonly) ?></div>
  </div>
</div>

<div class="section" data-show="day_type:day_off">
  <div class="sh"><h2>Работа</h2><p>На выходном тоже можно поработать</p></div>
  <div class="fields">
    <div class="full"><label class="title">Работал сегодня?</label><?= choice_html('dayoff_worked', $yesNoOpts, $day, $readonly, false) ?></div>
    <div data-show="dayoff_worked:1"><label class="title">Сфокусированная работа, часов</label><?= input_html('dayoff_focused_hours', 'number', $day, $readonly, 'часов', '0.25', val($day, 'focused_work_hours')) ?></div>
    <div data-show="dayoff_worked:1"><label class="title">Общее рабочее время, часов</label><?= input_html('dayoff_total_hours', 'number', $day, $readonly, 'часов', '0.25', val($day, 'total_work_hours')) ?></div>
  </div>
</div>

<div class="section" data-show="day_type:work">
  <div class="sh"><h2>Спады и отвлечения</h2><p>Что происходило, когда работать становилось тяжело</p></div>
  <div class="fields">
    <div><label class="title">Был заметный спад?</label><?= choice_html('had_dip', $yesNoOpts, $day, $readonly) ?></div>
    <div data-show="had_dip:1"><label class="title">Что сделал при спаде?</label><?= select_html('dip_action', $dipActionOpts, $day, $readonly) ?></div>
    <div data-show="had_dip:1"><label class="title">Вернулся к работе?</label><?= choice_html('returned_to_work', $yesNoOpts, $day, $readonly) ?></div>
    <div class="full" data-show="had_dip:1"><label class="title">Почему отвлёкся?</label><?= select_html('distraction_reason', $distractionOpts, $day, $readonly) ?></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>🚀 Работа над будущим</h2><p>Действия, которые потенциально улучшают твою жизнь в перспективе, а не просто закрывают текущие обязательства.</p></div>
  <div class="fields">
    <div class="full"><label class="title">Занимался сегодня чем-то для будущего?</label><?= choice_html('future_work', $yesNoOpts, $day, $readonly, false) ?></div>
    <div data-show="future_work:1">
      <label class="title">Сколько времени?</label>
      <div class="sleep-inline">
        <div class="sleep-field">
          <input type="number" name="future_work_hours" min="0" max="24" step="1" placeholder="0" <?= $readonly ? 'disabled' : '' ?> value="<?= htmlspecialchars($fwHoursPart) ?>">
          <span class="unit">часов</span>
        </div>
        <div class="sleep-field">
          <input type="number" name="future_work_minutes" min="0" max="59" step="1" placeholder="0" <?= $readonly ? 'disabled' : '' ?> value="<?= htmlspecialchars($fwMinutesPart) ?>">
          <span class="unit">минут</span>
        </div>
      </div>
    </div>
    <div data-show="future_work:1"><?= title_html('Насколько это было значимо для будущего?', $tips['future_work_importance']) ?><?= scale_html('future_work_importance', $day, $readonly) ?></div>
    <div class="full" data-show="future_work:1"><label class="title">Что делал?</label><?= input_html('future_work_note', 'text', $day, $readonly, 'Например: составлял финансовый план') ?></div>
  </div>
</div>

<div class="section">
  <div class="sh"><h2>Вечер</h2><p>Финальная оценка дня</p></div>
  <div class="fields">
    <div><?= title_html('Энергия вечером', $tips['evening_energy']) ?><?= scale_html('evening_energy', $day, $readonly) ?></div>
    <div><?= title_html('Готовность работать завтра', $tips['tomorrow_motivation']) ?><?= scale_html('tomorrow_motivation', $day, $readonly) ?></div>
    <div data-show="day_type:work"><label class="title">Почему остановил работу?</label><?= select_html('work_stop_reason', $stopReasonOpts, $day, $readonly) ?></div>
    <div class="full"><label class="title">Главный результат дня</label><?= input_html('main_result', 'text', $day, $readonly, 'Одним предложением: что сегодня реально продвинул?') ?></div>
    <div class="full"><label class="title">Комментарий</label><textarea name="comment" placeholder="Что было необычного или что стоит учесть при анализе?" <?= $readonly ? 'disabled' : '' ?>><?= htmlspecialchars(val($day, 'comment')) ?></textarea></div>
  </div>
</div>

<?php if (!$readonly): ?>
<div class="actions">
  <span class="muted" id="save-status">Ещё не сохранено</span>
  <div>
    <button class="btn" type="button" id="save-btn">Сохранить</button>
    <button class="btn primary" type="button" id="complete-btn">Завершить день</button>
  </div>
</div>
<div class="alert error hidden" id="error-box"></div>
<?php endif; ?>

</form>
<footer class="footer">
  <a href="<?= BASE_URL ?>/settings">Смена пароля</a>
  <a href="<?= BASE_URL ?>/logout">Выйти</a>
</footer>
</main>
</div>

<script>
window.APP = <?= json_encode([
    'base' => BASE_URL,
    'date' => $date,
    'isToday' => $isToday,
    'readonly' => $readonly,
    'status' => $day['status'] ?? null,
    'fields' => EDITABLE_COLUMNS,
    'workFields' => WORK_FIELDS,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/day.js') ?>"></script>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const SCALE_FIELDS = [
    'sleep_quality', 'morning_energy', 'day_readiness', 'exercise_intensity',
    'work_result', 'focus_quality', 'evening_energy', 'tomorrow_motivation',
    'future_work_importance',
];
const BOOL_FIELDS = ['left_home', 'exercise', 'had_dip', 'returned_to_work', 'future_work', 'dayoff_worked'];
const FLOAT_FIELDS = ['sleep_hours', 'focused_work_hours', 'total_work_hours', 'future_work_hours'];
const INT_FIELDS = ['exercise_duration'];
const TIME_FIELDS = ['bed_time', 'wake_time', 'work_start'];

// Текстовые поля (свободный ввод).
const TEXT_FIELDS = ['exercise_type', 'dip_action', 'distraction_reason', 'work_stop_reason', 'main_result', 'comment', 'future_work_note'];

const TIME_OUTSIDE_VALUES = ['less_30', '30_60', '1_3', 'more_3'];
const YOUTUBE_VALUES = ['none', 'lt30', '30_60', '1_2', 'gt2'];

const EDITABLE_COLUMNS = [
    'day_type', 'bed_time', 'wake_time', 'sleep_hours', 'sleep_quality', 'morning_energy', 'day_readiness',
    'left_home', 'time_outside', 'exercise', 'exercise_type', 'exercise_duration', 'exercise_intensity',
    'work_start', 'focused_work_hours', 'total_work_hours', 'work_result', 'focus_quality', 'youtube_time',
    'had_dip', 'dip_action', 'returned_to_work', 'distraction_reason', 'work_stop_reason',
    'dayoff_worked', 'future_work', 'future_work_hours', 'future_work_note', 'future_work_importance',
    'evening_energy', 'tomorrow_motivation', 'main_result', 'comment',
];

// Поля, скрываемые для планового выходного.
const WORK_FIELDS = [
    'work_start', 'focused_work_hours', 'total_work_hours', 'work_result', 'focus_quality', 'youtube_time',
    'had_dip', 'dip_action', 'returned_to_work', 'distraction_reason', 'work_stop_reason',
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        init_schema($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS days (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL UNIQUE,
            day_type TEXT,
            status TEXT NOT NULL DEFAULT \'draft\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            completed_at TEXT,
            bed_time TEXT,
            wake_time TEXT,
            sleep_hours REAL,
            sleep_quality INTEGER,
            morning_energy INTEGER,
            day_readiness INTEGER,
            left_home INTEGER,
            time_outside TEXT,
            exercise INTEGER,
            exercise_type TEXT,
            exercise_duration INTEGER,
            exercise_intensity INTEGER,
            work_start TEXT,
            focused_work_hours REAL,
            total_work_hours REAL,
            work_result INTEGER,
            focus_quality INTEGER,
            youtube_time TEXT,
            had_dip INTEGER,
            dip_action TEXT,
            returned_to_work INTEGER,
            distraction_reason TEXT,
            work_stop_reason TEXT,
            dayoff_worked INTEGER,
            future_work INTEGER,
            future_work_hours REAL,
            future_work_note TEXT,
            future_work_importance INTEGER,
            evening_energy INTEGER,
            tomorrow_motivation INTEGER,
            main_result TEXT,
            comment TEXT
        )'
    );

    migrate_schema($pdo);
}

// Безопасная аддитивная миграция: только ADD COLUMN, с бэкапом БД до изменений.
function migrate_schema(PDO $pdo): void
{
    $want = [
        'future_work' => 'INTEGER',
        'future_work_hours' => 'REAL',
        'future_work_note' => 'TEXT',
        'future_work_importance' => 'INTEGER',
        'dayoff_worked' => 'INTEGER',
    ];

    $have = [];
    foreach ($pdo->query('PRAGMA table_info(days)') as $col) {
        $have[$col['name']] = true;
    }

    $missing = [];
    foreach ($want as $col => $type) {
        if (!isset($have[$col])) {
            $missing[$col] = $type;
        }
    }
    if (!$missing) {
        return;
    }

    // Бэкап ПЕРЕД миграцией. Без бэкапа не мигрируем (fail-safe).
    $backup = backup_db();
    if ($backup === null) {
        write_log('migrate', [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'migrate',
            'status' => 'aborted',
            'reason' => 'backup failed',
            'missing' => array_keys($missing),
        ]);
        return;
    }

    $added = [];
    foreach ($missing as $col => $type) {
        $pdo->exec('ALTER TABLE days ADD COLUMN ' . $col . ' ' . $type);
        $added[] = $col;
    }

    write_log('migrate', [
        'time' => date('Y-m-d H:i:s'),
        'action' => 'migrate',
        'status' => 'ok',
        'added' => $added,
        'backup' => $backup,
    ]);
}

function backup_db(): ?string
{
    if (!is_file(DB_PATH)) {
        return null;
    }
    $dir = DATA_DIR . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return null;
    }
    $dest = $dir . DIRECTORY_SEPARATOR . 'tracker-' . date('Y-m-d-His') . '.sqlite';
    if (!@copy(DB_PATH, $dest)) {
        return null;
    }
    prune_backups($dir, 10);
    return $dest;
}

function prune_backups(string $dir, int $keep): void
{
    $files = glob($dir . DIRECTORY_SEPARATOR . 'tracker-*.sqlite') ?: [];
    if (count($files) <= $keep) {
        return;
    }
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
    foreach (array_slice($files, 0, count($files) - $keep) as $f) {
        @unlink($f);
    }
}

function get_day(string $date): ?array
{
    $stmt = db()->prepare('SELECT * FROM days WHERE date = ?');
    $stmt->execute([$date]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_days(): array
{
    return db()->query('SELECT * FROM days ORDER BY date DESC')->fetchAll();
}

function upsert_day(string $date, array $clean): void
{
    $pdo = db();
    $now = date('Y-m-d H:i:s');

    if (get_day($date) !== null) {
        $sets = [];
        $vals = [];
        foreach (EDITABLE_COLUMNS as $c) {
            $sets[] = "$c = ?";
            $vals[] = $clean[$c] ?? null;
        }
        $vals[] = $now;
        $vals[] = $date;
        $sql = 'UPDATE days SET ' . implode(', ', $sets) . ', updated_at = ? WHERE date = ?';
        $pdo->prepare($sql)->execute($vals);
    } else {
        $cols = ['date', 'status', 'created_at', 'updated_at'];
        $marks = ['?', '?', '?', '?'];
        $vals = [$date, 'draft', $now, $now];
        foreach (EDITABLE_COLUMNS as $c) {
            $cols[] = $c;
            $marks[] = '?';
            $vals[] = $clean[$c] ?? null;
        }
        $sql = 'INSERT INTO days (' . implode(',', $cols) . ') VALUES (' . implode(',', $marks) . ')';
        $pdo->prepare($sql)->execute($vals);
    }
}

function mark_completed(string $date): void
{
    db()->prepare('UPDATE days SET status = ?, completed_at = ? WHERE date = ?')
        ->execute(['completed', date('Y-m-d H:i:s'), $date]);
}

function scale_or_null($v): ?int
{
    $i = filter_var($v, FILTER_VALIDATE_INT);
    return ($i !== false && $i >= 1 && $i <= 5) ? $i : null;
}

function bool_or_null($v): ?int
{
    if ($v === 1 || $v === '1') return 1;
    if ($v === 0 || $v === '0') return 0;
    return null;
}

function float_or_null($v): ?float
{
    if ($v === null || $v === '') return null;
    $f = filter_var($v, FILTER_VALIDATE_FLOAT);
    return ($f === false) ? null : $f;
}

function int_or_null($v): ?int
{
    if ($v === null || $v === '') return null;
    $i = filter_var($v, FILTER_VALIDATE_INT);
    return ($i === false) ? null : $i;
}

function time_or_null($v): ?string
{
    if (!is_string($v)) return null;
    $v = trim($v);
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
}

function normalize_day(array $raw): array
{
    $out = [];

    foreach (SCALE_FIELDS as $f) $out[$f] = scale_or_null($raw[$f] ?? null);
    foreach (BOOL_FIELDS as $f) $out[$f] = bool_or_null($raw[$f] ?? null);
    foreach (FLOAT_FIELDS as $f) $out[$f] = float_or_null($raw[$f] ?? null);
    foreach (INT_FIELDS as $f) $out[$f] = int_or_null($raw[$f] ?? null);
    foreach (TIME_FIELDS as $f) $out[$f] = time_or_null($raw[$f] ?? null);

    $out['day_type'] = in_array($raw['day_type'] ?? null, ['work', 'day_off'], true) ? $raw['day_type'] : null;
    $out['time_outside'] = in_array($raw['time_outside'] ?? null, TIME_OUTSIDE_VALUES, true) ? $raw['time_outside'] : null;
    $out['youtube_time'] = in_array($raw['youtube_time'] ?? null, YOUTUBE_VALUES, true) ? $raw['youtube_time'] : null;

    foreach (TEXT_FIELDS as $f) {
        $v = $raw[$f] ?? null;
        $out[$f] = is_string($v) ? trim($v) : null;
        if ($out[$f] === '') $out[$f] = null;
    }

    return apply_conditional_clear($out);
}

// Значения, неприменимые из-за условной логики, хранятся как NULL.
function apply_conditional_clear(array $d): array
{
    // Флаг «работал на выходном» применим только к плановому выходному.
    if (($d['day_type'] ?? null) !== 'day_off') {
        $d['dayoff_worked'] = null;
    }

    // Рабочие поля.
    if (($d['day_type'] ?? null) === 'work') {
        // рабочий день — все рабочие поля применимы
    } elseif (($d['dayoff_worked'] ?? null) === 1) {
        // выходной, но поработал: оставляем только часы, остальное — NULL
        foreach (WORK_FIELDS as $f) {
            if (!in_array($f, ['focused_work_hours', 'total_work_hours'], true)) {
                $d[$f] = null;
            }
        }
    } else {
        foreach (WORK_FIELDS as $f) {
            $d[$f] = null;
        }
    }

    // Работа над будущим: подполя только при «Да».
    if (($d['future_work'] ?? null) !== 1) {
        $d['future_work_hours'] = null;
        $d['future_work_note'] = null;
        $d['future_work_importance'] = null;
    }

    if (($d['left_home'] ?? null) !== 1) $d['time_outside'] = null;
    if (($d['exercise'] ?? null) !== 1) {
        $d['exercise_type'] = null;
        $d['exercise_duration'] = null;
        $d['exercise_intensity'] = null;
    }
    if (($d['had_dip'] ?? null) !== 1) {
        $d['dip_action'] = null;
        $d['returned_to_work'] = null;
        $d['distraction_reason'] = null;
    }
    return $d;
}

function required_errors(array $day): array
{
    $err = [];
    $labels = field_labels();
    $isWork = ($day['day_type'] ?? null) === 'work';

    if (!in_array($day['day_type'] ?? null, ['work', 'day_off'], true)) {
        $err[] = 'Укажите ' . $labels['day_type'] . '.';
    }

    foreach (['bed_time', 'wake_time', 'sleep_hours', 'sleep_quality', 'morning_energy', 'day_readiness', 'evening_energy', 'tomorrow_motivation'] as $f) {
        if (($day[$f] ?? null) === null || $day[$f] === '') {
            $err[] = 'Заполните: ' . $labels[$f];
        }
    }

    if (($day['left_home'] ?? null) === null) $err[] = 'Заполните: ' . $labels['left_home'];
    if (($day['left_home'] ?? null) === 1 && empty($day['time_outside'])) $err[] = 'Заполните: ' . $labels['time_outside'];

    if (($day['exercise'] ?? null) === null) $err[] = 'Заполните: ' . $labels['exercise'];
    if (($day['exercise'] ?? null) === 1) {
        foreach (['exercise_type', 'exercise_duration', 'exercise_intensity'] as $f) {
            if (($day[$f] ?? null) === null || $day[$f] === '') $err[] = 'Заполните: ' . $labels[$f];
        }
    }

    if ($isWork) {
        foreach (['focused_work_hours', 'work_result'] as $f) {
            if (($day[$f] ?? null) === null || $day[$f] === '') $err[] = 'Заполните: ' . $labels[$f];
        }
    }

    return $err;
}

function field_labels(): array
{
    return [
        'day_type' => 'Тип дня',
        'bed_time' => 'Время, когда лёг',
        'wake_time' => 'Время пробуждения',
        'sleep_hours' => 'Сон по смарт-часам',
        'sleep_quality' => 'Качество сна',
        'morning_energy' => 'Энергия утром',
        'day_readiness' => 'Готовность к дню',
        'left_home' => 'Выходил из дома',
        'time_outside' => 'Время вне дома',
        'exercise' => 'Был спорт',
        'exercise_type' => 'Тип спорта',
        'exercise_duration' => 'Длительность спорта',
        'exercise_intensity' => 'Интенсивность спорта',
        'work_start' => 'Начало работы',
        'focused_work_hours' => 'Сфокусированная работа',
        'total_work_hours' => 'Общее рабочее время',
        'work_result' => 'Результативность работы',
        'focus_quality' => 'Качество фокуса',
        'youtube_time' => 'YouTube / развлечения',
        'had_dip' => 'Спад энергии/мотивации',
        'dip_action' => 'Действие при спаде',
        'returned_to_work' => 'Вернулся к работе',
        'distraction_reason' => 'Причина отвлечения',
        'work_stop_reason' => 'Почему остановил работу',
        'dayoff_worked' => 'Работал сегодня',
        'future_work' => 'Работа над будущим',
        'future_work_hours' => 'Время на будущее',
        'future_work_note' => 'Что делал для будущего',
        'future_work_importance' => 'Значимость для будущего',
        'evening_energy' => 'Энергия вечером',
        'tomorrow_motivation' => 'Готовность работать завтра',
        'main_result' => 'Главный результат дня',
        'comment' => 'Комментарий',
    ];
}

function day_type_label($v): string
{
    return $v === 'day_off' ? 'Выходной по плану' : ($v === 'work' ? 'Рабочий день' : '—');
}

function status_label($v): string
{
    return $v === 'completed' ? 'Завершён' : ($v === 'draft' ? 'Черновик' : '—');
}

function time_outside_label($v): string
{
    $map = [
        'less_30' => 'Менее 30 минут',
        '30_60' => '30 минут–1 час',
        '1_3' => '1–3 часа',
        'more_3' => 'Более 3 часов',
    ];
    return $map[$v] ?? '—';
}

function youtube_time_label($v): string
{
    $map = [
        'none' => 'Не было',
        'lt30' => 'Менее 30 минут',
        '30_60' => '30–60 минут',
        '1_2' => '1–2 часа',
        'gt2' => 'Более 2 часов',
    ];
    return $map[$v] ?? '—';
}

function fmt_hours($h): string
{
    if ($h === null || $h === '') return '—';
    $h = (float) $h;
    $whole = (int) floor($h);
    $mins = (int) round(($h - $whole) * 60);
    if ($mins === 60) { $whole++; $mins = 0; }
    if ($whole === 0 && $mins === 0) return '—';
    $s = '';
    if ($whole > 0) $s .= $whole . ' ч';
    if ($mins > 0) $s .= ($s !== '' ? ' ' : '') . $mins . ' мин';
    return $s !== '' ? $s : '—';
}

function fmt_scale($v): string
{
    return ($v === null || $v === '') ? '—' : $v . '/5';
}

function fmt_date(string $date): string
{
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $t = strtotime($date);
    if ($t === false) return $date;
    return date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function fmt_date_short(string $date): string
{
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $t = strtotime($date);
    if ($t === false) return $date;
    return date('j', $t) . ' ' . $months[(int) date('n', $t) - 1];
}

function fmt_date_full(string $date): string
{
    $week = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
    $t = strtotime($date);
    if ($t === false) return $date;
    return $week[(int) date('w', $t)] . ', ' . fmt_date($date);
}

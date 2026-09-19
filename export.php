<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

require_login();

$days = list_days();

$labels = field_labels();
$columns = [
    'date', 'day_type', 'status',
    'bed_time', 'wake_time', 'sleep_hours', 'sleep_quality', 'morning_energy', 'day_readiness',
    'left_home', 'time_outside', 'exercise', 'exercise_type', 'exercise_duration', 'exercise_intensity',
    'work_start', 'focused_work_hours', 'total_work_hours', 'work_result', 'focus_quality', 'youtube_time',
    'had_dip', 'dip_action', 'returned_to_work', 'distraction_reason', 'work_stop_reason',
    'dayoff_worked', 'future_work', 'future_work_hours', 'future_work_note', 'future_work_importance',
    'evening_energy', 'tomorrow_motivation', 'main_result', 'comment',
    'created_at', 'updated_at', 'completed_at',
];

$headerNames = [
    'date' => 'Дата',
    'day_type' => 'Тип дня',
    'status' => 'Статус',
    'created_at' => 'Создано',
    'updated_at' => 'Обновлено',
    'completed_at' => 'Завершено',
];

function bool_label($v): string
{
    if ($v === null || $v === '') return '';
    return (string) $v === '1' ? 'Да' : 'Нет';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="work_rhythm_export_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

$head = [];
foreach ($columns as $c) {
    $head[] = $headerNames[$c] ?? $labels[$c] ?? $c;
}
fputcsv($out, $head, ';', '"', '');

foreach ($days as $d) {
    $row = [];
    foreach ($columns as $c) {
        $v = $d[$c] ?? null;
        switch ($c) {
            case 'day_type':
                $v = day_type_label($v);
                break;
            case 'status':
                $v = status_label($v);
                break;
            case 'left_home':
            case 'exercise':
            case 'had_dip':
            case 'returned_to_work':
            case 'dayoff_worked':
            case 'future_work':
                $v = bool_label($v);
                break;
            case 'time_outside':
                $v = $v ? time_outside_label($v) : '';
                break;
            case 'youtube_time':
                $v = $v ? youtube_time_label($v) : '';
                break;
        }
        $row[] = $v === null ? '' : (string) $v;
    }
    fputcsv($out, $row, ';', '"', '');
}

fclose($out);
exit;

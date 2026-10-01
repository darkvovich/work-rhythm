<?php
declare(strict_types=1);
// Юнит-тесты чистых функций database.php. БД не открывается, файлы не пишутся.
// Запуск из корня проекта: php tests/unit.php
// Успех: код возврата 0 и строка "ALL PASS". Падение: строки "FAIL", итог "N FAILED", код 1.

require __DIR__ . '/../database.php';

$fail = 0;

// Проверяет равенство и печатает результат.
function check(string $name, $actual, $expected): void
{
    global $fail;
    $ok = $actual === $expected;
    if (!$ok) $fail++;
    printf("%s %s | got=%s want=%s\n", $ok ? 'PASS' : 'FAIL', $name,
        json_encode($actual, JSON_UNESCAPED_UNICODE), json_encode($expected, JSON_UNESCAPED_UNICODE));
}

// --- Мультивыбор спорта (exercise_type_or_null) ---

check('string with space', exercise_type_or_null('Бег, Йога'), 'Бег,Йога');
check('array join', exercise_type_or_null(['Бег', 'Йога']), 'Бег,Йога');
check('dedupe', exercise_type_or_null('Бег,Бег,Йога'), 'Бег,Йога');
check('unknown dropped', exercise_type_or_null('Бег,Футбол,Йога'), 'Бег,Йога');
check('unknown only', exercise_type_or_null('Футбол'), null);
check('empty string', exercise_type_or_null(''), null);
check('null', exercise_type_or_null(null), null);
check('not array/string', exercise_type_or_null(42), null);
check('legacy single value', exercise_type_or_null('Бег'), 'Бег');
check('trail comma', exercise_type_or_null('Бег,'), 'Бег');

// --- Условная логика: тип спорта живёт только при exercise=1 ---

$d = normalize_day(['day_type' => 'work', 'exercise' => 0, 'exercise_type' => 'Бег']);
check('cleared when no exercise', $d['exercise_type'], null);
$d = normalize_day(['day_type' => 'work', 'exercise' => 1, 'exercise_type' => 'Бег,Йога']);
check('kept when exercise', $d['exercise_type'], 'Бег,Йога');
check('type present -> no error', in_array('Заполните: Тип спорта', required_errors($d), true), false);
$d2 = normalize_day(['day_type' => 'work', 'exercise' => 1, 'exercise_type' => '']);
check('missing type -> error', in_array('Заполните: Тип спорта', required_errors($d2), true), true);

// Базовый валидный рабочий день: без вечерних полей, без качества фокуса и спада.
$mkWork = static function (array $extra = []): array {
    return normalize_day(array_merge([
        'day_type' => 'work', 'bed_time' => '23:00', 'wake_time' => '07:00', 'sleep_hours' => 7.5,
        'sleep_quality' => 4, 'morning_energy' => 4, 'day_readiness' => 4, 'left_home' => 0,
        'exercise' => 0, 'focused_work_hours' => 4, 'work_result' => 3,
    ], $extra));
};

// --- Послабление: вечерние поля не обязательны ---

check('evening fields optional', required_errors($mkWork()), []);
check('evening values accepted', required_errors($mkWork(['evening_energy' => 4, 'tomorrow_motivation' => 4])), []);

// --- Статус-кво: качество фокуса и спад — опциональны (как в ТЗ) ---

check('focus_quality optional', required_errors($mkWork(['focus_quality' => 5])), []);
check('had_dip=1 subfields optional', required_errors($mkWork(['had_dip' => 1])), []);
check('had_dip=0 ok', required_errors($mkWork(['had_dip' => 0])), []);

// --- Общее рабочее время опционально ---

check('total_work_hours optional', required_errors($mkWork(['total_work_hours' => ''])), []);

// --- Условные подполя остаются обязательными ---

$e = required_errors($mkWork(['left_home' => 1]));
check('time_outside required when left_home', in_array('Заполните: Время вне дома', $e, true), true);
$e = required_errors($mkWork(['exercise' => 1]));
check('exercise_type required when exercise', in_array('Заполните: Тип спорта', $e, true), true);
check('exercise_duration required when exercise', in_array('Заполните: Длительность спорта', $e, true), true);

echo $fail === 0 ? "ALL PASS\n" : "$fail FAILED\n";
exit($fail === 0 ? 0 : 1);

# AGENTS.md — правила для AI-агентов в этом репо

## Что это

Персональный трекер «Рабочий ритм»: PHP 8 + SQLite + vanilla JS, без фреймворков/Node/Composer.
Приоритет UX — минимум трения: заполнение дня за 2–3 мин, крупные touch-элементы, работает с телефона.

## Ключевые файлы

* `config.php` — `APP_ROOT/DATA_DIR/DB_PATH/LOG_DIR`, `APP_USER`, `AUTH_COOKIE/LIFETIME`, `BASE_URL`, `PASSWORD_HASH`, `auth_secret()/rotate_auth_secret()`, `write_log()`, `asset()`, `ico()`.
* `auth.php` — `make_auth_token/verify_auth_token/is_logged_in/require_login/login_user/do_logout`. Токен: `b64url(payload).b64url(HMAC-SHA256, auth_secret)`.
* `database.php` — единственный доступ к БД через `db()`. Константы полей `SCALE/BOOL/FLOAT/INT/TIME/TEXT_FIELDS`, `EXERCISE_TYPES`, `EDITABLE_COLUMNS`, `WORK_FIELDS`. Функции `normalize_day → apply_conditional_clear`, `exercise_type_or_null` (мультивыбор через `,`), `required_errors`, `upsert_day`, `mark_completed`, `mark_reopened`, `backup_db/prune_backups`, `migrate_schema`.
* Страницы: `index.php` (форма дня), `history.php`, `analytics.php` (avg/sum/pairs/pearson), `export.php` (CSV `;` + BOM), `login/logout/set_password (/settings)`.
* `migrate.php` (`/migrate`) — ручная миграция схемы: показывает недостающие колонки, кнопку запуска, список бэкапов. `migration_banner()` из `database.php` выводит ссылку на неё на странице «Сегодня», если миграция нужна.
* API: `api/save_day.php`, `api/complete_day.php`, `api/reopen_day.php`, `api/get_day.php`, `api/get_history.php`.
* Фронт: `assets/js/day.js` (форма, автосейв), `assets/js/analytics.js` (графики), `assets/js/app.js` (общее: `postJSON`, подсказки).
* Тесты: `tests/unit.php` (чистые функции), `tests/e2e.ps1` (HTTP на копии проекта), `tests/router.php` (ЧПУ для `php -S`).
* Роутинг/защита: корневой `.htaccess` (ЧПУ + `Deny` для `data/|logs/|*.sqlite|config|database|auth.php`), `data/.htaccess` + `logs/.htaccess` (`Require all denied`).

## Обязательные конвенции

1. В каждом PHP-файле: `declare(strict_types=1);`. Все страницы дневника и всё API — начинать с `require_login();`.
2. Вывод в HTML — только через `htmlspecialchars()`. Ссылки/ассеты — через `BASE_URL` и `asset()` (filemtime-антикеш).
3. БД — только prepared statements через `db()`. Неприменимые по условной логике значения — `NULL`, не `0`/`''` (см. `apply_conditional_clear()` в `database.php`).
4. Условная логика дублируется: JS скрывает поля + сервер чистит (`normalize_day`) и валидирует только активные (`required_errors`). Доверять только серверу. Список для прогресс-бара — `requiredFields()` в `assets/js/day.js` — обязан зеркалить `required_errors()`: правишь одно — правишь второе, иначе счётчик «N из M» врёт.
5. Завершённый день (`status=completed`) — read-only везде: UI `disabled` + API `409`. Исключение — кнопка «Редактировать» на странице дня: `POST api/reopen-day` делает `status=draft` (`completed_at=NULL`), после чего день можно править и снова завершить. Будущие даты — `403`. Завершение без обязательных полей — `422`.
6. Миграции схемы — только аддитивный `ADD COLUMN` в `migrate_schema()` и только после успешного `backup_db()` (fail-safe, иначе abort + `write_log('migrate')`). Бэкапов держать ≤10 (`prune_backups`). **Авто-миграция отключена:** `db()`/`init_schema()` только создают таблицу (`CREATE TABLE IF NOT EXISTS`); схема обновляется вручную со страницы `/migrate` (`missing_columns()` + `migrate_schema()`). Если миграция нужна, `migration_banner()` показывает ссылку на «Сегодня».
7. Логи — только через `write_log($name, $entry)` в `logs/Y-m-d-<name>.log` как JSON-line. Не логировать пароли/токены.
8. Не добавлять зависимости: без React/Vue/Node/Composer-пакетов, CDN — только уже используемый Chart.js. Стиль — править `assets/css/style.css`, не инлайн-простыни.
9. Часовой пояс — `Europe/Moscow` из `config.php`, не переопределять локально. Даты — `Y-m-d`, формат вывода — через `fmt_*()` из `database.php`.
10. Переводы строк — только LF (`\n`), никаких CRLF. Правило зафиксировано в `.gitattributes` (`* text=auto eol=lf`, картинки — `binary`). Редактор настраивать на LF; перед коммитом проверять `git diff --check` и отсутствие warning `LF will be replaced by CRLF`.

## Секреты — никогда в git

Запрещено коммитить (уже в `.gitignore`): `data/tracker.sqlite`, `data/*.db*`, `data/password_hash.*`, `data/auth_secret.txt`, `data/backups/`, `logs/*.log`, `*.csv`.
Дефолтный `PASSWORD_HASH` в `config.php` — публичный хеш слова `password` для первого запуска; реальный хеш создаётся через `/settings` или `php set_password.php <пароль>` (он же ротирует секрет и инвалидирует cookie).

## Как проверять

* Синтаксис: `php -l <file.php>` для каждого изменённого файла + `node --check assets/js/day.js` для JS.
* Автотесты: `php tests/unit.php` и `pwsh -File tests/e2e.ps1` — см. секцию [Тесты](#тесты), прогонять после каждой правки кода.
* Локальный запуск: `php -S localhost:8000 -t .` → `/login` (admin/password) → сохранить черновик → перезагрузить (черновик на месте) → «Завершить день» → повторный POST в `api/save-day` должен дать `409` → `/history`, `/analytics`, `/export` открываются.
* Нет PHPUnit/линтеров JS — ручной чек-лист выше обязателен. Аналитика: корреляции показывать как связи, без утверждений причинности.

## Тесты

Два автотеста в `tests/`, без зависимостей (PHP 8 с `pdo_sqlite`, PowerShell 5.1+):

* `php tests/unit.php` — чистые функции `database.php`: `exercise_type_or_null`, `normalize_day`/`apply_conditional_clear`, `required_errors` (границы обязательности полей, условные подполя). БД не открывается, файлы не пишутся. Успех: `ALL PASS` и код 0. Падение: строка `FAIL <имя> | got=... want=...` и итог `N FAILED`, код 1.
* `pwsh -File tests/e2e.ps1` — HTTP-прогон через `php -S` + `tests/router.php` (ЧПУ вместо `.htaccess`): логин admin/password, черновик, чипсы спорта, экспорт `Бег,Йога`, завершение дня и защита `409`, reopen (`200/409/403/400`), послабление обязательных полей (complete без вечерних → `200`). Требует `php` в `PATH` и свободный порт 8120–8199. Успех: строка `total=N failed=0` и код 0.
* **Гарантия данных:** e2e копирует проект во временную папку (`robocopy /XD .git data logs`) и поднимает сервер там — реальные `data/` и `logs/` проекта не затрагиваются никогда. Копия и процесс сервера удаляются в `finally` даже при падении.
* Порядок проверок после правки: `php -l` изменённых PHP → `node --check assets/js/day.js` → `git diff --check` → `php tests/unit.php` → `pwsh -File tests/e2e.ps1`.
* Если e2e упал: смотреть строку `FAIL`, затем `server-err: ...` (последние 30 строк лога сервера печатаются перед удалением копии) и строки `stage:` (на каком шаге прервалось).

## Расхождения с `Task.md` (историческое ТЗ)

`Task.md` лежит **вне репозитория** (`D:\DEV\!MY\Productivity Tracker\Task.md`), в git не входит и не редактируется. При конфликте приоритет — у этой секции.

1. **Обязательные поля.** ТЗ: 🔴 «Вечер» (энергия вечером, желание работать завтра) — основные. Здесь они **не обязательны** (послабление по решению владельца): `required_errors()` и `requiredFields()` их не проверяют. 🟡 поля (общее рабочее время, качество фокуса, спад + подполя, причина отвлечения) — опциональны, как в ТЗ. Условные подполя (время вне дома; тип/длительность/интенсивность спорта) обязательны при своём «Да».
2. **Reopen.** ТЗ: «после завершения день становится полностью read-only» + «редактирование только текущего дня». Здесь кнопка «Редактировать» (`POST api/reopen-day`) открывает **любой** прошедший день: `status=completed → draft`, `completed_at=NULL`, после чего день можно править и снова завершить.
3. **Спорт.** ТЗ: одиночный «тип». Здесь мультивыбор строкой через запятую без пробела (`Бег,Йога`) в той же колонке `exercise_type`, допустимые значения — константа `EXERCISE_TYPES`.

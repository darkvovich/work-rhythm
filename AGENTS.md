# AGENTS.md — правила для AI-агентов в этом репо

## Что это

Персональный трекер «Рабочий ритм»: PHP 8 + SQLite + vanilla JS, без фреймворков/Node/Composer.
Приоритет UX — минимум трения: заполнение дня за 2–3 мин, крупные touch-элементы, работает с телефона.

## Ключевые файлы

* `config.php` — `APP_ROOT/DATA_DIR/DB_PATH/LOG_DIR`, `APP_USER`, `AUTH_COOKIE/LIFETIME`, `BASE_URL`, `PASSWORD_HASH`, `auth_secret()/rotate_auth_secret()`, `write_log()`, `asset()`, `ico()`.
* `auth.php` — `make_auth_token/verify_auth_token/is_logged_in/require_login/login_user/do_logout`. Токен: `b64url(payload).b64url(HMAC-SHA256, auth_secret)`.
* `database.php` — единственный доступ к БД через `db()`. Константы полей `SCALE/BOOL/FLOAT/INT/TIME/TEXT_FIELDS`, `EDITABLE_COLUMNS`, `WORK_FIELDS`. Функции `normalize_day → apply_conditional_clear`, `required_errors`, `upsert_day`, `mark_completed`, `backup_db/prune_backups`, `migrate_schema`.
* Страницы: `index.php` (форма дня), `history.php`, `analytics.php` (avg/sum/pairs/pearson), `export.php` (CSV `;` + BOM), `login/logout/set_password (/settings)`.
* `migrate.php` (`/migrate`) — ручная миграция схемы: показывает недостающие колонки, кнопку запуска, список бэкапов. `migration_banner()` из `database.php` выводит ссылку на неё на странице «Сегодня», если миграция нужна.
* API: `api/save_day.php`, `api/complete_day.php`, `api/get_day.php`, `api/get_history.php`.
* Фронт: `assets/js/day.js` (форма, автосейв), `assets/js/analytics.js` (графики), `assets/js/app.js` (общее: `postJSON`, подсказки).
* Роутинг/защита: корневой `.htaccess` (ЧПУ + `Deny` для `data/|logs/|*.sqlite|config|database|auth.php`), `data/.htaccess` + `logs/.htaccess` (`Require all denied`).

## Обязательные конвенции

1. В каждом PHP-файле: `declare(strict_types=1);`. Все страницы дневника и всё API — начинать с `require_login();`.
2. Вывод в HTML — только через `htmlspecialchars()`. Ссылки/ассеты — через `BASE_URL` и `asset()` (filemtime-антикеш).
3. БД — только prepared statements через `db()`. Неприменимые по условной логике значения — `NULL`, не `0`/`''` (см. `apply_conditional_clear()` в `database.php`).
4. Условная логика дублируется: JS скрывает поля + сервер чистит (`normalize_day`) и валидирует только активные (`required_errors`). Доверять только серверу.
5. Завершённый день (`status=completed`) — read-only везде: UI `disabled` + API `409`. Будущие даты — `403`. Завершение без обязательных полей — `422`.
6. Миграции схемы — только аддитивный `ADD COLUMN` в `migrate_schema()` и только после успешного `backup_db()` (fail-safe, иначе abort + `write_log('migrate')`). Бэкапов держать ≤10 (`prune_backups`). **Авто-миграция отключена:** `db()`/`init_schema()` только создают таблицу (`CREATE TABLE IF NOT EXISTS`); схема обновляется вручную со страницы `/migrate` (`missing_columns()` + `migrate_schema()`). Если миграция нужна, `migration_banner()` показывает ссылку на «Сегодня».
7. Логи — только через `write_log($name, $entry)` в `logs/Y-m-d-<name>.log` как JSON-line. Не логировать пароли/токены.
8. Не добавлять зависимости: без React/Vue/Node/Composer-пакетов, CDN — только уже используемый Chart.js. Стиль — править `assets/css/style.css`, не инлайн-простыни.
9. Часовой пояс — `Europe/Moscow` из `config.php`, не переопределять локально. Даты — `Y-m-d`, формат вывода — через `fmt_*()` из `database.php`.
10. Переводы строк — только LF (`\n`), никаких CRLF. Правило зафиксировано в `.gitattributes` (`* text=auto eol=lf`, картинки — `binary`). Редактор настраивать на LF; перед коммитом проверять `git diff --check` и отсутствие warning `LF will be replaced by CRLF`.

## Секреты — никогда в git

Запрещено коммитить (уже в `.gitignore`): `data/tracker.sqlite`, `data/*.db*`, `data/password_hash.*`, `data/auth_secret.txt`, `data/backups/`, `logs/*.log`, `*.csv`.
Дефолтный `PASSWORD_HASH` в `config.php` — публичный хеш слова `password` для первого запуска; реальный хеш создаётся через `/settings` или `php set_password.php <пароль>` (он же ротирует секрет и инвалидирует cookie).

## Как проверять

* Синтаксис: `php -l <file.php>` для каждого изменённого файла.
* Локальный запуск: `php -S localhost:8000 -t .` → `/login` (admin/password) → сохранить черновик → перезагрузить (черновик на месте) → «Завершить день» → повторный POST в `api/save-day` должен дать `409` → `/history`, `/analytics`, `/export` открываются.
* Нет PHPUnit/линтеров JS — ручной чек-лист выше обязателен. Аналитика: корреляции показывать как связи, без утверждений причинности.

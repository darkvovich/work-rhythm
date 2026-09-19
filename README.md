# Рабочий ритм

Персональное веб-приложение для 3-недельного эксперимента: за 2–3 минуты в день фиксировать сон, энергию, выход из дома, спорт, работу и вечернее состояние — а потом искать закономерности.

Без фреймворков. Только PHP + SQLite + vanilla JS.

## Возможности

* **Сегодня (`/`)** — форма текущего дня: черновик, автосохранение (debounce), условные поля, кнопка «Завершить день». Завершённый день — read-only.
* **История (`/history`)** — таблица всех дней + просмотр дня по `/day/YYYY-MM-DD`.
* **Аналитика (`/analytics`)** — средние, суммы и корреляции Пирсона (сон → энергия, энергия → результат и т.д.). Только корреляции, без заявлений о причинности. Графики — Chart.js с CDN.
* **Экспорт (`/export`)** — весь дневник в CSV (`;`, UTF-8 с BOM).
* **Авторизация** — один пользователь, пароль только как `password_hash`, подписанная cookie `wr_auth` (~10 лет). Смена пароля — `/settings`.
* **PWA-минимум** — `manifest.json` + иконки в `assets/`.

## Стек

* PHP 8.x (strict_types), PDO SQLite
* HTML/CSS, vanilla JS, Chart.js 4.4.1 (CDN)
* Apache + `mod_rewrite` (ЧПУ и защита через `.htaccess`). Без React/Vue/Node/Composer.

## Структура

```text
work-rhythm/
├── index.php          # Сегодня / просмотр дня (?date=, /day/YYYY-MM-DD)
├── history.php        # История
├── analytics.php      # Аналитика + корреляции
├── export.php         # CSV-экспорт
├── login.php / logout.php / set_password.php  # Вход, выход, /settings
├── config.php         # Пути, auth-секрет, логгер, asset(), ico()
├── database.php       # Схема days, normalize/validate, бэкап + миграция
├── auth.php           # Подписанный токен, require_login()
├── api/
│   ├── save_day.php      # POST {date, ...fields} → черновик
│   ├── complete_day.php  # POST → проверка required_errors + completed
│   ├── get_day.php       # GET ?date=
│   └── get_history.php   # GET → все дни
├── assets/css/style.css
├── assets/js/app.js|day.js|analytics.js
├── manifest.json
├── data/  # tracker.sqlite, password_hash.txt, auth_secret.txt, backups/ (в git НЕ коммитится)
└── logs/  # Y-m-d-*.log (в git НЕ коммитится)
```

## Быстрый старт

Требования: PHP 8.0+ с `pdo_sqlite`, Apache с `mod_rewrite` (или `php -S` для локалки).

```bash
git clone <repo> && cd work-rhythm
# права на запись для веб-пользователя:
chmod 775 data logs
# вариант A — Apache: DocumentRoot → work-rhythm/
# вариант B — локалка без Apache:
php -S localhost:8000 -t .
```

1. Открой `http://localhost:8000/login`.
2. Логин по умолчанию: `admin` / пароль: `password`.
3. Сразу смени пароль: страница `/settings` или CLI:
   ```bash
   php set_password.php <новый-пароль>
   ```
4. Заполни «Сегодня» → «Завершить день».

База `data/tracker.sqlite` и секрет `data/auth_secret.txt` создадутся сами при первом запуске.

## Безопасность

* Дефолтный хеш в `config.php` — это известный хеш слова `password`. Только для первого запуска, обязательно сменить.
* Реальные `data/password_hash.txt`, `data/auth_secret.txt`, `data/*.sqlite`, `data/backups/`, `logs/*.log`, `*.csv` — никогда не коммитятся, уже covered в `.gitignore`.
* От веба закрыты через `.htaccess`: `data/`, `logs/`, `*.sqlite`, `config.php`, `database.php`, `auth.php`.
* Серверные запреты в API: будущие даты → `403`, правка завершённого дня → `409`, незаполненные обязательные поля при завершении → `422`.

## API (кратко, всё требует логина)

* `POST api/save-day` — сохранить черновик. Неприменимые поля (условная логика) хранятся как `NULL`.
* `POST api/complete-day` — валидация + `status=completed`.
* `GET api/get-day?date=YYYY-MM-DD`, `GET api/get-history` — чтение.

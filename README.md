# Stillroom

Спокойная музыка для сна, релаксации, медитации и фокуса: сайт, Android-приложение и CMS, в которой вы сами
добавляете треки. Название рабочее — меняется в админке (**Site & SEO → Site name**) без правки кода.

```
server/                  ← сайт + CMS (PHP 8.2+, MySQL/MariaDB; SQLite для разработки)
├─ public/               ← содержимое public_html на Hostinger
│  ├─ index.php          ← единая точка входа: сайт, /api/v1, /admin, /install
│  ├─ app.html           ← оболочка веб-клиента (SEO подставляет сервер)
│  ├─ assets/js/         ← клиент: app.js, player.js, listen.js, sound.js, backgrounds.js (33 сцены Codex) …
│  ├─ admin-assets/      ← стили и скрипт админки
│  └─ uploads/           ← загруженные аудио и обложки (скрипты здесь не исполняются)
├─ src/                  ← код CMS (вне public_html)
├─ templates/admin/      ← страницы админки
├─ migrations/           ← схема БД (одна для MySQL и SQLite)
├─ seed/catalog.json     ← исходный каталог: 33 трека, 4 категории, 14 тегов, 5 подборок
├─ bin/                  ← migrate, create-admin, backup, restore, export-catalog
├─ storage/              ← база SQLite, бэкапы, логи, сессии (вне public_html)
└─ .env.example          ← настройки; настоящий .env никогда не попадает в git
scripts/build-app.mjs    ← собирает www/ для Android из того же клиента
capacitor.config.json    ← Android/iOS оболочка (Capacitor)
docs/                    ← инструкции
```

## Документация

- [docs/DEPLOY-HOSTINGER.md](docs/DEPLOY-HOSTINGER.md) — как выложить на Hostinger (с SSH и без), база, бэкапы, обновления.
- [docs/CMS-GUIDE.md](docs/CMS-GUIDE.md) — как добавлять музыку, подборки, менять главную, тексты, логотип и SEO.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — почему так устроено, модель данных, API, безопасность, что проверено.

## Запуск на своём компьютере

Нужен PHP 8.2+ с расширениями pdo_sqlite, gd, fileinfo, zip (на macOS: `brew install php`).

```bash
cp server/.env.example server/.env
# в server/.env: DB_DRIVER=sqlite, PUBLIC_DIR=public, APP_URL=http://127.0.0.1:8080, APP_DEBUG=true
php server/bin/migrate.php --seed            # таблицы + перенос текущего каталога
php server/bin/create-admin.php you@example.com
npm run dev                                   # http://127.0.0.1:8080, админка: /admin
```

## Android

Приложение — тот же клиент, что и сайт. Оно загружает каталог из API, поэтому новые треки появляются без пересборки.
Адрес API задаётся переменной репозитория `STILLROOM_API_BASE` (`https://ваш-домен/api/v1`) —
GitHub → Settings → Secrets and variables → Actions → Variables. Без неё приложение играет встроенный офлайн-каталог.

- `main` → релиз **latest-build** (`Stillroom.apk`), как раньше.
- другие ветки → отдельный пре-релиз `preview-<ветка>`, `main` не затрагивается.

iOS: собирается сборка для симулятора; на iPhone нужна подпись Apple (Mac + Xcode, для TestFlight — Apple Developer).

# Выкладка на Hostinger

Подходит любой тариф Hostinger с PHP и MySQL (Web/Cloud hosting) и VPS. Платные услуги не нужны, кроме уже
имеющегося хостинга и домена. **Действующий сайт не трогайте**: сначала выложите на поддомен
(например `new.ваш-домен`) или на временный адрес Hostinger, проверьте, потом переключите.

## 1. Что нужно

- PHP **8.2 или новее** (hPanel → Advanced → PHP Configuration) с расширениями `pdo_mysql`, `gd`, `fileinfo`, `zip`, `intl`
  (на Hostinger они включены по умолчанию — проверьте вкладку PHP Extensions).
- База MySQL/MariaDB: hPanel → Databases → **MySQL Databases** → создать базу и пользователя. Запишите имя базы,
  пользователя и пароль (хост обычно `localhost`).
- SSL для домена: hPanel → Security → SSL (бесплатный Let's Encrypt включается в один клик).

## 2. Раскладка файлов

На Hostinger у каждого сайта есть папка `domains/ВАШ-ДОМЕН/` с `public_html` внутри.
Всё приватное лежит **рядом** с `public_html`, а не внутри:

```
domains/ВАШ-ДОМЕН/
├─ public_html/        ← содержимое server/public/ (index.php, app.html, assets/, admin-assets/, uploads/, .htaccess, .user.ini, sw.js)
├─ src/                ← server/src/
├─ templates/          ← server/templates/
├─ migrations/         ← server/migrations/
├─ seed/               ← server/seed/
├─ bin/                ← server/bin/
├─ storage/            ← server/storage/ (папки backups, logs, sessions)
└─ .env                ← из server/.env.example
```

Загрузить можно через hPanel → **File Manager** (архив → Extract) или по SFTP/SSH.
Скрытые файлы `.htaccess` и `.user.ini` обязательны — в File Manager включите показ скрытых файлов.

## 3. Настройка `.env`

Скопируйте `.env.example` в `.env` (в `domains/ВАШ-ДОМЕН/`) и заполните:

```
APP_URL=https://ВАШ-ДОМЕН
APP_DEBUG=false
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=u123456789_stillroom
DB_USER=u123456789_stillroom
DB_PASS=пароль-базы
PUBLIC_DIR=public_html
INSTALL_TOKEN=придумайте-16+-случайных-символов
MAX_AUDIO_MB=60
```

`.env` никогда не загружайте в GitHub — репозиторий публичный.

## 4. Установка

**Без SSH (через браузер):** откройте `https://ВАШ-ДОМЕН/install`, введите `INSTALL_TOKEN`, email и пароль
администратора, оставьте галочку «Import the existing 33 Stillroom tracks». Установщик создаст таблицы, перенесёт
каталог и первого администратора, после чего отключится сам. Удалите строку `INSTALL_TOKEN` из `.env`.

**С SSH:**
```bash
cd ~/domains/ВАШ-ДОМЕН
php bin/migrate.php --seed
php bin/create-admin.php you@example.com
```

Проверьте: `https://ВАШ-ДОМЕН/api/v1/health` → `{"ok":true,...}`, сайт открывается, `/admin` пускает.

## 5. Лимиты загрузки

`public_html/.user.ini` поднимает `upload_max_filesize` до 64 МБ и `post_max_size` до 72 МБ. Если Hostinger
показывает меньший лимит (видно на дашборде админки), поставьте те же значения в hPanel → PHP Configuration → PHP Options.
`MAX_AUDIO_MB` в `.env` должен быть не больше `upload_max_filesize`.

## 6. HTTPS и кэш

После включения SSL раскомментируйте в `public_html/.htaccess` три строки «force HTTPS».
Если на хостинге включён LiteSpeed Cache/CDN, исключите из кэша пути `/admin`, `/install`, `/api/` — иначе
админка и превью могут показывать устаревшее. Публичный каталог сам отдаёт `Cache-Control: max-age=60`.

## 7. Резервные копии

- Встроенные: админка → **Backups** → «Create backup now» (zip: вся база + загруженные файлы, хранится в `storage/backups`, вне сайта).
- Ежедневно автоматически: hPanel → Advanced → **Cron Jobs**, раз в сутки:
  `/usr/bin/php /home/ПОЛЬЗОВАТЕЛЬ/domains/ВАШ-ДОМЕН/bin/backup.php`
  (точный путь показан на странице Backups). Хранятся последние `BACKUP_KEEP` (по умолчанию 14).
- Скачивайте свежий бэкап к себе хотя бы раз в неделю — копия на том же сервере не спасёт от потери аккаунта.
- Восстановление (SSH): `php bin/restore.php storage/backups/stillroom-….zip --yes` — сначала сохраняет копию текущего состояния.
- Дополнительно у Hostinger есть собственные бэкапы аккаунта (hPanel → Files → Backups).

## 8. Android-приложение → ваш сервер

GitHub → репозиторий → Settings → Secrets and variables → Actions → **Variables** → New variable:
`STILLROOM_API_BASE` = `https://ВАШ-ДОМЕН/api/v1`. Следующая сборка APK будет брать каталог с вашего сервера.
Это переменная, а не секрет: API публичный и только для чтения.

## 9. Обновление кода

1. Сделайте бэкап (админка → Backups).
2. Залейте новые `src/`, `templates/`, `migrations/`, `bin/` и содержимое `public/` (кроме `uploads/`!).
3. Обновите базу: SSH — `php bin/migrate.php`; без SSH — админка → **Backups** → «Apply database updates»
   (кнопка появляется, только если есть новые миграции; перед применением делается копия базы).

## 10. Чек-лист безопасности

- [ ] `.env` лежит вне `public_html`, `APP_DEBUG=false`, `INSTALL_TOKEN` удалён после установки.
- [ ] `https://ВАШ-ДОМЕН/.env`, `/src/Auth.php`, `/storage/` не открываются (404/403).
- [ ] Пароль администратора 12+ символов, у редакторов — роль `editor`.
- [ ] SSL включён, HTTPS принудительный.
- [ ] Cron с бэкапом настроен, скачан хотя бы один бэкап.

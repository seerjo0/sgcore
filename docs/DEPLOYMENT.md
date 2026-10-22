# Deploying sgcore to production

A practical, self-hosted runbook. Assumes **Ubuntu/Debian 24.04 LTS** behind **Nginx + PHP-FPM 8.3+** with **MySQL/MariaDB**, but the steps apply to any Linux box with these pieces. On Windows Server / IIS the same commands work with the equivalent tools.

## 1. Requirements

- PHP **≥ 8.3** with extensions: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `zip`, `openssl`, `curl`.
- MySQL ≥ 8 or MariaDB ≥ 10.6. A non-root DB user with `CREATE/ALTER/DROP/INDEX` on one database only.
- Composer 2.x, Node.js ≥ 20 + npm (only on the build box; you can pre-build and ship).
- Redis is optional; the default cache/queue/session drivers are `database` (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`).

## 2. One-shot Linux install

Use the helper at `scripts/sgcore-install.sh` (idempotent), or run the equivalent steps below manually.

```bash
export SGCORE_DIR=/srv/sgcore \
       SGCORE_URL=https://app.example.com \
       DB_DATABASE=sgcore \
       DB_USERNAME=sgcore \
       DB_PASSWORD='change-me'

sudo -u www-data bash scripts/sgcore-install.sh v1.0.0
```

It will: clone the repo (if missing), fetch and check out the tag, run `composer install --no-dev`, copy `.env.example` → `.env`, set `APP_ENV/APP_DEBUG/APP_URL` + DB creds + locale, `php artisan key:generate`, `composer dump-autoload`, run `npm ci && npm run build` when Node is present, `migrate --force`, `storage:link`, and `optimize:clear`. It prints the exact `cms:install` command to run so the admin password is never a shell argument. It does **not** `chown`/`sudo` anything; run it as the web server user (e.g. `sudo -u www-data bash scripts/sgcore-install.sh v1.0.0`) or set permissions yourself.

## 3. Manual install

### 3.1 Code

```bash
sudo mkdir -p /srv/sgcore && sudo chown $USER: /srv/sgcore
git clone https://github.com/seerjo0/sgcore.git /srv/sgcore
cd /srv/sgcore
git checkout v1.0.0                    # never deploy from `main`
composer install --no-dev --no-interaction --optimize-autoloader
npm ci && npm run build                # produces public/build
```

### 3.2 Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`. Production essentials already in the shipped `.env.example`:

```dotenv
APP_NAME=sgcore
APP_ENV=production          # ← default in .env.example; do not ship local to prod
APP_KEY=base64:…            # from key:generate
APP_DEBUG=false             # ← default in .env.example
APP_URL=https://app.example.com
APP_TIMEZONE=America/Sao_Paulo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgcore
DB_USERNAME=sgcore
DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true  # if HTTPS

CACHE_STORE=database
QUEUE_CONNECTION=database
```

> **Note (v1.0.0+):** `.env.example` and `cms:install` default to `APP_ENV=production` and `APP_DEBUG=false`. Use `cms:install --dev` (or set them by hand) when you want local debug.

### 3.3 Database

```bash
mysql -e "CREATE DATABASE sgcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -e "CREATE USER 'sgcore'@'localhost' IDENTIFIED BY '…'; GRANT ALL ON sgcore.* TO 'sgcore'@'localhost'"
```

### 3.4 Install the schema + admin

You can either run the **web wizard** (`https://app.example.com/instalar`) once and delete the lock, or do it entirely from the CLI (recommended for automation):

```bash
php artisan cms:install \
  --db-host=127.0.0.1 \
  --db-database=sgcore \
  --db-username=sgcore \
  --db-password='…' \
  --site-name="Meu Site" \
  --admin-name="Admin" \
  --admin-email=admin@example.com \
  --admin-password='change-me-now' \
  --sample
```

That runs migrations, seeds the initial settings, creates the admin user, and writes `storage/app/installed.lock`. To reinstall on a scratch environment: `php artisan cms:uninstall --force`.

### 3.5 Storage link, permissions

```bash
php artisan storage:link
sudo chown -R www-data:www-data /srv/sgcore
sudo chmod -R ug+rwx /srv/sgcore/storage /srv/sgcore/bootstrap/cache
```

### 3.6 Web server (Nginx + PHP-FPM)

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;
    root /srv/sgcore/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

## 4. Background processes

The scheduler refreshes the "update available" banner (`sgcore:check-updates`) and cleans old revisions.

**Cron (running every minute):**
```
* * * * * cd /srv/sgcore && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker (Supervisor, `database` driver):**
```ini
[program:sgcore-queue]
command=php /srv/sgcore/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/srv/sgcore
user=www-data
numprocs=2
autostart=true
autorestart=true
```

## 5. Staying up to date

Set `CMS_VERSION` to your deployed version (default `1.1.0`). The admin panel checks GitHub Releases (falling back to tags) every hour; when a newer version exists, an admin-only banner appears. Update by:

- **CLI** — `php artisan sgcore:update` (dry-run first with `--dry-run`).
- **Panel** — set `CMS_UPDATER_ALLOW_APPLY=true` in `.env` on a host with `git` and `composer` available; the banner shows an "Apply update" button that requires typing the exact target version.

Both paths run inside maintenance mode, back up the DB when `mysqldump`/`pg_dump` is available, and roll back on failure. Never enable the button on shared hosting or without a filesystem backup.

### Upgrading an installation older than v1.0.1 (no updater yet)

The in-panel updater and `sgcore:update` only exist since **v1.0.1**. An installation bootstrapped from the v1.0.0 `public_html.zip` cannot update itself, so the first hop is manual. After that the panel handles the rest.

**1. Back up first.** `mysqldump` the database and copy `.env` + `storage/` off the server. Without this, stop.

**2. Download** `sgcore-<version>-public_html.zip` from the GitHub Release. The package ships `vendor/` (production) and `public/build`, so neither `composer` nor `npm` is needed on the server.

**3. Extract over the install** (merge/overwrite — never delete the install first). It is safe by construction:

| Path | Effect on extract |
|---|---|
| `.env` | not in the package — untouched |
| `storage/` | only `.gitignore` placeholders ship — uploads/logs untouched |
| `bootstrap/cache/` | `packages.php`/`services.php` caches are refreshed, nothing else |
| `index.php`, `.htaccess`, `public/.htaccess` | **overwritten** — re-apply any local customizations afterwards (the panel/CLI package updater carries the cPanel PHP handler block over automatically; a manual extract does not, so re-check the domain's PHP version in MultiPHP afterwards) |

**4. Run the pending migrations.** With a shell:

```bash
cd /path/to/site && php artisan migrate --force && php artisan optimize:clear
```

Without a shell, the freshly extracted code already contains the updater: add `CMS_UPDATER_ALLOW_APPLY=true` to `.env`, open **Configurações → Atualizações → Atualização manual → Pacote .zip** and upload the same file. That path runs `migrate --force` and `optimize:clear` from inside Laravel (a one-off PHP script that calls the two commands, deleted right after, also works).

Do this immediately after extracting: the v1.0.0 → v1.1.0 hop adds the `pages.builder_custom_css` column, and builder pages error until it exists.

**5. Verify.** Admin → Configurações → Atualizações shows the current version and "up to date"; then open a builder page and the public site.

## 6. Backup

Daily `mysqldump` (or `pg_dump`) + a filesystem tar of `storage/app/public/` covers everything the app writes. Restoring a snapshot + `php artisan migrate --force` is enough to catch up. The updater's own backups go to `storage/app/backups/`.

## 7. Logs & monitoring

`storage/logs/laravel.log` (via `LOG_CHANNEL=stack`+`single`). Bump `LOG_LEVEL=warning` in production if you find `debug` too noisy. Healthcheck endpoint at `GET /up` (Laravel's built-in) is available for your load balancer.

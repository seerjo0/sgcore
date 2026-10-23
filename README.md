# sgcore

**A lightweight, modular, self-hosted CMS built with Laravel — own your content, your site, and your data.**

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![Self-Hosted](https://img.shields.io/badge/Self--Hosted-100%25-blue)
![Free](https://img.shields.io/badge/100%25-Free-success)

## What is sgcore?

sgcore is a modular, self-hosted CMS built on Laravel 13. It's completely free and gives you everything you need to run a full website — whether that's an institutional site, a blog, or a portfolio — through an admin panel in Brazilian Portuguese.

It was built with developers and web designers in mind. The foundation stays flexible, but you also get practical tools for managing content, media, themes, menus, and visual settings without having to build everything from scratch.

Here's what comes included:

- Modular architecture
- Self-hosted admin panel
- Pages with rich-text content
- Visual page builder (drag-and-drop, GrapesJS)
- Blog with posts and categories
- Media library and galleries
- Customizable theme engine
- Nested menus
- Admin and editor roles
- SEO settings
- Light and dark panel themes
- Configurable admin path
- Installable modules (WordPress-style)
- Brazilian Portuguese and English admin panel

## Why sgcore?

- **You own your content.** Everything is self-hosted, so your site and its data stay under your control.
- **No SaaS dependency.** Run it on your own server instead of relying on a hosted platform.
- **No monthly cost.** It's free and released under the MIT license.
- **Built to be extended.** The modular architecture and theme system make it easy to adapt to whatever you're building.

## Features

### Modular architecture

The CMS is split into independent modules:

- Core
- Installer
- Auth
- Blog
- Media
- Pages
- PageBuilder
- Themes

This keeps things organized and gives you a clean base to build on.

Modules can be activated, deactivated, installed from a ZIP, or deleted from the **Modules** screen in the admin panel (WordPress-style), without touching the codebase. Protected modules (`core`, `installer`, `auth`) and all built-in modules are immutable, so a dependency can never be removed out from under another.

### Guided installation

You can install sgcore in two ways:

- Web installer at `/instalar`
- Command-line install via Artisan

You also have the option to include sample content during installation.

### User roles and permissions

Two roles come built in:

- **Admin** — full access to everything
- **Editor** — access limited by area permissions

### Media library

Manage all your site's media from one place.

- Image uploads
- Automatic resizing using GD
- Sortable galleries
- Reusable media selector in forms

### Pages and menus

Create and organize content with:

- Unique page slugs
- Draft and published states
- Sanitized rich-text content
- Nested menus

### Blog

Publish articles with posts and categories:

- Post CRUD with auto/unique slugs, excerpt, cover image and per-post SEO
- Draft, published and future-scheduled states (scheduled posts stay hidden until due)
- A public archive at `/blog` with pagination and category filtering, plus `/blog/{slug}` for each post
- Post bodies run through the same allow-list sanitizer as pages (`<script>`, `javascript:` and event handlers are stripped)
- Managed by the same editor role (its own `manage-blog` permission)

### Visual site builder

Build layouts by dragging components onto a GrapesJS canvas (Bootstrap 5). Publishing is versioned and safe by design:

- An allowlisted component registry — every block renders through a server-side Blade view, so user content is never executed as code
- 18 built-in components (sections, columns, headings, text, images, buttons, video, YouTube embeds, quotes, icons, cards, lists, alerts, badges…)
- Structured publishing: the GrapesJS project is reduced to a validated component tree and re-rendered on the public page
- Restorable publish revisions with a line-by-line diff
- A global design system (colors, fonts, spacing scale, breakpoints) shared by the editor and the front-end
- Dynamic bindings (`{site:name}`, `{page:title}`, `{year}`, `{media:ID}`)
- Site-wide header/footer/404 templates and render caching

Full details live in [`docs/visual-site-builder.md`](docs/visual-site-builder.md).

### Theme engine

Build themes using HTML and Blade. sgcore ships with:

- 3 built-in themes
- Theme import via validated ZIP files
- Protection against Zip-Slip attacks during imports
- Editable content slots available globally or per page

Content slot types include text, rich text, image, gallery, and logo.

### Visual customization

You can customize both the site and the admin panel:

- Light and dark panel themes
- 6 admin accent colors
- Site name, logo, and favicon
- SEO title and description
- Open Graph settings

### Custom admin path

Change the default `/admin` path using the `ADMIN_PATH` environment variable:

```bash
ADMIN_PATH=/backend
```

or:

```bash
ADMIN_PATH=/administrator
```

### Optional sample content

During installation, you can opt to create sample pages and a menu to get started faster.

### Brazilian Portuguese and English admin panel

The admin interface ships in Brazilian Portuguese (pt-BR) and English (en-US). The language is chosen during installation and can be switched at any time from **Settings → Language**, which updates the panel, the login, and the public site on the next request.

### Staying up to date

sgcore checks the project's GitHub Releases and shows an admin-only "Update available" banner with a link to the release notes when a newer version is published. The check is **read-only by default**: it never downloads or applies anything, and you update through your normal deploy step.

**Updates page** (`Configurações → Atualizações`) — the panel manages the whole cycle:

- **Check**: refresh the notification (also run daily by the scheduler).
- **Download package**: fetches the release `.zip` into `storage/app/updates` and stops there — no code is swapped. The file survives the install it is about to perform.
- **Install**: applies the package you downloaded earlier (behind a confirmation checkbox), then deletes it. The installed/most-recent package is listed with its version, size and download date, and can be discarded.
- **Automatic updates**: three independent preferences saved in the database, no `.env` edit needed — *check automatically*, *download automatically* and *install automatically* (install requires download). The scheduler runs `sgcore:auto-update` hourly and honours them; it needs the usual `* * * * * php artisan schedule:run` cron entry.

Packages are applied in place while keeping your `.env`, `storage/` and `bootstrap/cache/` untouched, then `migrate --force` runs and the cache is cleared. The download verifies the asset is hosted on `github.com`/`*.githubusercontent.com` for your repo, respects a size ceiling (`CMS_UPDATER_MAX_PACKAGE_BYTES`) and checks the GitHub **sha256** digest when present. This whole package flow works on **shared hosting** (no git/composer/node) and is opt-in: set `CMS_UPDATER_ALLOW_APPLY=true`; it can be narrowed with `CMS_UPDATER_ALLOW_PACKAGE` / `CMS_UPDATER_ALLOW_DOWNLOAD` (both default to on when apply is enabled; tune `CMS_UPDATER_DOWNLOAD_TIMEOUT`). The panel also keeps the cPanel **PHP handler** block from your `.htaccess` across updates, so the domain's PHP version is not reset. Updates run in maintenance mode; since v1.2.1 the page stays reachable while the site is down, shows a **Leave maintenance** button and self-heals a `down` file left behind by an interrupted run.

If your install is instead a **git clone on a host you control** (VPS with `git`/`composer` on `PATH` and write access to the app), the same opt-in unlock adds an "Apply update" button that enters maintenance mode, backs up the database (when a dump tool is available), does `git fetch` + `git checkout <tag>` + `composer install --no-dev` + `php artisan migrate --force`, and rolls back to the previous commit if anything fails. It is admin-only, throttled, and requires **typing the exact target version** to confirm. Auto-install uses the package when the release ships one and falls back to this Git flow otherwise.

To **build that package**, run `php artisan sgcore:package` (from a clean checkout with `composer`/`npm` available) — it produces a self-contained `dist/sgcore-<version>.zip` (vendored dependencies, built assets, a root `index.php` front controller for web-root installs, hardened `.htaccess`/`web.config` rules and a `sgcore-package.json` manifest). Flags: `--version`, `--out`, `--skip-composer`, `--skip-npm`, `--php-min`. Attach the resulting `.zip` to the GitHub Release so the page's download button can fetch it.

To **activate the release channel**, publish a tag: `git tag -a v1.0.0 -m "sgcore v1.0.0" && git push origin v1.0.0`. The checker reads the newest semver tag automatically (it doesn't require a GitHub Release). The running version comes from `config('cms.version')` (override with `CMS_VERSION`); the check is cached to respect GitHub's rate limit and can be refreshed from the banner or via `php artisan sgcore:check-updates` (also run daily by the scheduler). Disable it with `CMS_UPDATER_ENABLED=false`.

You can also apply updates from the command line without the button: `php artisan sgcore:update --dry-run` shows the plan (incoming commits + steps), and `php artisan sgcore:update` runs it after a confirmation.

## Requirements

You'll need the following:

- PHP 8.3 or newer
- Composer
- Node.js and npm
- MySQL or MariaDB
- Nginx or Apache pointing to the project's `public/` directory
- PHP extensions: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `zip`, `openssl`

For local development, `php artisan serve` works fine.

sgcore is developed and tested up to PHP 8.5.

## Installation (self-hosted)

This assumes you already have PHP 8.3+, Composer, Node.js/npm, MySQL or MariaDB, and a web server set up.

### 1. Clone the repository

Replace `<URL_DO_REPOSITORIO_NO_GITHUB>` with the actual repository URL:

```bash
git clone <URL_DO_REPOSITORIO_NO_GITHUB> sgcore && cd sgcore
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Configure the environment

Copy the example env file:

```bash
cp .env.example .env
```

Then fill in your database credentials:

```text
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

### 4. Generate the application key

```bash
php artisan key:generate
```

### 5. Install frontend dependencies and build assets

```bash
npm install && npm run build
```

### 6. Create the storage link

```bash
php artisan storage:link
```

### 7. Install sgcore

You can use either the web installer or the command line.

#### Option A — Web installer

Start your server with Nginx/Apache or Laravel's dev server:

```bash
php artisan serve
```

Then open `/instalar` in your browser.

#### Option B — Command line

```bash
php artisan cms:install --db-host=127.0.0.1 --db-database=sgcore --db-username=root --db-password=... --admin-name="Seu Nome" --admin-email=voce@exemplo.com --admin-password=... --site-name="Meu Site" --sample
```

The `--sample` flag creates sample content.

All `--db-*`, `--admin-*`, and `--site-name` options are optional and can be provided interactively.

### 8. Access the admin panel

The default path is:

```text
/admin
```

If you set `ADMIN_PATH` in your `.env`, use that instead.

### 9. Optional domain configuration

Point your domain through `APP_URL` in `.env`:

```text
APP_URL=<SEU_DOMINIO>
```

Then configure your web server to point to the project's `public/` directory.

> **Production tip:** Never leave `APP_DEBUG=true` in production.

## First steps

Once sgcore is installed:

1. Log in to the admin panel.
2. Create your pages — write rich text or lay them out with the visual builder — and organize them with nested menus.
3. Publish articles on the blog and group them by category.
4. Upload media and create galleries as needed.
5. Pick and customize a theme.
6. Set up your site identity — name, logo, favicon.
7. Configure SEO settings (title, description, Open Graph).
8. Customize the admin panel with light/dark mode and an accent color.
9. Change the admin path via `ADMIN_PATH` if you want a custom URL.

## Support & donate

sgcore is built and shared freely. If it's useful to you and you'd like to support its continued development, any contribution is genuinely appreciated.

- [Donate via LivePix](https://livepix.gg/seerjo0)
- [Donate via Ko-fi](https://ko-fi.com/seerjo0)

## License

sgcore is open source software licensed under the MIT License.

You're free to use, modify, and distribute it under the terms of the MIT License.

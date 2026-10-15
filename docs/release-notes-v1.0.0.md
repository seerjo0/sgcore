# sgcore v1.0.0

First stable release of **sgcore** — a lightweight, modular, self-hosted CMS built on
Laravel 13 and PHP ≥ 8.3. Own your content, your site, and your data.

## Highlights

- **Pages + nested menus** with unique slugs, draft/published states and allow-list-sanitized rich text.
- **Media library & galleries**: GD resizing, `/media/…` serving with traversal guards, reusable media picker.
- **Theme engine**: HTML+CSS themes with a `theme.json` manifest, editable slots (text/richtext/image/logo/gallery), menus injected by `data-cms-menu`, ZIP import protected against Zip-Slip, 3 built-in themes.
- **Visual Site Builder (Elementor-style)**: GrapesJS + Bootstrap 5, an allow-listed **component registry** (18 components) rendered server-side through Blade (user content is never executed), versioned publish with restorable revisions + line-by-line diff, a shared **design system**, dynamic bindings, and site-wide header/footer/404 templates.
- **Blog**: posts + categories, admin CRUD, public archive/single with per-post SEO; drafts and scheduled posts stay hidden until published.
- **Guided install** (web `/instalar` + CLI `php artisan cms:install`), **roles/permissions**, **i18n** (pt-BR + en), **SEO settings**, light/dark panel, custom admin path, and a WordPress-style **module manager**.
- **Update-aware**: the panel checks GitHub Releases (falls back to tags) and offers a **git-based one-click updater** (opt-in, behind a typed confirmation, with maintenance mode + DB backup + rollback).

## Upgrade / install

Fresh production install (see `docs/DEPLOYMENT.md` and `scripts/sgcore-install.sh`):
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
php artisan cms:install   # or visit /instalar
```

New in this release: `.env.example` and `cms:install` now default to
`APP_ENV=production` / `APP_DEBUG=false` (use `cms:install --dev` to keep a local
debug environment).

## Requirements
- PHP ≥ 8.3 with `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `zip`, `curl`, `openssl`
- MySQL / MariaDB
- Nginx or Apache pointed at `public/`

## Changelog
Full log: [`CHANGELOG.md`](https://github.com/seerjo0/sgcore/blob/v1.0.0/CHANGELOG.md).

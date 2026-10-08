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
- Media library and galleries
- Customizable theme engine
- Nested menus
- Admin and editor roles
- SEO settings
- Light and dark panel themes
- Configurable admin path

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
- Media
- Pages
- Themes

This keeps things organized and gives you a clean base to build on.

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

### Brazilian Portuguese admin panel

The admin interface is in Brazilian Portuguese (pt-BR).

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
2. Create your pages and organize them with nested menus.
3. Upload media and create galleries as needed.
4. Pick and customize a theme.
5. Set up your site identity — name, logo, favicon.
6. Configure SEO settings (title, description, Open Graph).
7. Customize the admin panel with light/dark mode and an accent color.
8. Change the admin path via `ADMIN_PATH` if you want a custom URL.

## Support & donate

sgcore is built and shared freely. If it's useful to you and you'd like to support its continued development, any contribution is genuinely appreciated.

- [Donate via LivePix](https://livepix.gg/seerjo0)
- [Donate via Ko-fi](https://ko-fi.com/seerjo0)

## License

sgcore is open source software licensed under the MIT License.

You're free to use, modify, and distribute it under the terms of the MIT License.

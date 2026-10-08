# sgcore

**A lightweight, modular, self-hosted CMS built with Laravel — own your content, your site, and your data.**

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel\&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php\&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss\&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![Self-Hosted](https://img.shields.io/badge/Self--Hosted-100%25-blue)
![Free](https://img.shields.io/badge/100%25-Free-success)

## What is sgcore?

**sgcore** is a modular, self-hosted and 100% free Content Management System built on **Laravel 13**. It gives you everything you need to create and manage a complete website — from institutional websites and blogs to portfolios — through its own administration panel in Brazilian Portuguese.

Designed with developers and web designers in mind, sgcore keeps the foundation flexible while giving you practical tools for managing content, media, themes, menus and visual settings.

### Built-in resources

* 🧩 Modular architecture
* 🖥️ Self-hosted administration panel
* 📝 Pages and rich-text content
* 🖼️ Media library and galleries
* 🎨 Customizable theme engine
* 🧭 Nested menus
* 👥 Admin and editor roles
* 🔍 Built-in SEO settings
* 🌗 Light and dark panel themes
* ⚙️ Customizable administration path

## Why sgcore?

* **Own your content** — Your website and its content are self-hosted under your control.
* **No SaaS dependency** — Run sgcore on your own server instead of depending on a hosted CMS platform.
* **No monthly cost** — sgcore is 100% free and released under the MIT license.
* **Built to be extended** — Its modular architecture and flexible theme system make it suitable for developers and web designers who want control over their implementation.

## Features

### Modular architecture

sgcore is organized into independent modules:

* **Core**
* **Installer**
* **Auth**
* **Media**
* **Pages**
* **Themes**

This structure keeps the CMS organized and provides a solid foundation for customization.

### Guided installation

Choose how you want to install sgcore:

* Web-based installation wizard available at `/instalar`
* Command-line installation through Artisan
* Optional sample content during installation

### User roles and permissions

Two user roles are included:

* **Admin** — full administrative access
* **Editor** — access controlled by area permissions

### Media library

Manage your website's media from a centralized library.

* Image uploads
* Automatic image resizing using GD
* Sortable galleries
* Reusable media selector in forms

### Pages and menus

Create and organize your website content with:

* Unique page slugs
* Draft and published states
* Sanitized rich-text content
* Nested menus

### Theme engine

Build themes your way with **HTML and Blade**.

sgcore includes:

* 3 built-in themes
* Theme import through validated ZIP files
* Protection against Zip-Slip attacks during theme imports
* Editable content slots available globally or per page
* Content slot types:

  * Text
  * Rich text
  * Image
  * Gallery
  * Logo

### Visual customization

Customize the appearance and identity of your site and administration panel.

* Light and dark panel themes
* 6 administration accent colors
* Site name
* Logo
* Favicon
* SEO title
* SEO description
* Open Graph settings

### Custom administration path

Change the default `/admin` path through the `ADMIN_PATH` environment variable.

For example:

```bash
ADMIN_PATH=/backend
```

or:

```bash
ADMIN_PATH=/administrator
```

### Optional sample content

During installation, you can choose to create sample content containing pages and a menu.

### Brazilian Portuguese administration panel

The administration interface is available in **Brazilian Portuguese (pt-BR)**.

## Requirements

### Server requirements

* [ ] PHP 8.3 or newer
* [ ] Composer
* [ ] Node.js and npm
* [ ] MySQL or MariaDB
* [ ] Nginx or Apache pointing to the project's `public/` directory
* [ ] PHP `pdo_mysql` extension
* [ ] PHP `gd` extension
* [ ] PHP `fileinfo` extension
* [ ] PHP `mbstring` extension
* [ ] PHP `zip` extension
* [ ] PHP `openssl` extension

For development, you can use Laravel's built-in server with `php artisan serve`.

sgcore is developed and tested up to **PHP 8.5**.

## Installation (self-hosted)

The following installation assumes you already have PHP 8.3+, Composer, Node.js/npm, MySQL or MariaDB, and a web server available.

### 1. Clone the repository

Replace `<URL_DO_REPOSITORIO_NO_GITHUB>` with the repository URL:

```bash
git clone <URL_DO_REPOSITORIO_NO_GITHUB> sgcore && cd sgcore
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Configure the environment

Copy the example environment file:

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

You can install sgcore using either the web installer or the command line.

#### Option A — Web installer

Start your web server using Nginx/Apache or Laravel's development server:

```bash
php artisan serve
```

Then open the installation wizard in your browser:

```text
/instalar
```

#### Option B — Command line

Run:

```bash
php artisan cms:install --db-host=127.0.0.1 --db-database=sgcore --db-username=root --db-password=... --admin-name="Seu Nome" --admin-email=voce@exemplo.com --admin-password=... --site-name="Meu Site" --sample
```

The `--sample` flag creates sample content.

All `--db-*`, `--admin-*`, and `--site-name` options are optional and can be provided interactively.

### 8. Access the administration panel

The default administration path is:

```text
/admin
```

If you configured `ADMIN_PATH` in your `.env`, use your configured path instead.

### 9. Optional domain configuration

You can point your domain through `APP_URL` in `.env`:

```text
APP_URL=<SEU_DOMINIO>
```

Then configure your web server to point to the project's:

```text
public/
```

> **Production tip:** Never keep `APP_DEBUG=true` in production.

## First steps

Once sgcore is installed:

1. **Log in** to the administration panel.
2. **Create your pages** and organize them using nested menus.
3. **Upload your media** to the media library and create galleries when needed.
4. **Choose and customize your theme** using the available theme system.
5. **Configure your site identity**, including its name, logo and favicon.
6. **Configure SEO settings**, including title, description and Open Graph information.
7. **Customize the administration panel** with light/dark mode and your preferred accent color.
8. **Change the admin path** through `ADMIN_PATH` if you want to use a custom administration URL.

## Support & donate

sgcore is built and shared freely. If the project is useful to you and you'd like to support its continued development, your contribution is genuinely appreciated.

* [Donate via LivePix](https://livepix.gg/seerjo0)
* [Donate via Ko-fi](https://ko-fi.com/seerjo0)

## License

sgcore is open source software licensed under the **MIT License**.

You are free to use, modify, and distribute it in accordance with the terms of the MIT License.

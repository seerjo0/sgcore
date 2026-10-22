# Publishing sgcore releases

sgcore is distributed as a **Git repository**, not a zip artifact. A release is a **Git tag + a GitHub Release** pointing at that tag. The in-app **update checker** (`UpdateChecker`) reads GitHub Releases first and falls back to Git tags when none exist (`source=auto|releases|tags`) — but a proper Release page is friendlier for humans (changelog, downloads, "Latest" badge) and is what the admin banner links to.

This project pins its dependencies in `composer.lock` and `package-lock.json` — both are committed. A release cut must **never** regenerate those locks; they lock what `composer install` / `npm ci` will resolve on the target machine.

## Versioning

We follow [SemVer](https://semver.org/lang/pt-BR/). The tag is `vMAJOR.MINOR.PATCH`, matching `config('cms.version')` (overridable via `CMS_VERSION`). The changelog follows [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

- **MAJOR** — breaking changes (schema, module contracts, upgrade path).
- **MINOR** — new features, backward-compatible.
- **PATCH** — bug fixes and security, backward-compatible.

**Revisions** — a re-release of an already published version that carries only fixes or packaging changes (no new feature, no schema change) may use the `-rN` suffix: `1.1.0-r1`, `1.1.0-r2`. It is a revision, **not** a pre-release: `1.1.0 < 1.1.0-r1 < 1.1.0-r2 < 1.1.1`, which is what `App\Modules\Core\Support\Version` implements (PHP's `version_compare()` would rank it the other way around).

## Pre-flight

1. `git status` — clean tree (no uncommitted changes).
2. `vendor/bin/pint --test` — must be `result: passed`.
3. `php artisan test` — **all green** (currently 359 tests / 1473 assertions).
4. `npm run build` — no errors, latest `public/build/manifest.json` committed if the app serves hashed assets directly.
5. `CHANGELOG.md` — the version you're cutting must have a section under "Unreleased" that you're moving into a dated section.
6. `config('cms.version')` — set to the new version (`app/Modules/Core/config/cms.php` `'version' => env('CMS_VERSION', 'X.Y.Z')`). Bump the default there.
7. Optional: run `sgcore:check-updates` against a local mirror to sanity-check the tag pipeline.

## Cutting a release (with `gh`)

```bash
# 1. Stage the bump + changelog
git add CHANGELOG.md app/Modules/Core/config/cms.php
git commit -m "chore(release): v1.1.0"

# 2. Push the branch first (so the tag is on a remote-known commit)
git push origin main

# 3. Annotated tag (never lightweight — annotated carries the message + author)
git tag -a v1.1.0 -m "sgcore v1.1.0"
git push origin v1.1.0

# 4. GitHub Release with the changelog as notes
gh release create v1.1.0 \
  --title "sgcore v1.1.0" \
  --notes-file release-notes-v1.1.0.md \
  --latest
```

`gh` is installed only in the CI/maintainer box. On machines without `gh` (like the developer's laptop here), you can create the release manually at <https://github.com/seerjo0/sgcore/releases/new> after pushing the tag — paste the changelog section, tick "Set as the latest release", publish.

## Release notes template

Save as `release-notes-vX.Y.Z.md`, generate from `CHANGELOG.md` (cut the section), and add:

- **Highlights** — the three or four most important changes.
- **Upgrade notes** — any migration you must run, config keys you added/renamed, cron/queue changes.
- **Minimum requirements** — PHP ≥ 8.3, MySQL/MariaDB, Node 20 for the build.
- **Full changelog link** — a permalink to the tag diff.

Example skeleton:

```markdown
## What's new
- …

## Upgrade
```bash
php artisan migrate --force
php artisan optimize:clear
```

## Requirements
- PHP ≥ 8.3, `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `zip`, `openssl`
- MySQL/MariaDB
```

## Applying a release to production

Two paths, both safe by default:

**CLI (any hosting with SSH)** — on the server:
```bash
cd /srv/sgcore
git fetch --tags
git checkout v1.1.0
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
```

**In-panel one-click** (admin) — set `CMS_UPDATER_ALLOW_APPLY=true` in `.env` on a controlled host. The banner shows "Aplicar atualização" once a newer release is cached. It runs the same steps as above inside a maintenance window, with a **best-effort DB backup** first and a **rollback to the previous SHA** if any step fails. Never enable this flag on shared hosting or where the web server user can't run `git`/`composer`/`php`.

## Rollback

`git checkout v<anterior>` + `composer install --no-dev` + `php artisan migrate:rollback --force` (or restore the pre-update DB dump if the migration wasn't reversible). The in-panel apply also calls `UpdateRunner::rollback()` on failure.

## What is intentionally not in the release process

- Pre-built `vendor/` or `node_modules/` — installed by the user on the target so `composer.lock` integrity is verifiable.
- Pre-built `public/build/` assets — the operator runs `npm ci && npm run build` on the target (see `docs/DEPLOYMENT.md`). Frontend is not committed in the built form beyond what Vite normally ignores.
- Signed git tags — a good next step (protect `composer`/`git` trust with a maintainer GPG key), but not enforced yet.

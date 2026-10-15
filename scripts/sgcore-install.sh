#!/usr/bin/env bash
#
# sgcore-install.sh — one-shot Linux installer for a *fresh* production box.
#
# Usage:
#   SGCORE_URL=https://app.example.com \
#   DB_DATABASE=sgcore DB_USERNAME=sgcore DB_PASSWORD='…' \
#   bash scripts/sgcore-install.sh v1.0.0
#
# It clones the repo (if missing), checks out the given tag, installs PHP/JS
# dependencies, prepares .env, and prints the exact `cms:install` command to
# run so the admin password is never a script argument.
#
# It does NOT sudo anything itself; run with the permissions you want, e.g.
#   sudo -u www-data SGCORE_DIR=/srv/sgcore ... bash scripts/sgcore-install.sh v1.0.0

set -euo pipefail

VERSION="${1:-${SGCORE_VERSION:-}}"
DIR="${SGCORE_DIR:-/srv/sgcore}"
REPO="${SGCORE_REPO:-https://github.com/seerjo0/sgcore.git}"
APP_URL="${SGCORE_URL:-http://localhost}"
APP_ENV="${SGCORE_ENV:-production}"
APP_DEBUG="${SGCORE_DEBUG:-false}"
APP_LOCALE="${SGCORE_LOCALE:-pt_BR}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:?DB_DATABASE is required}"
DB_USERNAME="${DB_USERNAME:?DB_USERNAME is required}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"

if [[ -z "$VERSION" ]]; then
  echo "usage: bash scripts/sgcore-install.sh <version-tag>   (ex.: v1.0.0)" >&2
  exit 1
fi

command -v composer >/dev/null || { echo "composer não encontrado no PATH" >&2; exit 1; }
command -v php     >/dev/null || { echo "php não encontrado no PATH" >&2; exit 1; }
command -v git     >/dev/null || { echo "git não encontrado no PATH" >&2; exit 1; }

echo "==> Preparando ${DIR} @ ${VERSION}"

if [[ ! -d "${DIR}/.git" ]]; then
  mkdir -p "${DIR%/*}"
  git clone "$REPO" "$DIR"
fi

cd "$DIR"
git fetch --tags --prune origin
if ! git rev-parse --verify "tags/${VERSION}" >/dev/null 2>&1; then
  echo "tag ${VERSION} não existe no remote ${REPO}" >&2
  exit 2
fi
git checkout --detach "tags/${VERSION}"

echo "==> composer install (no-dev, optimized)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "==> criamos .env a partir de .env.example"
else
  echo "==> .env já existe; nada sobrescrevemos"
fi

APP_ENV="$APP_ENV" APP_DEBUG="$APP_DEBUG" APP_URL="$APP_URL" APP_LOCALE="$APP_LOCALE" \
DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_DATABASE="$DB_DATABASE" DB_USERNAME="$DB_USERNAME" DB_PASSWORD="$DB_PASSWORD" \
php -r '
$f = getcwd() . "/.env";
$s = file_get_contents($f);
$set = function (&$s, $k, $v) {
  $pat = "/^".preg_quote($k, "/")."\s*=.*$/m";
  $line = $k."=".$v;
  $s = (preg_match($pat, $s) === 1) ? preg_replace($pat, $line, $s, 1) : rtrim($s, "\n")."\n".$line."\n";
};
foreach ([
  "APP_ENV", "APP_DEBUG", "APP_URL", "APP_LOCALE",
  "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME",
] as $k) {
  $v = getenv($k);
  if ($v !== false && $v !== "") { $set($s, $k, $v); }
}
$pw = getenv("DB_PASSWORD");
if ($pw !== false) {
  $set($s, "DB_PASSWORD", "\"".addcslashes($pw, "\\")."\"");
}
file_put_contents($f, $s);
'

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "==> composer dump-autoload (production, optimized)"
composer dump-autoload --no-dev --optimize

if command -v npm >/dev/null; then
  echo "==> npm ci + build"
  npm ci --no-audit --no-fund
  npm run build
else
  echo "aviso: npm não encontrado; pule o build. Copie public/build/ manualmente." >&2
fi

php artisan migrate --force

if ! php artisan storage:link --quiet 2>/dev/null; then
  echo "aviso: storage:link falhou (talvez já exista ou sem permissão de symlink)." >&2
fi

php artisan optimize:clear >/dev/null

echo
echo "==> Infra pronta em ${DIR} (${VERSION})."
echo "    1) Configure o servidor web com document-root ${DIR}/public."
echo "    2) Rode o instalador (cria admin + settings + lock):"
echo
cat <<CMD
    cd ${DIR} && php artisan cms:install \\
      --db-host=${DB_HOST} --db-port=${DB_PORT} --db-database=${DB_DATABASE} \\
      --db-username=${DB_USERNAME} --db-password='***' \\
      --site-name='Meu Site' \\
      --admin-name='Administrador' --admin-email=admin@example.com --admin-password='TROQUE-AGORA' \\
      --lang=${APP_LOCALE}
CMD

echo
echo "    3) Depois, otimize para produção:"
echo
echo "    cd ${DIR} && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache"
echo
echo "    4) Adicione o cron:  * * * * * cd ${DIR} && php artisan schedule:run >> /dev/null 2>&1"

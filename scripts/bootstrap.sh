#!/usr/bin/env bash
# ESTORA — idempotent local bootstrap. Run from the repository root.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
  echo "==> .env created from .env.example"
fi
set -a
# shellcheck disable=SC1091
source .env
set +a

WP_PORT="${WP_PORT:-8080}"
WP_SITE_URL="${WP_SITE_URL:-http://localhost:${WP_PORT}}"

echo "==> starting db + wordpress"
docker compose up -d db wordpress

echo "==> waiting for MySQL"
for i in $(seq 1 60); do
  if docker compose exec -T db mysqladmin ping -h 127.0.0.1 -u root -p"${MYSQL_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
  [ "$i" = "60" ] && { echo "MySQL did not become ready"; exit 1; }
done

echo "==> waiting for WordPress files"
for i in $(seq 1 90); do
  if docker compose exec -T wordpress test -f /var/www/html/wp-config.php >/dev/null 2>&1; then
    break
  fi
  sleep 2
  [ "$i" = "90" ] && { echo "WordPress did not produce wp-config.php"; exit 1; }
done

wp() { docker compose run --rm -T cli "$@"; }

echo "==> development constants"
if [ "$(wp config get WP_ENVIRONMENT_TYPE 2>/dev/null || true)" != "local" ]; then
  wp config set WP_ENVIRONMENT_TYPE local --type=constant
fi
if [ "$(wp config get FS_METHOD 2>/dev/null || true)" != "direct" ]; then
  wp config set FS_METHOD direct --type=constant
fi

echo "==> installing WordPress (if needed)"
if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="$WP_SITE_URL" \
    --title="${WP_SITE_TITLE:-ESTORA}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
fi
wp option update home "$WP_SITE_URL" >/dev/null
wp option update siteurl "$WP_SITE_URL" >/dev/null

echo "==> activating theme and plugin"
wp theme activate estora-theme
wp plugin activate estora-core
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

echo "==> database migrations"
wp estora migrate --yes

if [ "${ESTORA_DEMO:-1}" = "1" ]; then
  echo "==> demo seed"
  wp estora seed
else
  echo "==> demo seed skipped (ESTORA_DEMO=${ESTORA_DEMO:-0})"
fi

wp cache flush >/dev/null || true

echo
echo "ESTORA is available at ${WP_SITE_URL}"
echo "  admin:      ${WP_SITE_URL}/wp-admin/  (${WP_ADMIN_USER})"
echo "  phpMyAdmin: http://localhost:${PMA_PORT:-8081} (profile tools)"
echo "  staff:      manager@estora.test / agent@estora.test / property@estora.test / marketing@estora.test"
echo "  staff password: ${ESTORA_TEST_AGENT_PASSWORD:-Estora!Agent!2026}"

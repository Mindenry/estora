#!/usr/bin/env bash
# ESTORA — one-command setup for a single VM (Oracle Cloud Always Free or any
# Ubuntu host with Docker). Run from this directory after editing .env.
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env. Edit DOMAIN, passwords and WP_ADMIN_* then run this script again."
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

: "${DOMAIN:?Set DOMAIN in .env}"
: "${MYSQL_PASSWORD:?Set MYSQL_PASSWORD in .env}"
: "${MYSQL_ROOT_PASSWORD:?Set MYSQL_ROOT_PASSWORD in .env}"
: "${WP_ADMIN_PASSWORD:?Set WP_ADMIN_PASSWORD in .env}"

wp() { docker compose --profile tools run --rm -T cli "$@"; }

echo "==> starting database and WordPress"
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
  [ "$i" = "90" ] && { echo "WordPress did not initialise"; exit 1; }
done

echo "==> configuring WordPress"
if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="https://${DOMAIN}" \
    --title="ESTORA" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" \
    --skip-email
fi

wp option update home "https://${DOMAIN}" >/dev/null
wp option update siteurl "https://${DOMAIN}" >/dev/null
wp theme activate estora-theme
wp plugin activate estora-core
wp rewrite structure '/%postname%/' --hard
wp estora migrate --yes
if [ "${ESTORA_DEMO:-1}" = "1" ]; then
  wp estora seed || true
fi
wp cache flush >/dev/null || true

echo "==> starting Caddy (automatic HTTPS)"
docker compose up -d caddy

echo
echo "ESTORA is configured for https://${DOMAIN}"
echo "  public:     https://${DOMAIN}/"
echo "  Thai:       https://${DOMAIN}/?lang=th"
echo "  back office:https://${DOMAIN}/estora-admin/"
echo "  admin:      https://${DOMAIN}/wp-admin/"
echo
echo "DNS must already point ${DOMAIN} at this server, and ports 80/443 must be open."

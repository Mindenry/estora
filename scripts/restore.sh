#!/usr/bin/env bash
# ESTORA — restore a dump produced by scripts/backup.sh.
# Usage: scripts/restore.sh backups/estora-20261124-120000.sql.gz [uploads.tar.gz]
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && { set -a; source .env; set +a; }

DUMP="${1:-}"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
  echo "Usage: scripts/restore.sh backups/<dump>.sql.gz [uploads.tar.gz]"
  exit 1
fi

read -r -p "This replaces the '${MYSQL_DATABASE:-estora}' database. Type RESTORE to continue: " CONFIRM
[ "$CONFIRM" = "RESTORE" ] || { echo "Aborted"; exit 1; }

echo "==> restoring database"
gunzip -c "$DUMP" | docker compose exec -T db mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE:-estora}"

if [ -n "${2:-}" ] && [ -f "${2}" ]; then
  echo "==> restoring uploads"
  docker compose run --rm -T --entrypoint sh cli -c \
    'cd /var/www/html/wp-content && tar xzf -' < "$2"
fi

echo "==> running migrations to bring the schema forward"
docker compose run --rm -T cli wp estora migrate --yes
echo "Restore complete."

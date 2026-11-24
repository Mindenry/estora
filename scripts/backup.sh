#!/usr/bin/env bash
# ESTORA — database + uploads backup. Usage: scripts/backup.sh
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f .env ] && { set -a; source .env; set +a; }

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="backups/estora-${STAMP}.sql.gz"
mkdir -p backups

echo "==> dumping database to ${OUT}"
docker compose exec -T db mysqldump \
  --single-transaction --routines --triggers --quick \
  -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE:-estora}" | gzip > "$OUT"

echo "==> archiving uploads"
docker compose run --rm -T --entrypoint sh cli -c \
  'cd /var/www/html/wp-content && tar czf - uploads 2>/dev/null' > "backups/estora-${STAMP}-uploads.tar.gz"

echo "Backup complete:"
ls -lh "backups/estora-${STAMP}"*

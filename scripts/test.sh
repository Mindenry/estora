#!/usr/bin/env bash
# ESTORA — run every automated check against the Docker stack.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  cp .env.example .env
fi
set -a
# shellcheck disable=SC1091
source .env
set +a
WP_PORT="${WP_PORT:-8080}"
export ESTORA_BASE_URL="${ESTORA_BASE_URL:-http://localhost:${WP_PORT}}"

wp() { docker compose run --rm -T cli "$@"; }

echo "==> ensuring the stack is up"
docker compose up -d db wordpress >/dev/null
wp estora migrate --yes >/dev/null

echo "==> PHP syntax + coding standards"
bash scripts/lint.sh

echo "==> PHPUnit (dependency-free domain tests)"
docker compose run --rm -T cli php /opt/estora/vendor/bin/phpunit -c /opt/estora/phpunit.xml --colors=never

echo "==> WordPress integration"
wp eval-file /opt/estora/tests/wp/integration.php

echo "==> HTTP integration"
node --test tests/http/

echo "==> removing QA fixtures before browser tests"
wp eval-file /opt/estora/tests/wp/cleanup.php

echo "==> Playwright E2E"
if [ "${SKIP_E2E:-0}" != "1" ]; then
  npx playwright test -c tests/e2e/playwright.config.js
else
  echo "    skipped (SKIP_E2E=1)"
fi

echo "==> final QA cleanup"
wp eval-file /opt/estora/tests/wp/cleanup.php

echo
echo "All checks passed."

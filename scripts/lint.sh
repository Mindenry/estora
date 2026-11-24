#!/usr/bin/env bash
# ESTORA — syntax check every project PHP file, then PHPCS when installed.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose run --rm -T --entrypoint sh cli /opt/estora-scripts/lint-inner.sh

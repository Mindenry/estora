#!/usr/bin/env bash
# ESTORA — remove QA fixtures. Safe to run before demos or screenshots.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose run --rm -T cli eval-file /opt/estora/tests/wp/cleanup.php

# ESTORA — syntax + coding standards (Windows host; requires Docker).
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose run --rm -T --entrypoint sh cli /opt/estora-scripts/lint-inner.sh
if ($LASTEXITCODE -ne 0) { throw "Lint failed" }

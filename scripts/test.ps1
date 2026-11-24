# ESTORA — run all automated checks (Windows host; requires Docker + Node).
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
Get-Content ".env" | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
        [Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim(), "Process")
    }
}
$env:ESTORA_BASE_URL = "http://localhost:8080"

function Invoke-Checked {
    param([string]$Label, [scriptblock]$Command)
    Write-Host "==> $Label"
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label failed" }
}

Invoke-Checked "stack up" { docker compose up -d db wordpress }
Invoke-Checked "migrations" { docker compose run --rm -T cli estora migrate --yes }
Invoke-Checked "syntax + standards" { & "$PSScriptRoot\lint.ps1" }
Invoke-Checked "PHPUnit" { docker compose run --rm -T --entrypoint php cli /opt/estora/vendor/bin/phpunit -c /opt/estora/phpunit.xml --colors=never }
Invoke-Checked "WordPress integration" { docker compose run --rm -T cli eval-file /opt/estora/tests/wp/integration.php }
Invoke-Checked "HTTP integration" { node --test tests/http/ }
Invoke-Checked "QA cleanup" { docker compose run --rm -T cli eval-file /opt/estora/tests/wp/cleanup.php }

if ($env:SKIP_E2E -ne "1") {
    Invoke-Checked "Playwright E2E" { npx playwright test -c tests/e2e/playwright.config.js }
    Invoke-Checked "final cleanup" { docker compose run --rm -T cli eval-file /opt/estora/tests/wp/cleanup.php }
}

Write-Host "All checks passed."

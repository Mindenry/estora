# ESTORA — Windows bootstrap. Requires Docker Desktop and PowerShell 5+.
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "==> .env created from .env.example"
}

Get-Content ".env" | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
        [Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim(), "Process")
    }
}

$WpPort = if ($env:WP_PORT) { $env:WP_PORT } else { "8080" }
$SiteUrl = if ($env:WP_SITE_URL) { $env:WP_SITE_URL } else { "http://localhost:$WpPort" }

function Invoke-Wp {
    param([Parameter(ValueFromRemainingArguments = $true)] $WpArgs)
    & docker compose run --rm -T cli @WpArgs
    if ($LASTEXITCODE -ne 0) { throw "wp $($WpArgs -join ' ') failed" }
}

Write-Host "==> starting db + wordpress"
docker compose up -d db wordpress
if ($LASTEXITCODE -ne 0) { throw "docker compose up failed" }

Write-Host "==> waiting for MySQL"
for ($i = 0; $i -lt 60; $i++) {
    & docker compose exec -T db mysqladmin ping -h 127.0.0.1 -u root "-p$($env:MYSQL_ROOT_PASSWORD)" --silent 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 2
}

Write-Host "==> waiting for WordPress files"
for ($i = 0; $i -lt 90; $i++) {
    & docker compose exec -T wordpress test -f /var/www/html/wp-config.php 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 2
}

$envType = (& docker compose run --rm -T cli config get WP_ENVIRONMENT_TYPE 2>$null)
if ($envType -ne "local") { Invoke-Wp config set WP_ENVIRONMENT_TYPE local --type=constant }

$fsMethod = (& docker compose run --rm -T cli config get FS_METHOD 2>$null)
if ($fsMethod -ne "direct") { Invoke-Wp config set FS_METHOD direct --type=constant }

& docker compose run --rm -T cli core is-installed 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) {
    Invoke-Wp core install --url="$SiteUrl" --title="$($env:WP_SITE_TITLE)" --admin_user="$($env:WP_ADMIN_USER)" --admin_password="$($env:WP_ADMIN_PASSWORD)" --admin_email="$($env:WP_ADMIN_EMAIL)" --skip-email
}

Invoke-Wp option update home "$SiteUrl"
Invoke-Wp option update siteurl "$SiteUrl"
Invoke-Wp theme activate estora-theme
Invoke-Wp plugin activate estora-core
Invoke-Wp rewrite structure "/%postname%/" --hard
Invoke-Wp rewrite flush --hard
Invoke-Wp estora migrate --yes

if ($env:ESTORA_DEMO -ne "0") {
    Invoke-Wp estora seed
}

Write-Host ""
Write-Host "ESTORA is available at $SiteUrl"
Write-Host "  admin:      $SiteUrl/wp-admin/"
Write-Host "  staff:      manager@estora.test / agent@estora.test"

# ESTORA — Deployment Guide

## 1. Prerequisites

- Docker Desktop / Docker Engine 24+ with Compose v2 (host needs no PHP or MySQL)
- Node.js 20+ (tests, screenshots only)
- Ports 8080 (site), 8081 (phpMyAdmin), 3307 (MySQL) free

## 2. First run

```bash
git clone <repo> estora && cd estora
cp .env.example .env            # edit passwords if you like
docker compose up -d db wordpress
bash scripts/bootstrap.sh
```

`bootstrap.sh` waits for MySQL, installs WordPress (if absent), activates the theme and plugin, runs
migrations, sets permalinks, seeds demo content and prints the URLs and demo accounts.
Open <http://localhost:8080>. Staff sign in at `/wp-login.php` and land on the ESTORA back office at
<http://localhost:8080/estora-admin/>. Super Admin keeps technical WordPress administration at
<http://localhost:8080/wp-admin/>.

If you deploy over an existing install, load any page once (or run
`docker compose run --rm cli wp rewrite flush`) so the `/estora-admin/` rules are written. Business
staff are redirected away from `/wp-admin/` automatically; Super Admins are not.

The public site is bilingual. Thai browsers are detected automatically; `?lang=th` / `?lang=en`
switches immediately and stores the choice for 30 days. Demo photographs are imported into the
media library by `wp estora seed --force` and credited in the footer; production installs should
replace them with the client's own photography through the normal media editor.

Idempotent re-run: `bash scripts/bootstrap.sh` (Windows: `scripts\bootstrap.ps1`) — it skips existing installs, re-runs pending
migrations, and does not duplicate seeds.

## 3. Environment variables (`.env`)

| Key | Meaning |
| --- | --- |
| `WP_PORT` | host port for the site (8080) |
| `PMA_PORT` | host port for phpMyAdmin (8081) |
| `DB_PORT` | host port for MySQL (3307) |
| `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` | database |
| `MYSQL_ROOT_PASSWORD` | root password |
| `WP_TABLE_PREFIX` | default `wp_` |
| `WP_DEBUG` | `1` in development, `0` in production |
| `WP_SITE_URL` | `http://localhost:8080` (must match the served URL) |
| `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` / `WP_ADMIN_EMAIL` | bootstrap administrator |
| `ESTORA_DEMO` | `1` seeds demo data, `0` production-clean |
| `ESTORA_TEST_AGENT_PASSWORD` | password for seeded agent accounts + HTTP tests |

Never commit `.env`. The compose file reads it; WordPress also writes a generated `wp-config.php`
on first boot, which is normalised by `scripts/bootstrap.sh` (table prefix, debug flags, salts).

## 4. Everyday commands

```bash
docker compose up -d                 # start
docker compose stop                  # stop
docker compose logs -f wordpress     # logs
docker compose run --rm cli wp plugin list
docker compose run --rm cli wp estora migrate --yes
docker compose run --rm cli wp estora seed --force
docker compose run --rm cli wp estora purge-inquiries --older-than=365
docker compose down                  # stop + remove containers (volumes retained)
docker compose down -v               # DESTROY database + uploads volume
```

## 5. Tests

```bash
bash scripts/test.sh
# or
docker compose run --rm -T --entrypoint php cli /opt/estora/vendor/bin/phpunit -c /opt/estora/phpunit.xml
docker compose run --rm cli wp eval-file tests/wp/integration.php
node --test tests/http/
npx playwright test -c tests/e2e/playwright.config.js
```

## 6. Backups and restore

```bash
bash scripts/backup.sh                 # writes backups/estora-YYYYmmdd-HHMMSS.sql.gz + uploads tar
bash scripts/restore.sh backups/estora-20261124-120000.sql.gz
```

Backups run `mysqldump --single-transaction --routines --triggers`; restore drops and recreates the
schema from the dump. Both scripts require an explicit path and refuse to run with an empty
argument. Schedule via host cron; verify by restoring into a scratch database.

## 7. Production checklist

1. `WP_DEBUG=false`, `DISALLOW_FILE_EDIT=true`, unique salts, `FORCE_SSL_ADMIN=true`.
2. Serve over HTTPS with a real certificate; set `WP_SITE_URL`/`WP_HOME` to the HTTPS origin.
3. Dedicated MySQL user (SELECT/INSERT/UPDATE/DELETE/ALTER on the one schema), daily backups.
4. Object cache (Redis) optional; ESTORA invalidates its own group on writes.
5. Mail: install an SMTP plugin or configure the host MTA; watch `uploads/estora-mail.log` for failures.
6. Run `wp estora migrate --yes` on deploy; keep the previous release artefact for rollback.
7. `ESTORA_DEMO=0` — never seed demo leads in production.
8. Uploads directory: no PHP execution, `chmod 755` dirs / `644` files, web server owns writes.
9. Remove the `pma` service from the production compose file.
10. Verify the checklist in `docs/ACCEPTANCE_CRITERIA.md` against the deployed instance.

## 8. Release procedure

```bash
git tag -a v1.0.0 -m "ESTORA 1.0.0"
docker compose run --rm cli wp estora migrate --yes
docker compose run --rm cli wp cache flush
```

Artefacts: `wp-content/themes/estora-theme`, `wp-content/plugins/estora-core`, `docs/`,
`README.md`. Release notes in `docs/RELEASE_NOTES.md`.

## 9. Demo accounts (seeded, development only)

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@estora.test` | value of `WP_ADMIN_PASSWORD` |
| Sales manager | `manager@estora.test` | `ESTORA_TEST_AGENT_PASSWORD` |
| Sales agent | `agent@estora.test` | `ESTORA_TEST_AGENT_PASSWORD` |
| Property manager | `property@estora.test` | `ESTORA_TEST_AGENT_PASSWORD` |
| Marketing | `marketing@estora.test` | `ESTORA_TEST_AGENT_PASSWORD` |

These accounts are created only when `ESTORA_DEMO=1`. Passwords exist in `.env`, never in git.

## 10. Troubleshooting

| Symptom | Check |
| --- | --- |
| 502 / blank site | `docker compose logs wordpress`; PHP fatal shown when `WP_DEBUG=1` |
| "Error establishing a database connection" | `db` health, credentials in `.env` vs `wp-config.php` |
| 404 on properties | permalink structure; `wp rewrite flush --hard` |
| REST 403 with valid login | stale `wp_rest` nonce; hard reload |
| No styles | file permissions on `wp-content`; theme asset version cache |
| Seed refused | database already has demo content; use `--force` knowingly |

## 11. Regenerating screenshots

```bash
node scripts/rasterize-art.mjs   # only when the SVG artboards change
node scripts/screenshots.mjs     # writes docs/screenshots (desktop + mobile)
```

## 12. CI

`.github/workflows/ci.yml` installs Composer tooling, boots the same Compose stack, runs lint,
PHPUnit, the WordPress integration script, the HTTP suite and Playwright. It has not been executed
on GitHub from the development machine (no remote configured); the commands are identical to the
locally verified ones.

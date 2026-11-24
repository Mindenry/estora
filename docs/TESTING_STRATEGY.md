# ESTORA — Testing Strategy

## 1. Layers

| Layer | Tool | Location | Boots WordPress? | DB? |
| --- | --- | --- | --- | --- |
| Unit | PHPUnit 10 | `tests/unit` | No | No |
| WP integration | `wp eval-file` + assertions | `tests/wp` | Yes | Yes |
| HTTP integration | Node `node:test` + fetch | `tests/http` | Via HTTP | Yes |
| E2E | Playwright | `tests/e2e` | Via browser | Yes |
| Static | `php -l`, PHPCS (WordPress standard), ESLint-free JS syntax check | repo | No | No |

Unit tests target dependency-free classes only (status machines, validators, formatters, slot
maths), which is why they run without WordPress. Everything with a database dependency is proven
against the real stack instead of mocked.

## 2. Commands

```bash
# stack
cp .env.example .env
docker compose up -d db wordpress
bash scripts/bootstrap.sh          # install, activate, migrate, seed
bash scripts/test.sh          # lint, unit, integration, HTTP, E2E, cleanup

# individually
docker compose run --rm cli php -l <file>            # syntax
docker compose run --rm -T --entrypoint php cli /opt/estora/vendor/bin/phpunit -c /opt/estora/phpunit.xml   # unit suite
bash scripts/lint.sh                                  # php -l over the repo + PHPCS if installed
docker compose run --rm cli wp eval-file tests/wp/integration.php
node --test tests/http/             # HTTP integration
npx playwright test -c tests/e2e/playwright.config.js   # E2E
node scripts/screenshots.mjs                        # capture docs/screenshots
```

`scripts/test.sh` fails on the first non-zero exit, removes QA fixtures between phases, and prints a summary. Windows: `scripts/test.ps1`. CI runs the same commands.

## 3. Unit cases (PHPUnit)

**Lead_Status**: every allowed transition; rejects skipping (`new→won`), rejects unknown status,
`lost` reachable from each active stage, `won` only from `negotiation`, label map complete.
**Appointment_Status**: allowed/blocked transitions; terminal states immutable; `active` predicate
(cancelled/rejected/no_show are inactive).
**Availability**: 45-minute grid generation between open/close; excludes lunch break; excludes
slots before "now" on today; closed Sunday; DST-safe date arithmetic.
**Validator**: required, email, phone normalisation (spaces, dashes, `+66`), numeric ranges, enum,
date in past, max length, consent boolean, honeypot.
**Formatter**: THB formatting, area `m²`, reference padding.
**Dedupe**: key stability across phone formats; different properties produce different keys.

## 4. WP integration cases (`tests/wp/integration.php`)

Asserts using real repositories and services, creating and deleting its own fixtures:
1. Schema: all 10 tables exist with expected columns and indexes.
2. Property create/publish → public query finds it; unpublish → gone; sort by price correct.
3. Lead create → counts increment; dedupe returns same ID within window; note updates last contact.
4. Status transitions persist and invalid transition throws `Validation_Exception`.
5. Assignment history row exists; assigning to a non-agent fails.
6. Agent scope: agent A query excludes B's leads; manager sees both.
7. Appointment: booking writes row; overlapping booking throws conflict; cancelled slot can be
   rebooked; `active_key` uniqueness proven by direct duplicate insert failing.
8. Favorites: guest token uniqueness; duplicate add is idempotent; delete works.
9. Notifications: dedupe key prevents second row; mark-read owned only.
10. Dashboard aggregates equal direct SQL counts.
11. Capability map: each role has/lacks the documented capability.
12. Cleanup removes fixtures.

## 5. HTTP integration cases (`tests/http`)

Runs against `http://localhost:8080` with seeded data and an application password created by the
bootstrap script (`wp user application-password create`).
- Auth: no credentials on `/leads` → 401; agent credentials on manager-only route → 403.
- Property CRUD round trip; invalid price → 400; publish → public GET; delete → 404.
- Search: filter by purpose/price/beds returns only matching; sort verified; pagination bounds.
- Lead lifecycle: create → assign → note → status ×4 → won; invalid `new→won` → 422.
- IDOR: agent A `GET /leads/{B}` → 404; `PATCH` B's appointment → 403/404.
- Appointments: public create → 201; same slot again → 409; status confirm → 200; reschedule → 200.
- Favorites: guest add twice → one row; list contains property; delete → gone.
- Inquiries: valid → 201 and lead exists; `<script>` payload stored sanitised; sold property → 409.
- CSRF: authenticated cookie request without nonce → 403.
- Rate limit: 6th rapid inquiry → 429.
- Dashboard totals match seeded counts; CSV export returns rows and requires auth.

## 6. E2E journeys (Playwright)

1. **Visitor search** → home → filter purpose/price → result count updates → open detail →
   breadcrumb and specs visible → favorite toggles → compare two properties → table renders.
2. **Inquiry** → property detail → fill form → success confirmation with reference → admin can see
   the lead (verified via API in the same test).
3. **Appointment** → choose date/time → submit → pending confirmation; admin confirms; slot no
   longer offered.
4. **Admin** → log in → dashboard KPI values numeric → leads list → open lead → add note → move
   status → kanban reflects it → appointments calendar shows confirmed viewing → reports CSV link
   present.
5. **Responsive** → home, archive, admin dashboard at 390px: no horizontal overflow, nav opens.
6. **Security smoke** → direct `/estora-admin/settings/` as agent → 403 shell; marketing opens
   `/estora-admin/leads/` → 403; API 401/403 assertions live in the HTTP suite.

## 7. Data management

- Seed data (`wp estora seed`) is namespaced (`EST-…`, `LD-…`, `@estora.test`) and idempotent per
  `--force`. Tests create their own records with a `test:` prefix and delete them in `finally`.
- Integration tests never run against production; guard checks `wp_get_environment_type()` and
  refuses to delete fixtures when `production`.
- E2E uses the seeded demo database; the bootstrap is re-runnable to restore it.

## 8. Quality gates

| Gate | Pass condition |
| --- | --- |
| Syntax | `php -l` clean for every `.php` file |
| Standards | PHPCS WordPress-Core/Docs warnings ≤ recorded baseline; no errors |
| Unit | all assertions pass |
| WP integration | all assertions pass, fixtures cleaned |
| HTTP | all tests pass |
| E2E | all configured journeys pass in Chromium |
| No known criticals | `docs/ACCEPTANCE_CRITERIA.md` security section checked against executed tests |

## 9. Reporting

Each phase report records the exact command, pass/fail/skip counts and the failing assertion text
when red. Results are copied into `docs/TEST_REPORT.md` at release. Claims about untested behaviour
are marked "not verified" rather than assumed.

## 10. Results for v1.0.0

| Suite | Result |
| --- | --- |
| PHP syntax | 90 files, 0 errors |
| PHPCS (errors only gate) | 0 errors |
| PHPUnit | 37 tests, 142 assertions |
| WordPress integration | 12 check groups |
| HTTP / REST | 15 tests |
| Playwright E2E | 11 journeys |
| Screenshots | 20 captures, no console errors |

Executed 2026-11-24. Full output summary in [TEST_REPORT.md](TEST_REPORT.md).

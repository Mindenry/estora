# ESTORA — Test Report

Date: 2026-11-24 · Environment: Docker Desktop on Windows · PHP 8.3.37 · WordPress 6.7.2 ·
MySQL 8.0.46 · Node 26 · Playwright 1.49 (Chromium).

All results below were produced by the commands shown, against the running Docker stack with the
seeded demo database. Nothing here is asserted from generated code alone.

## Summary

| Gate | Command | Result |
| --- | --- | --- |
| PHP syntax | `bash scripts/lint.sh` (inner script in cli container) | 90 files, 0 syntax errors |
| Coding standards | PHPCS WordPress standard (`--warning-severity=0`) | **0 errors** |
| Unit | `phpunit -c phpunit.xml` | **37 tests, 142 assertions, 0 failures** |
| WordPress integration | `wp eval-file tests/wp/integration.php` | **12 check groups passed** |
| HTTP / REST | `node --test tests/http/` | **15 tests passed** |
| Browser E2E | `npx playwright test -c tests/e2e/playwright.config.js` | **11 journeys passed** |
| Screenshots | `node scripts/screenshots.mjs` | 20 captures, 0 console errors |
| Theme independence | activate Twenty Twenty-Four → API check → reactivate | leads 200, properties 200, all tables present |

## Unit suite (PHPUnit)

`AvailabilityTest`, `AppointmentStatusTest`, `FormatterTest`, `LeadStatusTest`,
`PropertyStatusTest`, `ValidatorTest`.

Coverage: pipeline transitions (including invalid skips and terminal states), appointment
transitions and active-slot predicate, property availability rules and inquiry eligibility, slot
grid maths (45-minute grid, lunch exclusion, past slots, overlap boundaries), validation rules
(required, email, phone, enum, numeric bounds, dates, consent), formatting (THB, m², reference,
phone normalisation including the Thai trunk prefix, relative time).

```
OK (37 tests, 142 assertions)
```

## WordPress integration suite

Executed with a real WordPress bootstrap, admin user and MySQL. Creates and deletes its own rows.

1. Seeded staff accounts exist.
2. All 10 custom tables and key columns exist.
3. Capability matrix matches the specification (admin, manager, agent, property manager, marketing).
4. Property create/public query/numeric sort/availability/duplicate.
5. Lead capture, dedupe within 30 days, assignment history, notes, pipeline, agent scope.
6. Appointment slots, agent conflict, property conflict, unique `active_key`, confirm, reschedule, cancel.
7. Favorites are unique per owner and removable.
8. Notification dedupe and read state.
9. Audit rows written and readable.
10. Dashboard aggregates equal repository/SQL counts.
11. Settings present and valid.
12. Property deletion detaches leads and removes favorites.

```
Success: WordPress integration suite passed.
```

## HTTP / REST suite

Against `http://localhost:8080` with cookie sessions, REST nonces and real MySQL.

- health reports all tables
- public listing, filters, numeric sort, pagination, empty state
- property CRUD, publish gate, sold-property inquiry block, force delete
- lead lifecycle, assignment validation, IDOR scope (agent A cannot read agent B's lead), invalid transition
- appointment availability, public booking, 409 double booking, past date, appointment IDOR, confirm/reschedule/cancel, sold property block
- guest favorites idempotent, isolated per visitor token, delete
- inquiry → lead, duplicate merge, script-tag sanitisation, sold property 409, sixth request 429
- auth 401, missing nonce 401, agent cannot assign, marketing cannot read settings, SQL-injection payload returns empty results
- campaign CRUD, attribution, CSV export content type + rows, permission check
- settings read/write admin-only
- media: `.php` upload rejected with the guard message, 1×1 PNG accepted (201) and deleted
- dashboard aggregates match API totals
- deactivated staff cannot sign in (user meta toggled via WP-CLI, then restored)
- cleanup through the API

```
✔ ESTORA API (15 tests) — 15 passed
```

## Browser journeys (Playwright, Chromium)

1. Homepage → purpose filter → price sort → property detail (specs, gallery).
2. Favorite a property, see it on the favorites page; compare two; remove one from the table.
3. Submit an inquiry (gets `LD-` reference) and a viewing request (gets `AP-` reference).
4. About, contact, news, privacy, terms, 404 return < 500 with a visible `h1`; no page errors.
5. Admin dashboard renders KPIs, chart, notifications.
6. Lead table → drawer → add note → close → kanban.
7. Appointment month/week/day calendar + list.
8. Inventory, reports (export URL), settings, audit screens.
9. Mobile 390px: public pages and navigation without horizontal scroll.
10. Mobile admin dashboard without horizontal scroll.
11. Images have `alt`; visible form controls have labels; keyboard focus reaches an element.

```
11 passed (7.9s)
```

## Security checks executed

| Check | Result |
| --- | --- |
| Anonymous `/leads` | 401 |
| Cookie session without nonce | 401 (core cookie guard) |
| Agent calls assignment | 403 |
| Marketing reads settings | 403 |
| Manager writes settings | 403 |
| Agent reads another agent's lead | 404 |
| Agent updates another agent's appointment | 404 |
| SQL injection in property search | 200, no SQL error, empty result |
| Stored XSS in inquiry name/message | tags stripped before storage |
| `.php` upload | rejected with guard message |
| PNG upload | 201 |
| Inquiry rate limit | 429 after five requests from one contact |
| Deactivated login | refused with message; account restored afterwards |
| Audit | rows for property/lead/appointment/settings/user events |

## Known limitations / not verified here

- The GitHub Actions workflow is committed but has not been executed on GitHub from this
  environment (no remote configured). It runs the same commands listed above.
- Email delivery depends on the host mail transport; failure logging was exercised by design but no
  live SMTP server was configured.
- 100k-listing scale-out is documented but not benchmarked.
- Accessibility was checked with structural assertions and keyboard focus, not a full screen-reader
  audit.

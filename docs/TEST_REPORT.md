# ESTORA — Test Report

Date: 2026-11-25 · Environment: Docker Desktop on Windows · PHP 8.3.37 · WordPress 6.7.2 ·
MySQL 8.0.46 · Node 26 · Playwright 1.49 (Chromium).

All results below were produced by the commands shown, against the running Docker stack with the
seeded demo database. Nothing here is asserted from generated code alone.

## Summary

| Gate | Command | Result |
| --- | --- | --- |
| PHP syntax | `bash scripts/lint.sh` (inner script in cli container) | **101 files, 0 syntax errors** |
| Coding standards | PHPCS WordPress standard (`--warning-severity=0`) | **0 errors** |
| Unit | `phpunit -c phpunit.xml` | **37 tests, 142 assertions, 0 failures** |
| WordPress integration | `wp eval-file tests/wp/integration.php` | **12 check groups passed** |
| HTTP / REST | `node --test --test-concurrency=1 tests/http/api.test.mjs tests/http/admin-app.test.mjs` | **24 tests passed** (15 API + 9 app) |
| Browser E2E | `npx playwright test -c tests/e2e/playwright.config.js` | **19 journeys passed** |
| Screenshots | `node scripts/screenshots.mjs` | **43 captures**, app routes with 0 console errors |
| Static demo | `npm run export:static && npm run test:static` | **85 pages**, filters/favourites/compare/forms pass, 0 console errors |
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
3. Capability matrix matches the specification (super admin, administrator, manager, agent,
   property manager, marketing).
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

### API (15 tests)

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

### Back-office application (9 tests)

- every staff role lands on `/estora-admin/` after login; `/wp-admin/` redirects business staff
- navigation is permission aware for all six roles (modules present/absent as specified)
- direct URLs return 200/403/404 correctly; marketing cannot open leads, agents or settings; manager
  cannot open campaigns; property manager can open reports
- lead detail route renders the requested lead with its id
- dashboard endpoints respect role scope and date range; agent `/dashboard/me` returns own data;
  marketing receives no lead/appointment aggregates
- marketing can use WordPress posts/pages; agents cannot
- legacy `admin.php?page=estora-dashboard` redirects into the app
- logout clears the session and protects the app afterwards

```
✔ ESTORA API (15 tests) — 15 passed
✔ ESTORA back-office application (9 tests) — 9 passed
✔ tests 24 — pass 24, fail 0
```

## Browser journeys (Playwright, Chromium, 1 worker)

Public (7):

1. Homepage → purpose filter → price sort → property detail (specs, gallery).
2. Favorite a property, see it on the favorites page; compare two; remove one from the table.
3. Submit an inquiry (`LD-` reference) and a viewing request (`AP-` reference).
4. Select a province, confirm the area list narrows, select a district and verify chips + cards.
5. Sale listing shows a payment estimate that recalculates when the rate changes.
6. Thai and English switch persists across navigation; Thai archive and cards render Thai data.
7. About, contact, news, privacy, terms, 404 return < 500 with a visible `h1`; no page errors.

Back office (4):

8. Admin dashboard KPIs, charts, notifications.
9. Lead table → drawer → add note → close → kanban.
10. Appointment month/week/day calendar + list.
11. Inventory, reports (export URL), settings, audit screens.

Application (5):

12. Six role accounts each land on their own dashboard with the right role label and navigation.
13. Sidebar navigation to leads, global search with `q`, pipeline board, unknown route 404.
14. Staff viewing dialog: load properties, pick a slot, submit, dialog closes.
15. Dashboard period filter redraws data; "Add lead" quick action opens the lead dialog.
16. 390px viewport: no horizontal overflow, drawer opens/closes, profile menu logout returns to login.

Responsive/accessibility (3):

17. Mobile public pages and navigation without horizontal scroll, including a 320px pass.
18. Mobile admin dashboard without horizontal scroll.
19. Images have `alt`; visible form controls have labels; keyboard focus reaches an element.

```
19 passed (22.2s)
```

## Screenshot pass

`node scripts/screenshots.mjs` after a forced demo seed captured 43 images: public home (English
desktop/full/mobile and Thai desktop/full), properties (English/Thai), detail (English/full/Thai),
news (English/Thai/mobile), a single article, favorites, compare, about, login, six role dashboards,
overview via modules (properties, leads,
kanban, appointments, agents, locations, campaigns, content, reports, settings, users, activity,
notifications), lead detail, native property editor, mobile dashboard and mobile navigation.

Inspection findings fixed during these passes: empty chart fills, invisible button labels, a leaked
public skip link, duplicate notification markup, stale version labels, blank bands under listing
photography, dark-surface heading contrast, short-viewport hero collisions, and comparison-table
overflow. The v1.2.0 public pass added real photography, 21 listings across six Thai destinations,
a market pulse and an English/Thai language layer. The v1.3.0 pass added hierarchical
province/area filtering, scroll motion, an editorial News section with seven articles, a payment
estimator, the proprietor credit and a capped ultrawide hero. Layouts were checked at 1440, 1366,
1280, 1024, 768, 420, 390, 360, 320, 1920, 2000, 2560 and 3440px. App routes produced no console
errors; the only browser console output is WordPress core's `Permissions-Policy: unload` warning
inside the native post editor.

## Security checks executed

| Check | Result |
| --- | --- |
| Anonymous `/leads` | 401 |
| Anonymous `/estora-admin/` | 302 to login |
| Cookie session without nonce | 401 (core cookie guard) |
| Agent calls assignment | 403 |
| Marketing reads settings | 403 |
| Marketing opens `/estora-admin/leads/` | 403 |
| Marketing opens `/estora-admin/agents/` | 403 |
| Sales manager opens `/estora-admin/campaigns/` | 403 |
| Agent opens `/estora-admin/settings/` | 403 |
| Agent opens `/estora-admin/users/` | 403 |
| Agent reads another agent's lead | 404 |
| Agent updates another agent's appointment | 404 |
| SQL injection in property search | 200, no SQL error, empty result |
| Stored XSS in inquiry name/message | tags stripped before storage |
| `.php` upload | rejected with guard message |
| PNG upload | 201 |
| Inquiry rate limit | 429 after five requests from one contact |
| Deactivated login | refused with message; account restored afterwards |
| Logout | session cleared; app returns 302 to login |
| Audit | rows for property/lead/appointment/settings/user events |

## Known limitations / not verified here

- The GitHub Actions workflow is committed but has not been executed on GitHub from this
  environment (no remote configured). It runs the same commands listed above.
- Email delivery depends on the host mail transport; failure logging was exercised by design but no
  live SMTP server was configured.
- 100k-listing scale-out is documented but not benchmarked.
- Accessibility was checked with structural assertions, focus checks and visual review, not a full
  screen-reader audit.
- WordPress core emits a `Permissions-Policy: unload` warning in the native editor; it is not an
  ESTORA console error.

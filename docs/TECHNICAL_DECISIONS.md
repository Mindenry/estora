# ESTORA — Technical Decisions

Format: decision, alternatives considered, consequence. These are binding for v1.

## TD-01 Hybrid storage: CPT + custom tables

**Decision.** Properties are a `property` CPT with taxonomies and post meta. Pipeline, scheduling,
favorites, notifications, campaigns and audit live in custom tables.
**Alternatives.** All-custom tables (loses WP admin list tables, revisions, media, sitemap, SEO)
or all post meta (slow pipeline queries, no clean uniqueness or status history).
**Consequence.** Two ID spaces; referential integrity is service-enforced and documented.

## TD-02 Plugin owns everything business

**Decision.** No business logic in the theme; the theme only renders.
**Consequence.** Changing theme keeps CRM, API, roles and data. Theme functions are thin wrappers
over REST/public template tags.

## TD-03 Custom admin shell, not default WP styling

**Decision.** Top-level ESTORA menu with its own CSS/JS; CPT editor remains native for media.
**Reason.** The brief requires a business application feel; fighting core admin CSS is worse than
a scoped shell. Native screens stay for what core does well (media, users).

## TD-04 REST-first admin data

**Decision.** Admin screens render shells; data arrives from `/wp-json/estora/v1/*` with
`X-WP-Nonce`, so every read/write passes the same authorization and validation as external clients.
**Consequence.** One code path to test; no duplicated server logic in `admin-ajax.php`.

## TD-05 Capabilities, not role name checks

**Decision.** 17 `estora_*` capabilities mapped onto 5 roles. Ownership predicates (`agent_id ===
current_user_id` unless `estora_view_all_leads`) live in `Lead_Repository` scope arguments.
**Consequence.** Roles can be re-mapped by an administrator without code change.

## TD-06 Status machines are code, not free text

**Decision.** `Lead_Status`, `Appointment_Status`, `Property_Status` classes expose allowed
transitions and labels; services reject invalid transitions with 422.
**Consequence.** Kanban drops and API PATCH share the same guard.

## TD-07 SQL with `$wpdb->prepare`, one query per aggregate

**Decision.** Repositories build prepared SQL; dashboard uses 6 aggregate queries (properties by
status, leads by status/source/agent, upcoming appointments, conversion) cached 300s.
**Consequence.** No N+1; query counts asserted in integration tests.

## TD-08 Dedupe by normalised contact + property within 30 days

**Decision.** `dedupe_key = sha256(phone_digits|property_id)`; lookup window 30 days. A duplicate
returns existing lead with `meta.duplicate = true` and appends the new message as a note.
**Consequence.** Public retries do not inflate reporting; repeat interest is visible as notes.

## TD-09 Appointment collision prevention at the database

**Decision.** Slot grid of 45 min inside settings business hours. Conflict query runs inside a
transaction with `SELECT ... FOR UPDATE` over the agent's day; additionally a unique index on
`(agent_id, requested_date, start_time)` where status is active is approximated by a generated
`active_key` column (`agent_id:date:start` when active, NULL otherwise) with a UNIQUE index.
**Consequence.** Two simultaneous confirmations cannot both succeed; loser receives 409.
**Why not FK/constraint only.** Overlaps are ranges, not points; the range check is in SQL, the
unique key is the last line of defence against exact-slot races.

## TD-10 Nonce for public forms, not for nothing

**Decision.** Public forms fetch `wp_create_nonce('wp_rest')` and send `X-WP-Nonce`; plus honeypot
field and 5 requests / 10 min / IP transient. CSRF on public forms is low impact but nonces also
stop naive scripted spam.
**Consequence.** Cached pages must render a fresh nonce (the page is not full-page cached).

## TD-11 Email through `wp_mail`, failures logged

**Decision.** No SMTP library. `wp_mail` result checked; failure written to
`uploads/estora-mail.log` and admin notice. In-app notification is the source of truth.
**Consequence.** Demo environment shows real feedback rather than claiming delivery.

## TD-12 Bootstrap 5 for public, bespoke CSS for admin

**Decision.** Bootstrap 5.3 grid/components on the public site (vendored locally, no CDN), custom
design-system CSS for the admin shell (density and control that Bootstrap does not give).
**Consequence.** Two stylesheets, shared tokens file to keep colour/type identical.

## TD-13 Vanilla JS, progressive enhancement

**Decision.** ES modules, `fetch`, no bundler required. Forms work as normal POST fallback where
practical; JS adds async submission and filtering. Gallery, compare, kanban are JS components with
keyboard support.
**Consequence.** No build step for reviewers; assets are versioned by `filemtime`.

## TD-14 Docker-first, host-independent

**Decision.** PHP, WP-CLI, MySQL and phpMyAdmin in Compose; host needs only Docker.
**Consequence.** Same commands in CI (`docker compose run --rm cli ...`), no "works on my machine".

## TD-15 Tests at three levels

**Decision.**
1. PHPUnit, no WordPress: status machines, validators, formatters, availability maths.
2. WP-bootstrapped integration (`wp eval-file`): repositories, services, permissions, real DB.
3. HTTP/E2E: REST with application passwords (Node test runner), Playwright journeys.
**Consequence.** Fast feedback plus proof against the real stack; CI runs all three.

## TD-16 No fake imagery

**Decision.** Six architectural SVG artboards ship with the theme; a Playwright script rasterises
them to JPEG/PNG fixtures imported into the media library by the seeder. Missing images fall back
to the artboard for the property purpose.
**Consequence.** No hotlinks, no broken images, no unlicensed stock.

## TD-17 Currency, timezone, locale

**Decision.** THB, WordPress `timezone_string`, dates stored as `Y-m-d`/`H:i:s` site-local with
`created_at` UTC, rendered via `wp_date()`. Money as `DECIMAL(15,2)`.
**Consequence.** No floating point money; no off-by-one-day from timezone drift.

## TD-18 Logging

**Decision.** `wp_estora_activity_logs` for business events (actor, action, object, meta, hashed IP);
PHP warnings go to the container log. Passwords, tokens and full contact messages are never logged.
**Consequence.** Audit screen is real; no PII leakage into logs.

## TD-19 Pagination and limits

**Decision.** Default 20, max 100 per page. Public listing 9 per page. Exports stream in chunks of
500 rows via `fputcsv` to `php://output`.
**Consequence.** Bounded memory; no unbounded table reads.

## TD-20 Versioning

**Decision.** Plugin `Version:` header + `estora_db_version` option; migrations keyed by version and
idempotent. Release tag `v1.0.0`.
**Consequence.** Repeat activation safe; upgrades run pending migrations only.

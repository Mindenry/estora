# ESTORA — Development Roadmap

Single source of truth. `DONE` means implemented and verified with executed evidence recorded in
`docs/TEST_REPORT.md`. Verified: 2026-11-24.

## Phase 0 — Discovery & planning — DONE

- Environment inspected: Windows host, Node 26, Docker 29; no host PHP/MySQL, so all PHP runs in
  containers.
- Skills route: `impeccable` used for design direction and craft rules; no WordPress-specific skill
  is installed, so WordPress API work followed core documentation and PHPCS/WPCS.
- `PRODUCT.md`, `DESIGN.md`, requirements, architecture, decisions, schema, ER, API, security, UI,
  testing, deployment and acceptance criteria written before code.

## Phase 1 — Database & infrastructure — DONE

- Compose services (db, wordpress, cli, pma), `.env.example`, Windows + POSIX bootstrap.
- PSR-4 autoloader, 10 custom tables via dbDelta, same-domain foreign keys, migration option.
- WP-CLI commands: `migrate`, `status`, `seed`, `purge-inquiries`.
- Evidence: schema check in integration suite; health endpoint reports all tables.

## Phase 2 — WordPress core & theme — DONE

- Theme setup, menus, tokens, Bootstrap 5 vendored, 7 local architectural artboards rasterised to PNG.
- Home, archive with GET filters, detail, about, contact, news, legal, 404, favorites, compare.
- SEO head output and structured data (verified by fetching the rendered pages).
- Evidence: public route checks in HTTP + E2E suites.

## Phase 3 — Property management — DONE

- CPT, taxonomies, meta box, gallery media control, validation, status machines, repository/service,
  REST CRUD, duplicate, publish guard, admin inventory view.
- Evidence: property lifecycle HTTP test + integration checks.

## Phase 4 — CRM — DONE

- Lead repository/service, capture, dedupe, assignment history, notes, forced transitions, follow-ups,
  agent scope, kanban, analytics.
- Evidence: lead lifecycle HTTP test, IDOR check, integration pipeline test.

## Phase 5 — Appointments — DONE

- Slot grid, business hours/lunch, conflict checks, active-key uniqueness, confirm/reschedule/cancel,
  month/week/day calendar, availability API.
- Evidence: appointment HTTP + integration + E2E calendar tests.

## Phase 6 — Admin & RBAC — DONE

- Custom admin shell, dashboard aggregates, lead screens, appointments, inventory, reports + CSV,
  campaigns, settings, audit, notifications, agent profile fields.
- Evidence: capability matrix integration check, E2E admin journeys.

## Phase 7 — REST API — DONE

- All documented routes, permission callbacks, envelopes, pagination, error codes, nonce and
  Application Password support, public config/health.
- Evidence: 15-test HTTP suite.

## Phase 8 — Additional features — DONE

- Favorites (guest + user), comparison, notifications + mail failure log, campaigns/UTM, SEO,
  CSV exports.
- Evidence: HTTP favorites/inquiry/campaign/export tests; E2E favorite/compare.

## Phase 9 — Security & performance — DONE

- Caps/ownership/nonce/prepared statements/escaping/upload guard/rate limits/audit/login throttle.
- Dashboard transient cache with write invalidation, bounded pagination, mirrored price/area meta.
- Evidence: security section in TEST_REPORT, PHPCS 0 errors.

## Phase 10 — Quality assurance — DONE

- Lint, PHPCS, 37 unit tests, 12 integration groups, 15 HTTP tests, 11 E2E journeys; defects fixed
  and re-run (meta-cap collision, nonce user, status reset, empty availability).
- Evidence: `docs/TEST_REPORT.md`.

## Phase 11 — Deployment & release — DONE (CI remote run outstanding)

- Bootstrap/backup/restore/cleanup scripts, production checklist, demo accounts, 20 screenshots,
  README, release notes, local git tag `v1.0.0`.
- CI workflow committed; it has not run on GitHub from this machine (no remote).

## Phase 12 — Admin experience upgrade (v1.1.0) — DONE

- Root cause fixed: dedicated `/estora-admin/` application, login redirects, legacy URL redirects,
  role-aware shell and six role landing experiences.
- New Administrator role; Super Admin retains technical WordPress admin.
- Overview dashboard rebuilt on live data with period filters, charts, quick actions and
  capability-filtered summaries; new scoped `/dashboard/me`.
- Modules completed for properties, leads/detail, pipeline, appointments + staff booking, agents,
  locations, campaigns, content, reports, notifications, activity, users and settings.
- QA fixes: header action markup/binding, button contrast, marketing agents 403, duplicate
  notification block, logout target, bar fills, app skip link, agent role scoping.
- Evidence: 24 HTTP tests, 16 E2E journeys, 33 screenshots, `docs/ADMIN_EXPERIENCE.md`.

## Post-1.0 backlog

- Denormalised search index for 100k+ listings; Redis object cache; SMS channel; saved searches
  with alerts; multi-office availability; document/e-signature on deals.

## Progress log

| Date | Event | Evidence |
| --- | --- | --- |
| 2026-11-24 | Documentation foundation | `docs/*.md`, `PRODUCT.md`, `DESIGN.md` |
| 2026-11-24 | Docker stack up, WordPress 6.7.2 installed, theme/plugin active | compose ps, wp core version |
| 2026-11-24 | Schema + seed | health endpoint, `wp estora status` |
| 2026-11-24 | REST + CRM + appointments implemented and verified | HTTP suite 15 passed |
| 2026-11-24 | Theme/UI verified, screenshots captured | `docs/screenshots/`, E2E 11 passed |
| 2026-11-24 | Unit + integration + standards green | 37 unit / 12 integration / 0 PHPCS errors |
| 2026-11-24 | Theme-switch data check | leads 200, properties 200, tables true |
| 2026-11-24 | v1.1.0 back-office app, roles, dashboard and modules | `/estora-admin/`, 24 HTTP tests, 16 E2E, 33 screenshots |
| 2026-11-24 | v1.1.0 lint, unit, integration, app and browser gates | 99 files / 37 unit / 12 integration / 24 HTTP / 16 E2E |

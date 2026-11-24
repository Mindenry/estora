# Changelog

All notable changes to ESTORA. Format follows Keep a Changelog; versions follow Semantic
Versioning.

## [1.1.1] — 2026-11-24

### Changed

- Homepage art direction rebuilt around an editorial split hero: oversized serif headline with a
  single italic accent, a framed architectural visual, a live neighbourhood card and a compact
  proof strip.
- Property search promoted to a full-width, labelled panel overlapping the hero edge, with visible
  field affordances, popular-province shortcuts and a stronger primary action.
- Section system refined with numbered editorial headings, a dark province band, full-bleed
  location cards with real listing counts, balanced three-column listing grids and wider
  principles rows.
- Testimonials now scale the lead quote; the closing CTA uses a large statement and a circular
  action.
- Header alignment moved to a three-column grid (brand / navigation / actions); the tagline is hidden
  below 1320px to prevent crowding.

### Fixed

- Card and location images could render at intrinsic height instead of filling their frames,
  leaving a blank band under the artwork.
- Location titles and the closing CTA inherited the dark body text colour on dark surfaces.
- Hero content could collide with the overlapping search panel at 720–900px viewport heights.
- Screenshot capture now scrolls the page so lazy-loaded listing images are present in full-page
  shots.

## [1.1.0] — 2026-11-24

### Added

- Dedicated back-office application at `/estora-admin/` with rewrite routing, app shell, 403/404
  states and legacy wp-admin redirects.
- Role-aware login redirects and `/wp-admin/` redirect for business staff; Super Admin keeps the
  technical admin.
- `estora_administrator` ("Administrator") role with ESTORA modules, CMS content and user
  management, distinct from the WordPress Super Admin.
- Application shell: grouped permission-aware sidebar, collapsible rail, mobile drawer, breadcrumbs,
  global search, notifications panel, profile menu and branded login.
- Overview dashboard with live KPI cards, eight charts, period/custom-range filter, recent activity,
  next viewings and working quick actions.
- Modules for properties, leads, lead detail, pipeline, appointments (+ staff booking dialog),
  agents, locations, campaigns, content, reports, notifications, activity, users and settings.
- `/dashboard/me` scoped dashboard endpoint and range parameters on `/dashboard/summary`.
- App HTTP suite (9 tests) and Playwright app suite (5 journeys); responsive and role checks.
- `docs/ADMIN_EXPERIENCE.md` root-cause and implementation record; 34 updated screenshots.

### Changed

- Staff landing page is the ESTORA dashboard instead of the WordPress dashboard.
- Dashboard summaries are filtered by capability server-side; agents receive their own scope only.
- Agent lists (directory, assignment, settings, performance) are restricted to `estora_sales_agent`.
- Login screen uses the ESTORA design system.
- HTTP test commands use `--test-concurrency=1` in package scripts, CI and test runners.

### Fixed

- Header actions stripped by `wp_kses_post`; buttons outside the screen root not bound.
- Invisible primary-button labels caused by global link colour.
- Marketing role 403 on the agents bootstrap request.
- Duplicate hidden notification blocks in the content header.
- Logout returning staff to the public site.
- Empty bar-chart fills caused by inline spans.
- Public-theme skip link leaking into the app shell.
- Version strings and asset cache-busting now report `1.1.0`.

## [1.0.0] — 2026-11-24

### Added

- Public real-estate site: search, filters, sorting, pagination, property detail, favorites,
  3-way comparison, inquiry and viewing forms, articles, contact and legal pages.
- Property CPT with taxonomies, 25+ validated fields, gallery, availability/publication state
  machines, duplicate, publish guard and admin inventory.
- CRM: dedupe, assignment history, notes, pipeline transitions, follow-ups, agent scoping, kanban
  and SQL analytics.
- Appointment scheduling with slot grid, lunch exclusion, conflict checks, reschedule and unique
  active-slot index.
- REST API under `/wp-json/estora/v1`, notifications with dedupe/read state, campaigns/UTM,
  reports with CSV exports, audit log and settings.
- Five roles, seventeen capabilities, ownership checks, nonces, prepared statements, upload guard,
  rate limiting and login throttling.
- Docker stack, migrations, idempotent demo seed, backup/restore, CI workflow, PHPUnit/HTTP/E2E
  suites, documentation and screenshots.

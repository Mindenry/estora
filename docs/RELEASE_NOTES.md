# ESTORA — Release Notes

## v1.4.2 — Free WordPress hosting kit and GitHub Pages

Release date: 2026-11-25

- **Thai switch fixed on the static demo.** Language links now resolve to `/th/...` instead of the
  current page, and the smoke test clicks the switch in both directions.
- **Demo moved to GitHub Pages:** https://mindenry.github.io — free, permanent and without a
  hosting provider's injected source comment. `npm run deploy:pages` publishes the export.
- **Full WordPress on a free VM.** `deploy/wordpress-free/` contains a production Compose stack
  with WordPress, MySQL and Caddy (automatic HTTPS), an idempotent `setup.sh`, and a guide covering
  Oracle Cloud Always Free, free shared hosting and a temporary Cloudflare Tunnel.
- Netlify deployment configuration and references removed.

## v1.4.1 — Fresh-clone and CI fixes

Release date: 2026-11-25

A patch release after deploying the portfolio demo.

- Bootstrap CSS/JS are committed again; the ignore rule for Composer's `vendor/` also hid the
  theme's `assets/vendor/`, which broke the mobile header on a fresh clone.
- PHP deprecations are written to the log instead of the response. On a fresh install they were
  printed before `wp_safe_redirect()` and stopped wp-admin redirects from working.
- Theme activation no longer assumes ESTORA Core is already active.
- The dashboard date assertion and HTTP test command are midnight- and Node-22-safe.
- CI now covers lint, unit, integration, HTTP, 19 Playwright journeys and the static demo export.

## v1.4.0 — Financing calculator and a deployable portfolio demo

Release date: 2026-11-24

The public site becomes a complete portfolio piece: the payment estimate is now a real mortgage
calculator, and the whole public front end can be exported and deployed as a static demo.

- **Mortgage & purchase costs.** Sale listings open a calculator with price, down payment, sample
  rates, term and a 50/50 or 100% transfer-fee split. It outputs the monthly payment, loan amount,
  total interest, total repayment, transfer fee, stamp duty, cash needed at transfer and a yearly
  amortisation table. Everything recalculates live in English or Thai and is labelled an estimate,
  not a bank quote.
- **Static portfolio demo.** `npm run export:static` renders 85 pages (English and Thai), mirrors all
  assets and rewrites language paths. A small demo layer answers the REST calls so property search,
  province/area filtering, sorting, pagination, favourites, comparison and both forms still work
  without PHP or MySQL.
- **Honest demo chrome.** A discreet badge states that forms are simulated and links to the source.
- **API additions.** Property payloads now expose destination slugs (including ancestors) and the
  raw province/district used by the filters.
- **Performance.** WordPress emoji scripts are no longer printed on public pages.

## v1.3.0 — Motion, hierarchical search and an editorial journal

Release date: 2026-11-24

This release polishes the public site rather than changing its architecture. Data model, REST
routes and the back office are unchanged.

- **Location filter, properly hierarchical.** Province now drives Area. The lists are built from
  the province and district values actually stored on published listings, so Hua Hin correctly
  sits under Prachuap Khiri Khan and Pattaya under Chonburi. Picking a province narrows the area
  list; both appear as removable chips on the results page.
- **Motion with restraint.** Sections reveal on scroll with a short stagger, the proof figures
  count up, the hero photograph drifts slowly, and a two-pixel progress bar plus a back-to-top
  button follow the page. Every effect respects `prefers-reduced-motion`; with JavaScript off the
  content is simply visible.
- **News & Notes rebuilt.** A featured lead article, topic filters with live counts, a two-column
  editorial grid with real thumbnails and reading times, a sticky office/topic sidebar, and a
  dedicated article page with share controls and previous/next notes. Seven articles across five
  categories ship with the demo seed.
- **A working affordability estimator.** Sale listings now include a monthly-payment calculator
  (price, down payment, interest rate, term) that recalculates live in the selected language.
  It is clearly labelled as an estimate, not a quote.
- **Owner credit.** พีรวิชญ์ หล้าคำภา / Peerawit Lakhampa appears in the footer proprietor band, on
  the About page portrait and proprietor panel, in article bylines and in the theme/plugin headers.
- **About image fixed.** The photograph is now a full-bleed band; the previous version was pinned
  to the left edge by a global `figure { margin: 0 }` reset.
- **Ultrawide composition.** The hero is capped at 880px with centred copy, and the container grows
  to 1400px above 1800px and 1560px above 2400px instead of leaving a dead band on a 34-inch screen.
- **Contrast audit.** Small muted text was darkened; footer links, the office phone, dark-panel
  links and hero buttons were measured before and after hover — every combination tested is above
  9:1, and none turns white on white.
- **Screenshot set:** 43 images, adding News, a single article, Thai News and mobile News.

## v1.2.0 — Public site, real photography and Thai/English

Release date: 2026-11-24

The public site is rebuilt around real property photography, a new editorial design system and a
site-wide bilingual layer. Back-office routes and behaviour are unchanged.

- **New design system.** Forest/paper/brass palette, Fraunces display, Noto Serif Thai, IBM Plex
  Sans Thai, hairline rules and restrained 2–3px radii.
- **Photo-led home.** Full-bleed hero, labelled search panel, featured collection, six destination
  cards, live market pulse, working-method split, latest listings, testimonials, journal and a
  photo-backed closing CTA.
- **English / Thai switch.** Header, mobile navigation and footer. Choice persists for 30 days;
  browser Thai is detected automatically. UI, pages, properties, articles, testimonials,
  destinations, districts, amenities and staff titles are localised.
- **21 demo listings** across Bangkok, Chiang Mai, Phuket, Hua Hin, Pattaya and Khon Kaen, with
  Thai titles, descriptions, addresses and SEO metadata, plus real featured images and galleries.
- **Responsive rebuild.** Hero/search, destination grid, property grids, detail sidebar, forms,
  footer and compare table were checked from 1440 down to 320px.
- **Fixes.** Amenities no longer save as zero; footer/filter headings no longer inherit the huge
  body heading scale; comparison table scrolls inside its wrapper; mixed-language compare and
  favourite states are translated; contact duplicate copy and about-page character corruption
  removed.
- **Screenshot set:** 39 images, including Thai home, Thai archive and Thai detail.

## v1.1.1 — Homepage art direction

Release date: 2026-11-24

A focused patch to the public homepage. No database, REST or back-office behaviour changed.

- Split editorial hero: large serif headline with one italic accent, a framed architectural visual,
  a live “neighbourhood focus” card and a compact proof strip.
- Full-width property search panel overlapping the hero edge, with clear labels, visible select
  affordances, popular-province shortcuts and a stronger primary button.
- Numbered section system: selected listings, a dark province band with real listing counts,
  balanced three-column latest listings, wider “how we work” rows, editorial testimonials and a
  statement CTA.
- Header realigned to brand / navigation / actions; the tagline hides below 1320px.
- Fixed image frames that left blank bands under card and location artwork, dark-surface text
  contrast, and hero/search collisions at 720–900px viewport heights.
- Full-page screenshots now wait for lazy-loaded listing images.

## v1.1.0 — Back-office release

Release date: 2026-11-24

## Highlights

### A real back office, not a wp-admin skin

- **New entry point: `/estora-admin/`.** Staff now sign in and land directly on a dedicated,
  server-rendered ESTORA application. The default WordPress dashboard is no longer the staff
  experience.
- **Role-aware routing.** Route definitions own their capabilities; hidden modules return a styled
  403, unknown routes return a 404, and `/wp-admin/` redirects business staff to the app.
- **New `Administrator` role** (`estora_administrator`) for operations staff: every ESTORA module,
  CMS pages/posts/media, and user management, without plugin/theme/site-option access.
- **Super Admin remains Super Admin.** WordPress administrators keep the full technical admin,
  reachable from the app sidebar and profile menu.
- **Legacy URLs redirect.** `/wp-admin/admin.php?page=estora-*` maps to the matching app route.

### Application shell

- Grouped sidebar (Workspace / Growth / System), inline icons, active state, collapsible icon rail
  and an off-canvas mobile drawer with scrim and Escape handling.
- Top bar with breadcrumbs, global search, notifications panel, avatar, name, role label, profile
  menu and working logout.
- Branded login screen (grid paper, ESTORA wordmark, pine primary button, focus rings).
- Reusable panels, KPI cards, tables, forms, dialogs, pagination, skeletons, empty/error states and
  toasts. Mobile layouts are deliberate reflows, not scaled-down desktop.

### Dashboard

- Eight live KPI cards (properties, available, sold, rentals, leads, new leads, won, upcoming
  viewings) plus conversion and range strips, all calculated from the database.
- Trend and distribution charts: lead acquisition, inventory status, pipeline stages, listings by
  type, agent performance, viewings by weekday, lead sources and pipeline health.
- Period filter (7/30/90 days, this month, custom range) with server-side validation; refresh
  bypasses the five-minute cache.
- Recent activity and next viewings from real audit/appointment rows.
- Quick actions that open real screens: add property, add lead, schedule viewing, reports.

### Modules

Overview, Properties, Leads (+ detail), CRM Pipeline (kanban), Appointments (+ staff booking),
Agents, Locations, Campaigns, Content, Reports, Notifications, Activity log, User management and
Settings. Property media and long-form content stay in the native WordPress editor with a "Back to
ESTORA" notice. CSV exports stream the same rows the filters select.

## Fixes made during this pass

- Header action buttons were stripped by `wp_kses_post`; actions now render from trusted,
  source-escaped markup.
- Buttons outside `[data-screen]` were never bound; dashboard, lead and appointment actions are now
  wired through the screen's admin wrapper.
- Link styling made primary-button labels invisible; explicit button colours added.
- Marketing staff triggered an unconditional `/agents` 403 on load; agent data is fetched only when
  a control needs it.
- Removed duplicate hidden notification markup from the content header.
- Logout left staff on the public site; it now returns to the branded login with the app as the
  post-login target.
- Bar-chart fills rendered empty because inline spans had no box; track and fill are now block
  elements.
- The theme's public skip link leaked into the app; hidden in app context.
- Agent directory, assignable agents, settings agent list and agent dashboard now all use the
  `estora_sales_agent` role instead of "anyone with `estora_edit_leads`".

## Upgrade / install

1. Deploy the updated plugin/theme.
2. Visit any page once (or run `wp rewrite flush`) so the `/estora-admin/` rewrite rules register.
3. Run `wp estora migrate --yes` if your install predates v1.1.0. Capability version `1.1.0` is
   applied idempotently on load; run `wp estora seed --force` only on demo installs.
4. Sign in at `/wp-login.php`. Staff land on `/estora-admin/`; Super Admins can still open
   `/wp-admin/`.

Passwords and existing business data are unchanged. The database schema did not change in this
release (`ESTORA_DB_VERSION` remains `1.0.0`).

## Verification

- PHP syntax: 99 files; PHPCS: 0 errors.
- PHPUnit: 37 tests, 142 assertions.
- WordPress integration: 12 groups.
- HTTP/REST + application: 24 tests (15 API, 9 app).
- Playwright: 16 journeys (4 back office, 5 app, 4 public, 3 responsive).
- 34 screenshots regenerated from a forced demo seed and inspected, including six role dashboards,
  lead detail, appointment calendar, property editor and mobile drawer.

## Known limitations

- Seeded photography uses local architectural artboards; real media upload is implemented and
  tested.
- Email uses `wp_mail`; no SMTP provider is bundled. Failures are logged.
- Single office, single currency, no payments/SMS/MLS/native apps.
- The CI workflow is committed but has not run on GitHub from this environment (no remote).
- WordPress core emits a `Permissions-Policy: unload` console warning inside the native editor; it
  is not an ESTORA error.

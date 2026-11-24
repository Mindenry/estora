# Changelog

All notable changes to ESTORA. Format follows Keep a Changelog; versions follow Semantic
Versioning.

## [1.4.2] — 2026-11-25

### Added

- `deploy/wordpress-free/`: a production Docker Compose stack (WordPress, MySQL, Caddy with
  automatic HTTPS) and a one-command `setup.sh` for Oracle Cloud Always Free or any Ubuntu VM.
  Includes a deployment guide comparing Oracle, free shared hosting, Cloudflare Tunnel and the
  static demo.

### Changed

- The portfolio demo is hosted on **GitHub Pages** (`https://mindenry.github.io`) instead of a
  Netlify site. The export is the same; `deploy:pages` force-pushes `dist/` to the Pages repository.

### Fixed

- Thai/English switching on the static demo: language links now resolve to `/th/...` on English
  pages instead of pointing back at the current page.

## [1.4.1] — 2026-11-25

### Fixed

- Bootstrap CSS and JS are tracked again: the root `vendor/` ignore rule also matched
  `assets/vendor/`, so a fresh clone rendered the mobile header without its utility classes.
- PHP notices are logged instead of printed: `display_errors` was emitting deprecations before
  `wp_safe_redirect()` on a fresh install and breaking the wp-admin redirects.
- The theme tolerates ESTORA Core being inactive while it is being activated.
- The dashboard range assertion uses the site timezone, so the HTTP suite is midnight-safe.

### Changed

- CI installs dependencies inside the workspace, runs the HTTP files explicitly, and includes the
  static demo smoke test. Pushes to `main` are green.

## [1.4.0] — 2026-11-24

### Added

- Full mortgage and purchase-cost estimator on sale listings: price, down payment, interest rate
  with sample presets, term, transfer-fee split, monthly payment, loan amount, total interest,
  total repayment, transfer fee, stamp duty, cash needed at transfer and a collapsible yearly
  amortisation schedule.
- Static portfolio export for Netlify (`npm run export:static`): 85 rendered pages in English and
  Thai, mirrored CSS/JS/fonts/uploads, rewritten language paths and a demo layer that keeps
  search, province/area filters, sorting, pagination, favourites, comparison, the estimator and
  both forms usable without PHP or MySQL.
- `scripts/check-static.mjs` smoke test for the exported demo.
- `locations` (including ancestors) and `location_raw` in the public property payload, so a
  destination such as Hua Hin also matches its parent and filters compare raw meta values.

### Changed

- Comparison and favourites on the static demo are backed by `localStorage` rather than the REST
  database; inquiry and viewing requests return a simulated reference.
- WordPress emoji detection scripts removed from the public theme.

### Fixed

- Archive district/sort controls now update results after the debounce on the static demo.
- Static export preserves the viewport meta and removes prefetch speculation rules.

## [1.3.0] — 2026-11-24

### Added

- Hierarchical location filters: a **Province** select drives an **Area** select, both built from
  the province/district values of published listings. Active-filter chips show both.
- Motion system: reveal-on-scroll with stagger, animated proof counters, a slow hero image drift,
  a reading-progress bar, a back-to-top control and arrow micro-interactions. All of it is disabled
  under `prefers-reduced-motion`.
- News & Notes rebuilt: featured lead article, topic chips with real counts, editorial two-column
  grid, article thumbnails, reading time, a sticky sidebar and a dedicated article template with
  share, previous/next navigation and byline.
- Seven seeded journal articles across five categories, each with a featured image, Thai metadata
  and its own page.
- Working monthly-payment estimator on sale listings (price, down payment, rate and term, with live
  output in Thai or English).
- Owner credit: **พีรวิชญ์ หล้าคำภา / Peerawit Lakhampa** in the footer proprietor band, the About
  page portrait and proprietor panel, article bylines and the theme/plugin metadata.
- Large-screen composition: 1400px container above 1800px and 1560px above 2400px, larger type and
  taller destination cards.
- Article typography, share controls and prev/next styling.

### Changed

- Hero height is capped (`clamp(620px, 100svh − header, 880px)`) and its copy is vertically centred,
  so a 34-inch or ultrawide display no longer opens with a dead band.
- The About photograph is now a full-bleed band with a proprietor tag instead of a framed image.
- `--muted` darkened for small-text contrast; footer, language and dark-panel hover states are
  explicit so nothing fades into its background.
- Posts per page is 7 so the featured article plus a 3×2 grid fill the page.

### Fixed

- About image sat at the left edge because the global `figure { margin: 0 }` reset overrode the
  centred frame.
- Dark-panel headings were invisible (base heading colour won on specificity).
- The light hero button and search shortcuts turned white on white on hover.
- Comparison/lightbox micro-copy and the mobile language switcher no longer disappear at narrow
  widths.
- Search URLs no longer carry empty query parameters.

## [1.2.0] — 2026-11-24

### Added

- Full public-site redesign around real photography, local Fraunces / IBM Plex Sans Thai / Noto
  Serif Thai fonts and an editorial forest/paper/brass design system.
- English / Thai switcher in the header, mobile menu and footer, with cookie and browser-language
  detection.
- Bilingual UI strings, page/property/article metadata, destination and district names, staff
  titles, amenities and company details.
- 21 demo listings across Bangkok, Chiang Mai, Phuket, Hua Hin, Pattaya and Khon Kaen, with Thai
  titles, descriptions, addresses, SEO metadata and 14 neighbourhood terms.
- Real listing photography imported into the media library with featured images and multi-image
  galleries; Unsplash credited in the footer.
- New home sections: photo hero, search panel, collection, destination grid, market pulse, working
  method, latest listings, testimonials, journal and photo CTA.
- `docs/PUBLIC_SITE.md` design, bilingual and data record.

### Changed

- Public cards, archive, property detail, about, contact, news, favourites and compare pages now
  share the new type, spacing, colour and responsive system.
- Amenity meta now stores text; the registered meta sanitiser previously converted every amenity
  to integer zero.
- Language-aware REST property payloads, search options, filter chips and the JavaScript compare /
  favourite states.
- Version bumped to 1.2.0; screenshot set expanded to 39 images including Thai pages.

### Fixed

- Footer and filter headings inheriting the oversized body heading scale.
- Search / hero collisions on short desktop viewports.
- Comparison table overflow on mobile; compare remove-button text rendering.
- Mixed English strings in Thai compare, favourite and empty states.
- Contact page duplicated introduction and about-page character corruption.

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

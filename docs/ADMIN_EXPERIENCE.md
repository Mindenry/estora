# ESTORA — Back-office Experience (v1.1.0)

The v1.1.0 release replaces the "ESTORA pages inside wp-admin" experience with a dedicated,
server-rendered business application at **`/estora-admin/`**. WordPress remains the CMS and the
technical administration surface; it is no longer the staff landing page.

## 1. Root cause of the default WordPress admin experience

The v1.0.0 back office was implemented as WordPress admin pages registered with
`add_menu_page()` / `add_submenu_page()`. That produced three separate problems:

1. **No login redirect.** `wp-login.php` therefore sent every user to `/wp-admin/`, which is the
   WordPress dashboard — the screen the user reported. The ESTORA dashboard was one submenu item
   among WordPress menus.
2. **No application shell.** Each ESTORA page was wrapped in the WordPress admin chrome (admin bar,
   collapsible WP menu, footer). There was no ESTORA sidebar, top bar, breadcrumb, user menu or
   responsive drawer; navigation between modules went through `admin.php?page=estora-*`.
3. **No staff-oriented route layer.** Direct URLs were WordPress query strings, so deep links,
   refresh behaviour and 404s were governed by wp-admin rather than by the product.

Restyling wp-admin could not fix this: the landing page, the navigation model and the URL model
were all WordPress-owned. v1.1.0 adds the missing application layer instead.

## 2. What was built

### 2.1 Dedicated entry point and routing

| Item | Value |
| --- | --- |
| Staff entry point | `/estora-admin/` |
| Login | `/wp-login.php` (branded) |
| Lead detail | `/estora-admin/leads/{id}/` |
| Unknown route | HTTP 404 with the app shell and a "back to overview" action |
| Unauthorised route | HTTP 403 shell explaining the missing permission |
| Legacy pages | `/wp-admin/admin.php?page=estora-*` permanently redirect to the matching app route |

`Admin_App` registers rewrite rules (`estora_app`, `estora_id` query vars), flushes them once via
`estora_app_route_version`, disables canonical redirects on app routes, and dispatches before the
theme template. The app renders its own document, enqueues only the ESTORA admin assets, removes
the public theme styles and hides the WordPress toolbar. Refresh, browser back/forward and direct
URL access all work because every screen is a real route.

### 2.2 Login and redirects

- `login_redirect` sends any user with ESTORA access to `/estora-admin/`.
- A user without ESTORA access keeps the normal WordPress redirect (subscribers are unaffected).
- `/wp-admin/` index redirects business staff to the app. Super Admins (`manage_options`) keep
  `/wp-admin/` and can open it from the sidebar or the profile menu.
- Legacy ESTORA admin pages redirect server-side; there is no redirect loop because the app is not
  a wp-admin page.
- Logout returns to the branded login screen with `redirect_to` pointing back at the app.

### 2.3 Application shell

- Left sidebar grouped into **Workspace / Growth / System**, with inline SVG icons, active state and
  a brass rail marker. Desktop can collapse to an icon rail (persisted in `localStorage`).
- At ≤1180px the sidebar becomes an off-canvas drawer with a scrim, `aria-expanded`, Escape to close
  and automatic close after navigation.
- Top bar: breadcrumbs, page title in content, global search (leads for CRM roles, properties
  otherwise), notifications panel, avatar, name and role label, profile menu and logout.
- Content area: reusable panels, KPI cards, tables with horizontal overflow, forms, dialogs,
  pagination, skeletons, empty states, error states and toasts.

### 2.4 Role-aware navigation and dashboards

Navigation is generated from route capability definitions; hidden modules return 403 when requested
directly. No page is "secured" by hiding a link.

| Role | Landed experience | Modules |
| --- | --- | --- |
| Super Admin (`administrator`) | Company dashboard | All modules + WordPress admin link |
| Administrator (`estora_administrator`) | Company dashboard | All ESTORA modules, CMS and users; no technical WP menus |
| Property Manager | Company dashboard, property and viewing cards | Properties, Appointments, Agents, Locations, Reports, Notifications |
| Sales Manager | Company dashboard | Properties, Leads, Pipeline, Appointments, Agents, Locations, Reports, Notifications |
| Sales Agent | Personal dashboard (`/dashboard/me`) | Properties, own Leads, Pipeline, own Appointments, Locations, Notifications |
| Marketing Staff | Company dashboard, marketing cards | Properties, Locations, Campaigns, Content, Reports, Notifications |

Dashboard payloads are filtered server-side:

- `/dashboard/summary` requires `estora_view_reports`; lead aggregates and lead activity are removed
  without `estora_view_leads`, appointment aggregates without `estora_manage_appointments`.
- `/dashboard/me` returns only the caller's leads, assigned properties, next viewings and trend.
- Agent-facing agent lists are restricted to the `estora_sales_agent` role, so property managers and
  marketing staff do not appear as assignable agents.

### 2.5 Overview dashboard

All figures come from repositories/SQL at request time (30-day default cached for five minutes,
explicit refresh bypasses cache). The period control supports last 7/30/90 days, this month and a
custom range; the range is validated server-side and echoed in the range strip.

- KPI cards: properties, availability, sold, rentals, leads, new leads, won, upcoming viewings and
  conversion; each is a link into the relevant module.
- Charts: lead acquisition trend, inventory status, pipeline stages, listings by type, agent
  performance, upcoming viewings by weekday, lead sources and pipeline health.
- Recent activity and next viewings come from the audit and appointment repositories.
- Quick actions open real screens: native property editor, lead dialog, staff viewing dialog,
  reports (and campaigns for marketing).

### 2.6 Modules

Overview, Properties, Leads (+ detail), CRM Pipeline, Appointments, Agents, Locations, Campaigns,
Content, Reports, Notifications, Activity log, User management and Settings share one shell and one
design system. Properties keep the native WordPress editor for media, gallery and long-form content,
with a persistent "Back to ESTORA" notice; the app's inventory table remains the business view.

### 2.7 Fixes found during independent QA

- Header action buttons were stripped by `wp_kses_post` and buttons outside the screen root were
  never bound; both fixed, so period, refresh, new-lead, new-viewing and export controls work.
- `admin.css` coloured every link pine, making button labels invisible; explicit button colours added.
- The marketing role received an unconditional `/agents` 403 from a bootstrap agent fetch; the fetch
  now happens only where agents are used.
- Duplicate hidden notification blocks were removed from the content header; the top bar owns them.
- Mobile skip link from the public theme leaked into the app; hidden in app context.
- Bar-chart fills were inline spans with no box, so they rendered empty; track/fill are now block
  elements.
- Logout used `home_url()` and could leave the user on a public page; it now returns to login with
  the app as the post-login target.
- Agent directory, assignable agents, settings agent list and agent dashboard now share one role
  filter (`estora_sales_agent`) instead of any user holding `estora_edit_leads`.

## 3. Security model

- Every app route declares the capabilities it needs; `handle_request()` returns 403 before rendering.
- REST permissions are unchanged: capability callbacks plus ownership predicates. The app never
  bypasses them.
- Login redirect only applies to users with ESTORA capability; WordPress administrators keep full
  wp-admin access.
- `estora_administrator` receives ESTORA capabilities plus the CMS/user capabilities needed for the
  role; it does not receive `manage_options` or plugin/theme management.
- Content links render only when the user has the matching WordPress capability (`edit_pages`,
  `upload_files`, `manage_categories`, …). Unauthorised direct wp-admin URLs are still enforced by
  WordPress.

## 4. Design system

The app reuses the public design tokens (ink, pine, brass, sand, paper, display serif, UI sans) and
adds a compact productivity layer: 3–4px radii, 1px hairlines, restrained shadows, 38px controls,
12–13px table type and generous whitespace. No gradient backgrounds, glass cards or decorative
dashboard filler. Tables scroll horizontally instead of shrinking; KPI grids collapse 4 → 2 → 2
columns and quick actions 4 → 2 → 1.

## 5. Verification

Commands and results are recorded in [`TEST_REPORT.md`](TEST_REPORT.md). Relevant suites:

- `tests/http/admin-app.test.mjs` — 9 app tests (login redirects, nav visibility, 403/404, lead
  detail, dashboard scoping, CMS access, legacy redirects, logout).
- `tests/e2e/app.spec.js` — 5 journeys (six role dashboards, navigation/search/404, staff booking,
  period filter + quick action, mobile drawer/logout).
- Existing API, integration, unit and public E2E suites continue to pass.

Screenshots are in `docs/screenshots/`: `login.png`, `role-{admin,operations,manager,agent,property,marketing}.png`,
`app-overview.png`, `app-{properties,leads,kanban,appointments,agents,locations,campaigns,content,reports,settings,users,activity,notifications}.png`,
`app-lead-detail.png`, `app-property-editor.png`, `app-mobile-dashboard.png`, `app-mobile-nav.png`.

## 6. Limitations

- Email delivery still depends on `wp_mail`; the app surfaces failures rather than hiding them.
- WordPress core emits a `Permissions-Policy: unload` warning inside the native editor; it comes from
  WordPress, not ESTORA, and does not affect behaviour.
- The CI workflow is committed but has not run on GitHub from this machine (no remote configured).
- The app is server-rendered with progressive enhancement; there is no offline mode or service
  worker.

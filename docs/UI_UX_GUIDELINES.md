# ESTORA — UI / UX Guidelines

Design system source of truth: `/DESIGN.md`. This document covers application rules.

## 1. Layout

**Public.** Container 1240px, gutter `clamp(20px,4vw,48px)`. Vertical rhythm in multiples of 8;
section padding `clamp(56px,8vw,112px)`. Header: 72px, hairline bottom, transparent over hero,
solid on scroll (`.is-stuck`). Footer: charcoal, 4 columns, 1px dividers.

**Admin.** Fixed 248px rail + fluid content, max 1560px. Sticky page header with title and actions.
Content padding 28px. Tables full-width inside a bordered surface; forms max 760px unless a
two-column field grid is needed. No nested cards beyond one level.

## 2. Components

| Component | Rules |
| --- | --- |
| Button | primary pine, secondary outline ink, ghost, danger; 40px height, 8px padding-x, 3px radius; icon+label gap 8px; disabled 45% opacity |
| Input | label above (13px, 600), 40px, 1px `--line`, focus ring pine 3px 18% |
| Select | native select styled; no custom listbox without keyboard support |
| Checkbox / radio | 18px, accent pine, label clickable |
| Badge | uppercase 11px, tracked .06em, tinted bg, 3px radius; status→colour map in §5 |
| Table | 13px, head 11px uppercase muted, 44px rows, numeric right, row action menu |
| Tabs | underline active 2px pine, 44px targets, `role=tablist` |
| Drawer | right, 480px, for lead detail on wide screens; full-screen sheet under 768px |
| Toast | bottom-right desktop, top mobile, `aria-live=polite`, 4s auto-dismiss |
| Modal | only for destructive confirmation and gallery lightbox; focus trapped, Esc closes |
| KPI | eyebrow, serif 32px number, delta line, optional sparkline |
| Pagination | prev/next + page numbers, `aria-current="page"` |
| Property card | image 4:3, purpose chip top-left, favorite top-right, price serif 20px, title 17px, location muted 13px, spec row with hairline top |
| Empty state | 1 sentence + 1 action, 64px padding, no illustration |

## 3. Page inventory

Public: home, property archive (`/properties/`), property detail, about, contact, news archive,
article, favorites, compare, privacy, terms, 404, campaign landing (`/go/{slug}` template).

Admin: `/estora-admin/` application shell with `overview`, `properties`, `leads`, `leads/{id}`,
`pipeline`, `appointments`, `agents`, `locations`, `campaigns`, `content`, `reports`,
`notifications`, `activity`, `users`, `settings`. Property editing stays on the native CPT screen,
skinned with ESTORA meta panels; legacy `admin.php?page=estora-*` URLs redirect into the app.

## 4. Interaction rules

- Server is the source of truth. Optimistic UI only for the favorite toggle and kanban drag; both
  roll back visibly on failure with a toast.
- Filters on the public archive submit via GET (works without JS); JS adds live debounce (350ms)
  and partial refresh with `fetch`, preserving history via `pushState`.
- Kanban drop calls `POST /leads/{id}/status`; on 422 the card animates back and an inline message
  names the allowed next stages.
- Forms: validate on blur, submit disabled during request, field errors under the field, summary
  focus at top on failure, success replaces the form with a confirmation containing the reference.
- Appointment picker: date → available slots fetched; past/closed days disabled with a reason;
  timezone shown as "Asia/Bangkok (site time)".
- Destructive actions require a confirmation dialog naming the object.
- Loading skeletons for tables (5 rows) and property grids (3 cards); never a spinner-only page.

## 5. Status colour map

| Domain | Value | Badge |
| --- | --- | --- |
| Lead | new | pine-soft / pine |
| | contacted | sand / ink-2 |
| | qualified | `#E8EEF6` / `#2B4C7E` |
| | negotiation | `#F6EEDC` / `#8A5A12` |
| | won | `#E3F0E7` / ok |
| | lost | `#F3E4E1` / danger |
| Appointment | pending | warning tint |
| | confirmed | pine-soft |
| | completed | neutral |
| | cancelled / rejected / no_show | danger tint |
| Property availability | available | ok tint |
| | reserved | warning tint |
| | sold / rented | ink tint |
| | unavailable | muted |

## 6. Accessibility acceptance

- Keyboard: skip link, visible focus, all interactive elements reachable, gallery arrows, kanban
  move via menu fallback (drag is not the only path).
- Semantics: one `h1`; tables with `<th scope>`; forms with `label for`; buttons are `<button>`.
- Contrast: body `--ink-2` on `--paper` ≥ 7:1; muted meta ≥ 4.6:1; badge pairs ≥ 4.5:1.
- Images: meaningful `alt`, decorative `alt=""`, dimensions set to avoid CLS.
- Motion: 150–200ms ease-out for hovers; 240ms drawer; `prefers-reduced-motion` disables transforms.
- Announcements: async form results and filter counts in `aria-live="polite"` regions.

## 7. Responsive checks

375 (iPhone SE), 390, 768 (tablet), 1024, 1280, 1440. Back-office tables keep their columns and
scroll horizontally inside the panel; the sidebar becomes an overlay drawer at 1180px; KPI grids
collapse 4 → 2 → 2 and quick actions 4 → 2 → 1. No page-level horizontal scrolling at any width.

## 8. Copy rules

Sentence case, no exclamation marks, no marketing adjectives in the back office. Errors say what
happened and what to do ("That time is already booked. Choose another slot."). Empty states name
the next action. Numbers use thousands separators; currency `฿` with no decimals for whole baht.

## 9. Screenshot set (docs/screenshots)

`login.png`, `home.png`, `properties.png`, `property-detail.png`, `compare.png`,
`role-admin.png`, `role-operations.png`, `role-manager.png`, `role-agent.png`,
`role-property.png`, `role-marketing.png`, `app-overview.png`, `app-properties.png`,
`app-leads.png`, `app-kanban.png`, `app-appointments.png`, `app-agents.png`,
`app-reports.png`, `app-settings.png`, `app-lead-detail.png`, `app-property-editor.png`,
`app-mobile-dashboard.png`, `app-mobile-nav.png`.
Captured by `scripts/screenshots.mjs` at 1440×1000 and 390×844 (mobile variants suffixed `-mobile`).

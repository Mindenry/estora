# ESTORA — Design System

Two surfaces: **public site** (Persuade/Read) and **back office** (Operate). They share the ESTORA
brand — warm paper, forest, brass — but use separate token files: the public site now carries the
editorial palette below, while the back office keeps its compact operational tokens in
`wp-content/plugins/estora-core/assets/css/tokens.css`.

## Visual world

Editorial real-estate catalogue: warm paper, charcoal ink, deep pine accent, brass hairlines.
Photography and architecture carry the page; type and whitespace carry the hierarchy.
Hairline rules (1px, `--line`) instead of card shadows. Radius is small (2–3px) — printed
brochure, not SaaS dashboard.

## Public tokens (`wp-content/themes/estora-theme/assets/css/tokens.css`)

| Token | Value | Use |
| --- | --- | --- |
| `--paper` | `#F7F3E9` | page |
| `--surface` | `#FFFDF8` | cards, forms, admin panels |
| `--sand` | `#EAE2D2` | image plates, hover rows |
| `--ink` | `#17201B` | headings, primary text |
| `--ink-2` | `#414A43` | body |
| `--muted` | `#72796F` | meta, captions |
| `--line` | `#DDD4C2` | borders |
| `--pine` | `#1C4A3A` | primary action, links, active state |
| `--pine-deep` | `#0F3026` | dark bands, footer |
| `--pine-soft` | `#E4EEE7` | selected chips, soft badges |
| `--brass` | `#AF8B52` | premium detail, rules in section heads |
| `--brass-deep` | `#8A6938` | small labels on paper |
| `--danger` | `#9B3B32` | destructive, lost |
| `--warning` | `#8A5A12` | pending |
| `--ok` | `#2C6B4F` | won/confirmed |

Type: display `"Fraunces", "Noto Serif Thai", Georgia, serif`; UI `"IBM Plex Sans Thai", "Segoe UI",
system-ui, sans-serif`; numbers `font-variant-numeric: tabular-nums` in tables and prices. Fonts are
local WOFF2 files in `assets/fonts/`.

Scale: 10 / 11 / 12 / 13 / 14 / 16 / 18 / 22 / 28 / 36 / 52 / 68 px. Line-height 1.65 body, 1.08
display. Spacing: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128. Container `1280px`, gutter
`clamp(20px,4.4vw,56px)`.
Breakpoints: 360 / 420 / 720 / 960 / 1024 / 1180 / 1440.

## Public site

- Hero: full-bleed real photograph, forest gradient, serif headline, proof strip and a white
  search panel inside the first viewport.
- Sections: photograph-led collection, six destination cards on deep forest, live market pulse,
  split method block, latest listings, testimonials, journal and photo CTA.
- Cards: 4:3 photo, purpose chip top-left, favourite top-right, serif price, location, spec row,
  compare control. Hairline border, 3px radius, 3px hover lift.
- Section heads: index + brass rule + eyebrow + serif h2.
- Language: EN / TH in header, mobile menu and footer; persists via cookie.
- Footer: deep forest band, four columns, office details, credit line.

## Back office

- Left rail 248px charcoal-tinted (`#17181A`) with pine active bar; content on `#F6F3EC`.
- Page header: eyebrow (section), serif title 28px, actions right.
- Tables: 13px, sticky header, 44px rows, hairline rows, numeric right-aligned; row hover `--sand`.
- KPI cards: label uppercase 11px, value 32px serif, delta as text (`+12 vs prev.`), no icons-as-decoration.
- Charts: inline SVG, 2px pine line, sand fill, no gridline clutter; tooltips native `<title>`.
- Forms: label above, 40px control, 1px border, focus ring `0 0 0 3px rgba(47,93,80,.18)`.
- Badges: tinted background + dark text, 11px uppercase tracked, 3px radius.
- Empty states: one sentence + one action, never an illustration.

## States

Every list has loading (skeleton rows), empty (copy + action), error (message + retry). Every
mutation: disabled button while pending, inline field errors, toast on success, confirmation for
destructive actions, `aria-live` for async results.

## Motion (public)

- Reveal-on-scroll: opacity + 22px rise, 720ms, 70ms sibling stagger, only when
  IntersectionObserver is available and reduced motion is off. Scoped to `html.estora-motion` so a
  failed script never leaves content invisible.
- Figures count up once with `Intl.NumberFormat` in the active language.
- Hero photograph drifts 1.03 → 1.09 over 28 seconds inside a clipped frame.
- 2px reading-progress bar under the header; circular back-to-top after 720px.
- Hover: card lift 3px, image zoom 1.04, arrows move 3px. Dark surfaces switch to explicit light
  colours so no link fades into its background.

## Accessibility

Semantic landmarks, one `h1` per page, visible focus, 4.5:1 text contrast, 44px touch targets,
labels bound to inputs, `aria-current` navigation, keyboard-operable gallery and kanban, reduced
motion honoured.

## Assets

Local WOFF2 fonts (Fraunces, IBM Plex Sans Thai, Noto Serif Thai). Fifteen Unsplash photographs
under `assets/img/photos/` are used as demo stand-ins and credited in the footer; they are imported
into the media library on seed and are not hotlinked. Six architectural plates remain as a fallback
when a property has no featured image. Real listings upload their own media through the existing
WordPress editor.

# ESTORA — Design System

Two surfaces share one token set: **public site** (Persuade/Read) and **back office** (Operate).

## Visual world

Editorial real-estate catalogue: warm paper, charcoal ink, deep pine accent, brass hairlines.
Photography and architecture carry the page; type and whitespace carry the hierarchy.
Hairline rules (1px, `--line`) instead of card shadows. Radius is small (2–4px) — printed
brochure, not SaaS dashboard.

## Tokens (`assets/css/tokens.css` in both theme and plugin)

| Token | Value | Use |
| --- | --- | --- |
| `--paper` | `#FAF8F3` | page |
| `--surface` | `#FFFFFF` | cards, forms, admin panels |
| `--sand` | `#EDE6DA` | image plates, hover rows |
| `--ink` | `#1B1A18` | headings, primary text |
| `--ink-2` | `#4A463F` | body |
| `--muted` | `#847D71` | meta, captions |
| `--line` | `#E3DCCF` | borders |
| `--pine` | `#2F5D50` | primary action, links, active state |
| `--pine-ink` | `#22453B` | hover |
| `--pine-soft` | `#E4EDE8` | selected chips, soft badges |
| `--brass` | `#B08D57` | premium detail, rules in section heads |
| `--danger` | `#9B3B32` | destructive, lost |
| `--warning` | `#9A6B1F` | pending |
| `--ok` | `#2C6B4F` | won/confirmed |

Type: display `"Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif`;
UI `"Segoe UI", system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif`;
numbers `font-variant-numeric: tabular-nums` in tables and prices.

Scale: 12 / 13 / 14 / 16 / 18 / 22 / 28 / 36 / 52 / 68 px. Line-height 1.55 body, 1.05 display.
Spacing: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96. Container `1240px`, gutter `clamp(20px,4vw,48px)`.
Breakpoints: 576 / 768 / 992 / 1200 / 1440.

## Public site

- Hero: full-bleed architectural line-art plate (local SVG), serif headline max 2 lines, search
  panel overlapping the plate bottom edge (single white panel, 4 fields + button).
- Cards: 4:3 image, price in serif, location with pin, specs as a single hairline row, favorite as
  a top-right circular button. No shadow, 1px border, hover lifts 2px.
- Section heads: small brass rule + eyebrow (uppercase 11px tracked) + serif h2.
- Status: `For sale` / `For rent` as a small solid chip on the image; availability as text meta.
- Footer: charcoal band, 4 columns, hairline dividers.

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

## Accessibility

Semantic landmarks, one `h1` per page, visible focus, 4.5:1 text contrast, 44px touch targets,
labels bound to inputs, `aria-current` navigation, keyboard-operable gallery and kanban, reduced
motion honoured.

## Assets

Local SVG architectural plates ship with the theme; seeded photography is rasterised from those
artboards and imported into the media library. No hotlinked imagery. Missing image falls back to
the plate for the property's purpose.

# ESTORA — Public Site & Bilingual Design

Version 1.2.0 replaces the drawn artboards on the public site with real photography,
expands the demo catalogue to 21 listings across six Thai destinations, and adds a
site-wide English / Thai language layer.

## 1. Design direction

The public site is an editorial property catalogue, not a SaaS dashboard:

- **Photography first.** Full-bleed hero, 4:3 listing images, 4:3 destination cards and a
  photo-backed closing CTA. Every image is a local raster asset served from the theme.
- **Editorial type.** Fraunces for Latin display, Noto Serif Thai for Thai display, IBM Plex
  Sans Thai for UI and body. Thai and Latin are loaded from the theme, not Google at runtime.
- **Restrained palette.** Warm paper `#F7F3E9`, forest `#1C4A3A`, deep forest `#0F3026`,
  brass `#AF8B52`, ink `#17201B`. Brass is used for micro-detail, never as a large field.
- **Small radii, hairline rules.** 2–3px corners, 1px rules and subtle shadows. No glass,
  no oversized gradients, no abstract art replacement for the actual product.
- **One coherent rhythm.** Hero → collection → destinations → market pulse → method →
  latest → testimonials → journal → photo CTA → footer.

## 2. Page architecture

| Section | Purpose | Data source |
| --- | --- | --- |
| Hero | Positioning, headline, proof strip, search | Property stats, destination counts |
| Search panel | Purpose, type, destination, search | Repository filter options |
| Collection | Three featured listings | `featured=1` properties |
| Destinations | Six destination cards with counts | Location terms + child counts |
| Market pulse | Available, for sale, for rent, average asking price | `Property_Repository::stats()` |
| The ESTORA standard | Three operating promises + interior photo | Static editorial |
| Latest listings | Six newest properties | Repository |
| Testimonials | Three client quotes | `testimonial` CPT |
| Journal | Three latest articles | WordPress posts |
| Photo CTA | Contact conversion | Company settings |
| Footer | Navigation, office, language, image credit | Settings |

## 3. Bilingual layer

Language is resolved in this order:

1. `?lang=th` / `?lang=en`
2. `estora_lang` cookie (30 days)
3. Browser `Accept-Language` (`th` prefix → Thai)
4. English

Implementation:

- `estora_language()`, `estora_is_thai()`, `estora_text()` in the plugin helper layer.
- `Language` integration persists the query choice as a cookie and filters
  `determine_locale` to `th` on public pages. WP Admin, `/estora-admin/`, REST-only and the
  login screen keep the site locale.
- Runtime `gettext` / `ngettext` maps translate theme and plugin UI strings. Missing strings
  fall back to English instead of disappearing.
- `the_title`, `the_content`, `get_the_excerpt` and `nav_menu_item_title` filters read Thai
  post meta for properties, pages, testimonials and journal entries.
- Property data is localised in `Property_Repository::to_array()`: title, description,
  address, province/district/subdistrict, amenities, purpose, type and availability.
- Destination and district names, staff titles and company settings have Thai maps.

The switcher appears in the desktop header, the mobile navigation panel and the footer.
Switching preserves the current path and query string.

## 4. Data model additions

- 21 demo properties: Bangkok (6), Chiang Mai (7), Phuket (3), Hua Hin (2), Pattaya (2),
  Khon Kaen (1).
- 6 top-level destination terms and 14 neighbourhood terms.
- Each demo property has English content, Thai `title/content/address/SEO` meta, a real
  featured image, a 2–3 image gallery, price, availability, amenities, agent and location.
- Seeder imports theme photographs into the media library once and reuses them across
  `seed --force` runs. `_estora_photo_file` prevents duplicates.
- Amenity meta was previously sanitised to integer zero by the registered meta callback.
  v1.2.0 stores text amenities correctly for both the front end and REST.

## 5. Search and cards

- Hero search: purpose, type, destination + submit; popular destination shortcuts below.
- Archive search: keyword, purpose, type, location, min/max price, bedrooms, sort.
- Destination options are indented child terms (`— Watthana`) while province terms remain
  selectable for an aggregate search.
- Cards show real featured images, purpose chip, price (monthly suffix for rentals),
  localised title, district/province, specs, type and compare control.
- Favourites and compare use the existing REST routes; all newly generated labels are
  localised in `main.js` via `estoraData.strings`.

## 6. Responsive behaviour

Breakpoints: 1180, 1024, 960, 720, 420 and 360 px.

- Hero headline: 3 lines at 1440, compact type at short laptop heights; full-width actions
  and a 2×2 proof strip on phones.
- Search converts from a four-column bar to 2×2 fields and then one column.
- Destinations 3 → 2 → 1 columns.
- Property grids 3 → 2 → 1.
- Single property: two-column body + sticky sidebar → one column.
- Navigation becomes a full-width panel with language, favourites and CTA.
- Tables that cannot reflow (compare) scroll inside their own wrapper.
- No horizontal overflow at 320, 360, 390, 768, 1024, 1280, 1366 or 1440.

## 7. Assets

- `assets/img/photos/*.jpg` — 15 Unsplash photographs used as demo stand-ins, credited in
  the footer (“Demo photography via Unsplash”). They are not represented as the client's
  own photography.
- `assets/fonts/*.woff2` — Fraunces (Latin), IBM Plex Sans Thai (Thai/Latin) and Noto Serif
  Thai (Thai/Latin).
- Existing architectural plates remain in `assets/img/plate-*.png` as a fallback for
  properties without a featured image.
- No external image or font hotlinks at runtime.

## 8. Accessibility

- One `h1` per page, ordered h2/h3 sections.
- Visible labels on all search and form controls.
- 44px minimum touch targets on navigation and primary controls.
- Focus ring from `--focus`; skip link first in tab order.
- `aria-current` on the active language and navigation item.
- `prefers-reduced-motion` disables entrance and hover motion.
- Images have descriptive alt text or empty alt when the adjacent text carries meaning.

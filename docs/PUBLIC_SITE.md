# ESTORA — Public Site & Bilingual Design

Version 1.2.0 replaced the drawn artboards on the public site with real photography,
expanded the demo catalogue to 21 listings across six Thai destinations, and added a
site-wide English / Thai language layer. Version 1.3.0 adds hierarchical province/area
filtering, a motion system, an editorial News & Notes section and a payment estimator.

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
- **v1.3.0:** the archive and hero forms use two dependent selects instead of one location list.
  **Province** is built from the distinct `_estora_province` meta values of published listings and
  **Area** from `_estora_district`, grouped by province. Selecting a province rebuilds the area
  list in JavaScript and clears an invalid district. Both values are query args the repository
  already filters on, and both appear as removable chips.
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

## 9. Motion system (v1.3.0)

- `main.js` adds `estora-motion` to `<html>` only when IntersectionObserver exists and reduced
  motion is off. Reveal styles are scoped to that class, so a failed script leaves content visible.
- Revealed blocks: section heads, cards, destinations, method media/copy, quotes, article cards,
  pulse metrics, panels, CTA copy, page titles and the article sidebar. Siblings get a 70ms stagger
  capped at six steps.
- Counters animate the hero proof figures once, using `Intl.NumberFormat` for the active language.
- The hero photograph drifts with a 28-second alternate keyframe inside the clipped frame.
- A 2px gradient progress bar sits under the fixed header and a circular back-to-top button appears
  after 720px of scroll.
- Links and buttons move their arrow 3px on hover; cards lift 3px and zoom their image 1.04.
- `prefers-reduced-motion: reduce` removes the class, jumps counters to their final value and uses
  `behavior: auto` for the back-to-top scroll.

## 10. News & Notes (v1.3.0)

- Featured lead article on page 1, followed by a two-column editorial grid of the remaining posts.
- Topic chips generated from categories with published posts, including counts.
- Sticky sidebar: office CTA, "read by topic" list and a catalogue link.
- Each card carries a real featured image, category, date, reading time and excerpt. Reading time
  counts Thai characters and Latin words separately.
- Article template adds the same metadata, the author/proprietor line, share controls and
  previous/next notes.
- Seed: seven articles across Market notes, Buying guide, Renting, Ownership and Neighbourhoods,
  each with Thai title, content and excerpt plus a media-library thumbnail.

## 11. Payment estimator (v1.3.0)

Sale listings render an amortising-loan estimator: price (prefilled), down-payment percentage,
annual interest rate and term. It recalculates on input with

```
monthly = loan × r / (1 − (1 + r)^−n),  r = annual rate / 12 / 100,  n = years × 12
```

and shows the loan amount, term and rate underneath. It is labelled as an estimate and never
presented as a bank quote.

## 12. Owner and credits

- Proprietor: **พีรวิชญ์ หล้าคำภา / Peerawit Lakhampa**, stored as the `company_owner` setting and
  rendered in the footer band, About portrait, proprietor panel and article bylines.
- Demo photography is credited to Unsplash in the footer. The site does not claim it as the
  client's own property photography.

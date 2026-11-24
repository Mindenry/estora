# ESTORA — Requirements

Identifiers are referenced by tests and by `docs/ACCEPTANCE_CRITERIA.md`.

## Functional requirements

### Public site (FR-PUB)

| ID | Requirement |
| --- | --- |
| FR-PUB-01 | Homepage shows hero search, featured properties, latest properties, locations, categories, testimonials, articles, CTA. |
| FR-PUB-02 | Listing archive filters by keyword, purpose, type, province, district, price range, beds, baths, area range, availability; sorts by newest, oldest, price ↑↓, area ↑↓; paginates at 9. |
| FR-PUB-03 | Every filter is a URL parameter and survives pagination; reset clears them. |
| FR-PUB-04 | Property detail shows reference, price, location, description, gallery, specs, amenities, agent, map link, similar properties, share links, favorite. |
| FR-PUB-05 | Inquiry, contact and appointment forms validate server-side and persist rows. |
| FR-PUB-06 | Favorite toggle works for guests (signed visitor token) and logged-in users; no duplicates. |
| FR-PUB-07 | Compare holds up to 3 properties and renders a mobile-safe comparison table. |
| FR-PUB-08 | About, Contact, News, Privacy, Terms, 404 pages render from real WordPress content. |
| FR-PUB-09 | Sold/rented/unavailable properties accept no new active inquiry or viewing (HTTP 409 + explanatory message). |

### Inventory (FR-PROP)

| ID | Requirement |
| --- | --- |
| FR-PROP-01 | `property` CPT with `property_type`, `property_purpose`, `property_location` taxonomies. |
| FR-PROP-02 | All fields in §2 of the brief stored as validated post meta; required set depends on purpose. |
| FR-PROP-03 | Create/edit/publish/unpublish/archive/delete/duplicate, bulk actions, agent assignment. |
| FR-PROP-04 | Gallery upload, reorder, remove through the media library; MIME/size/extension validated. |
| FR-PROP-05 | Publication transitions `draft→pending→published→archived`; availability `available→reserved→sold/rented→unavailable`. |
| FR-PROP-06 | Search endpoint and archive use the same query builder; no client-side filtering of full inventory. |
| FR-PROP-07 | Property activity appears in the audit log and on the property history panel. |

### CRM (FR-CRM)

| ID | Requirement |
| --- | --- |
| FR-CRM-01 | Leads captured from property inquiry, contact form, appointment, manual entry, external API. |
| FR-CRM-02 | Pipeline `new→contacted→qualified→negotiation→won/lost`; invalid transitions rejected (422). |
| FR-CRM-03 | Assignment and reassignment record history; only holders of `estora_assign_leads` may do it. |
| FR-CRM-04 | Agents see only their assigned leads; managers see all. Enforced in repository and REST. |
| FR-CRM-05 | Notes with type, author, timestamp; internal flag respected by API output. |
| FR-CRM-06 | Next follow-up and last contact stored and surfaced; overdue highlighting in list. |
| FR-CRM-07 | Dedupe: same normalised phone + property within 30 days returns the existing lead with `duplicate: true`. |
| FR-CRM-08 | Kanban groups by status and persists moves through the API, not client state. |
| FR-CRM-09 | Analytics: totals per status, conversion rate, by source, by agent, over time — from SQL aggregates. |

### Appointments (FR-APT)

| ID | Requirement |
| --- | --- |
| FR-APT-01 | Public request requires property, name, phone, date, time; email optional but validated. |
| FR-APT-02 | Past dates, closed days, outside business hours, 45-minute grid enforced server-side. |
| FR-APT-03 | Two active appointments cannot overlap for the same agent, nor two active viewings for the same property. |
| FR-APT-04 | Status machine `pending→confirmed→completed` plus `rejected|cancelled|no_show`; confirmed may be rescheduled. |
| FR-APT-05 | Calendar month/week/day views with agent and property filters sourced from the API. |
| FR-APT-06 | Public request creates/links a CRM lead and notifies staff. |

### Back office (FR-ADM)

| ID | Requirement |
| --- | --- |
| FR-ADM-01 | Custom ESTORA admin shell with dashboard KPIs computed from MySQL. |
| FR-ADM-02 | Lead list with search/filter/sort/pagination, detail view, notes, timeline. |
| FR-ADM-03 | Appointment list + calendar with status actions. |
| FR-ADM-04 | Reports: inventory, leads, agent performance, appointments, conversion; CSV export with real rows. |
| FR-ADM-05 | Settings: company identity, contact, business hours, slot length, notification recipient. |
| FR-ADM-06 | Campaign CRUD with UTM fields; inquiry attribution captured from cookie. |
| FR-ADM-07 | Audit log screen filterable by actor, action, object type. |

### Users & RBAC (FR-RBAC)

| ID | Requirement |
| --- | --- |
| FR-RBAC-01 | Roles: administrator (all caps), property manager, sales manager, sales agent, marketing. |
| FR-RBAC-02 | Capability checks on every admin page, admin-post action and REST route. |
| FR-RBAC-03 | Ownership checks for agent-scoped reads and writes. |
| FR-RBAC-04 | User create/edit/deactivate, role assignment, agent profile (phone, title, photo). |
| FR-RBAC-05 | Password handling delegated to WordPress; no plaintext storage. |

### API (FR-API)

| ID | Requirement |
| --- | --- |
| FR-API-01 | Namespace `estora/v1`; envelope `{success,data,meta,message}`; errors add `errors{}` and correct status. |
| FR-API-02 | Properties, leads, appointments, agents, dashboard, favorites, notifications, campaigns endpoints as specified. |
| FR-API-03 | Pagination `page`/`per_page` (max 100) with `X-WP-Total` headers and `meta.pagination`. |
| FR-API-04 | Public routes limited to published properties, favorites and form submissions; everything else requires auth. |
| FR-API-05 | Application Passwords supported for external integrations; cookie + `X-WP-Nonce` for admin JS. |

### Notifications & SEO (FR-NOT, FR-SEO)

| ID | Requirement |
| --- | --- |
| FR-NOT-01 | In-app notifications with read state; email via `wp_mail` with failure logged and surfaced. |
| FR-NOT-02 | Dedupe key prevents duplicate notification per event/object/user. |
| FR-SEO-01 | Unique title/description, canonical, Open Graph, `RealEstateListing` JSON-LD on properties. |
| FR-SEO-02 | WordPress core sitemap includes properties; no duplicate entries. |
| FR-SEO-03 | Campaign landing template captures `utm_*` and stores attribution on the lead. |
| FR-SEO-04 | Reveal only factual data in structured data (no invented ratings). |

## Non-functional requirements

| ID | Requirement |
| --- | --- |
| NFR-01 | PHP 8.3+, WordPress 6.7+, MySQL 8.0+, Bootstrap 5.3, ES2020. |
| NFR-02 | All SQL through `$wpdb->prepare()`; no string concatenation of user input. |
| NFR-03 | Every list paginated; dashboard aggregates cached 5 min and invalidated on write. |
| NFR-04 | Public pages: no layout shift from images (explicit dimensions / aspect-ratio). |
| NFR-05 | Responsive at 375, 768, 1024, 1440; no horizontal scroll. |
| NFR-06 | WCAG 2.1 AA for colour contrast, labels, focus, keyboard operation. |
| NFR-07 | Docker stack reproducible from a clean clone with documented commands. |
| NFR-08 | Secrets only via environment variables; `.env` ignored by git. |
| NFR-09 | All tests runnable non-interactively; CI executes the same commands. |
| NFR-10 | Theme change does not remove business data or break the API. |

## Assumptions

- Single brokerage, one office; agent availability is office-wide business hours.
- Email delivery uses the host's mail transport (logged to `wp-content/uploads/estora-mail.log` when `wp_mail` fails).
- No external map key; the detail page links to OpenStreetMap and embeds it lazily.
- Currency THB, formatted with `number_format_i18n`; timezone from WordPress settings.
- Photography is generated locally (see DESIGN.md) because no licensed photo library is available in this environment.

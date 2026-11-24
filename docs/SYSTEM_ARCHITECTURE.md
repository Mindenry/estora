# ESTORA — System Architecture

## 1. Style

Modular monolith inside WordPress. One plugin owns the domain; the theme owns presentation.
No SPA, no external service dependency for core flows. Layering inside the plugin is explicit:

```
HTTP (REST controllers / admin-post / admin pages)
   ↓ permission callback → sanitize → validate
Services (business rules, transactions, notifications, cache invalidation)
   ↓
Repositories (SQL, WP_Query, meta persistence)
   ↓
MySQL (wp_posts/wp_postmeta/wp_users + wp_estora_*)
```

Rules:
- Controllers never contain SQL.
- Repositories never check capabilities.
- Services are the only place that changes state and the only place that emits audit/notifications.
- Theme calls the plugin through public REST endpoints or documented template functions; it never
  writes business tables directly.

## 2. Runtime topology

```
Browser ──▶ Apache/PHP 8.3 (WordPress) ──▶ MySQL 8
                   │
                   ├─ wp-content/themes/estora-theme
                   ├─ wp-content/plugins/estora-core
                   └─ uploads/ (media, logs, backups)
WP-CLI container ──▶ same volumes, migrations/seed/tests
Playwright ──▶ http://localhost:8080
```

`docker-compose.yml` services: `db`, `wordpress`, `cli` (profile `tools`), `pma` (profile `tools`).
Volumes: `db_data`, `wp_data`; source mounted read-write for development.

## 3. Request paths

| Path | Handler |
| --- | --- |
| Public page | theme template → `WP_Query`/`estora_get_properties()` → escaped HTML |
| Public form | `fetch()` → `/wp-json/estora/v1/inquiries` or `/appointments` → Public_Controller → Lead/Appointment service |
| Admin screen | `admin.php?page=estora-*` → capability gate → view PHP shell → JS fetch with `X-WP-Nonce` |
| External integration | Application Password (Basic) → REST → permission callback |
| Cron | `estora_daily_maintenance` → overdue follow-up notifications, stale pending appointment expiry |

## 4. Module map (`estora-core`)

| Directory | Responsibility |
| --- | --- |
| `includes/Support` | Autoloader, container, helpers, capabilities map |
| `database` | `Schema`, `Migrator`, table name resolver |
| `includes/Domain` | Enums/status machines: `Lead_Status`, `Appointment_Status`, `Property_Status` |
| `includes/Repositories` | `Property_Repository`, `Lead_Repository`, `Appointment_Repository`, `Favorite_Repository`, `Notification_Repository`, `Activity_Repository`, `Campaign_Repository` |
| `includes/Services` | `Lead_Service`, `Appointment_Service`, `Property_Service`, `Favorite_Service`, `Notification_Service`, `Report_Service`, `Settings` |
| `api` | `Rest_Controller` base + Properties, Leads, Appointments, Agents, Dashboard, Favorites, Inquiries, Notifications, Campaigns |
| `admin` | `Admin_Menu`, `Admin_Assets`, screens in `views/`, CSV export in `Exports` |
| `integrations` | WordPress hooks: CPT/taxonomies, roles, mail, sitemap, cleanup |
| `tests` | Plugin-side test bootstrap helpers |

## 5. Data ownership

- WordPress owns users, posts (properties), terms, media.
- Plugin owns pipeline, scheduling, favorites, notifications, campaigns, audit, locations mirror.
- Referential strategy: custom tables store `*_id` referencing WordPress IDs **without** FK
  constraints (WordPress may run MyISAM/InnoDB historically, and posts can be deleted by core).
  Integrity is enforced in services: property deletion archives leads' `property_id` to NULL and
  keeps a snapshot label; user deletion reassigns leads to the manager and nulls agent refs.
  Intra-plugin references use FKs with `ON DELETE CASCADE` only where the parent is in the same
  plugin domain (`lead_notes`, `lead_assignments`, `inquiries`).

## 6. Caching

- Transient cache: dashboard summary (300s), filter options (3600s). Invalidated on the writes listed below.
- Invalidation on: property save/status change, lead create/assign/status, appointment change,
  favorite change. `Notification_Repository` and audit log are never cached.
- REST responses send `Cache-Control: no-store` for authenticated data; public property lists use
  `ETag`-less short cache and are purged on save.

## 7. Security boundaries

- Authentication: WordPress core (cookies + nonce, Application Passwords).
- Authorization: capabilities (`estora_*`) + ownership predicates in services.
- Public write endpoints: nonce, honeypot, per-IP rate limit (transient), content length limits,
  strict schema validation, duplicate suppression.
- File uploads: WordPress media APIs with MIME allow-list, 5 MB cap, image dimension check.
- Output: escaping at render (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`); API returns data
  already sanitised, JSON encoded by `WP_REST_Response`.

## 8. Failure behaviour

| Failure | Behaviour |
| --- | --- |
| DB error | exception logged via `error_log`, API 500 with generic message, no SQL echoed |
| Mail failure | logged to `uploads/estora-mail.log`, in-app notification still created, admin notice |
| Invalid transition | 422 with `errors.status` |
| Slot conflict | 409 with conflicting slot in `meta` |
| Missing capability | 403, no data leakage |
| Rate limit | 429 with `Retry-After` |

## 9. Extensibility

- Filters: `estora/lead/created`, `estora/appointment/created`, `estora/property/query_args`,
  `estora/dashboard/cards`, `estora/notification/channels`.
- Actions: `estora/lead/status_changed`, `estora/appointment/status_changed`, `estora/property/status_changed`.
- New channels plug into `Notification_Service::dispatch()` and register in settings.

## 10. Deployment units

Docker images are immutable except the bind-mounted `wp-content`; production can ship the built
theme/plugin as a release artefact, with migrations run by `wp estora migrate --yes`.

# ESTORA — REST API Specification

Base: `/wp-json/estora/v1` · Namespace version `1` · JSON only.
Interactive reference: `/wp-json/estora/v1` (route index) and `docs/API_SPECIFICATION.md`.

## Conventions

**Envelope**
```json
{ "success": true, "data": {}, "meta": {}, "message": "Operation completed successfully" }
```
**Error**
```json
{ "success": false, "data": null, "message": "Validation failed",
  "errors": { "email": ["A valid email address is required."] } }
```

**Status codes** 200 read/update, 201 created, 204 no content (delete), 400 malformed,
401 unauthenticated, 403 forbidden, 404 not found, 409 conflict (slot/property state),
422 invalid transition or business rule, 429 rate limited, 500 server error.

**Auth**
- Same-origin admin: WordPress cookie + `X-WP-Nonce: <wp_create_nonce('wp_rest')>`.
- External: HTTP Basic with an Application Password (`user:xxxx xxxx xxxx`).
- Public writes: `X-WP-Nonce` from the page plus honeypot `company` field and rate limit.
- A cookie session without a nonce is treated as unauthenticated by WordPress core (401);
  ESTORA's own permission guard returns 403 when a request reaches it without a valid nonce.

**Pagination** `page` (default 1), `per_page` (default 20, max 100). Response `meta.pagination`
`{page, per_page, total, total_pages}` and headers `X-WP-Total`, `X-WP-TotalPages`.

**Dates** `YYYY-MM-DD`; times `HH:MM` (24h, site timezone); timestamps ISO-8601 UTC.

---

## Properties

### `GET /properties`
Public. Only `publish` posts.

Query: `purpose` (sale|rent), `type` (slug), `province`, `district`, `q`, `min_price`,
`max_price`, `bedrooms`, `bathrooms`, `min_area`, `max_area`, `availability`, `featured` (0|1),
`sort` (`newest|oldest|price_asc|price_desc|area_asc|area_desc`), `page`, `per_page`.

```json
{ "success": true,
  "data": [ { "id": 42, "reference": "EST-1042", "title": "Courtyard House, Nimman",
              "slug": "courtyard-house-nimman", "purpose": "sale", "type": "house",
              "price": 12500000, "price_formatted": "฿12,500,000",
              "location": { "province": "Chiang Mai", "district": "Suthep" },
              "bedrooms": 3, "bathrooms": 3, "usable_area": 240, "land_area": 400,
              "availability": "available", "featured": true,
              "image": { "url": "…/courtyard.jpg", "width": 1600, "height": 1000, "alt": "…" },
              "agent": { "id": 7, "name": "Ploy S." } } ],
  "meta": { "pagination": { "page": 1, "per_page": 9, "total": 24, "total_pages": 3 },
            "filters": { "purpose": "sale" } } }
```

### `GET /properties/{id}`
Public if published; drafts visible with `estora_edit_properties`. Returns full detail plus
`gallery[]`, `amenities[]`, `map {lat,lng,url}`, `agent`, `similar[]` (3), `seo`.

### `GET /properties/search`
Alias of `GET /properties` (same validation, same response).

### `POST /properties`
Capability `estora_edit_properties`. Body:
`title*`, `description`, `purpose*` (sale|rent), `type*` (term slug), `price*` (number ≥ 0),
`rent_period` (month|year, required when purpose=rent), `address`, `province`, `district`,
`subdistrict`, `postal_code`, `latitude`, `longitude`, `land_area`, `usable_area`, `bedrooms`,
`bathrooms`, `parking`, `floors`, `year_built`, `amenities[]`, `gallery[]` (attachment IDs),
`agent_id`, `availability`, `status` (draft|pending|published), `featured`, `seo_title`,
`seo_description`, `video_url`.
201 with the created resource.

### `PUT|PATCH /properties/{id}`
Capability `estora_edit_properties`. Same fields; PUT replaces supplied field groups, PATCH merges.

### `DELETE /properties/{id}`
Capability `estora_delete_properties`. `force=true` permanent, otherwise trash. 204.

### `POST /properties/{id}/duplicate`
Capability `estora_edit_properties`. Copies meta, terms, gallery (same attachment IDs) as a new
draft with a new reference. 201.

---

## Leads

All lead routes require authentication and `estora_view_leads` / `estora_create_leads` /
`estora_edit_leads`. Agents are scoped to `assigned_agent_id = current user` unless the user holds
`estora_view_all_leads`.

### `GET /leads`
`status`, `source`, `agent_id`, `priority`, `q`, `overdue=1`, `property_id`, `page`, `per_page`.
Returns lead summaries and `meta.counts` per status for the current scope.

### `GET /leads/{id}`
Lead plus `notes[]`, `assignments[]`, `timeline[]`, `property {id,title,reference}`, `agent`.
403 when the lead is not in the caller's scope (existence is not disclosed: 404 for agents).

### `POST /leads`
Capability `estora_create_leads`. `full_name*`, `phone` or `email` required, `property_id`,
`budget_min`, `budget_max`, `preferred_location`, `message`, `source` (default manual),
`priority`, `assigned_agent_id`, `next_follow_up_at`. 201; `meta.duplicate` when deduped.

### `PATCH /leads/{id}`
Capability `estora_edit_leads` + scope. Editable: contact fields, budget, priority,
`next_follow_up_at`, `preferred_location`.

### `DELETE /leads/{id}`
Capability `estora_delete_leads`. Deletes notes/assignments by FK cascade. 204.

### `POST /leads/{id}/assign`
Capability `estora_assign_leads`. Body `agent_id*` (active user with agent role), `reason`.
Writes assignment history, notifies the agent, audits. 200 with updated lead.

### `POST /leads/{id}/notes`
Capability `estora_edit_leads` + scope. `body*` (1–5000), `note_type` (note|call|email|meeting),
`is_internal`. Updates `last_contact_at`. 201.

### `POST /leads/{id}/status`
Capability `estora_edit_leads` + scope. `status*`, optional `note`, `won_value`.
Allowed: `new→contacted`, `contacted→qualified`, `qualified→negotiation`,
`negotiation→won`, any active→lost, plus re-open `lost→contacted` by `estora_assign_leads` holders.
422 on invalid transition.

---

## Appointments

### `GET /appointments`
Capability `estora_manage_appointments` (agents may pass `mine=1` and only see their own).
Query `status`, `agent_id`, `property_id`, `from`, `to`, `mine`, pagination. Ordered by date/time.

### `GET /appointments/availability`
Params `date*`, `agent_id` (default: best available agent), `property_id`. Returns
`slots [{start,end,available,reason?}]` for business hours, excluding booked slots and past times.

### `POST /appointments`
Public. `property_id*`, `date*`, `time*`, `name*`, `phone*`, `email`, `message`, `agent_id`,
`company` (honeypot, must be empty), `consent` (must be true). Creates/attaches a lead.
201 `{id, public_ref, status: "pending", date, time}`. 409 when the slot is taken, 422 for past
dates/closed hours, 429 when rate limited.

### `PATCH /appointments/{id}/status`
Capability `estora_manage_appointments`. `status*` in
`confirmed|cancelled|rejected|completed|no_show`, optional `internal_notes`, `agent_id`.
Confirmation re-runs the conflict check and sets `active_key`. 409 on conflict.

### `POST /appointments/{id}/reschedule`
Capability `estora_manage_appointments`. `date*`, `time*`, `agent_id`, `reason`.
Only pending/confirmed/rescheduled appointments; recorded in internal notes + audit.

### `PUT /appointments/{id}`
Full staff update (notes, internal notes, agent, customer contact) with the same validation.

---

## Agents & users

### `GET /agents`
Capability `estora_view_all_leads` or `estora_manage_appointments`. Returns active agents:
`id, name, email, phone, title, avatar, active, open_leads` (counts only for managers).

### `GET /agents/{id}`
Same capability; 404 for non-agents.

---

## Dashboard

All require `estora_view_reports`.

### `GET /dashboard/summary`
```json
{ "success": true, "data": {
  "properties": { "total": 28, "published": 24, "available": 19, "sold": 4, "rented": 3 },
  "leads": { "total": 142, "new": 18, "qualified": 12, "won": 9, "lost": 31, "conversion_rate": 6.3 },
  "appointments": { "upcoming": 6, "pending": 2, "this_week": 9 },
  "recent_activity": [ { "action": "lead.assigned", "description": "…", "at": "…" } ],
  "lead_trend": [ { "date": "2026-11-01", "count": 4 } ]
}, "meta": { "cached": true, "ttl": 300 } }
```

### `GET /dashboard/property-statistics`
Counts by purpose, type, availability, province; average and median asking price; stale listings
(not updated 90 days).

### `GET /dashboard/lead-statistics?days=30`
By status, source, agent, priority; conversion rate; median time-to-first-contact; daily series.

### `GET /dashboard/appointment-statistics`
By status, agent, weekday; pending older than 24h; completion rate.

---

## Favorites

Guest identity is a 32-byte hex token sent as `X-Estora-Visitor` (the theme also mirrors it to the
`estora_visitor` cookie). Authenticated users are identified by session. Rows are unique per
`owner_key + property_id`, so repeat adds are idempotent.

- `GET /favorites` → `{data: {ids: [], properties: []}}` (only published properties).
- `POST /favorites` body `property_id*` → `{data:{favorited:true, property_id, count}}`.
- `DELETE /favorites/{property_id}` → `{data:{favorited:false, property_id, count}}`.

---

## Inquiries

### `POST /inquiries`
Public. `property_id*`, `name*`, `phone*` or `email*`, `message*`, `consent*`, `company` (empty),
`utm_source|utm_medium|utm_campaign`. 201 `{lead_ref, duplicate}`; sets `estora_last_lead` cookie.
409 when the property is not accepting inquiries.

### `GET /inquiries`
Capability `estora_view_leads`; filters `status`, `property_id`, pagination.

---

## Notifications

- `GET /notifications?unread=1` → current user's notifications + `meta.unread`.
- `POST /notifications/{id}/read` → `{data:{unread: n}}` (owner only).
- `POST /notifications/read-all` → `{data:{unread: 0}}`.

## Campaigns

Capability `estora_manage_campaigns`.

- `GET /campaigns` filters `status`, pagination, plus `attribution_count` (leads with matching UTM
  campaign in the last 30 days).
- `POST /campaigns` `name*`, `channel`, `utm_source`, `utm_medium`, `utm_campaign*`, `status`,
  `budget`, `starts_at`, `ends_at`, `landing_page_id`, `notes`.
- `PUT /campaigns/{id}`, `DELETE /campaigns/{id}` (204).

## System, settings and audit

### `GET /public/config`
Public. Returns a fresh REST nonce for the visitor session, site/company identity, currency,
timezone, business hours, slot length, compare limit and plugin version. The theme localises the
same values for JS.

### `GET /health`
Public. `{plugin_version, db_version, tables:{...}, environment}`. Returns 500 only when a custom
table is missing.

### `GET /settings` / `POST /settings`
Capability `estora_manage_settings`. GET returns the sanitised settings plus available agents; POST
accepts any subset of the setting keys, sanitises and validates time ranges/slot length, and returns
the merged result. Every change is written to the audit log.

### `GET /audit`
Capability `estora_view_audit`. Filters `actor_id`, `action`, `object_type`, `object_id`, paginated.
Passwords, tokens and full contact messages are never stored in this log.

## CSV exports (not REST)

`POST /wp-admin/admin-post.php` with `action=estora_export`, `_wpnonce`, `report` in
`properties|leads|agents|appointments|conversion`, optional filters. Requires
`estora_view_reports`; `leads` additionally requires `estora_view_leads`. Streams `text/csv` with
`Content-Disposition: attachment` and the real rows.

## Error examples

```json
{ "success": false, "data": null, "message": "Validation failed",
  "errors": { "phone": ["A phone number or email address is required."] } }
```
```json
{ "success": false, "data": null, "message": "This slot is no longer available.",
  "meta": { "conflicts": ["2026-12-02 14:00"] } }
```

## Rate limits

Public inquiry, appointment and favorite writes use a transient counter keyed by
`IP + bucket + normalised contact` (phone or email): 5 requests / 10 minutes for inquiries and
appointments, 40 / 10 minutes for favorites. The sixth rapid inquiry from one contact returns 429.
Login attempts are limited to 10 failures / 15 minutes per IP before a lockout message; successful
sign-in clears the counter.

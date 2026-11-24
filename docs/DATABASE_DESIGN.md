# ESTORA — Database Design

Engine: InnoDB, `utf8mb4_unicode_ci`, MySQL 8.0. Prefix notation `{p}` = `$wpdb->prefix` (default `wp_`).
All custom tables are created with `dbDelta()` from `estora-core/database/Schema.php` and versioned
by the `estora_db_version` option.

## 1. WordPress-owned data

| Data | Storage | Notes |
| --- | --- | --- |
| Users / auth / roles | `{p}users`, `{p}usermeta` | Plus `estora_agent_*` user meta |
| Properties | `{p}posts` (`post_type=property`) | Revisions, media, sitemap come free |
| Property taxonomy | `{p}terms`, `{p}term_taxonomy`, `{p}term_relationships` | `property_type`, `property_purpose`, `property_location` |
| Media | `{p}posts` (`attachment`) + uploads | Gallery list stored as attachment IDs |
| Pages / articles | `{p}posts` | Privacy, Terms, About, Contact, News |

### Property meta keys (`{p}postmeta`)

`_estora_reference`, `_estora_price_sale`, `_estora_price_rent`, `_estora_rent_period`,
`_estora_address`, `_estora_province`, `_estora_district`, `_estora_subdistrict`,
`_estora_postal_code`, `_estora_latitude`, `_estora_longitude`, `_estora_land_area`,
`_estora_usable_area`, `_estora_bedrooms`, `_estora_bathrooms`, `_estora_parking`,
`_estora_floors`, `_estora_year_built`, `_estora_amenities` (serialised array),
`_estora_gallery` (serialised attachment IDs), `_estora_agent_id`, `_estora_availability`,
`_estora_featured`, `_estora_seo_title`, `_estora_seo_description`, `_estora_video_url`,
`_estora_placeholder` (artboard key) and the derived sort mirrors.

Numeric meta are also mirrored into `_estora_price_sort` and `_estora_area_sort` as
zero-padded strings so `WP_Query` `meta_key` ordering is lexicographically correct. This is
written by `Property_Repository::save()`, not by hand.

## 2. Custom tables

### `{p}estora_leads`

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED AI | PK |
| public_ref | VARCHAR(20) | `LD-000123`, UNIQUE |
| full_name | VARCHAR(150) | required |
| email | VARCHAR(190) NULL | validated when present |
| phone | VARCHAR(30) NULL | required unless email present |
| preferred_contact | VARCHAR(20) | phone/email/whatsapp/line, default phone |
| property_id | BIGINT UNSIGNED NULL | WP post ID (logical reference) |
| budget_min / budget_max | DECIMAL(15,2) NULL | THB |
| preferred_location | VARCHAR(190) NULL | free text |
| message | TEXT NULL | |
| source | VARCHAR(40) | website/contact/appointment/manual/api/campaign |
| utm_source / utm_medium / utm_campaign | VARCHAR(100) NULL | attribution |
| status | VARCHAR(20) | default `new` |
| priority | VARCHAR(10) | low/normal/high/urgent |
| assigned_agent_id | BIGINT UNSIGNED NULL | WP user ID |
| dedupe_key | CHAR(64) NULL | sha256(phone_digits|property_id) |
| last_contact_at | DATETIME NULL | |
| next_follow_up_at | DATETIME NULL | |
| won_value | DECIMAL(15,2) NULL | recorded on `won` |
| created_at / updated_at | DATETIME | UTC |

Indexes: `UNIQUE(public_ref)`, `KEY(status, created_at)`, `KEY(assigned_agent_id, status)`,
`KEY(property_id)`, `KEY(dedupe_key, created_at)`, `KEY(next_follow_up_at)`.

### `{p}estora_lead_notes`

`id`, `lead_id`, `author_id`, `note_type` (note/call/email/meeting/status), `body` TEXT,
`is_internal` TINYINT(1), `created_at`. `KEY(lead_id, created_at)`, FK `lead_id → leads(id) ON DELETE CASCADE`.

### `{p}estora_lead_assignments`

`id`, `lead_id`, `from_agent_id` NULL, `to_agent_id`, `assigned_by`, `reason` VARCHAR(255),
`created_at`. `KEY(lead_id, created_at)`, FK cascade to leads.

### `{p}estora_appointments`

| Column | Type | Notes |
| --- | --- | --- |
| id | BIGINT UNSIGNED AI | PK |
| public_ref | VARCHAR(20) | `AP-000123` |
| property_id | BIGINT UNSIGNED | WP post ID |
| lead_id | BIGINT UNSIGNED NULL | logical ref |
| agent_id | BIGINT UNSIGNED NULL | WP user ID; required once confirmed |
| customer_name | VARCHAR(150) | |
| customer_email | VARCHAR(190) NULL | |
| customer_phone | VARCHAR(30) | |
| requested_date | DATE | site-local |
| start_time / end_time | TIME | 45-minute grid |
| status | VARCHAR(20) | pending/confirmed/rescheduled/completed/cancelled/rejected/no_show |
| source | VARCHAR(40) | website/staff/api |
| notes | TEXT NULL | customer message |
| internal_notes | TEXT NULL | staff only |
| active_key | VARCHAR(80) NULL | `agent:{id}\|date\|start` when active, else NULL |
| created_by | BIGINT UNSIGNED NULL | 0 for public |
| created_at / updated_at | DATETIME | |

Indexes: `UNIQUE(active_key)`, `KEY(agent_id, requested_date, status)`,
`KEY(property_id, requested_date)`, `KEY(status, requested_date)`, `KEY(lead_id)`.

`active_key` is the last-line defence against two concurrent confirmations of the same slot:
MySQL permits many `NULL`s but only one identical non-null key, so the losing transaction gets a
duplicate-key error which the service converts to HTTP 409.

### `{p}estora_favorites`

`id`, `owner_key` VARCHAR(80) (`u:{user_id}` or `v:{64-hex-token}`), `user_id` NULL,
`visitor_token` CHAR(64) NULL, `property_id`, `created_at`.
`UNIQUE(owner_key, property_id)`, `KEY(property_id)`. This avoids the MySQL "many NULLs in a
unique index" trap for guests.

### `{p}estora_inquiries`

`id`, `property_id`, `lead_id` NULL, `name`, `email`, `phone`, `message`, `source`, `status`
(new/handled), `created_at`. `KEY(property_id, created_at)`, `KEY(status)`, `KEY(lead_id)`.

### `{p}estora_locations`

`id`, `name`, `slug`, `type` (province/district/subdistrict), `parent_id` NULL, `created_at`.
`UNIQUE(slug, type)`, `KEY(parent_id)`. Mirrors taxonomy terms for API filters and imports.

### `{p}estora_activity_logs`

`id`, `actor_id`, `action` VARCHAR(60), `object_type` VARCHAR(40), `object_id` BIGINT,
`description` VARCHAR(255), `meta` LONGTEXT (JSON), `ip_hash` CHAR(64), `created_at`.
`KEY(object_type, object_id)`, `KEY(actor_id, created_at)`, `KEY(action)`.

### `{p}estora_notifications`

`id`, `user_id`, `type` VARCHAR(60), `title` VARCHAR(190), `body` TEXT, `link` VARCHAR(255),
`is_read` TINYINT(1) default 0, `dedupe_key` CHAR(64) NULL, `created_at`.
`UNIQUE(dedupe_key)`, `KEY(user_id, is_read, created_at)`.

### `{p}estora_campaigns`

`id`, `name`, `slug` UNIQUE, `channel`, `utm_source`, `utm_medium`, `utm_campaign`,
`landing_page_id` NULL, `status` (draft/active/paused/ended), `budget` DECIMAL(15,2) NULL,
`starts_at` DATE NULL, `ends_at` DATE NULL, `notes` TEXT NULL, `created_at`, `updated_at`.
`KEY(status, starts_at)`, `KEY(utm_campaign)`.

## 3. Referential integrity strategy

| Relation | Enforcement |
| --- | --- |
| `leads.property_id → posts.ID` | logical; service nulls it and writes a snapshot note when a property is deleted |
| `leads.assigned_agent_id → users.ID` | logical; `deleted_user` hook reassigns to settings fallback manager |
| `appointments.property_id / agent_id / lead_id` | logical; booking validates existence and post status |
| `favorites.property_id` | logical; deleted property favorites cascade via `before_delete_post` |
| `lead_notes.lead_id`, `lead_assignments.lead_id` | FK, `ON DELETE CASCADE` (same domain) |
| `inquiries.lead_id` | logical; kept for history after lead purge |

FKs against `{p}posts` / `{p}users` are deliberately **not** created: core cleanup, imports and
multisite routines do not expect them, and a failed FK can block core operations.

## 4. Transactions

- Lead capture: insert lead + first note + audit + notification in one transaction.
- Manual lead creation with assignment: lead + assignment row + audit.
- Appointment confirm/reschedule: `START TRANSACTION` → `SELECT ... FOR UPDATE` over the agent's
  date → range-overlap query → update/insert → `COMMIT`; duplicate-key on `active_key` → rollback + 409.
- Property status change: meta update + audit + cache invalidation (no transaction needed; single row).

## 5. Migration and seeding

- `Schema::create()` uses `dbDelta` (idempotent).
- `Migrator::maybe_upgrade()` compares `estora_db_version` with `ESTORA_DB_VERSION` on
  `plugins_loaded`; `wp estora migrate` forces it.
- Seed data (`wp estora seed`) inserts demo properties, taxonomy terms, agents, leads,
  appointments, testimonials and pages. It is guarded by `--force` when demo content exists.
  Production installs never auto-seed.

## 6. Backup and restore

`scripts/backup.sh` → `docker compose exec db mysqldump --single-transaction --routines` plus a
tar of `uploads/`; `scripts/restore.sh` pipes the dump back and restores uploads. Both refuse to run
without an explicit file argument. See `docs/DEPLOYMENT_GUIDE.md`.

## 7. Capacity notes

Indexes cover the dashboard aggregates and the two hottest filters (agent+status, status+date).
Public search joins taxonomy and postmeta; the `_estora_price_sort` / `_estora_area_sort` mirrors
keep sorting off `CAST(meta_value AS DECIMAL)` scans. At 100k properties the archive query should
move to a denormalised search table; that is documented as a scale-out path, not implemented in v1.

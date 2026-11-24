# ESTORA — ER Diagram

```mermaid
erDiagram
    USERS ||--o{ LEADS : "assigned_agent_id (logical)"
    USERS ||--o{ APPOINTMENTS : "agent_id (logical)"
    USERS ||--o{ LEAD_NOTES : "author_id"
    USERS ||--o{ NOTIFICATIONS : "user_id"
    USERS ||--o{ ACTIVITY_LOGS : "actor_id (logical)"
    USERS ||--o{ FAVORITES : "user_id (logical, nullable)"

    POSTS_PROPERTY ||--o{ LEADS : "property_id (logical)"
    POSTS_PROPERTY ||--o{ APPOINTMENTS : "property_id (logical)"
    POSTS_PROPERTY ||--o{ INQUIRIES : "property_id (logical)"
    POSTS_PROPERTY ||--o{ FAVORITES : "property_id (logical)"
    POSTS_PAGE ||--o{ CAMPAIGNS : "landing_page_id (logical)"

    LEADS ||--o{ LEAD_NOTES : "lead_id (FK cascade)"
    LEADS ||--o{ LEAD_ASSIGNMENTS : "lead_id (FK cascade)"
    LEADS ||--o{ INQUIRIES : "lead_id (logical)"
    LEADS ||--o{ APPOINTMENTS : "lead_id (logical)"

    LOCATIONS ||--o{ LOCATIONS : "parent_id"

    LEADS {
        bigint id PK
        varchar public_ref UK
        varchar full_name
        varchar email
        varchar phone
        varchar preferred_contact
        bigint property_id FK
        decimal budget_min
        decimal budget_max
        varchar preferred_location
        text message
        varchar source
        varchar utm_source
        varchar utm_medium
        varchar utm_campaign
        varchar status
        varchar priority
        bigint assigned_agent_id FK
        char dedupe_key
        datetime last_contact_at
        datetime next_follow_up_at
        decimal won_value
        datetime created_at
        datetime updated_at
    }

    LEAD_NOTES {
        bigint id PK
        bigint lead_id FK
        bigint author_id FK
        varchar note_type
        text body
        tinyint is_internal
        datetime created_at
    }

    LEAD_ASSIGNMENTS {
        bigint id PK
        bigint lead_id FK
        bigint from_agent_id
        bigint to_agent_id
        bigint assigned_by
        varchar reason
        datetime created_at
    }

    APPOINTMENTS {
        bigint id PK
        varchar public_ref UK
        bigint property_id FK
        bigint lead_id FK
        bigint agent_id FK
        varchar customer_name
        varchar customer_email
        varchar customer_phone
        date requested_date
        time start_time
        time end_time
        varchar status
        varchar source
        text notes
        text internal_notes
        varchar active_key UK
        bigint created_by
        datetime created_at
        datetime updated_at
    }

    FAVORITES {
        bigint id PK
        varchar owner_key
        bigint user_id
        char visitor_token
        bigint property_id FK
        datetime created_at
    }

    INQUIRIES {
        bigint id PK
        bigint property_id FK
        bigint lead_id FK
        varchar name
        varchar email
        varchar phone
        text message
        varchar source
        varchar status
        datetime created_at
    }

    LOCATIONS {
        bigint id PK
        varchar name
        varchar slug
        varchar type
        bigint parent_id FK
        datetime created_at
    }

    ACTIVITY_LOGS {
        bigint id PK
        bigint actor_id
        varchar action
        varchar object_type
        bigint object_id
        varchar description
        longtext meta
        char ip_hash
        datetime created_at
    }

    NOTIFICATIONS {
        bigint id PK
        bigint user_id
        varchar type
        varchar title
        text body
        varchar link
        tinyint is_read
        char dedupe_key UK
        datetime created_at
    }

    CAMPAIGNS {
        bigint id PK
        varchar name
        varchar slug UK
        varchar channel
        varchar utm_source
        varchar utm_medium
        varchar utm_campaign
        bigint landing_page_id
        varchar status
        decimal budget
        date starts_at
        date ends_at
        text notes
        datetime created_at
        datetime updated_at
    }
```

## Relationship notes

- `USERS`, `POSTS_PROPERTY` and `POSTS_PAGE` are WordPress core tables; arrows are **logical**
  references (no FK constraints) so core deletes/imports are never blocked. Services clean up.
- `LEAD_NOTES` and `LEAD_ASSIGNMENTS` are inside the plugin domain and use real FKs with
  `ON DELETE CASCADE`.
- `FAVORITES.owner_key` carries the uniqueness for both authenticated (`u:{id}`) and guest
  (`v:{token}`) owners because MySQL unique indexes treat `NULL` values as distinct.
- `APPOINTMENTS.active_key` is the concurrency guard for exact-slot double booking; range overlap
  is still checked in SQL.
- `LEADS.dedupe_key` is a lookup aid, not unique, because repeat interest is meaningful and is
  appended as notes rather than rejected.

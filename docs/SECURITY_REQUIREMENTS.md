# ESTORA — Security Requirements & Model

## 1. Trust boundaries

| Boundary | Untrusted input | Control |
| --- | --- | --- |
| Public theme → REST | query params, form bodies, cookies | schema validation, sanitisation, escaping, nonce, honeypot, rate limit |
| Admin JS → REST | bodies, IDs | cookie auth, REST nonce, capability + ownership, schema validation |
| External client → REST | API credentials, bodies | Application Password, capability, validation, rate limit |
| Plugin → MySQL | all values | `$wpdb->prepare()`, allow-listed ORDER BY / column names |
| Media upload | files | MIME + extension allow-list, size cap, `wp_handle_upload`, image validation |

## 2. Authentication

- WordPress core cookie authentication; `X-WP-Nonce` (`wp_rest`) required for state-changing
  same-origin calls. Nonce failure → 403 with `rest_cookie_invalid_nonce`.
- Application Passwords for integrations (HTTPS assumed; documented).
- Login rate limiting: transients keyed by IP+username, 10 failures / 15 min → 429-style lockout
  message, reset on success. Logged, never logs the password.
- Logout, password reset and session handling are WordPress core; no custom auth tables.
- Deactivated users (`estora_active = 0`) cannot log in (filter) and their Application Passwords
  are revoked by capability removal.

## 3. Authorization matrix

| Capability | Admin | Property mgr | Sales mgr | Sales agent | Marketing |
| --- | --- | --- | --- | --- | --- |
| estora_view_properties | ✓ | ✓ | ✓ | ✓ | ✓ |
| estora_edit_properties | ✓ | ✓ | | | |
| estora_delete_properties | ✓ | ✓ | | | |
| estora_publish_properties | ✓ | ✓ | | | |
| estora_view_leads | ✓ | | ✓ | ✓ (own) | |
| estora_view_all_leads | ✓ | | ✓ | | |
| estora_create_leads | ✓ | | ✓ | ✓ | |
| estora_edit_leads | ✓ | | ✓ | ✓ (own) | |
| estora_assign_leads | ✓ | | ✓ | | |
| estora_delete_leads | ✓ | | | | |
| estora_manage_appointments | ✓ | ✓ | ✓ | ✓ (own) | |
| estora_view_reports | ✓ | ✓ | ✓ | | ✓ (marketing) |
| estora_manage_cms | ✓ | | | | ✓ |
| estora_manage_campaigns | ✓ | | | | ✓ |
| estora_manage_settings | ✓ | | | | |
| estora_manage_users | ✓ | | | | |
| estora_view_audit | ✓ | | | | |

Ownership predicate: `estora_view_all_leads` bypasses; otherwise `assigned_agent_id === get_current_user_id()`.
Return 404 (not 403) for out-of-scope lead IDs so agents cannot enumerate records. Appointments
use the same predicate on `agent_id`.

UI hiding is never the control: REST permission callbacks and service guards run regardless.

## 4. Input and output

- Sanitise on write: `sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`,
  `sanitize_key`, `absint`, `floatval`, `wp_unslash`, `wp_kses_post` for CMS rich text only.
- Validate against an explicit schema (type, enum, min/max, required) in `Validator`.
- Escape on render: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, `wp_json_encode` for JS.
- API responses go through `rest_ensure_response`; HTML never returned where JSON is promised.
- JSON-LD built from a whitelisted array and encoded with `wp_json_encode(..., JSON_HEX_TAG|...)`.
- No `eval`, no dynamic `call_user_func` from request data, no unserialise of user input.

## 5. SQL

- Every value bound: `$wpdb->prepare()` or `$wpdb->insert/update/delete` with formats.
- Dynamic SQL limited to identifiers from a fixed allow-list (`ORDER BY` map, table names from
  `Schema::table()`); never `$_GET` into SQL.
- `LIKE` values escaped with `$wpdb->esc_like()`.
- No raw `$wpdb->query()` with interpolation; code review gate (`rg '\$wpdb->(query|get_)'`).

## 6. CSRF

- All admin state changes: nonce in REST header or `check_admin_referer` on `admin-post.php`.
- Public form nonce + honeypot + rate limit (defence in depth; public writes are low privilege).
- Logout is a WordPress nonce-protected link.

## 7. File uploads

Core media handling plus `Upload_Guard`: executable/script extensions (`php`, `phtml`, `phar`,
`pl`, `py`, `sh`, `exe`, `bat`, `js`, `html`, `svg`) are rejected, files over 5 MB are rejected,
`sanitize_file_name` runs, image dimensions are read by WordPress, and gallery IDs are checked to
be image attachments before being saved. Disallowed MIME types are refused by core REST media with
an error response (tested). SVG uploads remain disabled.

## 8. XSS / CSRF / IDOR / SQLi test coverage

| Threat | Test |
| --- | --- |
| Stored XSS in lead name/message | HTTP suite posts `<script>` payload, asserts escaped output and sanitized storage |
| Reflected XSS in search `q` | E2E asserts literal text render |
| CSRF on status change | HTTP suite without nonce/cookie → 401/403, DB unchanged |
| IDOR lead | agent A requests agent B's lead → 404 |
| IDOR appointment | agent A patches B's appointment → 403/404 |
| SQLi in `q` / `orderby` | payload `%' OR 1=1 --` returns empty result, no error leak |
| Privilege escalation | agent posts `/leads/{id}/assign` → 403 |
| Upload abuse | `.php` upload → 422 |
| Information disclosure | public property list never includes draft/private/AI data |
| Rate limit | 6th inquiry in 10 min → 429 |

## 9. Data protection

- No secrets in repo; `.env` ignored; `.env.example` placeholders only.
- PII minimisation in API: public endpoints return agent display name and office phone only.
- IP addresses stored hashed (sha256 with `AUTH_SALT` prefix) for rate limit/audit only.
- Audit never stores passwords, tokens, API keys or full message bodies.
- Deletion: deleting a lead cascades notes/assignments; inquiries keep a statistical row with PII
  nulled by `wp estora purge-inquiries --older-than=365`.
- Export endpoints require `estora_view_reports` and log the export action.

## 10. Transport & deployment hardening (production)

HTTPS only, `DISALLOW_FILE_EDIT=true`, `FORCE_SSL_ADMIN=true`, `WP_DEBUG=false`,
`AUTH_KEY`/`SALT` values unique, database user with no `FILE`/`SUPER`, uploads served without PHP
execution, XML-RPC disabled, REST discovery restricted where appropriate, automatic updates for
core minor versions, backups verified.

## 11. Residual risks (documented, accepted for v1)

- Email is `wp_mail` (deliverability depends on host); failure is logged, not retried.
- Public forms can still be spammed within the limit; no captcha dependency.
- Application Passwords require HTTPS in production; demo runs on HTTP localhost.
- rich-text CMS content is limited to users with `unfiltered_html` (core rule).

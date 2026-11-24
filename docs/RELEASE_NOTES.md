# ESTORA v1.0.0 — Release Notes

Release date: 2026-11-24

## Highlights

- Complete public real-estate website with real server-side search, filtering, sorting and
  pagination, property detail, favorites, 3-way comparison, and a working inquiry/viewing flow.
- Full back office: custom dashboard, lead pipeline (table, drawer, kanban), appointment calendar,
  inventory, reports with CSV exports, campaigns, settings and audit log.
- Five roles and seventeen capabilities with ownership scoping; agents only see their own records.
- Appointment conflict protection with an actual database uniqueness guard for the exact slot plus
  range checks for agent and property.
- Public REST API with consistent envelopes, pagination, validation and error codes, cookie+nonce
  and Application Password authentication.
- SEO: canonical, Open Graph, `RealEstateListing` JSON-LD, core sitemap inclusion, campaign UTM
  attribution.
- Docker stack with migrations, idempotent demo seed, backup/restore scripts, and CI.
- Documentation, ER diagram, API contract, security model, test report and 20 screenshots.

## Fixes made during verification

- CPT meta-capability collision (`estora_edit_properties`) made `current_user_can()` return
  `do_not_allow`; fixed by letting WordPress derive singular meta caps.
- REST nonce generated for user 0 on a public config request; fixed by resolving the cookie session
  before minting the nonce.
- Property updates without a status reset published items to draft; fixed to preserve the current
  status.
- Empty availability value on create rejected; now defaults to `available`.
- Duplicate favorites and notification dedupe verified idempotent.
- Test fixtures are cleaned automatically (`tests/wp/cleanup.php`); demo seed can be rebuilt with
  `wp estora seed --force`.

## Known limitations

- Seeded photography uses local architectural artboards (PNG) and fallbacks; real media upload is
  implemented and tested.
- Email uses `wp_mail`; no SMTP provider is bundled. Failures are logged.
- Single office, single currency, no payments/SMS/MLS/mobile apps.
- CI workflow has not run on GitHub from this environment.

## Upgrade / install

See [`docs/DEPLOYMENT_GUIDE.md`](DEPLOYMENT_GUIDE.md). Existing installs: run
`wp estora migrate --yes`. The database version is stored in `estora_db_version`.

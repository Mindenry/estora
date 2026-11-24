# ESTORA — Acceptance Criteria

Status verified 2026-11-24 against the Docker stack. Evidence is in `docs/TEST_REPORT.md` and the
suites referenced below. `PASS` = executed evidence, `PARTIAL` = delivered but one external
verification remains (stated).

## A. Functional

| ID | Criterion | Evidence | Status |
| --- | --- | --- | --- |
| AC-01 | Homepage: hero search, featured/latest, locations, articles, CTA | E2E visitor journey; home screenshots | PASS |
| AC-02 | Archive filters return only matching rows | HTTP `property listing, filters` | PASS |
| AC-03 | Sort is numeric/correct; pagination bounded; filters persist | HTTP sort/pagination + integration numeric sort | PASS |
| AC-04 | Detail shows reference, gallery, specs, amenities, agent, similar, share, favorite | E2E detail + screenshot | PASS |
| AC-05 | Inquiry creates inquiry + lead with attribution; dedupe works | HTTP inquiry test + integration dedupe | PASS |
| AC-06 | Appointment validates hours/past/conflicts | HTTP appointments + integration | PASS |
| AC-07 | Staff confirm/reschedule/cancel with transitions | HTTP + integration | PASS |
| AC-08 | Favorites guest/user, no duplicates, list/delete | HTTP favorites + integration | PASS |
| AC-09 | Compare holds three and renders | E2E favorite/compare | PASS |
| AC-10 | Property CRUD, publish gate, availability transitions | HTTP property lifecycle | PASS |
| AC-11 | Lead CRUD, notes, assignment history, pipeline, dedupe | HTTP + integration | PASS |
| AC-12 | Agent scope vs manager scope | HTTP IDOR + integration scope | PASS |
| AC-13 | Kanban persists server-side; invalid transitions rejected | E2E board + HTTP 422 | PASS |
| AC-14 | Dashboard KPIs equal SQL counts | Integration dashboard aggregates | PASS |
| AC-15 | CSV exports real rows, permission enforced | HTTP export + E2E link | PASS |
| AC-16 | Notifications dedupe/read state | Integration notifications | PASS |
| AC-17 | Campaign records + attribution counted | HTTP campaign test | PASS |
| AC-18 | About, contact, news, privacy, terms, 404 render | E2E public pages | PASS |
| AC-19 | Roles hold documented capabilities | Integration capability matrix | PASS |
| AC-20 | Deactivated users cannot authenticate | HTTP deactivated login | PASS |

## B. Technical

| ID | Criterion | Evidence | Status |
| --- | --- | --- | --- |
| AC-21 | MySQL persistence across requests/containers | integration + HTTP against MySQL volume | PASS |
| AC-22 | Migrations idempotent with version tracking | `wp estora migrate` repeated; option check | PASS |
| AC-23 | REST namespace documented with envelope | HTTP suite + API docs | PASS |
| AC-24 | Theme change keeps business data/API | switched to Twenty Twenty-Four: leads 200, properties 200, tables true; switched back | PASS |
| AC-25 | Stack starts from clean volumes with documented commands | fresh compose volumes + bootstrap | PASS |
| AC-26 | CI executes real checks | workflow committed; **not run on GitHub from this machine** | PARTIAL |
| AC-27 | PHP syntax/PHPCS no errors | 99 files; PHPCS 0 errors | PASS |
| AC-28 | Unit + integration + HTTP + E2E pass | 37 / 12 / 24 / 16 | PASS |

## C. Security

| ID | Criterion | Evidence | Status |
| --- | --- | --- | --- |
| AC-29 | Anonymous protected routes 401, no data | HTTP | PASS |
| AC-30 | Missing capability 403; missing nonce not authenticated (401) | HTTP | PASS |
| AC-31 | IDOR blocked for leads and appointments | HTTP | PASS |
| AC-32 | SQL injection payload no error/leak | HTTP | PASS |
| AC-33 | Stored XSS escaped/sanitised | HTTP + integration | PASS |
| AC-34 | Executable upload rejected; PNG accepted | HTTP media test | PASS |
| AC-35 | Rate limits return 429 | HTTP inquiry burst | PASS |
| AC-36 | Audit rows for business events | Integration audit | PASS |
| AC-37 | No secrets committed; `.env` ignored | `.gitignore` + repo scan | PASS |

## D. Quality & delivery

| ID | Criterion | Evidence | Status |
| --- | --- | --- | --- |
| AC-38 | Responsive 390/768/1440 without horizontal scroll | Playwright responsive tests + mobile screenshots | PASS |
| AC-39 | Labels bound, alt text, keyboard focus | Playwright accessibility assertions | PASS |
| AC-40 | Loading/empty/error/success states | component implementation + screenshots; empty archive/empty favorites/empty audit | PASS |
| AC-41 | Local images only, no broken images | all assets local; `img[alt]` check | PASS |
| AC-42 | README install reproducible | bootstrap path executed; commands documented for bash and PowerShell | PASS |
| AC-43 | Documentation matches routes/schema | review against implementation | PASS |
| AC-44 | No placeholder handler presented as complete | manual review; only labeled art fallbacks | PASS |
| AC-45 | Screenshots current | 34 files in `docs/screenshots` from the final seed | PASS |
| AC-46 | Release notes + v1.1.0 tag | `docs/RELEASE_NOTES.md`, local tag | PASS |

## E. Back-office experience (v1.1.0)

| ID | Criterion | Evidence | Status |
| --- | --- | --- | --- |
| AC-47 | Root cause of the default wp-admin landing identified | `docs/ADMIN_EXPERIENCE.md` §1: admin-page-only implementation, no login redirect, no app shell/route layer | PASS |
| AC-48 | Dedicated entry point with deep links, refresh, back/forward, 404 | `/estora-admin/`, lead route, HTTP app suite, E2E 404 | PASS |
| AC-49 | Login redirects every staff role to the correct dashboard | 9 app HTTP tests + role E2E; login redirect asserted for six accounts | PASS |
| AC-50 | Permission-aware navigation and server-side route authorization | six-role nav assertions; 403 matrix (settings, users, leads, agents, campaigns) | PASS |
| AC-51 | Dashboard figures and charts come from real data; filters update data | dashboard endpoints match SQL counts; period/custom range wired to API | PASS |
| AC-52 | Quick actions, staff booking, search, notifications, logout function | E2E period + add-lead, staff booking, search, drawer/logout; HTTP notifications | PASS |
| AC-53 | WordPress CMS remains available where authorized; technical admin to Super Admin | marketing posts/pages 200, agent posts blocked; Super Admin WP link and `/wp-admin/` | PASS |
| AC-54 | Responsive app shell and mobile navigation | mobile E2E + `app-mobile-dashboard.png`/`app-mobile-nav.png`; no horizontal overflow | PASS |
| AC-55 | Updated screenshots inspected and defects fixed | 34 captures; chart fills, button labels, skip link and version label corrected | PASS |

## Out of scope for acceptance

Payment processing, SMS, MLS import, multi-tenant SaaS, native apps, external map tiles, content
translation.

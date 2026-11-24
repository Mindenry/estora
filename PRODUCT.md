# ESTORA — Product Context

> Written from the client brief. Where the brief was silent, the assumption is marked **Assumption**.

## Product

ESTORA is a real-estate management platform for a mid-sized brokerage: a public property
website plus an internal back office (inventory, CRM, appointments, reporting) built on
WordPress, PHP 8.3, MySQL 8 and the WordPress REST API.

## Users

| User | Job to be done |
| --- | --- |
| Buyer / renter (anonymous) | Find a property that fits budget and location, save it, ask about it, book a viewing. |
| Sales agent | Work only their assigned leads, log contact, move pipeline stages, see their viewings. |
| Sales manager | Assign and reassign leads, watch conversion, manage the pipeline, cover agent gaps. |
| Property manager | Keep inventory accurate: create, publish, archive, set availability, assign agents. |
| Marketing staff | Publish articles, campaigns and landing content; read campaign attribution. |
| Administrator | Users, roles, settings, audit trail, everything above. |

## Core flows (must work end to end)

1. Property is created in the back office → published → appears in public search.
2. Visitor filters/search → opens a property → submits an inquiry → a lead exists in the CRM with source attribution.
3. Visitor requests a viewing → appointment is pending → staff confirms without double-booking the agent.
4. Manager assigns the lead → agent sees only that lead, adds notes, changes status, schedules follow-up.
5. Lead reaches `won`/`lost` → reporting and dashboard aggregates change.
6. Availability changes (`sold`, `rented`) stop new active inquiries on that property.

## Non-goals

- Multi-tenant SaaS, MLS syndication, mortgage calculation, mobile apps.
- Replacing WordPress authentication with a custom identity provider.

## Constraints

- PHP 8.3+, WordPress 6.7+, MySQL 8, Bootstrap 5, vanilla ES6. No SPA framework.
- Business data survives a theme change (it lives in the plugin).
- Every protected operation is authorized server-side.
- Docker is the only supported local runtime (host has no PHP).

## Business rules that shape the product

- Publication status (`draft → pending → published → archived`) is separate from availability
  (`available / reserved / sold / rented / unavailable`).
- Lead pipeline: `new → contacted → qualified → negotiation → won | lost`, with `lost` reachable
  from any active stage; `won` only from `negotiation`.
- Appointments are 45-minute slots inside configured business hours, Mon–Sat; no two active
  appointments may overlap for the same agent; a property cannot hold two active viewings at once.
- A lead captured from the public site is deduplicated on normalised phone + property within 30 days.
- Agents may only read/write leads assigned to them; managers may reassign.

## Success measures (v1)

- A visitor can complete search → inquiry → appointment without staff help.
- Staff can run the full pipeline without touching the database.
- All automated checks in `docs/TESTING_STRATEGY.md` pass against the Docker stack.
- No fake data paths: dashboards read aggregates from MySQL; forms write rows.

## Brand

Premium, architectural, quiet. Estate-agency trust rather than startup gloss.
No gradients as decoration, no glassmorphism, no emoji, no stock "handshake" clichés.

# ESTORA — Project Overview

## 1. What this is

ESTORA is a production-oriented real-estate management platform delivered as a custom
WordPress theme (`estora-theme`) plus a business plugin (`estora-core`). The public site
presents inventory and captures demand; the plugin owns the inventory rules, the CRM,
scheduling, reporting, notifications, audit trail and REST API.

## 2. Problem

A brokerage typically runs its website on WordPress and its pipeline in spreadsheets. The
website cannot see the pipeline, inquiries rot in an inbox, viewings double-book agents, and
nobody can say which channel produced a sale. ESTORA closes that loop inside one system:
the same database row that powers a public listing also powers the CRM stage, the viewing,
the notification and the report.

## 3. Objectives

| # | Objective | Evidence of completion |
| --- | --- | --- |
| O1 | Public website with real search | `archive-property.php` builds `WP_Query` from sanitised GET params; empty state renders when no rows |
| O2 | Inventory management | CPT + meta + status machine + media; admin screens and REST |
| O3 | CRM | `wp_estora_leads` lifecycle, assignment history, notes, dedupe, scoped reads |
| O4 | Scheduling | `wp_estora_appointments`, server-side conflict detection, calendar views |
| O5 | Back office | Custom ESTORA admin shell, dashboard aggregates, reports, CSV export |
| O6 | RBAC | 5 custom roles, 17 capabilities, ownership checks in services and REST |
| O7 | REST API | `/wp-json/estora/v1/*`, cookie+nonce and application-password auth |
| O8 | Security | nonces, caps, prepared SQL, escaping, upload validation, rate limits, audit log |
| O9 | Quality | PHPUnit unit suite, WP-bootstrapped integration suite, HTTP integration tests, Playwright E2E, PHPCS, CI |
| O10 | Delivery | Docker Compose stack, seed command, docs, screenshots, release notes |

## 4. Actors and surfaces

| Actor | Surface | Scope |
| --- | --- | --- |
| Visitor | Public theme | published properties, inquiry/appointment forms, favorites, compare |
| Sales agent | `/wp-admin/admin.php?page=estora-*` | own leads, own appointments |
| Sales manager | Back office | all leads, assignment, pipeline, reports |
| Property manager | Back office + CPT editor | inventory, availability, agent assignment |
| Marketing | Back office | articles, campaigns, attribution, marketing reports |
| Administrator | Back office | users, roles, settings, audit log |

## 5. End-to-end workflow

```
Property (CPT) ──publish──▶ Public listing ──inquiry──▶ Lead (deduped, sourced)
                                                   │
                                     assign ───────┤
                                                   ▼
                                            Agent follow-up ──▶ Appointment (conflict-checked)
                                                   │                     │
                                                   ▼                     ▼
                                            status transitions      confirmed / completed
                                                   │
                                                   ▼
                                             won / lost ──▶ reports + dashboard
```

## 6. Scope of v1.0.0

In scope: all modules 01–10 of the brief, Docker stack, API, RBAC, tests, docs, CI.
Out of scope: multi-tenant isolation, MLS syndication, payment processing, native apps,
map tile API keys, SMS gateway (email only), translation of content (UI strings are
translatable via text domain).

## 7. Delivery model

Phases 0–11 with an exit gate per phase (`docs/DEVELOPMENT_ROADMAP.md`). A phase is complete
only when its tests are executed and their real output recorded. No module is reported as
complete on the strength of generated code alone.

## 8. Repository map

```
docs/                  specification, decisions, runbooks
docker/                PHP config, MySQL init, wp-cli wrapper
wp-content/themes/estora-theme/   public site
wp-content/plugins/estora-core/   business domain + admin + API
tests/unit/            PHPUnit, no WordPress bootstrap
tests/wp/              integration assertions executed inside WordPress
tests/http/            HTTP/REST integration suite (Node test runner)
tests/e2e/             Playwright journeys
scripts/               bootstrap, seed, backup, restore, screenshots
```

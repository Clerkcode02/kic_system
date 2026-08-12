---
name: marketplace-platform
description: Authoritative architecture spec for the multi-service marketplace platform (Laravel 12 modular-monolith API, React + TypeScript SPA, React Native/Expo mobile app, PostgreSQL + PostGIS, Sanctum, Stripe Connect, Leaflet/OpenStreetMap, Redis/Horizon queues). Use this skill for ANY work on this codebase — writing or reviewing migrations, models, Actions, StateMachines, Policies, FormRequests, API Resources, controllers, queued jobs, scheduler entries, React features/hooks, or React Native screens; and for any question about bookings, quotations, proposals, contracts, milestones, escrow, payouts, disputes, reviews, verification, audit logs, provider availability, radius/geo search, or push notifications. Trigger it whenever the request mentions the marketplace, the SRS, customer/provider/freelancer/admin roles, booking or quotation flow, milestone escrow, Stripe Connect payouts, PostGIS ST_DWithin provider search, `/api/v1` endpoints, or files under `app/Domain/`, `src/features/`, or `mobile/` — even if the person doesn't name the spec. Always consult it BEFORE proposing schema, endpoint, state, or package choices, because this system has already-settled decisions (no Google Maps, no GraphQL/BFF, no WebSockets, no offline sync) that a from-scratch answer will contradict.
---

# Multi-Service Marketplace Platform — Architecture Skill

This skill carries the settled design of a production marketplace platform (Thumbtack/Angi/TaskRabbit/Upwork shape) covering four account types: **customer**, **service provider (business)**, **freelancer**, and **admin**.

The full SRS is preserved verbatim at `references/srs-full.md`. Everything else in `references/` is that same document sliced by concern so you can load only what a task needs.

## How to use this skill

1. **Identify which slice the task touches** using the routing table below, and read that reference file before writing code. Don't work from the summary on this page alone — the reference files hold the actual tables, diagrams, and snippets.
2. **Treat the spec as the source of truth.** If a request conflicts with a decision recorded here, say so and name the conflicting section rather than silently implementing the request. These decisions have downstream consequences (see "Locked decisions" below).
3. **Run the relevant checklist** in `references/checklists.md` before calling a change done — most defects in this design come from skipping the audit-log entry, the Policy, or the server-side recompute, not from getting the happy path wrong.
4. **Where the spec is silent, say it's silent.** The SRS is deliberately scoped. Propose an addition explicitly as an addition instead of inventing it as if it were already specified.

## Routing table

| If the task involves… | Read |
|---|---|
| System topology, request flow, design principles, maps/geocoding strategy, deployment, future scaling | `references/architecture.md` |
| Tables, columns, relationships, PKs, polymorphic shapes, indexes, caching, query performance | `references/data-model.md` |
| Endpoints, REST conventions, Sanctum guards, roles, permissions, policies, middleware | `references/api-and-auth.md` |
| Booking / quotation / project / milestone status transitions, notifications, admin ops, audit trail | `references/workflows.md` |
| PaymentIntents, destination charges, application fees, escrow, refunds, payouts, Stripe webhooks | `references/payments.md` |
| Laravel module layout, Actions, StateMachines, queue jobs, scheduler, file uploads, security | `references/backend-conventions.md` |
| React folder structure, TanStack Query, forms, Leaflet map components, package choices | `references/frontend-conventions.md` |
| React Native/Expo app, token auth, FCM push, device registration, web↔mobile parity | `references/mobile.md` |
| Business rules to enforce (booking, quotation, cancellation, payment, freelance, review, admin) | `references/validation-rules.md` |
| Verifying a change before shipping it | `references/checklists.md` |
| Anything not covered above, or checking a summary against the original | `references/srs-full.md` |

Copy-pasteable artifacts live in `assets/`:

| File | Use |
|---|---|
| `assets/erd.mmd` | Mermaid ERD — regenerate diagrams, or diff against migrations |
| `assets/indexes.sql` | The full indexing strategy, ready to drop into a migration |
| `assets/scheduler.php` | Scheduler definitions for `routes/console.php` |
| `assets/state-machines.mmd` | Booking, quotation, and freelance-project state diagrams in one file |
| `assets/laravel-module-tree.txt` | `app/` directory layout to scaffold against |
| `assets/react-structure.txt` | Web `src/` and mobile `mobile/` directory layouts |

## The shape of the system in one screen

Two first-party clients — a responsive React SPA (all four roles) and a native React Native app (customer/provider/freelancer only; **admin stays web-only**) — consume one versioned Laravel API at `/api/v1`. There is no separate mobile backend.

The backend is a **modular monolith**: `app/Domain/{User,Business,Freelance,Catalog,Booking,Quotation,Payment,Review,Notification,Dispute,Audit}`, each with its own models, actions, events, and policies. Writes go through single-responsibility **Action** classes; every status change routes through a **StateMachine** class that owns the legal transition graph. Side effects (notifications, audit entries, payout scheduling) hang off **domain events** consumed by queued listeners, never inline in the action.

Two revenue paths sit on Stripe Connect: **direct bookings** use destination charges with `application_fee_amount` (funds land on the provider's connected account immediately), while **freelance milestones** use true escrow — charged into the platform balance and transferred out only on milestone approval.

## Locked decisions

Flag any request that contradicts one of these rather than quietly complying:

- **No Google Maps.** Leaflet + OSM tiles via MapTiler/Stadia; Geoapify or LocationIQ for one-time geocoding on form submit only; browser Geolocation / `expo-location` for "near me"; **PostGIS `ST_DWithin`** for radius search — never application-side haversine, never a maps-provider "nearby" API.
- **No GraphQL, no BFF, no mobile-specific backend.** Mobile is a peer consumer of the same REST API.
- **No WebSockets or live chat.** There is no messaging domain; notifications plus TanStack Query refetch/polling are the only near-real-time mechanism.
- **No offline-first sync** on mobile.
- **Modular monolith, not microservices**, for v1 — the `Domain/*` seams exist so extraction stays possible later, not because it's planned now.
- **UUIDv7 primary keys**, `decimal(12,2)` for money (never float), per-row `currency`.
- **`audit_logs` and `booking_status_history` are append-only** at the application layer.
- **Server always recomputes money.** Quotation totals and platform fees are never trusted from the client.
- **Admin is web-only.** Don't design mobile admin screens.

## Working conventions to apply without being asked

- Every write endpoint gets a **FormRequest**; every controller action gets a **Policy**. No ad-hoc ownership `if` statements in controllers.
- Business rules live in the StateMachine/Action (the enforcing copy) and are **mirrored** in the FormRequest for a friendly field-level `422`. A business-rule violation must never surface as a 500.
- `Idempotency-Key` is required on `POST /bookings` and `POST /payments/intents`, from both clients.
- List endpoints use **cursor pagination** and eager-load via `->with([...])` paired to the API Resource.
- New notifications implement `via()` against `notification_preferences`; in-app is always on, other channels are opt-out, and each channel fails independently.
- Critical actions fire an event implementing the `Auditable` marker interface so `RecordAuditEntry` captures the before/after JSONB diff.
- File uploads go S3-direct via presigned URL, then virus-scan, then variant generation — never through the app server.

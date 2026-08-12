# Architecture

Source: SRS §1, §21, §22. Read alongside `mobile.md` for the second client and `data-model.md` for the PostGIS pieces referenced here.

**Contents:** System architecture & design principles · Location/geocoding architecture · Production deployment · Future scalability

---

## 1. System Architecture

The platform is served by two first-party clients sharing one Laravel API: a **responsive React web app** (desktop and mobile browsers, one codebase, breakpoint-adaptive) and, as of this addendum, a **native React Native mobile app** (iOS/Android, installed from the App Store/Play Store) covering the customer, provider, and freelancer roles. **[MOBILE ADDITION]** Admin operations (verification queue, dispute resolution, category management, analytics — see §12) remain web-only; nothing in the original spec described an admin mobile workflow, so that surface was left on the responsive SPA rather than invented for this addition. Both clients consume the same versioned REST API (`/api/v1`) — there is no separate mobile backend, no GraphQL layer, no BFF. The only differences are the Sanctum auth *mode* (cookie for web, bearer token for mobile — §6) and a handful of platform-native UI substitutions for things a browser API can't do on-device (§23).

```
              ┌────────────────────────┐   ┌──────────────────────────────┐
              │     React SPA (TS)      │   │   React Native App (TS)       │  ◄─ MOBILE ADDITION
              │  Responsive web app —   │   │   iOS / Android — Customer /   │
              │  Customer / Provider /  │   │   Provider / Freelancer        │
              │  Freelancer / Admin,    │   │   (native, app-store           │
              │  desktop + mobile       │   │   distributed)                 │
              │  browsers               │   │                                │
              └───────────┬────────────┘   └───────────────┬────────────────┘
                          │ HTTPS/JSON (Axios)               │ HTTPS/JSON (Axios)
                          │ Sanctum SPA cookie                │ Sanctum bearer token
                          └─────────────────┬──────────────────┘
                                            │
                                 ┌──────────▼───────────┐
                                 │   Nginx / Load Bal.   │
                                 └──────────┬───────────┘
                                            │
                     ┌────────────────────────▼────────────────────────┐
                     │              Laravel 12 API (Sanctum)            │
                     │  Modular monolith, domain-driven module split    │
                     └───┬─────────┬─────────┬─────────┬────────┬──────┘
                         │         │         │         │        │
                 ┌───────▼──┐ ┌────▼───┐ ┌───▼────┐ ┌──▼─────┐ ┌▼──────────┐
                 │PostgreSQL│ │ Redis  │ │  S3 /   │ │ Stripe │ │ Geoapify /│
                 │+ PostGIS │ │(cache, │ │ Spaces  │ │  API   │ │ LocationIQ│
                 │(primary) │ │queue,  │ │(files)  │ │        │ │(geocoding)│
                 │          │ │sessions│ │         │ │        │ │           │
                 └──────────┘ └───┬────┘ └─────────┘ └────────┘ └───────────┘
                                  │
                       ┌──────────▼──────────┐
                       │ Laravel Queue Workers │
                       │ (notifications, payouts,
                       │  quotation expiry, etc.)│
                       └──────────┬───────────┘
                                  │
                       ┌──────────▼──────────┐
                       │  Laravel Scheduler   │
                       │ (cron-driven jobs)   │
                       └──────────────────────┘

     External: Mail provider (SES/Postmark), SMS provider (Twilio, optional),
               Web Push (browser Push API via service worker + VAPID,
               delivered through Laravel Notification channels),
               Mobile Push (Firebase Cloud Messaging → APNs/FCM, delivered   ◄─ MOBILE ADDITION
               through the same Laravel Notification channel system),
               map tiles (MapTiler / Stadia Maps, OSM-derived)
```

**Request flow (both clients, identical past the auth guard):** Client (Web SPA or Mobile App) → Nginx → Sanctum guard (cookie or token) → Controller → Policy check → Action/StateMachine (business logic) → Eloquent/PostgreSQL (+ Redis cache, S3 files, Stripe, Geoapify as needed) → API Resource → JSON response. **[MOBILE ADDITION]** No business logic, validation rule, or database table was duplicated or forked to accommodate mobile — the Action classes, StateMachines, Policies, and FormRequests in §3 are called by both clients through the exact same controllers.

**Design principles**
- **Modular monolith** (not microservices) for v1 — one Laravel codebase organized into bounded-context modules (`app/Domain/{Booking,Quotation,Freelance,Payment,User,Notification,Admin}`), each with its own models, services, actions, events, and policies. This keeps deployment simple while preserving clean seams for future extraction into services.
- **CQRS-lite**: write paths go through Action classes (single-responsibility, testable); read paths use dedicated Query classes / API Resources to avoid N+1 and over-fetching.
- **Event-driven side effects**: state transitions (booking accepted, payment captured, milestone approved) fire domain events consumed by queued listeners — this is what powers notifications, audit logging, and payout scheduling without coupling core logic to peripheral concerns.
- **Idempotency** at the API boundary for payment and webhook endpoints (Stripe webhooks, retavailable client actions) via an `idempotency_keys` table. This applies identically to both clients — the mobile app must send the same `Idempotency-Key` header as the web app on `POST /bookings` and `POST /payments/intents`.
- **[MOBILE ADDITION] Client-agnostic API core**: because the API was already versioned, stateless-per-request at the resource layer, and cookie/session logic was isolated behind the Sanctum guard, adding a second client (native mobile) only required a second Sanctum guard mode (§6) plus a mobile push notification channel (§11) — zero backend business logic was duplicated or branched per client.

**Location & geocoding architecture (no Google Maps):** map rendering uses **Leaflet** (`react-leaflet`) with OpenStreetMap-derived tiles served through **MapTiler** or **Stadia Maps** — both are OSM-based with generous free tiers and paid plans that stay cheap at scale; raw `tile.openstreetmap.org` is not usable for production/commercial traffic under OSM's tile usage policy, so a tile provider (or self-hosted tile server, e.g. TileServer GL, once volume justifies it) sits in front of it. Beyond tile rendering, the platform has four distinct location use cases, each handled with the lightest tool that fits rather than one general-purpose maps API:

| Use Case | Approach |
|---|---|
| **Signup / Set Service Address** | **Leaflet** map with a draggable pin as the primary input method, paired with a text address field. The pin can be dragged directly to the exact spot; the text field is only geocoded when the form is submitted, not on every keystroke. |
| **One-time Address → Coordinates** | **Geoapify** or **LocationIQ** (free tier) for one-time geocoding of the submitted address. A few thousand free geocoding requests per month comfortably covers expected signup volume. |
| **Find Providers Near Me** | Browser **Geolocation API** (`navigator.geolocation.getCurrentPosition`) returns the user's latitude/longitude directly in the client — no geocoding call is made for this feature. |
| **Radius Search** | **PostGIS `ST_DWithin`** against the stored `geography(Point)` column (§18) finds nearby providers efficiently. This is the only operation the API executes for provider search — never a naive haversine calculation in application code, and never a second geocoding call. |

Server-side radius search stays on PostGIS regardless of which geocoding provider is used for the one-time address lookup, since it's a database concern, not a maps-API concern.


---

## 21. Production Deployment Recommendations

- **Compute**: containerized Laravel app (Docker) behind an ALB, autoscaling app servers (stateless — sessions/cache in Redis); separate Horizon worker deployment scaled independently from web traffic.
- **Database**: managed PostgreSQL (RDS/Cloud SQL) with automated backups, PITR enabled, read replica for reporting once load justifies it.
- **Cache/Queue**: managed Redis (ElastiCache) — separate logical DBs for cache vs. queue vs. session to avoid eviction collisions.
- **Storage**: S3 (or DO Spaces) with lifecycle rules (move old attachments to infrequent-access tier).
- **CI/CD**: GitHub Actions — lint (Pint, ESLint), static analysis (Larastan, `tsc --noEmit`), test suite (Pest/PHPUnit + Vitest), build, migrate (`php artisan migrate --force` gated behind manual approval for prod), deploy.
- **Zero-downtime deploys**: rolling deploy behind the load balancer; `php artisan down --render` fallback only for breaking migrations.
- **Observability**: Laravel Pulse + an APM (Sentry for errors, plus structured logging shipped to CloudWatch/ELK); Stripe webhook failures alert directly to on-call.
- **Environments**: local → staging (mirrors prod config, Stripe test mode) → production, with feature flags (`laravel/pennant`) for gradual rollout of new modules (e.g., enabling the freelance marketplace per-region).
- **[MOBILE ADDITION] Mobile release pipeline**: EAS Build (Expo) or Fastlane produces signed iOS/Android binaries in CI, submitted to App Store Connect / Google Play Console; JS-only changes ship as over-the-air updates (Expo Updates) without an app-store review cycle, native-code or permission changes go through the normal store review. Mobile builds point at the same staged/prod API base URL as the web app via environment config — no separate mobile API deployment.

---

## 22. Future Scalability Considerations

- **Module → service extraction**: because the codebase is already split into `Domain/*` bounded contexts with events at the seams, the highest-load modules (Payments, Notifications, Search) can be extracted into standalone services first without a rewrite — event bus becomes a real message broker (SQS/Kafka) instead of Laravel's in-process queue.
- **Search**: once category/service count and geo-search volume grow, move discovery off Postgres/PostGIS onto a dedicated search engine (Meilisearch/Elasticsearch) fed by a queued indexer listening to `ServiceUpdated`/`BusinessUpdated` events.
- **Multi-region**: read replicas per region for browse/search traffic; writes stay centralized until true multi-region write requirements emerge (unlikely at thousands-of-users scale).
- **Rate-limited fairness**: as provider volume grows, move from simple per-day booking caps to a proper allocation/queueing system for high-demand categories.
- **Data warehouse**: once the `admin_dashboard_metrics` materialized-view approach outgrows Postgres aggregation, stream events into a warehouse (BigQuery/Snowflake) via CDC (Debezium) for heavier analytics without touching the OLTP database.
- **Progressive Web App**: if offline support or "add to home screen" installability becomes valuable for mobile *browser* users (as opposed to the native app in §23), the existing React SPA can add a service worker and web app manifest — this stays entirely within the browser-based model and requires no app-store distribution or separate codebase.


# CLAUDE.md — Multi-Service Marketplace Platform

This file is loaded automatically into every Claude Code session. It is the operating
manual for this repo. **The SRS (`docs/SRS.md`) is the source of truth for architecture,
entities, and workflows.** If a request contradicts the SRS, stop and ask — do not invent
requirements.

---

## 1. What this is


- **Services & bookings** — customers book verified provider businesses; providers respond
  with quotations; payment on quotation acceptance. **Booking does not require an account**:
  a guest can start on the public landing page and go end to end, including payment. See
  §5 "Guest booking" and SRS §6.1.
- **Freelance marketplace** — clients publish projects; freelancers submit proposals;
  hiring creates a contract broken into milestones paid from escrow. **Account required for
  everything here** — there is no guest path into the freelance side.

Four account types: **Customer, Provider (Business), Freelancer, Administrator.**

### Phase status

- **Phase 1 (current): web only.** Laravel 12 API + responsive React SPA. Admin included.
- **Phase 2 (later): React Native mobile app** for customer/provider/freelancer. Admin
  stays web-only forever.
- Phase 1 code must not block Phase 2. See §9 "Mobile-readiness rules" — treat these as
  hard constraints even though no mobile client exists yet.

---

## 2. Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel 12 (PHP 8.3+) | Modular monolith, REST API only — no Blade UI |
| API | REST, versioned `/api/v1` | No GraphQL, no BFF, no separate mobile backend |
| Frontend | React 18 + TypeScript + Vite | SPA, responsive (one codebase, breakpoint-adaptive) |
| Database | PostgreSQL 16 + **PostGIS** | PostGIS is required, not optional |
| Auth | Laravel Sanctum | SPA cookie mode now; token mode reserved for mobile |
| Cache/Queue/Session | Redis (separate logical DBs) + Horizon | |
| Storage | S3-compatible (AWS S3 / DO Spaces / MinIO local) | Presigned direct upload |
| Payments | Stripe Connect (`stripe/stripe-php`, Payment Element) | |
| Maps | **Leaflet + `react-leaflet`**, OSM tiles via MapTiler/Stadia | |
| Geocoding | Geoapify or LocationIQ (one-time, on form submit) | |
| Calendar | FullCalendar (`@fullcalendar/react`) | |
| Server state | TanStack Query | Never mirror server state into Zustand/Redux |
| Forms | react-hook-form + zod | |
| API docs/types | `dedoc/scramble` → OpenAPI → generated TS types | |
| Mail | **Gmail API (OAuth2)** for production/staging | Not SMTP, not a third-party ESP (SES/Mailgun/Postmark); Mailpit is local-dev only, never a production transport |

### Hard "do not" list

- ❌ **No Google Maps / Google Places / Google Geocoding.** Ever. Leaflet + OSM only.
- ❌ No raw `tile.openstreetmap.org` in production — tiles come from MapTiler/Stadia.
- ❌ No haversine distance math in PHP — radius search is **PostGIS `ST_DWithin`** only.
- ❌ No floats for money — `decimal(12,2)`, currency stored per row.
- ❌ No `Model::unguard()`, no mass-assignment shortcuts — explicit `$fillable`.
- ❌ No raw SQL string interpolation — Eloquent / query builder only.
- ❌ No business logic in controllers — controllers delegate to Actions/StateMachines.
- ❌ No trusting client-computed totals — quotation totals, platform fees, tax are always
  recomputed server-side before persisting or charging.
- ❌ No WebSockets / live chat / offline sync — explicitly out of scope (SRS §23.5).
- ❌ No microservices. Modular monolith for v1.
- ❌ **No read access to a booking authorized by booking number, email, or the two
  combined.** The only key that opens a guest booking is a valid, unexpired, unrevoked
  booking access token scoped to that booking. Failures return **404**, never 403 — the API
  must never confirm that a booking number exists to a caller who can't already read it.
- ❌ **No unauthenticated endpoint outside the guest surface listed in §5.** That surface is
  `POST /bookings` plus `/guest/*`; adding any other public write route is a spec change.
- ❌ **No claiming a guest booking on registration or login.** Claiming happens on **email
  verification only**. An account that registered but never verified claims nothing.
- ❌ No guest fork of a booking Action, StateMachine, or React wizard — guest and registered
  paths share the same code, differing only by the `BookingActor` passed in.
- ❌ No placeholder `users` row for a guest. Guest mail goes out via
  `Notification::route('mail', $email)`.
- ❌ No plaintext booking access token in the database, in a log line, or in the DOM.
- ❌ No SMS integration anywhere — not phone-based OTP, not a phone-verification-via-SMS
  flow, not even a stubbed/log-driver SMS interface. Out of scope, full stop. `phone` on
  the user record stays a plain contact field with no SMS-backed verification wired to it.

---

## 3. Repository layout

```
/                       # monorepo root
├── api/                # Laravel 12
├── web/                # React + TypeScript SPA
├── docs/
│   ├── SRS.md          # source of truth
│   └── PROJECT_SETUP.md
├── docker-compose.yml  # postgres+postgis, redis, minio, mailpit
└── CLAUDE.md
```

### Backend (`api/app/`)

```
app/
├── Domain/                     # bounded contexts — business logic lives HERE
│   ├── User/         Models(User, Address) Actions Policies Events
│   ├── Business/     Models(Business, BusinessDocument, ProviderAvailability) Actions Services
│   ├── Catalog/      Models(Category, Service, ServicePricingTier) Actions
│   ├── Booking/      Models Actions StateMachines/BookingStateMachine Validators
│   ├── Quotation/    Models Actions Jobs
│   ├── Freelance/    Models(FreelancerProfile, PortfolioItem, Project, Proposal,
│   │                        Contract, Milestone, Deliverable) Actions StateMachines
│   ├── Payment/      Models(Payment, Payout, Refund) Services Actions Webhooks
│   ├── Review/       Models Actions
│   ├── Notification/ Notifications Channels
│   ├── Dispute/      Models Actions
│   └── Audit/        Models(AuditLog) Listeners(RecordAuditEntry)
├── Http/
│   ├── Controllers/Api/V1/{Customer,Provider,Freelancer,Admin,Shared}/
│   ├── Requests/               # one FormRequest per write intent
│   ├── Resources/              # one API Resource per read model
│   └── Middleware/             # EnsureVerified, EnsureNotSuspended, RoleMiddleware
├── Policies/
├── Providers/
└── Console/Commands/
```

Rules:
- A Domain module owns its models, actions, events, policies. Cross-module calls go
  through Actions or events — never by reaching into another module's internals.
- **Write path:** Controller → FormRequest → Policy → Action/StateMachine → Eloquent.
- **Read path:** Controller → Query class → API Resource. Always eager load.
- Every status change goes through the module's StateMachine. Never `$model->status = x`.

### Frontend (`web/src/`)

```
src/
├── app/routes/{public,customer,provider,freelancer,admin}/
│                             # `public/` renders with no session: no /auth/me blocking
│                             # first paint, no redirect to login. A 401 from /auth/me is
│                             # the normal anonymous state, not an error.
├── app/providers/            # AuthProvider, QueryClientProvider, ToastProvider
├── features/                 # feature-sliced, mirrors backend Domain modules
│   └── <feature>/{components,hooks,api}/
├── components/               # shared dumb UI (design system)
├── lib/api/                  # axios instance + interceptors + error normalization
├── lib/maps/                 # Leaflet map, geolocation hook, geocoding client
├── lib/calendar/             # FullCalendar config/adapters
├── types/                    # generated from OpenAPI
└── stores/                   # zustand — client-only state, never server state
```

---

## 4. Account types & permissions

Roles via `spatie/laravel-permission` (not a hardcoded enum switch):
`customer`, `provider_owner`, `provider_staff`, `freelancer`, `admin`, `super_admin`.

**Guest** is not a role — it's the absence of one. A guest has no `users` row and is
authorized purely by a booking access token (§5 "Guest booking").

| Action | Guest | Customer | Provider | Freelancer | Admin |
|---|:--:|:--:|:--:|:--:|:--:|
| Browse services / projects | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create booking | ✅ | ✅ | ❌ | – | – |
| Request quotation | ✅ | ✅ | ❌ | – | – |
| View / track own booking | ✅ token only | ✅ | ✅ own | – | ✅ |
| List own bookings | ❌ | ✅ | ✅ own | – | ✅ |
| Send / revise quotation | ❌ | ❌ | ✅ verified only | – | – |
| Accept / reject quotation | ✅ token only | ✅ | – | – | – |
| Pay for booking | ✅ token only | ✅ | – | – | – |
| Cancel own booking | ✅ token only | ✅ | ✅ own | – | ✅ |
| Leave a review | ❌ invited to register | ✅ | – | – | – |
| Save addresses | ❌ | ✅ | – | – | – |
| Publish project (as client) | ❌ | ✅ | – | – | – |
| Submit proposal | ❌ | – | – | ✅ approved only | – |
| Approve milestone / release escrow | ❌ | ✅ (client) | – | – (receives) | audit only |
| Approve/reject business or freelancer | ❌ | ❌ | ❌ | ❌ | ✅ |
| Manage categories / platform fees | ❌ | ❌ | ❌ | ❌ | ✅ |
| Issue refunds | ❌ | ❌ | ❌ | ❌ | ✅ |
| View audit trail | ❌ | ❌ | own scope | own scope | ✅ full |

On top of roles, **every model has a Policy** enforcing ownership
(`$booking->customer_id === $user->id`). No ad-hoc ownership `if`s in controllers.

Protected route middleware order: `auth:sanctum` → `EnsureVerified` → `EnsureNotSuspended`.
Guest routes instead run `ResolveBookingActor`, which tries `auth:sanctum` first and falls
back to the `X-Booking-Token` header.

---

## 5. Domain concepts

### Booking
Customer requests a service at a date/time slot and address. Booking number is a public
human-readable identifier; the PK is a UUIDv7.

States: `Pending → WaitingForQuotation | Scheduled` (fixed-price services skip quoting) →
`QuotationSent → WaitingForCustomer → Accepted` (on payment) `→ Scheduled → InProgress →
Completed`. Terminal: `Declined`, `QuotationExpired`, `Cancelled`, `Refunded`.

Booking validation: no past dates; no double-booking the same provider slot; must be
within provider availability; blocked if provider is suspended or at their daily cap;
service must be active.

### Guest booking
A booking has **exactly one actor**: `customer_id` OR a guest contact triple
(`guest_name`, `guest_email`, `guest_phone`). A DB `CHECK` constraint rejects both-or-neither
— PHP validation is not the enforcement point. The address is a denormalized snapshot on
`bookings` (line1/line2/city/province/postal_code + `service_location geography(Point,4326)`,
GIST-indexed) so a guest needs no `addresses` row; `address_id` stays nullable for
registered users.

- **The state machine graph is unchanged.** Guest is ownership, not state.
- Every booking Action takes `App\Support\ValueObjects\BookingActor`, never a `User`.
  Guest and registered paths run the *same* Actions and StateMachines.
- **Nothing may branch on `customer_id === null`.** Use `Booking::isGuest()`,
  `contactEmail()`, `contactName()`.
- Guests may only: create, view/track, accept/reject quotation, pay, cancel.
- Authorization is a hashed, expiring booking access token in an `X-Booking-Token` header
  (`booking_access_tokens.token_hash`, sha256, unique; plaintext returned exactly once at
  creation and emailed as a tracking link). Constant-time compare. Anything wrong → 404.
- `POST /guest/bookings/lookup` always returns 202 with an identical body; it emails a fresh
  link only on a match.
- Claiming happens on **email verification only**, matching `guest_email_normalized`;
  claimed bookings have their tokens revoked and fire an `Auditable` event.
- Rate limits, TTLs and the captcha flag live in `platform_settings`, not constants.
- `Idempotency-Key` is still required on creation, scoped by `(key, guest_email_normalized)`.
- Audit actor is `guest:<sha256 of normalized email>` in `audit_logs.actor_label`.

### Quotation
A provider's priced response to a booking. Line-itemized: labor, materials, additional
fees, platform fee, tax, discount → total. Has `valid_until` and a `revision_number`.

States: `sent → accepted | rejected | superseded | expired`. A revision creates a **new
row** with `revision_number + 1` and supersedes the old one — quotations are never edited
in place. Expiry is a scheduled sweep, with T-24h/T-2h customer reminders. If a provider
sends no quote within 48h they're reminded daily; the booking auto-expires after 5 days.
Both windows live in `platform_settings` and are admin-configurable.

### Freelance project → proposal → contract → milestone
Client publishes a **Project** (budget range, deadline, category). Freelancers submit
**Proposals** (amount, cover letter, delivery days) — one per freelancer per project.
Client hires one proposal → creates a **Contract** → broken into **Milestones**.

Hiring is **exclusive**: `HireFreelancer` wraps the project + proposal update in a
transaction with `lockForUpdate()` on the project, and auto-rejects all other proposals
with notifications. Milestone amounts must sum to the contract total.

Milestone states: `pending → submitted → approved → paid`, or `submitted → disputed →
submitted` on resubmission. **Money never moves before approval.**

### Payments
Stripe Connect, providers/freelancers are connected accounts.

- **Bookings:** destination charge with `application_fee_amount` — funds land on the
  connected account, platform fee taken at charge time.
- **Freelance milestones:** charge into the **platform balance** (no `transfer_data`),
  hold as escrow, and create a `Transfer` only on milestone approval. This gives a real
  hold for disputed funds.
- `payments` is polymorphic (`payable_type` = booking | milestone).
- Deposits: `deposit_percentage` on the quotation creates a separate `Payment` row with
  `type=deposit`; the remainder is captured on completion.
- Refunds are admin-authorized above a configurable threshold.

**Webhooks:** `StripeWebhookController` verifies the signature, writes the raw event to
`stripe_events` (deduped by `event.id`) **before** dispatching a queued processing job.
Replay-safe and crash-safe.

**Idempotency:** `Idempotency-Key` header is required on `POST /bookings` and
`POST /payments/intents`, backed by an `idempotency_keys` table.

### Reviews
Post-completion only, one per completed transaction, no self-review, provider may reply
once, edit window configurable then locked (`edit_locked_at`). Polymorphic over
booking | project.

### Verification (business & freelancer)
Approving/rejecting a pending business or freelancer profile always goes through
`App\Domain\Business\Actions\{Approve,Reject}BusinessVerification` or
`App\Domain\Freelance\Actions\{Approve,Reject}FreelancerVerification` — never a direct
`$model->update(['verification_status' => ...])`/`['approval_status' => ...])`. These
Actions are the only places that dispatch `BusinessVerificationApproved/Rejected` and
`FreelancerVerificationApproved/Rejected`, which is what drives the SRS §11
`VerificationApproved/RejectedNotification` pair. No admin HTTP endpoint calls these yet
(the admin verification queue UI is unbuilt) — when it is, wire the controller to these
existing Actions instead of re-implementing the status flip inline.

### Audit trail
Single insert-only `audit_logs` table. A global `RecordAuditEntry` listener subscribes to
every domain event implementing the `Auditable` marker interface. Stores actor, dot-notation
action (`booking.status_changed`), auditable type/id, JSONB before/after **diffs** (not full
snapshots), IP, user agent. Never updated or deleted by the app.

`App\Support\Auditable` is not a bare marker — it requires 6 methods (`auditActorId`,
`auditAction`, `auditableType`, `auditableId`, `auditBeforeState`, `auditAfterState`) so the
one global listener can extract a consistent row from any event shape. To make a new critical
action (approval, suspension, refund, payout, status transition, admin override) audited:
1. Implement `Auditable` on the event the Action already dispatches (or add one if it doesn't
   dispatch anything yet), filling in the 6 methods from data the event's constructor already
   carries — add an `?User $actor` constructor param if the event doesn't have one.
2. **Never call `AuditLog::create()` directly from an Action, Job, or controller.**
   `RecordAuditEntry` is the only writer — a manual create sitting next to a dispatch of an
   `Auditable` event produces a duplicate row for the same fact. The one narrow exception is a
   webhook-reaction Action with no corresponding domain event to hang the entry off; even then,
   prefer adding a small event over a manual create.
3. Add the event class to the enumerated list in `tests/Architecture/AuditableEventsTest.php`
   so a future critical event that forgets `Auditable` fails a test instead of going unaudited.

### Location
Four separate concerns, each with the lightest tool:
1. **Set address** — Leaflet map with a draggable pin + a text address field.
2. **Address → coords** — Geoapify/LocationIQ, called **once on form submit**, never per
   keystroke.
3. **"Near me"** — browser `navigator.geolocation`, no geocoding call at all.
4. **Radius search** — PostGIS `ST_DWithin` against `businesses.location
   geography(Point,4326)`, GIST-indexed.

### Market scope
**Canada-only for launch.** All location, map, geocoding, currency, and payment logic
should assume Canada as the sole market unless explicitly told otherwise.

- Leaflet `maxBounds` and default `setView` are scoped to Canada's bounding box.
- Geoapify/LocationIQ geocoding calls are biased/filtered to Canada — never geocode
  against the whole world.
- Currency is CAD only, no multi-currency handling yet.
- Stripe Connect onboarding assumes Canadian connected accounts only.

Don't build multi-country abstractions (currency switching, i18n address formats,
per-country tax/licensing logic) preemptively — Canada-only is a hard constraint for
Phase 1, not a placeholder to generalize around. If a request implies expanding beyond
Canada, stop and ask.

---

## 6. Coding conventions

### PHP / Laravel
- PSR-12, enforced by **Laravel Pint**. Static analysis with **Larastan** (level 6+).
- `declare(strict_types=1);` in every new PHP file. Typed properties, typed returns.
- Actions are single-public-method classes: `public function handle(...): Result`.
- Enums are native PHP backed enums (`BookingStatus: string`), not string literals.
- Money is always `decimal(12,2)` in DB and handled as integer minor units or a Money
  value object in code — never a float.
- All PKs are **UUIDv7** (`Illuminate\Support\Str::uuid7()` / `HasUuids` with a v7 driver).
- Migrations are additive and reversible. Never edit a migration that has run in staging.
- Every write endpoint has a FormRequest. Business-rule violations return `422` with
  field-level messages — never a 500, never a raw exception message.
- Tests are **Pest**. Every Action and StateMachine gets a unit test; every endpoint gets a
  feature test covering happy path + authorization failure + validation failure.

### TypeScript / React
- Strict mode on. No `any`. API types are **generated from OpenAPI** — do not hand-write
  response types.
- Server state: TanStack Query only. Client state: zustand. Never both for the same data.
- Every API call goes through `lib/api` — no bare `fetch`/`axios` in components.
- Components are function components with hooks. Feature logic lives in
  `features/<x>/hooks`, not inside JSX.
- ESLint + Prettier. `tsc --noEmit` must pass in CI.

### Git / workflow
- Conventional commits (`feat(booking): ...`, `fix(payments): ...`).
- One feature module per PR where possible. Migrations and their models ship together.

---

## 7. API conventions

- Base path `/api/v1`. Versioned from day one; breaking changes mean `/api/v2`.
- Cursor pagination on all list endpoints (not offset).
- Consistent envelope: `{ data, meta, links }` via API Resources.
- Errors: `422` validation with `errors.<field>[]`; `403` policy denial; `409` illegal state
  transition; `429` rate limited. Never leak exception traces.
- Rate limits per group: `throttle:auth` and `throttle:payments` are tighter than general API.
- `Idempotency-Key` required on booking creation and payment intent creation.

---

## 8. Queues & scheduled work

Queues split by isolation so a notification burst never delays a payment capture:
`payments` (high) · `payouts` · `notifications` · `reporting` · `default`. Redis-backed,
monitored with Horizon.

Scheduler (all `->withoutOverlapping()->onOneServer()`):
`ExpireStaleQuotationsJob` every 5 min · `ExpireUnquotedBookingsJob` every 15 min ·
`SendBookingReminderJob` hourly · `RunProviderPayoutJob` daily 02:00 ·
`GenerateAdminAnalyticsSnapshotJob` hourly · audit archive monthly · backup daily 01:00.

---

## 9. Mobile-readiness rules (Phase 2 enablers — enforce now)

These cost nothing today and prevent a rewrite later:

1. **API is the only contract.** No server-rendered views, no session-dependent business
   logic, no controller that behaves differently for the SPA.
2. **Auth is guard-agnostic.** `POST /auth/login` returns a `Set-Cookie` for stateful
   origins and a JSON `{ token }` for everything else. Build both branches now, even
   though only the cookie path is exercised. Policies and controllers read
   `$request->user()` and never care which guard resolved it. The guest booking token is a
   third credential mode (`X-Booking-Token`), also header-based and equally guard-agnostic;
   `lib/api` picks between cookie / Bearer / booking-token in **one** module.
3. **Device-scoped tokens from day one** — `POST /auth/logout` (current device),
   `POST /auth/logout-all-devices`. Token names carry a device label.
4. **No cookie/CSRF assumptions inside domain code.** Session handling stays behind the
   Sanctum guard and middleware.
5. **Presigned direct-to-S3 uploads**, never multipart-through-the-app-server — this works
   identically from a native camera roll.
6. **Coordinates in, coordinates out.** Endpoints accept raw lat/lng; they never care
   whether it came from `navigator.geolocation` or device GPS.
7. **Notification channels are pluggable.** Build `via()` off a `notification_preferences`
   table so adding an FCM channel later is one class + one column.
8. **Publish the OpenAPI spec** and generate client types from it, so a second client
   inherits the contract for free.
9. **Keep the web-specific libraries (Leaflet DOM, FullCalendar) inside
   `lib/` wrappers**, not scattered through features — Phase 2 swaps the wrapper.

---

## 10. Environment

Local dev runs on Docker Compose: PostgreSQL+PostGIS, Redis, MinIO (S3), Mailpit (mail).
Stripe in test mode via Stripe CLI for webhook forwarding.

See `docs/PROJECT_SETUP.md` for the full env var list and third-party account checklist.

---

## 11. When working in this repo

- Read `docs/SRS.md` before implementing anything you haven't seen before.
- If the SRS is silent or ambiguous on a requirement, **ask** — do not fill the gap with
  assumptions from other marketplace products.
- Prefer extending an existing Domain module over creating a new one.
- After changing an endpoint, regenerate the OpenAPI spec and the frontend types.
- Run `composer pint`, `composer larastan`, `php artisan test`, and `npm run typecheck`
  before declaring a task done.

### Pest test-isolation gotchas (backend)

- **Global helper functions belong in `tests/Pest.php`, never in a single test file.**
  A `function foo(): ... {}` declared at the top of one test file is only visible to
  another file once PHP has loaded the first one — true by accident under serial
  execution (alphabetical-ish load order) and false under `--parallel` (separate worker
  processes). If a fixture helper (`authHeader()`, `bookingCustomer()`, etc.) is used
  from more than one test file, it must live in `tests/Pest.php`.
- **The `array` cache driver (`CACHE_STORE=array` in `phpunit.xml`) lives for the whole
  PHP process, not per test.** Unlike Redis in every real environment, a value cached by
  one test (e.g. `CategoryTreeCache::remember()`) survives `RefreshDatabase` and leaks
  into a later test. `tests/TestCase::setUp()` calls `Cache::flush()` for this reason —
  don't remove it.
- **`ConcurrentBookingTest` and `ConcurrentHireTest` fork real child processes and
  commit real rows outside `RefreshDatabase`** (a transaction-wrapped test can't see
  another connection's writes, which is the whole point of those tests). They clean up
  what they can, but `ConcurrentBookingTest`'s winning booking can never be deleted —
  `booking_status_history` is append-only by DB trigger, and that FK chain blocks it.
  Any new test that asserts an exact global count/position (e.g. "the categories table
  has 0 rows", "the first item in the list is X") is asserting something that can be
  true or false purely depending on suite run order. Assert against a **baseline**
  captured with the same query the endpoint/job itself uses instead (see
  `AdminCategoryTreeTest` and `GenerateAdminAnalyticsSnapshotJobTest` for the pattern).

## 12. Maintaining this file
   - If you discover an important project convention, gotcha, or repeated correction during a session, propose adding it to CLAUDE.md before the session ends.

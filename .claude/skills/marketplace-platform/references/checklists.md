# Checklists

Derived from the cross-cutting requirements in SRS §3, §6, §13, §14, §16, §17, §18, §19. Run the relevant list before calling a change done. Most defects in this design come from omitting a cross-cutting concern (audit entry, Policy, server-side recompute), not from getting the happy path wrong.

## New or changed API endpoint

- [ ] Route lives under `/api/v1` and in the right role controller namespace (`Http/Controllers/Api/{Customer,Provider,Freelancer,Admin}`)
- [ ] Behind `auth:sanctum` → `EnsureVerified` → `EnsureNotSuspended` unless deliberately public
- [ ] A dedicated **FormRequest** validates every input — no validation inline in the controller
- [ ] A **Policy** method authorizes it, checking ownership as well as role
- [ ] Write path delegates to an **Action** class; the controller stays thin
- [ ] Response goes through an **API Resource**, not a raw model or array
- [ ] List endpoints use cursor pagination and eager-load via `->with([...])`
- [ ] Validation failures return `422` with field-level messages; no raw exception leaks
- [ ] Rate-limit group applied (`throttle:auth` / `throttle:payments` are tighter than general API)
- [ ] Works identically for both clients — cookie guard (web) and bearer token (mobile) resolve `$request->user()` the same way
- [ ] If it's a payment or booking creation endpoint, `Idempotency-Key` is required and honored

## New or changed status transition

- [ ] The transition is added to the owning **StateMachine** class, not asserted ad hoc in a controller or job
- [ ] Illegal transitions raise the typed exception rather than silently no-op'ing
- [ ] The transition graph still matches the diagram in `workflows.md` — update the diagram if the change is intentional
- [ ] A domain event fires; side effects (notifications, payouts, rating sync) live in queued listeners, not in the action
- [ ] The event implements the `Auditable` marker so `RecordAuditEntry` captures the before/after JSONB diff
- [ ] Status history rows are inserted, never updated (append-only)
- [ ] The mirrored FormRequest rule is updated so the client gets a friendly `422` for the now-illegal case

## New migration

- [ ] UUIDv7 primary key
- [ ] Money columns are `decimal(12,2)`, never float; `currency` char(3) stored per row
- [ ] Foreign keys constrained, with the intended `onDelete` behavior chosen deliberately
- [ ] Indexes added for the query patterns this table actually serves — cross-check `../assets/indexes.sql`
- [ ] Geo columns are `geography(Point,4326)` with a GIST index; searched via `ST_DWithin`, never haversine
- [ ] `$fillable` set explicitly on the model (no `unguard()`)
- [ ] Sensitive fields (government ID numbers, etc.) use Laravel encrypted casts
- [ ] Append-only tables are protected at the model or trigger level against `update()`/`delete()`
- [ ] The ERD in `../assets/erd.mmd` is updated to match

## New queued job

- [ ] Assigned to the correct queue: `payments` (high priority), `payouts`, `notifications`, `reporting`, or `default` — a notification burst must never delay payment capture
- [ ] Idempotent, or keyed so a retry can't double-charge, double-transfer, or double-notify
- [ ] Failure path considered: retries, backoff, and what a permanent failure in `failed_jobs` should alert on
- [ ] If scheduled, `->withoutOverlapping()` and `->onOneServer()` are set, and the entry is added to `../assets/scheduler.php`

## Anything touching money

- [ ] Totals, platform fees, and tax are **recomputed server-side** before persisting or charging — client-supplied amounts are never trusted
- [ ] The operation is idempotent on the Stripe intent/transfer ID
- [ ] Correct Connect model used: destination charge with `application_fee_amount` for bookings, platform-balance escrow with a manual transfer for milestones
- [ ] Payout is blocked until the completion/approval condition in `validation-rules.md` is met
- [ ] Webhook path verifies the Stripe signature, stores the raw event first (dedupe by `event.id`), then dispatches a queued job
- [ ] An audit log entry is written for the transaction
- [ ] Refunds above the configured threshold require admin authorization

## New notification

- [ ] One `Illuminate\Notification` class, with `via()` driven by the user's `notification_preferences`
- [ ] In-app (database) channel is always on; mail, SMS, web push, and mobile push are opt-out per category
- [ ] Each channel is dispatched with independent try/catch — one channel failing must not block the others
- [ ] Mobile push resolves device tokens from `device_tokens`; tokens FCM reports as unregistered are pruned on send failure
- [ ] Sent from a queued listener on a domain event, never inline in the request path

## New file upload path

- [ ] Client uploads directly to S3 via a presigned URL from the API — the file never transits the app server
- [ ] Metadata row written to the polymorphic `attachments` table
- [ ] File is not served to anyone until the queued virus scan marks it `available`
- [ ] Downloads go through a per-request, short-TTL signed URL that is policy-checked first
- [ ] Image variants generated in a queued job, not on the request path
- [ ] The same endpoint serves web and mobile (camera/gallery) without branching

## Frontend feature (web or mobile)

- [ ] Feature-sliced under `features/<domain>/` with `components/`, `hooks/`, `api/` — mirroring the backend domain
- [ ] Server state via TanStack Query; never mirrored into Zustand/Redux
- [ ] Types come from the generated OpenAPI types, not hand-written duplicates
- [ ] Forms use `react-hook-form` + `zod`, mirroring the server FormRequest rules
- [ ] Maps use the shared `react-leaflet` draggable-pin component (hosted in `react-native-webview` on mobile) — geocoding fires on submit only, never per keystroke
- [ ] "Near me" reads coordinates from `navigator.geolocation` (web) or `expo-location` (mobile) and posts them to the same endpoint
- [ ] Mobile stores the Sanctum token in the OS keychain, never `AsyncStorage`
- [ ] Feature exists on the intended surfaces — admin features are web-only by design

## Before proposing an addition to the spec

- [ ] Confirmed it isn't already covered — check `srs-full.md`, not just the sliced references
- [ ] It doesn't contradict a locked decision in SKILL.md (no Google Maps, no GraphQL/BFF, no WebSockets, no offline sync, no mobile admin)
- [ ] Stated plainly that it's an addition rather than presenting it as existing spec
- [ ] Named the backend surface it touches: new table, new endpoint, new channel, new state — or explicitly "none"

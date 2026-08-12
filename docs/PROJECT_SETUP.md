# Project Setup — Accounts, Secrets, Prerequisites, Risks

Companion to `CLAUDE.md` and `BUILD_PROMPTS.md`. Save as `docs/PROJECT_SETUP.md`.

---

## 1. Third-party accounts to create

| Service | Why | When you need it | Cost signal |
|---|---|---|---|
| **Stripe** (+ **Connect** enabled) | Payments, escrow, payouts to providers/freelancers | Before Phase 7 | Free to start; Connect has per-payout fees. Connect onboarding review can take days — **apply early** |
| **MapTiler** or **Stadia Maps** | OSM tile hosting. Raw `tile.openstreetmap.org` is not permitted for production traffic | Before Phase 9.2 | Generous free tier |
| **Geoapify** or **LocationIQ** | One-time address→coords geocoding on form submit | Before Phase 9.2 | Free tier covers signup volume |
| **S3-compatible storage** (AWS S3 or DigitalOcean Spaces) | Documents, portfolios, deliverables, booking photos | Before staging (MinIO locally) | Cheap |
| **Email provider** (AWS SES or Postmark) | Transactional mail. Both need domain verification + SES needs sandbox exit | Before staging | SES cheapest; Postmark better deliverability out of the box |
| **Sentry** | Error tracking | Phase 10.3 | Free tier fine |
| **GitHub** | Repo + Actions CI | Day 1 | |
| **Twilio** (optional) | SMS OTP + notifications | Whenever you enable SMS | Pay per message |
| **Domain + TLS** | Cookie auth requires the SPA and API to share a parent domain (`app.x.com` / `api.x.com`) | Before staging | |
| **Apple/Google developer accounts** | Only for Phase 2 mobile | Later | $99/yr + $25 one-time |

---

## 2. Local prerequisites

- Docker + Docker Compose
- PHP 8.3+ with `pdo_pgsql`, `redis`, `gd`/`imagick`, `bcmath`, `intl`
- Composer 2
- Node 20+ and npm
- **Stripe CLI** (`stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe`) — you cannot develop the payment flow without it
- `psql` client (optional but useful)
- Claude Code

---

## 2.1 First run — local stack

The local stack (`docker-compose.yml` at the repo root) brings up Postgres+PostGIS, Redis,
MinIO (S3), and Mailpit. It comes up **before** `api/` and `web/` are scaffolded — it has
no dependency on either.

1. Copy the compose env file and adjust credentials if you want non-default ones:
   ```bash
   cp .env.example .env
   ```
2. Start everything:
   ```bash
   make up          # or: docker compose up -d
   ```
3. Confirm all services are healthy:
   ```bash
   docker compose ps
   ```
   Every service should show `healthy`. `createbuckets` is a one-shot job — it exits `0`
   after creating the `marketplace-local` bucket and won't show as running; check its exit
   code with `docker compose logs createbuckets` if the bucket isn't there.
4. Verify PostGIS is actually installed in the database:
   ```bash
   make psql
   ```
   ```sql
   CREATE EXTENSION IF NOT EXISTS postgis;
   SELECT postgis_version();
   ```
5. Open the MinIO console at http://localhost:9001 (see §2.2 for credentials) and confirm
   the `marketplace-local` bucket exists.
6. `make fresh` wipes all named volumes (`down -v`) and starts clean — use it when you need
   a truly empty database/bucket, not for routine restarts (`make down` / `make up` is
   enough for those and preserves data).

## 2.2 Reaching each service

| Service | URL / address | Credentials | Notes |
|---|---|---|---|
| Postgres + PostGIS | `localhost:5432` | `DB_USERNAME` / `DB_PASSWORD` from `.env` (default `marketplace` / `marketplace`), db `marketplace` | `make psql` opens a shell |
| Redis | `localhost:6379` | none (local dev only) | DB 0 = cache, 1 = queue, 2 = session (app-level split, not container-level). `make redis-cli` opens a shell |
| MinIO API (S3-compatible) | http://localhost:9000 | `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` from `.env` | This is `AWS_ENDPOINT` in `api/.env` |
| MinIO Console | http://localhost:9001 | same as above | Browse/manage the `marketplace-local` bucket |
| Mailpit SMTP | `localhost:1025` | none | This is `MAIL_HOST`/`MAIL_PORT` in `api/.env` |
| Mailpit UI | http://localhost:8025 | none | View every email the app sends locally |

---

## 3. Environment variables

### `api/.env`

```dotenv
APP_NAME="Marketplace"
APP_ENV=local
APP_KEY=                      # php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
FRONTEND_URL=http://localhost:5173

# Database (PostGIS required)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marketplace
DB_USERNAME=marketplace
DB_PASSWORD=

# Redis — separate logical DBs so cache eviction never eats queued jobs
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CACHE_DB=0
REDIS_QUEUE_DB=1
REDIS_SESSION_DB=2
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Sanctum / session — critical for cookie auth
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false      # true in staging/prod
SESSION_SAME_SITE=lax

# Storage (MinIO locally, S3/Spaces in prod)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=marketplace-local
AWS_ENDPOINT=http://localhost:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# Stripe
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CONNECT_CLIENT_ID=ca_...
PLATFORM_FEE_PERCENT=10          # seed value; authoritative copy lives in platform_settings

# Geocoding (NOT Google)
GEOCODER_DRIVER=geoapify
GEOAPIFY_API_KEY=
LOCATIONIQ_API_KEY=

# Mail
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS=no-reply@marketplace.local

# Web push (generate a VAPID keypair)
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:ops@marketplace.local

# Optional
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
SENTRY_LARAVEL_DSN=
```

### `web/.env`

```dotenv
VITE_API_URL=http://localhost:8000/api/v1
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
VITE_MAP_TILE_URL=https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=...
VITE_MAP_ATTRIBUTION=© OpenStreetMap contributors © MapTiler
VITE_GEOCODER_PROXY=/geocode        # prefer proxying through Laravel to hide the key
VITE_VAPID_PUBLIC_KEY=
```

### Never in `.env`, always in a secrets manager (prod)

`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `AWS_SECRET_ACCESS_KEY`, `APP_KEY`, DB password, `VAPID_PRIVATE_KEY`, geocoding keys, Twilio token. Use AWS Secrets Manager / SSM Parameter Store per SRS §17, and rotate.

---

## 4. Gaps in the SRS you should decide before Phase 5

These are genuinely underspecified — resolve them now rather than letting Claude Code guess mid-build.

1. **Cancellation fee policy.** The SRS says "fee per policy" but never defines the policy. You need concrete tiers (e.g. free >48h, 25% 24–48h, 50% <24h) and who absorbs the Stripe fee on a partial refund.
2. **Tax.** `tax_amount` exists on quotations with no calculation rule. Is it a flat rate, per-jurisdiction, provider-supplied, or Stripe Tax? Marketplace VAT/sales-tax obligations vary sharply by country — this is a legal decision, not a technical one.
3. **Currency.** Money rows carry a `currency` column but there is no multi-currency logic. Pick a single launch currency and treat the column as future-proofing.
4. **Who pays the platform fee** — is it deducted from the provider's payout or added on top of the customer's total? This changes every displayed number and every test fixture.
5. **Refund flows through escrow vs. destination charges** differ substantially in Stripe. Decide the refund policy for a milestone already transferred out.
6. **`provider_staff` role** appears in SRS §6 but no table models a staff↔business membership. Either add a `business_user` pivot or drop the role for v1.
7. **Deposit remainder capture.** The SRS says the remainder is "captured on completion" — decide whether that's an automatic off-session charge (needs saved payment method + SCA handling) or a second customer-initiated payment.
8. **Review reciprocity.** Can providers review customers? The schema allows it (`reviewer_id`/`reviewee_id`) but no workflow describes it.
9. **Disputes vs. Stripe disputes.** Your `disputes` table and Stripe chargebacks are different things that will collide. Define how a `charge.dispute.created` webhook maps onto your internal dispute record.
10. **Freelancer "approved" status.** Providers have `verification_status`; freelancers need an equivalent gate since SRS §6 says "approved only" can submit proposals. Confirm the column.

---

## 5. Risks worth pricing in now

- **Stripe Connect onboarding is the long pole.** Platform account review, KYC for connected accounts, and payout capability can take days to weeks. Apply on day one, not in Phase 7.
- **Escrow is a regulated-adjacent word.** Holding client funds on your platform balance pending milestone approval is a real money-transmission consideration in some jurisdictions. Get advice before launch; Stripe's terms also constrain how long you can hold.
- **PostGIS + managed Postgres.** Confirm your target managed provider supports the PostGIS extension version you develop against (RDS and Cloud SQL do; some smaller providers don't). Pin the version in Docker to match.
- **UUIDv7.** Laravel's `HasUuids` historically defaults to v4 ordered UUIDs. Verify you're actually getting v7 and add a test asserting monotonicity, or index bloat will surprise you at scale.
- **Cookie auth across subdomains.** Get `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, CORS `supports_credentials`, and SameSite right early. This is the single most common Laravel+SPA time sink, and it gets worse if the SPA and API live on unrelated domains.
- **Scope size.** Bookings + quotations + a full freelance marketplace + escrow + admin + audit is genuinely two products. Consider shipping the booking/quotation half first behind a Pennant flag and enabling freelance later — Prompt 10.3 sets that flag up for exactly this reason.
- **Webhook-driven state.** Payment truth arrives asynchronously. Every UI that shows "paid" must poll or refetch, never assume. Local dev without the Stripe CLI running will silently look broken.
- **Virus scanning** (SRS §16) needs a real implementation — ClamAV in a sidecar container or a cloud AV API. Budget time for it; it's easy to leave as a permanent stub.
- **Two clients later means the OpenAPI spec is load-bearing.** If it drifts, mobile pays the cost. The CI staleness check in Prompt 10.3 is not optional.

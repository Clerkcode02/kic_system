# API Design, Authentication & Authorization

Source: SRS §5 and §6. Mobile's token-mode guard is detailed further in `mobile.md`.

**Contents:** Endpoint table by domain · REST conventions · Sanctum guards (web cookie / mobile token) · Roles & permissions · Policies & middleware · Role permission matrix

---

## 5. API Design (RESTful)

Base: `/api/v1`. Versioned from day one. All authenticated routes via Sanctum, using SPA cookie mode for the responsive web app (the sole client).

| Domain | Method | Endpoint | Purpose |
|---|---|---|---|
| Auth | POST | `/auth/register/customer` | Customer signup |
| | POST | `/auth/register/business` | Business signup (starts verification) |
| | POST | `/auth/register/freelancer` | Freelancer signup |
| | POST | `/auth/login` / `/auth/logout` | Session |
| | POST | `/auth/email/verify/{id}/{hash}` | Email verification |
| | POST | `/auth/otp/verify` | Phone OTP verification |
| Catalog | GET | `/categories` | List categories (tree) |
| | POST | `/admin/categories` | Admin creates category (no-code) |
| | GET | `/services?category=&location=&sort=` | Browse services |
| | GET | `/services/{id}/pricing` | Estimated pricing before booking |
| Booking | POST | `/bookings` | Create booking request |
| | GET | `/bookings/{id}` | Booking detail incl. status history |
| | PATCH | `/bookings/{id}/cancel` | Cancel with reason |
| | GET | `/providers/{id}/availability?date=` | Availability check (drives calendar UI) |
| Quotation | POST | `/bookings/{id}/quotations` | Provider sends quotation |
| | POST | `/quotations/{id}/accept` | Customer accepts → triggers payment intent |
| | POST | `/quotations/{id}/reject` | Customer rejects |
| | POST | `/quotations/{id}/revise` | Provider sends revised quote |
| Freelance | POST | `/projects` | Client publishes project |
| | GET | `/projects?category=&budget_min=&budget_max=` | Freelancer browses |
| | POST | `/projects/{id}/proposals` | Submit proposal |
| | POST | `/proposals/{id}/hire` | Client hires → creates Contract |
| | POST | `/milestones/{id}/submit` | Freelancer submits deliverable |
| | POST | `/milestones/{id}/approve` | Client approves → releases escrow |
| Payments | POST | `/payments/intents` | Create Stripe PaymentIntent |
| | POST | `/webhooks/stripe` | Stripe webhook receiver (signature-verified) |
| | GET | `/providers/me/earnings` | Earnings/payout ledger |
| Reviews | POST | `/bookings/{id}/reviews` | Leave review (post-completion only) |
| | POST | `/reviews/{id}/reply` | Provider reply |
| Admin | POST | `/admin/businesses/{id}/approve` \| `/reject` | Verification decisions |
| | GET | `/admin/dashboard/metrics` | Aggregated analytics |
| | GET | `/admin/disputes` / `POST /admin/disputes/{id}/resolve` | Dispute queue |
| | GET | `/admin/audit-logs?actor=&action=&date=` | Audit trail viewer |

Conventions: cursor pagination on list endpoints; `422` for validation with field-level messages (never raw exceptions); `Idempotency-Key` header required on POST `/payments/intents` and `/bookings`.


---

## 6. Authentication & Authorization

- **Sanctum SPA mode** for the React web app (cookie-based, CSRF-protected).
- **[MOBILE ADDITION] Sanctum API token mode** for the React Native app (Bearer `personal_access_tokens`, one named/revocable token per device, e.g. `"iPhone 14 — John's iPhone"`). Both guards sit on the same `auth:sanctum` middleware, so every controller, Policy, and FormRequest below applies unchanged regardless of which client called it — only the guard used to resolve `$request->user()` differs. Login (`POST /auth/login`) returns a `Set-Cookie` for web requests (origin in Sanctum's `stateful` domain list) and a JSON `{ token }` for mobile requests (any other client); `POST /auth/devices/logout` revokes the calling device's token only, `POST /auth/logout-all-devices` revokes every token for the user (e.g. "lost my phone"). Mobile stores the token in the OS keychain (`expo-secure-store` / Android Keystore via `react-native-keychain`), never in plain `AsyncStorage`.
- **Roles**: `customer`, `provider_owner`, `provider_staff`, `freelancer`, `admin`, `super_admin` — implemented with `spatie/laravel-permission` (roles + granular permissions), not a hardcoded enum switch, so admins can adjust permission sets without deploys.
- **Policies** per model (`BookingPolicy`, `QuotationPolicy`, `ProjectPolicy`, …) enforce ownership (`$booking->customer_id === $user->id`) on top of role-based gates.
- **Middleware stack** on protected routes: `auth:sanctum` → `EnsureVerified` (blocks unverified providers/freelancers from write actions) → `EnsureNotSuspended`.

### Role & Permission Matrix (excerpt)

| Action | Customer | Provider (Business) | Freelancer | Admin |
|---|:---:|:---:|:---:|:---:|
| Browse services / projects | ✅ | ✅ | ✅ | ✅ |
| Request quotation | ✅ | ❌ | – | – |
| Send quotation | ❌ | ✅ (verified only) | – | – |
| Accept booking payment | ✅ | – | – | – |
| Publish project | ✅ (as client) | – | – | – |
| Submit proposal | – | – | ✅ (approved only) | – |
| Approve milestone / release payment | – | – | – (receives) | ✅ audit only |
| Approve business/freelancer | ❌ | ❌ | ❌ | ✅ |
| Manage categories | ❌ | ❌ | ❌ | ✅ |
| Manage platform fees | ❌ | ❌ | ❌ | ✅ |
| Issue refunds | ❌ | ❌ | ❌ | ✅ |
| View audit trail | ❌ | own scope only | own scope only | ✅ full |


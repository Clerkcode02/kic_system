# Mobile Application (React Native / Expo)

Source: SRS §23, plus the [MOBILE ADDITION] markers in §1, §6, §11, §16, §20, §21. The mobile client is additive — it introduced only a `device_tokens` table and one column on `notification_preferences`. No Action, Policy, StateMachine, endpoint, or other table was changed or forked for it.

**Contents:** Scope (admin excluded) · Client→API→data flow · Project structure · Web/mobile requirement mapping · Explicitly excluded scope

---

## 23. Mobile Application Architecture — [MOBILE ADDITION]

This section documents the native mobile client added to the platform. Everything here is additive: no table, endpoint, Action, Policy, or StateMachine described in §1–§22 was changed to accommodate it, aside from the two small additive tables noted below (`device_tokens`, and one new column on `notification_preferences`). Where the mobile app cannot use a browser-only mechanism the original spec relied on (Leaflet as a DOM library, `navigator.geolocation`, FullCalendar, browser Push API), it substitutes the closest platform-native equivalent while still calling the same backend endpoint.

### 23.1 Scope

The mobile app implements the **customer, provider, and freelancer** experience end-to-end (browse, book, quote, pay, message-free notifications, manage projects/milestones, review). **Admin (§12) is intentionally out of scope for mobile** — verification queue, dispute resolution, category management, and the analytics dashboard remain on the responsive web SPA, since nothing in the original spec described those as mobile-first workflows and they're inherently desktop-oriented tools (data tables, bulk actions, charts).

### 23.2 Client → API → Business Logic → Data flow

```
React Native App (iOS/Android)
        │  HTTPS/JSON, Sanctum Bearer token, Idempotency-Key header on writes
        ▼
Laravel 12 API (/api/v1)  ── identical routes, FormRequests, Resources as web (§5)
        │
        ▼
Sanctum guard (token mode) → EnsureVerified → EnsureNotSuspended → Policy check
        │
        ▼
Domain Action / StateMachine (§3)  ── same classes the web client invokes
        │
        ▼
PostgreSQL + PostGIS · Redis (cache/queue) · S3 (files) · Stripe · Geoapify/LocationIQ
```

No new services, no mobile-specific database, no GraphQL/BFF layer sits in this path — the mobile app is simply another authenticated consumer of the §5 API.

### 23.3 Project structure (mirrors the existing React folder structure in §4)

```
mobile/
├── app/                         # Expo Router / navigation, providers
│   ├── (customer)/
│   ├── (provider)/
│   └── (freelancer)/
│   └── providers/ (AuthProvider — token-based, QueryClientProvider, PushProvider)
├── features/                    # same feature-sliced split as web src/features/
│   ├── auth/                    # login/register, secure token storage
│   ├── booking/
│   │   ├── components/ (BookingWizard, ScheduleStep — react-native-calendars,
│   │   │                 LocationStep — Leaflet-in-WebView draggable pin)
│   │   ├── hooks/ (useCreateBooking, useBookingStatus — same TanStack Query hooks pattern)
│   │   └── api/ (bookingApi.ts — same request shape as web's bookingApi.ts)
│   ├── quotation/ ├── catalog/ ├── freelance/ ├── payments/ (Stripe RN SDK)
│   ├── reviews/ └── notifications/ (push permission + device registration)
├── components/                  # shared/dumb UI, native equivalents of the web design system
├── lib/
│   ├── api/ (axios instance + interceptors — attaches Bearer token, not cookies)
│   ├── maps/ (WebView wrapper embedding the same react-leaflet component as web,
│   │          expo-location for device GPS, Geoapify/LocationIQ client — reused as-is)
│   └── push/ (expo-notifications registration → POST /devices/register)
└── types/                       # same generated OpenAPI types as web (shared package if a monorepo)
```

### 23.4 Mobile-specific requirement mapping

| Requirement | Web (existing) | Mobile (addition) | Backend change |
|---|---|---|---|
| Authentication | Sanctum SPA cookie | Sanctum API token, per-device, revocable | New guard mode (native to Sanctum) — §6 |
| API communication | Axios → `/api/v1`, same Resources | Same Axios pattern, same `/api/v1`, same Resources | None |
| Push notifications | Web Push (VAPID) | Mobile Push (FCM/APNs) via new `MobilePushChannel` | New `device_tokens` table, one new Notification channel — §11 |
| File/image uploads | Presigned S3 URL, direct upload | Same presigned S3 URL, direct upload from camera/gallery | None — §16 |
| Maps / "set address" | `react-leaflet` draggable pin | Same Leaflet component, hosted in `react-native-webview` | None |
| "Find providers near me" | `navigator.geolocation` | `expo-location` (device GPS) | None — same lat/lng payload to the same endpoint |
| Radius search | PostGIS `ST_DWithin` | PostGIS `ST_DWithin` | None — server-side, client-agnostic |
| Geocoding (address → coords) | Geoapify/LocationIQ REST call | Same REST call from RN | None |
| Scheduling UI | FullCalendar | `react-native-calendars` against `/providers/{id}/availability` | None |
| Payments | Stripe Payment Element (`@stripe/react-stripe-js`) | Stripe RN SDK (`@stripe/stripe-react-native`) against the same `/payments/intents` | None |
| Real-time updates | TanStack Query refetch/polling + push/in-app notifications | Same — TanStack Query refetch/polling + push/in-app notifications | None |

### 23.5 Explicitly not added

To avoid scope creep beyond what this SRS supports: **no WebSocket/live-chat layer** was introduced (the original spec has no messaging domain — notifications are the only real-time-ish mechanism, and mobile reuses them as-is); **no offline-first data sync** (not specified anywhere in §1–§22); **no mobile-only backend, GraphQL API, or BFF** (mobile is a peer consumer of the same `/api/v1`). If any of these become real requirements, they'd need their own spec pass rather than being assumed here.

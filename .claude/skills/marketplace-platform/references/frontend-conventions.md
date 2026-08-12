# Web Frontend Conventions & Package Choices

Source: SRS §4 and §20. The mobile equivalents are in `mobile.md`; directory layouts are also at `../assets/react-structure.txt`.

**Contents:** React folder structure · Data fetching & type generation · Suggested packages (Laravel / React / React Native)

---

## 4. React Folder Structure

```
src/
├── app/                        # app shell, providers, router
│   ├── routes/
│   │   ├── customer/
│   │   ├── provider/
│   │   ├── freelancer/
│   │   └── admin/
│   └── providers/ (AuthProvider, QueryClientProvider, ToastProvider)
├── features/                   # feature-sliced, mirrors backend domains
│   ├── auth/
│   ├── booking/
│   │   ├── components/ (BookingWizard, ScheduleStep,
│   │   │                 LocationStep — Leaflet draggable pin + address field,
│   │   │                 geocoded via Geoapify/LocationIQ on submit)
│   │   ├── hooks/ (useCreateBooking, useBookingStatus)
│   │   └── api/ (bookingApi.ts)
│   ├── quotation/
│   ├── catalog/
│   ├── freelance/
│   │   ├── projects/
│   │   ├── proposals/
│   │   └── milestones/
│   ├── payments/ (StripeCheckoutForm, PayoutDashboard)
│   ├── reviews/
│   ├── notifications/
│   └── admin/ (BusinessApprovalQueue, DisputeManager, AnalyticsDashboard)
├── components/                 # shared/dumb UI (design-system layer)
├── lib/
│   ├── api/ (axios instance, interceptors, error normalization)
│   ├── maps/ (Leaflet draggable-pin map component, useBrowserGeolocation hook,
│   │          Geoapify/LocationIQ geocoding client — called only on form submit)
│   └── calendar/ (FullCalendar config + adapters)
├── types/                      # shared TS types, generated from OpenAPI if possible
└── stores/                     # Zustand/Redux slices for cross-cutting client state
```

- Data fetching via **TanStack Query** (React Query) — server state is never duplicated into a global store.
- Types generated from the Laravel OpenAPI spec (via `dedoc/scramble` or `zircote/swagger-php`) to keep FE/BE contracts in sync automatically.


---

## 20. Suggested Packages

**Laravel**
- `laravel/sanctum` — auth
- `spatie/laravel-permission` — roles/permissions
- `spatie/laravel-model-states` or a custom StateMachine — status transitions
- `stripe/stripe-php` — payments
- `spatie/laravel-medialibrary` — file/attachment management on top of S3
- `spatie/laravel-activitylog` (or custom, per §13) — audit trail
- `laravel/horizon` — queue monitoring
- `dedoc/scramble` — OpenAPI spec generation from code (keeps FE types in sync)
- `laravel/pulse` — app performance monitoring
- `pragmarx/google2fa-laravel` (optional) — 2FA for admin accounts
- `barryvdh/laravel-ide-helper`, `larastan` — dev-time quality

**React**
- `@tanstack/react-query` — server state
- `react-hook-form` + `zod` — forms/validation
- `@stripe/react-stripe-js` + `@stripe/stripe-js` — Payment Element
- `@fullcalendar/react` — scheduling UI
- `react-leaflet` + `leaflet` — map rendering + draggable pin (OpenStreetMap tiles via MapTiler/Stadia Maps)
- Geoapify or LocationIQ REST client for one-time address geocoding on form submit (simple fetch wrapper, no dedicated npm package needed)
- Browser `navigator.geolocation` API for "find providers near me" (native browser API, no package needed)
- `zustand` — light client state
- `recharts` — admin analytics charts
- `react-hot-toast` — notifications UI
- `axios` — HTTP client with interceptors for auth/error normalization

**React Native (Mobile App) — [MOBILE ADDITION]**
- `react-native` (via Expo, managed workflow) — reuses the team's existing React/TypeScript skillset
- `@tanstack/react-query` + `axios` — same server-state/data-fetching pattern as web, same generated API types
- `expo-secure-store` (or `react-native-keychain` on bare RN) — encrypted on-device storage for the Sanctum token; never `AsyncStorage`
- `react-native-webview` — hosts the existing `react-leaflet` draggable-pin map component unmodified (postMessage bridge for pin coordinates), so the OSM/MapTiler map stays exactly one implementation shared across both clients instead of a second native mapping library
- `expo-location` — device GPS for "Find Providers Near Me" (native equivalent of the browser's `navigator.geolocation`)
- `expo-notifications` (or `@react-native-firebase/messaging`) — device push token registration, FCM/APNs delivery
- `@stripe/stripe-react-native` — Stripe's official RN SDK, mirrors `@stripe/react-stripe-js` on web
- `react-native-calendars` — booking date/time and provider-availability UI, native equivalent of `@fullcalendar/react`
- `zod` + `react-hook-form` — same form/validation libraries as web


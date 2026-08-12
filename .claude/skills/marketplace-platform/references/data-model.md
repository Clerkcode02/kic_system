# Data Model & Performance

Source: SRS §2 (ERD) and §18 (indexing, caching, query strategy). The ERD is also available standalone at `../assets/erd.mmd`; the index DDL at `../assets/indexes.sql`.

**Contents:** ERD · Schema notes (UUIDv7, polymorphism, append-only tables, money columns) · Indexing strategy · PostGIS radius search · Caching · N+1 prevention

---

## 2. Database Schema (ERD)

```mermaid
erDiagram
    USERS ||--o{ BUSINESSES : owns
    USERS ||--o{ FREELANCER_PROFILES : has
    USERS ||--o{ BOOKINGS : "requests as customer"
    USERS ||--o{ ADDRESSES : has
    BUSINESSES ||--o{ SERVICES : offers
    BUSINESSES ||--o{ BUSINESS_DOCUMENTS : uploads
    BUSINESSES ||--o{ PROVIDER_AVAILABILITY : defines
    FREELANCER_PROFILES ||--o{ FREELANCER_SKILLS : lists
    FREELANCER_PROFILES ||--o{ PORTFOLIO_ITEMS : shows
    CATEGORIES ||--o{ CATEGORIES : "parent/child"
    CATEGORIES ||--o{ SERVICES : classifies
    SERVICES ||--o{ SERVICE_PRICING_TIERS : has
    SERVICES ||--o{ BOOKINGS : "booked as"
    BOOKINGS ||--o{ QUOTATIONS : receives
    BOOKINGS ||--o{ BOOKING_STATUS_HISTORY : logs
    BOOKINGS ||--o{ BOOKING_ATTACHMENTS : has
    BOOKINGS ||--|| PAYMENTS : "paid via"
    QUOTATIONS ||--o{ QUOTATION_LINE_ITEMS : itemizes
    PROJECTS ||--o{ PROPOSALS : receives
    PROJECTS ||--o{ MILESTONES : "broken into"
    PROJECTS ||--o| CONTRACTS : "results in"
    PROPOSALS }o--|| FREELANCER_PROFILES : "submitted by"
    CONTRACTS ||--o{ MILESTONES : tracks
    MILESTONES ||--o{ DELIVERABLES : "submitted for"
    MILESTONES ||--o| PAYMENTS : "released via"
    PAYMENTS ||--o{ PAYOUTS : "settled into"
    PAYMENTS ||--o{ REFUNDS : "may have"
    USERS ||--o{ REVIEWS : writes
    REVIEWS }o--|| BOOKINGS : "for"
    REVIEWS }o--|| PROJECTS : "for"
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ AUDIT_LOGS : "acted by"
    DISPUTES }o--|| BOOKINGS : "raised on"
    DISPUTES }o--|| PROJECTS : "raised on"

    USERS {
        uuid id PK
        string name
        string email UK
        string phone UK
        enum role "customer|provider|freelancer|admin"
        string password
        timestamp email_verified_at
        timestamp phone_verified_at
        enum status "active|suspended|pending"
        timestamps created_at_updated_at
    }
    BUSINESSES {
        uuid id PK
        uuid user_id FK
        string legal_name
        string registration_number UK
        enum verification_status "pending|verified|rejected"
        jsonb business_hours
        decimal rating_avg
        integer max_bookings_per_day
        geography location "Point,4326 — service location, set via Leaflet draggable pin + geocoded address; used by PostGIS ST_DWithin radius search"
        timestamps created_at_updated_at
    }
    CATEGORIES {
        uuid id PK
        uuid parent_id FK "nullable, self-ref"
        string name
        string slug UK
        string icon
        boolean is_active
        integer sort_order
    }
    SERVICES {
        uuid id PK
        uuid business_id FK
        uuid category_id FK
        string title
        text description
        enum pricing_type "hourly|fixed|package|inspection"
        decimal base_price
        integer estimated_duration_minutes
        boolean is_active
    }
    BOOKINGS {
        uuid id PK
        string booking_number UK
        uuid customer_id FK
        uuid provider_id FK
        uuid service_id FK
        date scheduled_date
        time time_slot_start
        time time_slot_end
        uuid address_id FK
        decimal lat
        decimal lng
        text notes
        enum status
        enum payment_status "unpaid|partial|paid|refunded"
        timestamps created_at_updated_at
    }
    QUOTATIONS {
        uuid id PK
        uuid booking_id FK
        decimal labor_cost
        decimal materials_cost
        decimal additional_fees
        decimal platform_fee
        decimal tax_amount
        decimal discount_amount
        decimal total_amount
        timestamp valid_until
        integer revision_number
        enum status "sent|accepted|rejected|expired|superseded"
    }
    PROJECTS {
        uuid id PK
        uuid client_id FK
        uuid category_id FK
        string title
        text description
        decimal budget_min
        decimal budget_max
        date deadline
        enum status "open|in_progress|completed|cancelled"
    }
    PROPOSALS {
        uuid id PK
        uuid project_id FK
        uuid freelancer_id FK
        decimal proposed_amount
        text cover_letter
        integer delivery_days
        enum status "submitted|shortlisted|accepted|rejected|withdrawn"
    }
    CONTRACTS {
        uuid id PK
        uuid project_id FK
        uuid proposal_id FK
        decimal total_amount
        enum status "active|completed|terminated"
    }
    MILESTONES {
        uuid id PK
        uuid contract_id FK
        string title
        decimal amount
        date due_date
        enum status "pending|submitted|approved|paid|disputed"
    }
    PAYMENTS {
        uuid id PK
        string payable_type "polymorphic: booking|milestone"
        uuid payable_id
        string stripe_payment_intent_id
        decimal amount
        decimal platform_fee_amount
        decimal provider_net_amount
        enum type "full|deposit|partial|escrow"
        enum status "pending|succeeded|failed|refunded|disputed"
    }
    PAYOUTS {
        uuid id PK
        uuid provider_id FK
        decimal amount
        string stripe_transfer_id
        enum status "scheduled|processing|paid|failed"
    }
    REVIEWS {
        uuid id PK
        uuid reviewer_id FK
        uuid reviewee_id FK
        string reviewable_type "booking|project"
        uuid reviewable_id
        tinyint rating
        text comment
        text provider_reply
        timestamp edit_locked_at
    }
    AUDIT_LOGS {
        uuid id PK
        uuid actor_id FK
        string action
        string auditable_type
        uuid auditable_id
        jsonb before_state
        jsonb after_state
        string ip_address
        timestamp created_at
    }
    DISPUTES {
        uuid id PK
        string disputable_type "booking|project"
        uuid disputable_id
        uuid raised_by FK
        enum status "open|under_review|resolved|closed"
        text resolution_notes
    }
```

**Notes**
- All primary keys are UUIDv7 (sortable) to avoid enumeration and to work cleanly with distributed IDs if services split later.
- `PAYMENTS.payable_type/payable_id` and `REVIEWS.reviewable_type/reviewable_id` are polymorphic — one payments table serves both booking and milestone payments; one reviews table serves both booking and project reviews.
- `BOOKING_STATUS_HISTORY` and `AUDIT_LOGS` are append-only (no updates/deletes at the application layer) — enforced via a Postgres trigger or a model-level `Immutable` trait that blocks `update()`/`delete()`.
- Money columns are `decimal(12,2)` never float. Currency stored per-row (`currency` char(3)) for future multi-currency support.


---

## 18. Performance Optimization & Database Indexing

**Indexing strategy** (PostgreSQL):

```sql
-- Booking lookups by provider + date (double-booking checks, calendar views)
CREATE INDEX idx_bookings_provider_date ON bookings (provider_id, scheduled_date, time_slot_start);
CREATE INDEX idx_bookings_customer ON bookings (customer_id, status);
CREATE INDEX idx_bookings_status ON bookings (status) WHERE status NOT IN ('completed','cancelled');

-- Service discovery / browse
CREATE INDEX idx_services_category_active ON services (category_id) WHERE is_active = true;
CREATE INDEX idx_services_geo ON businesses USING GIST (location); -- geography(Point,4326), PostGIS for radius search

-- "Find providers near me": client sends lat/lng from the Browser Geolocation API,
-- the API runs exactly this query — no geocoding call, no application-side haversine:
-- SELECT * FROM businesses
-- WHERE ST_DWithin(location, ST_MakePoint(:lng, :lat)::geography, :radius_meters);

-- Quotation expiry sweep
CREATE INDEX idx_quotations_status_validuntil ON quotations (status, valid_until) WHERE status = 'sent';

-- Audit log queries
CREATE INDEX idx_audit_actor_date ON audit_logs (actor_id, created_at);
CREATE INDEX idx_audit_entity ON audit_logs (auditable_type, auditable_id);

-- Reviews aggregation
CREATE INDEX idx_reviews_reviewee ON reviews (reviewee_id);
```

- **PostGIS** extension for geo radius queries: `ST_DWithin` against the `geography(Point)` column is the only operation the API runs for provider search — instead of naive haversine in application code or a maps-provider "nearby search" API call. Coordinates for "near me" come straight from the browser's **Geolocation API**; coordinates for a saved service address come from the one-time Geoapify/LocationIQ geocode captured at signup.
- **Read replicas** for the admin analytics dashboard and reporting queries once traffic warrants it — analytics never hits the primary write connection.
- **Caching**: category tree, service pricing, and platform settings cached in Redis with tagged invalidation on admin writes; provider availability cached per-day with short TTL (5 min) since it changes frequently.
- **Eager loading everywhere**: API Resources paired with `->with([...])` on every list query; N+1 caught in CI via `barryvdh/laravel-debugbar` assertions or `spatie/laravel-query-detector` in non-prod.
- **Materialized view** (or an hourly snapshot table, `admin_dashboard_metrics`) for the analytics dashboard so heavy aggregation queries don't run on every page load.


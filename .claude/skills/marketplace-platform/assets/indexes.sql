-- Indexing strategy — SRS §18 (PostgreSQL + PostGIS)

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

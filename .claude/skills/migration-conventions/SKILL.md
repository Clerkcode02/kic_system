---
name: migration-conventions
description: Use whenever writing or reviewing a Laravel migration in the KIC-System backend. Covers UUIDv7 primary keys, money columns, PostGIS geography columns, and partial indexes. Trigger on requests like "write a migration for X", "add a column to Y table", "create the Z table".
---

# Migration Conventions

## Primary keys — UUIDv7, not v4

```php
Schema::create('bookings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // ...
});
```

The model must use `HasUuids` with the `newUniqueId()` override to guarantee v7 (time-ordered), not Laravel's default v4:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Booking extends Model
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
```

**Verify this, don't assume it** — write a one-off test asserting IDs generated in sequence sort in creation order (monotonicity check). Some Laravel versions' `HasUuids` default to v4 unless explicitly overridden, and this has silently regressed before.

## Money — always `decimal(12,2)`, never float or double

```php
$table->decimal('total_amount', 12, 2);
$table->decimal('platform_fee', 12, 2)->default(0);
```

Cast on the model as `decimal:2`, never `float`. Never do money arithmetic in PHP floats — use integer cents internally if a calculation needs precision beyond what `decimal` casting gives you cleanly, or lean on `brick/money` if the project already depends on it.

## Location — PostGIS geography, not two floats

```php
$table->geography('location', subtype: 'point', srid: 4326);
```

If the Laravel PostGIS package in use doesn't support the `geography()` blueprint macro yet, drop to raw SQL in the migration:

```php
DB::statement('ALTER TABLE providers ADD COLUMN location geography(Point, 4326)');
```

Radius search is always `ST_DWithin(location, ST_MakePoint(:lng, :lat)::geography, :radius_meters)` — never haversine computed in PHP, and never a naive lat/lng bounding-box query. Index it:

```php
DB::statement('CREATE INDEX providers_location_idx ON providers USING GIST (location)');
```

## Partial indexes

Use these for columns where most rows share a common value and only a minority need fast lookup (e.g. active bookings, unread notifications, undisputed transactions):

```php
DB::statement('CREATE INDEX bookings_active_idx ON bookings (provider_id) WHERE status IN (\'pending\', \'confirmed\')');
```

Don't index the whole table for a query that only ever filters to a small subset of rows.

## Append-only tables (audit log, ledger entries)

No `updated_at`, no update/delete route ever touches these rows. Enforce it with a DB trigger, not just application-layer discipline:

```sql
CREATE OR REPLACE FUNCTION prevent_update_delete() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'This table is append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_log_no_update
  BEFORE UPDATE OR DELETE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION prevent_update_delete();
```

## Before finalizing any migration

Use the Postgres MCP server to inspect the real schema state and confirm the migration's assumptions (existing FKs, column types on tables it references) rather than guessing from other migration files, which can drift from what's actually applied.

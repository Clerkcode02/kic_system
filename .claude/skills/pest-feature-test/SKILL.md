---
name: pest-feature-test
description: Use whenever writing a Pest feature test for an API endpoint or Action in the KIC-System backend. Ensures every endpoint's test covers the standard happy/401/403/422/409 matrix. Trigger on requests like "write a test for X", "add feature tests for Y endpoint".
---

# Pest Feature Test Matrix

Every endpoint that changes or reads protected data gets tests for all applicable cases below, in this order, in one file: `tests/Feature/{Entity}/{Action}Test.php`.

```php
use function Pest\Laravel\{actingAs, postJson, getJson};

it('creates a booking as an authenticated customer', function () {
    $customer = User::factory()->customer()->create();
    $provider = User::factory()->provider()->create();

    actingAs($customer)
        ->postJson('/api/bookings', [
            'provider_id' => $provider->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'lat' => 14.6091, 'lng' => 121.0223,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

it('rejects an unauthenticated request', function () {
    postJson('/api/bookings', [])->assertUnauthorized(); // 401
});

it('rejects a provider trying to create a booking as if they were the customer', function () {
    $provider = User::factory()->provider()->create();

    actingAs($provider)
        ->postJson('/api/bookings', [/* ... */])
        ->assertForbidden(); // 403 — wrong role
});

it('rejects invalid input', function () {
    $customer = User::factory()->customer()->create();

    actingAs($customer)
        ->postJson('/api/bookings', ['provider_id' => 'not-a-uuid'])
        ->assertUnprocessable() // 422
        ->assertJsonValidationErrors(['provider_id']);
});

it('rejects a double-booking of the same slot', function () {
    $customer = User::factory()->customer()->create();
    $provider = User::factory()->provider()->create();
    $slot = now()->addDay();

    Booking::factory()->for($provider)->create(['scheduled_at' => $slot, 'status' => 'confirmed']);

    actingAs($customer)
        ->postJson('/api/bookings', [
            'provider_id' => $provider->id,
            'scheduled_at' => $slot->toIso8601String(),
        ])
        ->assertConflict(); // 409
});
```

## Rules

1. **Every applicable case gets its own `it()` block** — don't combine 401 and 403 into one test, they're distinct failure modes and a regression in one shouldn't hide behind the other passing.
2. **409 tests are mandatory for any endpoint touching a state machine or a uniqueness constraint under concurrency** (bookings, exclusive freelance hiring, milestone approval). If the endpoint can't realistically hit a 409, say so explicitly in a code comment rather than silently omitting the test — future readers shouldn't have to guess whether it was forgotten.
3. **Use factories, never raw `DB::insert` or hand-built arrays**, so tests stay resilient to schema changes.
4. **Concurrency tests for double-booking / double-accept** use two near-simultaneous requests (or a manually forced race via `DB::transaction` + a second connection) to prove the `lockForUpdate()` in the Action actually prevents the race, not just that the happy path works once.
5. **Test names describe behavior, not implementation** — `it('rejects a double-booking of the same slot')`, not `it('tests booking action returns 409')`.
6. **Money assertions compare exact decimal strings**, never float equality (`assertJsonPath('data.total_amount', '150.00')`, not a float comparison that can fail on precision).

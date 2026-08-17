<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// The array cache store (and every rate limiter built on it, per
// AuthenticationFlowTest's precedent) is a container singleton that
// outlives a single test's transaction rollback. Flush it per test so hits
// from one test never bleed into the next.
beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

// --- throttle:payments ------------------------------------------------------

it('throttles a payments-group route past its configured per-user limit', function () {
    // No business record on the user means every call resolves to a
    // deterministic 403 from the policy check, well after the throttle
    // middleware has already counted the hit — cheaper to set up than a
    // full Stripe-mocked success path, and irrelevant to what this test is
    // actually verifying (the limiter, not the endpoint's business logic).
    $provider = User::factory()->provider()->create();
    $provider->assignRole(RoleName::ProviderOwner->value);

    for ($i = 0; $i < 20; $i++) {
        $this->withHeaders(authHeader($provider))
            ->postJson('/api/v1/provider/me/stripe/onboarding-link')
            ->assertForbidden();
    }

    $this->withHeaders(authHeader($provider))
        ->postJson('/api/v1/provider/me/stripe/onboarding-link')
        ->assertStatus(429);
});

// --- throttle:booking-create --------------------------------------------------

it('throttles booking creation per user past its configured limit', function () {
    [$customer, $address] = bookingCustomer();
    [, $business] = bookingProvider(50);
    $service = bookingService($business, ServicePricingType::Fixed);

    for ($i = 0; $i < 15; $i++) {
        $this->withHeaders(authHeader($customer))
            ->postJson('/api/v1/bookings', [
                'service_id' => $service->id,
                'address_id' => $address->id,
                'scheduled_date' => now()->addDays(10 + $i)->toDateString(),
                'time_slot_start' => '10:00:00',
                'time_slot_end' => '11:00:00',
            ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated();
    }

    $this->withHeaders(authHeader($customer))
        ->postJson('/api/v1/bookings', [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'scheduled_date' => now()->addDays(60)->toDateString(),
            'time_slot_start' => '10:00:00',
            'time_slot_end' => '11:00:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(429);
});

// --- throttle:proposal-create -------------------------------------------------

it('throttles proposal creation per user past its configured limit', function () {
    $freelancer = User::factory()->freelancer()->create();
    $freelancer->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->approved()->payoutsEnabled()->create(['user_id' => $freelancer->id]);

    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);
    $category = Category::factory()->create();

    for ($i = 0; $i < 15; $i++) {
        $project = Project::factory()->open()->create(['client_id' => $client->id, 'category_id' => $category->id]);

        $this->withHeaders(authHeader($freelancer))
            ->postJson("/api/v1/projects/{$project->id}/proposals", [
                'proposed_amount' => '750.00',
                'cover_letter' => 'I would love to help with this.',
                'delivery_days' => 14,
            ])
            ->assertCreated();
    }

    $overflowProject = Project::factory()->open()->create(['client_id' => $client->id, 'category_id' => $category->id]);

    $this->withHeaders(authHeader($freelancer))
        ->postJson("/api/v1/projects/{$overflowProject->id}/proposals", [
            'proposed_amount' => '750.00',
            'cover_letter' => 'One more, past the limit.',
            'delivery_days' => 14,
        ])
        ->assertStatus(429);
});

<?php

declare(strict_types=1);

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Payment\Models\Payout;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

if (! function_exists('authHeader')) {
    /**
     * @return array<string, string>
     */
    function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }
}

/**
 * @return array{0: User, 1: Business}
 */
function providerDashboardOwner(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->verified()->create(['user_id' => $user->id]);

    return [$user, $business];
}

it('summarizes the provider dashboard', function () {
    [$user, $business] = providerDashboardOwner();

    Booking::factory()->waitingForQuotation()->create(['provider_id' => $business->id]);
    Booking::factory()->scheduled()->create([
        'provider_id' => $business->id,
        'scheduled_date' => now()->addDays(3)->toDateString(),
    ]);
    Booking::factory()->scheduled()->create([
        'provider_id' => $business->id,
        'scheduled_date' => now()->toDateString(),
    ]);
    Payout::factory()->paid()->create(['provider_id' => $business->id]);

    $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/provider/me/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'data.today_schedule')
        ->assertJsonCount(1, 'data.pending_quotations')
        ->assertJsonCount(1, 'data.upcoming_bookings')
        ->assertJsonPath('data.earnings.currency', 'CAD')
        ->assertJsonCount(1, 'data.earnings.recent_payouts');
});

it('denies a customer from viewing the provider dashboard', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/provider/me/dashboard')
        ->assertForbidden();
});

it('rejects an unauthenticated request to the provider dashboard', function () {
    $this->getJson('/api/v1/provider/me/dashboard')->assertUnauthorized();
});

it('summarizes the provider earnings ledger', function () {
    [$user, $business] = providerDashboardOwner();
    Payout::factory()->count(2)->paid()->create(['provider_id' => $business->id]);

    $this->withHeaders(authHeader($user))
        ->getJson('/api/v1/provider/me/earnings')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

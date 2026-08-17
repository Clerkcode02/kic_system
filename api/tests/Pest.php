<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Architecture');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Laravel's AuthManager memoizes guard instances (and each guard memoizes
 * its resolved user) for the life of the container. Pest reuses the same
 * container across sequential postJson()/getJson() calls within one test,
 * so a guard resolved on an earlier call silently answers later calls too
 * — masking things like a token revoked by a logout in between. Call this
 * before any request whose auth outcome must reflect state changed by a
 * prior request in the same test.
 */
function forgetAuthGuards(): void
{
    app('auth')->forgetGuards();
}

/**
 * Endpoint + valid payload for each of the three self-registration flows,
 * keyed by role so auth tests can run the same assertions across all three
 * without duplicating the request shape.
 *
 * @return array{endpoint: string, payload: array<string, mixed>}
 */
function authHeader(\App\Domain\User\Models\User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

/**
 * @return array{0: \App\Domain\User\Models\User, 1: \App\Domain\User\Models\Address}
 */
function bookingCustomer(): array
{
    $user = \App\Domain\User\Models\User::factory()->customer()->create();
    $user->assignRole(\App\Domain\User\Enums\RoleName::Customer->value);
    $address = \App\Domain\User\Models\Address::factory()->for($user, 'user')->create();

    return [$user, $address];
}

/**
 * @return array{0: \App\Domain\User\Models\User, 1: \App\Domain\Business\Models\Business}
 */
function bookingProvider(int $maxBookingsPerDay = 10): array
{
    $user = \App\Domain\User\Models\User::factory()->provider()->create();
    $user->assignRole(\App\Domain\User\Enums\RoleName::ProviderOwner->value);
    $business = \App\Domain\Business\Models\Business::factory()->verified()->payoutsEnabled()->create([
        'user_id' => $user->id,
        'max_bookings_per_day' => $maxBookingsPerDay,
    ]);

    foreach (range(0, 6) as $day) {
        \App\Domain\Business\Models\ProviderAvailability::factory()->create([
            'business_id' => $business->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    return [$user, $business];
}

function bookingService(\App\Domain\Business\Models\Business $business, \App\Domain\Catalog\Enums\ServicePricingType $pricingType = \App\Domain\Catalog\Enums\ServicePricingType::Fixed): \App\Domain\Catalog\Models\Service
{
    return \App\Domain\Catalog\Models\Service::factory()->create([
        'business_id' => $business->id,
        'pricing_type' => $pricingType,
        'is_active' => true,
    ]);
}

function futureBookingDate(): string
{
    return now()->addDays(5)->toDateString();
}

function registrationRequestFor(string $role): array
{
    $base = [
        'name' => 'Jordan Rivera',
        'email' => strtolower($role).'@example.com',
        'phone' => '+1416555'.random_int(1000, 9999),
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ];

    return match ($role) {
        'customer' => [
            'endpoint' => '/api/v1/auth/register/customer',
            'payload' => $base,
        ],
        'business' => [
            'endpoint' => '/api/v1/auth/register/business',
            'payload' => [
                ...$base,
                'legal_name' => 'Rivera Home Services Inc.',
                'registration_number' => 'BN'.random_int(100000, 999999),
                'business_hours' => ['mon' => ['09:00', '17:00']],
                'max_bookings_per_day' => 8,
            ],
        ],
        'freelancer' => [
            'endpoint' => '/api/v1/auth/register/freelancer',
            'payload' => [
                ...$base,
                'headline' => 'Full-stack developer',
                'bio' => 'Ten years building marketplace platforms.',
                'hourly_rate' => 85.00,
                'years_experience' => 10,
            ],
        ],
        default => throw new InvalidArgumentException("Unknown role: {$role}"),
    };
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Business>
     */
    protected $model = Business::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->provider(),
            'legal_name' => fake()->company(),
            'registration_number' => strtoupper(fake()->unique()->bothify('BN########RC####')),
            'verification_status' => BusinessVerificationStatus::Pending,
            'business_hours' => [
                'mon' => ['09:00', '17:00'],
                'tue' => ['09:00', '17:00'],
                'wed' => ['09:00', '17:00'],
                'thu' => ['09:00', '17:00'],
                'fri' => ['09:00', '17:00'],
            ],
            'rating_avg' => 0,
            'max_bookings_per_day' => fake()->numberBetween(3, 15),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => BusinessVerificationStatus::Verified,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => BusinessVerificationStatus::Pending,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => BusinessVerificationStatus::Rejected,
        ]);
    }

    public function payoutsEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_connect_account_id' => 'acct_'.fake()->unique()->bothify('##########????'),
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
        ]);
    }

    /**
     * Set `location` via raw SQL after creation — geography columns have no
     * native Eloquent cast (see the model's docblock).
     */
    public function nearCity(float $lat, float $lng, int $radiusMeters = 8000): static
    {
        return $this->afterCreating(function (Business $business) use ($lat, $lng, $radiusMeters) {
            $bearing = fake()->randomFloat(4, 0, 2 * M_PI);
            $distance = fake()->randomFloat(4, 0, $radiusMeters);

            // Small-offset approximation — fine for demo-data clustering, not for real geo math.
            $deltaLat = ($distance * cos($bearing)) / 111_320;
            $deltaLng = ($distance * sin($bearing)) / (111_320 * cos(deg2rad($lat)));

            $pointLat = $lat + $deltaLat;
            $pointLng = $lng + $deltaLng;

            DB::statement(
                'UPDATE businesses SET location = ST_MakePoint(?, ?)::geography WHERE id = ?',
                [$pointLng, $pointLat, $business->id]
            );
        });
    }
}

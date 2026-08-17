<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reporting\Models\AdminAnalyticsSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAnalyticsSnapshot>
 */
class AdminAnalyticsSnapshotFactory extends Factory
{
    /**
     * @var class-string<AdminAnalyticsSnapshot>
     */
    protected $model = AdminAnalyticsSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snapshot_at' => now(),
            'metrics' => [
                'bookings_total' => $this->faker->numberBetween(0, 500),
                'bookings_active' => $this->faker->numberBetween(0, 100),
                'gmv' => $this->faker->randomFloat(2, 0, 50000),
                'new_signups' => ['customer' => 0, 'provider' => 0, 'freelancer' => 0],
                'verification_queue_depth' => $this->faker->numberBetween(0, 20),
                'open_disputes' => $this->faker->numberBetween(0, 10),
                'payout_volume' => $this->faker->randomFloat(2, 0, 20000),
            ],
        ];
    }
}

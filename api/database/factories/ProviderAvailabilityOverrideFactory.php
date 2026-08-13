<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailabilityOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAvailabilityOverride>
 */
class ProviderAvailabilityOverrideFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProviderAvailabilityOverride>
     */
    protected $model = ProviderAvailabilityOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'date' => fake()->dateTimeBetween('+1 day', '+2 weeks')->format('Y-m-d'),
            'is_blackout' => false,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }

    public function blackout(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blackout' => true,
            'start_time' => null,
            'end_time' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAvailability>
 */
class ProviderAvailabilityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProviderAvailability>
     */
    protected $model = ProviderAvailability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ];
    }
}

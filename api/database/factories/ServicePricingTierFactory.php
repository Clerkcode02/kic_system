<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServicePricingTier;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePricingTier>
 */
class ServicePricingTierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ServicePricingTier>
     */
    protected $model = ServicePricingTier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'tier_name' => fake()->randomElement(['Basic', 'Standard', 'Premium']),
            'description' => fake()->sentence(),
            'price' => Money::fromMinorUnits(fake()->numberBetween(4_000, 60_000), 'CAD'),
            'currency' => 'CAD',
            'estimated_duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'sort_order' => 0,
        ];
    }
}

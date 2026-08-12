<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Service>
     */
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'category_id' => Category::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->paragraph(),
            'pricing_type' => fake()->randomElement(ServicePricingType::cases()),
            'base_price' => Money::fromMinorUnits(fake()->numberBetween(4_000, 40_000), 'CAD'),
            'currency' => 'CAD',
            'estimated_duration_minutes' => fake()->randomElement([30, 60, 90, 120, 180]),
            'is_active' => true,
        ];
    }
}

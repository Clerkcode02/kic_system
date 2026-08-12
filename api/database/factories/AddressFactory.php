<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Address>
     */
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Clustered loosely around the Greater Toronto Area — Canada-only market (CLAUDE.md §5).
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', null]),
            'street' => fake()->streetAddress(),
            'unit' => fake()->optional()->buildingNumber(),
            'city' => fake()->randomElement(['Toronto', 'Mississauga', 'Brampton', 'Vaughan', 'Markham']),
            'state_province' => 'ON',
            'postal_code' => fake()->postcode(),
            'country' => 'CA',
            'lat' => fake()->latitude(43.60, 43.85),
            'lng' => fake()->longitude(-79.60, -79.20),
            'is_default' => true,
        ];
    }
}

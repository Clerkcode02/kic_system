<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PlatformSetting>
     */
    protected $model = PlatformSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => (string) fake()->numberBetween(1, 100),
            'type' => 'integer',
            'description' => fake()->sentence(),
        ];
    }
}

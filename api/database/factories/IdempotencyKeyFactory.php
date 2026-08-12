<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Platform\Models\IdempotencyKey;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<IdempotencyKey>
     */
    protected $model = IdempotencyKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->uuid(),
            'user_id' => User::factory(),
            'endpoint' => 'POST /api/v1/bookings',
            'response_status' => 201,
            'response_body' => ['id' => fake()->uuid()],
            'expires_at' => now()->addDay(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\User\Models\FailedLoginAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedLoginAttempt>
 */
class FailedLoginAttemptFactory extends Factory
{
    /**
     * @var class-string<FailedLoginAttempt>
     */
    protected $model = FailedLoginAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

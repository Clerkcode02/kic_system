<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AuditLog>
     */
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->admin(),
            'action' => 'booking.status_changed',
            'auditable_type' => 'booking',
            'auditable_id' => fake()->uuid(),
            'before_state' => ['status' => 'pending'],
            'after_state' => ['status' => 'accepted'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<NotificationPreference>
     */
    protected $model = NotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement([
                'booking.created', 'booking.accepted', 'quotation.sent', 'payment.succeeded',
                'proposal.received', 'milestone.approved',
            ]),
            'email' => true,
            'sms' => false,
            'push_web' => true,
            'push_mobile' => true,
        ];
    }
}

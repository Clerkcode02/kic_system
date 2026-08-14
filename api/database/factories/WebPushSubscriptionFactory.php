<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Notification\Models\WebPushSubscription;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebPushSubscription>
 */
class WebPushSubscriptionFactory extends Factory
{
    /**
     * @var class-string<WebPushSubscription>
     */
    protected $model = WebPushSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid(),
            'public_key' => base64_encode(random_bytes(65)),
            'auth_token' => base64_encode(random_bytes(16)),
            'content_encoding' => 'aes128gcm',
        ];
    }
}

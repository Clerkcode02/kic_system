<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Payment\Models\StripeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripeEvent>
 */
class StripeEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<StripeEvent>
     */
    protected $model = StripeEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_event_id' => 'evt_'.fake()->unique()->bothify('##########????'),
            'type' => fake()->randomElement(['payment_intent.succeeded', 'payment_intent.payment_failed', 'charge.refunded']),
            'payload' => ['id' => fake()->uuid(), 'object' => 'event'],
            'processed_at' => now(),
        ];
    }

    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes) => ['processed_at' => null]);
    }
}

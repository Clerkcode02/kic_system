<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Models\Booking;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Review>
     */
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reviewer_id' => User::factory()->customer(),
            'reviewee_id' => User::factory()->provider(),
            'reviewable_type' => 'booking',
            'reviewable_id' => Booking::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->paragraph(),
            'provider_reply' => null,
            'edit_locked_at' => null,
        ];
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewable_type' => 'booking',
            'reviewable_id' => $booking->id,
        ]);
    }

    public function withProviderReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_reply' => fake()->sentence(),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'edit_locked_at' => now(),
        ]);
    }
}

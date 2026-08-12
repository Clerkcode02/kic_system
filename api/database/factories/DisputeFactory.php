<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Models\Booking;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Dispute>
     */
    protected $model = Dispute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disputable_type' => 'booking',
            'disputable_id' => Booking::factory(),
            'raised_by' => User::factory(),
            'status' => DisputeStatus::Open,
            'resolution_notes' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => DisputeStatus::Open]);
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => ['status' => DisputeStatus::UnderReview]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DisputeStatus::Resolved,
            'resolution_notes' => fake()->paragraph(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => DisputeStatus::Closed]);
    }
}

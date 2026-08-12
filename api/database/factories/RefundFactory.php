<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Refund>
     */
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount' => Money::fromMinorUnits(fake()->numberBetween(5_000, 40_000), 'CAD'),
            'currency' => 'CAD',
            'reason' => fake()->sentence(),
            'stripe_refund_id' => 're_'.fake()->unique()->bothify('##########????'),
            'status' => RefundStatus::Pending,
            'initiated_by' => User::factory()->admin(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RefundStatus::Pending]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RefundStatus::Succeeded]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => RefundStatus::Failed]);
    }
}

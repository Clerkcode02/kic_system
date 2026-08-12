<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Enums\PayoutStatus;
use App\Domain\Payment\Models\Payout;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Payout>
     */
    protected $model = Payout::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Business::factory(),
            'amount' => Money::fromMinorUnits(fake()->numberBetween(10_000, 200_000), 'CAD'),
            'currency' => 'CAD',
            'stripe_transfer_id' => 'tr_'.fake()->unique()->bothify('##########????'),
            'status' => PayoutStatus::Scheduled,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PayoutStatus::Scheduled]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PayoutStatus::Processing]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PayoutStatus::Paid]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PayoutStatus::Failed]);
    }
}

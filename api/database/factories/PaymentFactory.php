<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Models\Booking;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Models\Payment;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Payment>
     */
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(10_000, 60_000);
        $platformFee = (int) round($amount * 0.1);

        return [
            'payable_type' => 'booking',
            'payable_id' => Booking::factory(),
            'stripe_payment_intent_id' => 'pi_'.fake()->unique()->bothify('##########????'),
            'amount' => Money::fromMinorUnits($amount, 'CAD'),
            'platform_fee_amount' => Money::fromMinorUnits($platformFee, 'CAD'),
            'provider_net_amount' => Money::fromMinorUnits($amount - $platformFee, 'CAD'),
            'currency' => 'CAD',
            'type' => PaymentType::Full,
            'status' => PaymentStatus::Succeeded,
        ];
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn (array $attributes) => [
            'payable_type' => 'booking',
            'payable_id' => $booking->id,
        ]);
    }

    public function escrow(): static
    {
        return $this->state(fn (array $attributes) => [
            'payable_type' => 'milestone',
            'type' => PaymentType::Escrow,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Pending]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Succeeded]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Failed]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Enums\QuotationStatus;
use App\Domain\Quotation\Models\Quotation;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Quotation>
     */
    protected $model = Quotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $labor = fake()->numberBetween(10_000, 40_000);
        $materials = fake()->numberBetween(0, 15_000);
        $fees = fake()->numberBetween(0, 5_000);
        $platformFee = (int) round(($labor + $materials + $fees) * 0.1);
        $tax = (int) round(($labor + $materials + $fees) * 0.13);
        $discount = 0;
        $total = $labor + $materials + $fees + $platformFee + $tax - $discount;

        return [
            'booking_id' => Booking::factory(),
            'labor_cost' => Money::fromMinorUnits($labor, 'CAD'),
            'materials_cost' => Money::fromMinorUnits($materials, 'CAD'),
            'additional_fees' => Money::fromMinorUnits($fees, 'CAD'),
            'platform_fee' => Money::fromMinorUnits($platformFee, 'CAD'),
            'tax_amount' => Money::fromMinorUnits($tax, 'CAD'),
            'discount_amount' => Money::fromMinorUnits($discount, 'CAD'),
            'total_amount' => Money::fromMinorUnits($total, 'CAD'),
            'currency' => 'CAD',
            'valid_until' => fake()->dateTimeBetween('+1 day', '+5 days'),
            'revision_number' => 1,
            'status' => QuotationStatus::Sent,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuotationStatus::Sent]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuotationStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuotationStatus::Rejected]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuotationStatus::Superseded]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::Expired,
            'valid_until' => fake()->dateTimeBetween('-5 days', '-1 day'),
        ]);
    }

    public function withDeposit(float $percentage = 25.0): static
    {
        return $this->state(fn (array $attributes) => ['deposit_percentage' => $percentage]);
    }
}

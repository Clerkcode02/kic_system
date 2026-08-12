<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\Models\QuotationLineItem;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationLineItem>
 */
class QuotationLineItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<QuotationLineItem>
     */
    protected $model = QuotationLineItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(2_000, 10_000);
        $quantity = fake()->numberBetween(1, 4);

        return [
            'quotation_id' => Quotation::factory(),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => Money::fromMinorUnits($unitPrice, 'CAD'),
            'amount' => Money::fromMinorUnits($unitPrice * $quantity, 'CAD'),
            'currency' => 'CAD',
            'sort_order' => 0,
        ];
    }
}

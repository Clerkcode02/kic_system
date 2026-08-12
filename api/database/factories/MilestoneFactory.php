<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Enums\MilestoneStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Milestone;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Milestone>
     */
    protected $model = Milestone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'amount' => Money::fromMinorUnits(fake()->numberBetween(20_000, 80_000), 'CAD'),
            'currency' => 'CAD',
            'due_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'status' => MilestoneStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MilestoneStatus::Pending]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MilestoneStatus::Submitted]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MilestoneStatus::Approved]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MilestoneStatus::Paid]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MilestoneStatus::Disputed]);
    }
}

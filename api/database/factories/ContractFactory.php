<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Enums\ContractStatus;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Contract>
     */
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'proposal_id' => Proposal::factory(),
            'total_amount' => Money::fromMinorUnits(fake()->numberBetween(60_000, 250_000), 'CAD'),
            'currency' => 'CAD',
            'status' => ContractStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContractStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContractStatus::Completed]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContractStatus::Terminated]);
    }
}

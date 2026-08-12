<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Enums\ProposalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Proposal>
     */
    protected $model = Proposal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'freelancer_id' => FreelancerProfile::factory()->approved(),
            'proposed_amount' => Money::fromMinorUnits(fake()->numberBetween(60_000, 250_000), 'CAD'),
            'currency' => 'CAD',
            'cover_letter' => fake()->paragraphs(2, true),
            'delivery_days' => fake()->numberBetween(5, 60),
            'status' => ProposalStatus::Submitted,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProposalStatus::Submitted]);
    }

    public function shortlisted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProposalStatus::Shortlisted]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProposalStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProposalStatus::Rejected]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProposalStatus::Withdrawn]);
    }
}

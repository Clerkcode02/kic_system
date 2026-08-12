<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use App\Domain\Freelance\Enums\ProjectStatus;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Project>
     */
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $budgetMin = fake()->numberBetween(50_000, 200_000);

        return [
            'client_id' => User::factory()->customer(),
            'category_id' => Category::factory(),
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->paragraphs(3, true),
            'budget_min' => Money::fromMinorUnits($budgetMin, 'CAD'),
            'budget_max' => Money::fromMinorUnits($budgetMin + fake()->numberBetween(20_000, 100_000), 'CAD'),
            'currency' => 'CAD',
            'deadline' => fake()->dateTimeBetween('+2 weeks', '+3 months'),
            'status' => ProjectStatus::Open,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Open]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Cancelled]);
    }
}

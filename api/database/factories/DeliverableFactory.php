<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deliverable>
 */
class DeliverableFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Deliverable>
     */
    protected $model = Deliverable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'milestone_id' => Milestone::factory(),
            'file_path' => 'deliverables/'.fake()->uuid().'.zip',
            'description' => fake()->sentence(),
            'submitted_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\User\Models\User;
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
            'uploaded_by' => User::factory(),
            'file_path' => 'deliverables/'.fake()->uuid().'.zip',
            'mime_type' => 'application/zip',
            'size_bytes' => fake()->numberBetween(1_000, 5_000_000),
            'description' => fake()->sentence(),
            'submitted_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'scanned' => true,
        ];
    }

    public function unscanned(): static
    {
        return $this->state(fn (array $attributes) => ['scanned' => false]);
    }
}

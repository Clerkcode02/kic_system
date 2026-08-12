<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\PortfolioItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioItem>
 */
class PortfolioItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PortfolioItem>
     */
    protected $model = PortfolioItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_profile_id' => FreelancerProfile::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->paragraph(),
            'file_path' => 'portfolio-items/'.fake()->uuid().'.jpg',
            'project_url' => fake()->optional()->url(),
            'sort_order' => 0,
        ];
    }
}

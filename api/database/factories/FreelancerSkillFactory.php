<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Enums\SkillProficiencyLevel;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\FreelancerSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerSkill>
 */
class FreelancerSkillFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FreelancerSkill>
     */
    protected $model = FreelancerSkill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'freelancer_profile_id' => FreelancerProfile::factory(),
            'skill_name' => fake()->randomElement([
                'PHP', 'Laravel', 'React', 'TypeScript', 'PostgreSQL', 'UI/UX Design',
                'Plumbing', 'Electrical Wiring', 'Copywriting', 'SEO', 'Video Editing',
            ]),
            'proficiency_level' => fake()->randomElement(SkillProficiencyLevel::cases()),
        ];
    }
}

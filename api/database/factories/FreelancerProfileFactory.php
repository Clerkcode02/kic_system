<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreelancerProfile>
 */
class FreelancerProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FreelancerProfile>
     */
    protected $model = FreelancerProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->freelancer(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraphs(2, true),
            'hourly_rate' => Money::fromMinorUnits(fake()->numberBetween(3_000, 15_000), 'CAD'),
            'currency' => 'CAD',
            'years_experience' => fake()->numberBetween(1, 20),
            'approval_status' => FreelancerApprovalStatus::Pending,
            'rating_avg' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => FreelancerApprovalStatus::Approved,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => FreelancerApprovalStatus::Pending,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => FreelancerApprovalStatus::Rejected,
        ]);
    }

    public function payoutsEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_connect_account_id' => 'acct_'.fake()->unique()->bothify('##########????'),
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
        ]);
    }
}

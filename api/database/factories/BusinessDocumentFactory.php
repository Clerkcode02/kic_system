<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Business\Enums\BusinessDocumentType;
use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessDocument>
 */
class BusinessDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BusinessDocument>
     */
    protected $model = BusinessDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'document_type' => fake()->randomElement(BusinessDocumentType::cases()),
            'document_number' => strtoupper(fake()->bothify('??######')),
            'file_path' => 'business-documents/'.fake()->uuid().'.pdf',
            'issuing_authority' => fake()->randomElement(['Province of Ontario', 'Canada Revenue Agency', 'ServiceOntario']),
            'issued_at' => fake()->dateTimeBetween('-3 years', '-1 year'),
            'expires_at' => fake()->dateTimeBetween('+1 year', '+3 years'),
            'verification_status' => BusinessVerificationStatus::Pending,
            'rejection_reason' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => BusinessVerificationStatus::Verified,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => BusinessVerificationStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}

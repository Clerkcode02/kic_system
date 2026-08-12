<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Platform\Models\Attachment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Attachment>
     */
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => 'deliverable',
            'attachable_id' => fake()->uuid(),
            'uploaded_by' => User::factory(),
            'disk' => 's3',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
            'scanned' => true,
        ];
    }

    public function unscanned(): static
    {
        return $this->state(fn (array $attributes) => ['scanned' => false]);
    }
}

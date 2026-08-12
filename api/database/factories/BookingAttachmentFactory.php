<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAttachment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAttachment>
 */
class BookingAttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BookingAttachment>
     */
    protected $model = BookingAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'booking-attachments/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(50_000, 5_000_000),
            'caption' => fake()->optional()->sentence(),
        ];
    }
}

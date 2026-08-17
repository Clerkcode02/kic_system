<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Booking>
     */
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Created eagerly (not passed as a nested factory) so the address
        // and the booking share the same customer instead of each foreign
        // key independently spinning up its own unrelated User.
        $customer = User::factory()->customer()->create();

        return [
            'booking_number' => 'BK-'.fake()->unique()->numerify('########'),
            'customer_id' => $customer->id,
            'provider_id' => Business::factory(),
            'service_id' => Service::factory(),
            'scheduled_date' => fake()->dateTimeBetween('+1 day', '+2 weeks')->format('Y-m-d'),
            'time_slot_start' => '09:00:00',
            'time_slot_end' => '11:00:00',
            'address_id' => Address::factory()->for($customer, 'user'),
            'lat' => fake()->latitude(43.60, 43.85),
            'lng' => fake()->longitude(-79.60, -79.20),
            'notes' => fake()->optional()->sentence(),
            'status' => BookingStatus::Pending,
            'payment_status' => BookingPaymentStatus::Unpaid,
        ];
    }

    /**
     * SRS §6.1: a guest booking has no customer and no saved address — the
     * contact triple and the inline address snapshot stand in for both.
     * The `bookings_exactly_one_actor` CHECK means these must be cleared
     * together, which is exactly what this state does.
     */
    public function guest(?string $email = null): static
    {
        return $this->state(function (array $attributes) use ($email) {
            $email ??= fake()->unique()->safeEmail();

            return [
                'customer_id' => null,
                'address_id' => null,
                'guest_name' => fake()->name(),
                'guest_email' => $email,
                'guest_phone' => fake()->numerify('+1416#######'),
                'guest_email_normalized' => mb_strtolower(trim($email)),
                'service_address_line1' => fake()->streetAddress(),
                'service_address_city' => 'Toronto',
                'service_address_province' => 'ON',
                'service_address_postal_code' => 'M5V 2T6',
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::Pending]);
    }

    public function waitingForQuotation(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::WaitingForQuotation]);
    }

    public function quotationSent(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::QuotationSent]);
    }

    public function waitingForCustomer(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::WaitingForCustomer]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Accepted,
            'payment_status' => BookingPaymentStatus::Paid,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Scheduled,
            'payment_status' => BookingPaymentStatus::Paid,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::InProgress,
            'payment_status' => BookingPaymentStatus::Paid,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
            'payment_status' => BookingPaymentStatus::Paid,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::Declined]);
    }

    public function quotationExpired(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::QuotationExpired]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookingStatus::Cancelled]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Refunded,
            'payment_status' => BookingPaymentStatus::Refunded,
        ]);
    }
}

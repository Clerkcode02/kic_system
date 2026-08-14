<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Enums\BookingPaymentStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Booking\Validators\BookingAvailabilityValidator;
use App\Domain\Booking\Validators\DoubleBookingValidator;
use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Enums\ServicePricingType;
use App\Domain\Catalog\Models\Service;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use App\Support\Action;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SRS §8/§19: creates a booking, routes it to WaitingForQuotation or
 * straight to Scheduled depending on the service's pricing type, and
 * enforces every §19 booking rule server-side (never trusting a client to
 * have already checked them).
 */
final class CreateBookingRequest implements Action
{
    public function __construct(
        private readonly BookingAvailabilityValidator $availabilityValidator,
        private readonly DoubleBookingValidator $doubleBookingValidator,
    ) {
    }

    /**
     * @param  array{service_id: string, address_id: string, scheduled_date: string, time_slot_start: string, time_slot_end: string, notes?: ?string}  $data
     */
    public function handle(User $customer, array $data): Booking
    {
        $service = Service::query()->with('business.user')->findOrFail($data['service_id']);
        $provider = $service->business;

        $address = Address::query()
            ->where('user_id', $customer->id)
            ->findOrFail($data['address_id']);

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'service_id' => 'This service is not currently available for booking.',
            ]);
        }

        if ($provider->verification_status !== BusinessVerificationStatus::Verified) {
            throw ValidationException::withMessages([
                'service_id' => 'This provider is not yet verified and cannot accept bookings.',
            ]);
        }

        if ($provider->user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'service_id' => 'This provider is currently unavailable.',
            ]);
        }

        $date = CarbonImmutable::parse($data['scheduled_date']);
        $this->assertNotInThePast($date, $data['time_slot_start']);

        return DB::transaction(function () use ($customer, $data, $service, $provider, $address, $date) {
            $this->doubleBookingValidator->assertAvailable(
                $provider,
                $date,
                $data['time_slot_start'],
                $data['time_slot_end'],
            );

            $this->availabilityValidator->assertWithinAvailability(
                $provider,
                $date,
                $data['time_slot_start'],
                $data['time_slot_end'],
            );

            $this->assertUnderDailyCap($provider, $date);

            $status = $service->pricing_type === ServicePricingType::Fixed
                ? BookingStatus::Scheduled
                : BookingStatus::WaitingForQuotation;

            $booking = Booking::create([
                'booking_number' => $this->generateBookingNumber(),
                'customer_id' => $customer->id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'scheduled_date' => $date->toDateString(),
                'time_slot_start' => $data['time_slot_start'],
                'time_slot_end' => $data['time_slot_end'],
                'address_id' => $address->id,
                'lat' => $address->lat,
                'lng' => $address->lng,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'payment_status' => BookingPaymentStatus::Unpaid,
            ]);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => null,
                'to_status' => $status->value,
                'changed_by' => $customer->id,
                'note' => 'Booking requested by customer.',
            ]);

            AuditLog::create([
                'actor_id' => $customer->id,
                'action' => 'booking.created',
                'auditable_type' => 'booking',
                'auditable_id' => $booking->id,
                'before_state' => null,
                'after_state' => ['status' => $status->value],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            BookingCreated::dispatch($booking);

            return $booking;
        });
    }

    private function assertNotInThePast(CarbonImmutable $date, string $timeSlotStart): void
    {
        $today = CarbonImmutable::now()->startOfDay();

        if ($date->lt($today)) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'The scheduled date cannot be in the past.',
            ]);
        }

        if ($date->isSameDay($today) && $date->toDateString().' '.$timeSlotStart < CarbonImmutable::now()->format('Y-m-d H:i:s')) {
            throw ValidationException::withMessages([
                'time_slot_start' => 'The scheduled time cannot be in the past.',
            ]);
        }
    }

    private function assertUnderDailyCap(Business $provider, CarbonImmutable $date): void
    {
        $activeCount = Booking::query()
            ->where('provider_id', $provider->id)
            ->where('scheduled_date', $date->toDateString())
            ->whereNotIn('status', [
                BookingStatus::Declined,
                BookingStatus::QuotationExpired,
                BookingStatus::Cancelled,
                BookingStatus::Refunded,
            ])
            ->count();

        if ($activeCount >= $provider->max_bookings_per_day) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'This provider has reached its maximum bookings for the selected date.',
            ]);
        }
    }

    private function generateBookingNumber(): string
    {
        return 'BK-'.CarbonImmutable::now()->format('ymd').strtoupper(Str::random(6));
    }
}

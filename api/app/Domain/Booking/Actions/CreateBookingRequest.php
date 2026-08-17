<?php

declare(strict_types=1);

namespace App\Domain\Booking\Actions;

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
use App\Domain\Platform\Services\SettingsRepository;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Models\Address;
use App\Support\Action;
use App\Support\ValueObjects\BookingActor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SRS §8/§19: creates a booking, routes it to WaitingForQuotation or
 * straight to Scheduled depending on the service's pricing type, and
 * enforces every §19 booking rule server-side (never trusting a client to
 * have already checked them).
 *
 * SRS §6.1: this is the *only* creation path — guest and registered alike.
 * The actor kind is carried by {@see BookingActor}; every rule below runs
 * identically for both, and the guest-specific rules (open-booking cap) are
 * additive rather than a separate code path.
 */
final class CreateBookingRequest implements Action
{
    /**
     * @var list<BookingStatus>
     */
    private const CLOSED_STATUSES = [
        BookingStatus::Declined,
        BookingStatus::QuotationExpired,
        BookingStatus::Cancelled,
        BookingStatus::Refunded,
        BookingStatus::Completed,
    ];

    public function __construct(
        private readonly BookingAvailabilityValidator $availabilityValidator,
        private readonly DoubleBookingValidator $doubleBookingValidator,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * @param  array{
     *     service_id: string,
     *     address_id?: ?string,
     *     service_address?: array{line1: string, line2?: ?string, city: string, province: string, postal_code: string, lat: float|string, lng: float|string},
     *     scheduled_date: string,
     *     time_slot_start: string,
     *     time_slot_end: string,
     *     notes?: ?string
     * }  $data
     */
    public function handle(BookingActor $actor, array $data): Booking
    {
        $service = Service::query()->with('business.user')->findOrFail($data['service_id']);
        $provider = $service->business;

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

        $address = $this->resolveAddress($actor, $data);

        $date = CarbonImmutable::parse($data['scheduled_date']);
        $this->assertNotInThePast($date, $data['time_slot_start']);

        if ($actor->isGuest()) {
            $this->assertUnderGuestOpenBookingCap($actor);
        }

        return DB::transaction(function () use ($actor, $data, $service, $provider, $address, $date) {
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
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'scheduled_date' => $date->toDateString(),
                'time_slot_start' => $data['time_slot_start'],
                'time_slot_end' => $data['time_slot_end'],
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'payment_status' => BookingPaymentStatus::Unpaid,
                // Keeps the "exactly one actor" CHECK satisfied by
                // construction rather than by remembering to null the
                // other side out here.
                ...$actor->bookingAttributes(),
                ...$address,
            ]);

            // PostGIS point for the address snapshot, so radius queries
            // work off bookings without an addresses row (SRS §18).
            DB::statement(
                'UPDATE bookings SET service_location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [(float) $address['lng'], (float) $address['lat'], $booking->id],
            );

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => null,
                'to_status' => $status->value,
                // Nullable: a guest has no users row to reference.
                'changed_by' => $actor->userId(),
                'note' => 'Booking requested by customer.',
            ]);

            BookingCreated::dispatch($booking);

            return $booking;
        });
    }

    /**
     * Registered actors may book against a saved address; guests always
     * supply the address inline. Either way the booking stores a
     * denormalized snapshot, so every downstream reader has one shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveAddress(BookingActor $actor, array $data): array
    {
        if (! $actor->isGuest() && ! empty($data['address_id'])) {
            /** @var Address $saved */
            $saved = Address::query()
                ->where('user_id', $actor->userId())
                ->findOrFail($data['address_id']);

            return [
                'address_id' => $saved->id,
                'service_address_line1' => $saved->street,
                'service_address_line2' => $saved->unit,
                'service_address_city' => $saved->city,
                'service_address_province' => $saved->state_province,
                'service_address_postal_code' => $saved->postal_code,
                'lat' => $saved->lat,
                'lng' => $saved->lng,
            ];
        }

        if (empty($data['service_address'])) {
            throw ValidationException::withMessages([
                'service_address' => 'A service address is required.',
            ]);
        }

        $inline = $data['service_address'];

        return [
            'address_id' => null,
            'service_address_line1' => $inline['line1'],
            'service_address_line2' => $inline['line2'] ?? null,
            'service_address_city' => $inline['city'],
            'service_address_province' => $inline['province'],
            'service_address_postal_code' => $inline['postal_code'],
            'lat' => $inline['lat'],
            'lng' => $inline['lng'],
        ];
    }

    /**
     * SRS §6.1 "Abuse controls" — a guest can't accumulate unbounded open
     * bookings against one email. Admin-configurable, never a constant.
     */
    private function assertUnderGuestOpenBookingCap(BookingActor $actor): void
    {
        $cap = (int) $this->settings->get('guest.max_open_bookings', 5);

        $open = Booking::query()
            ->where('guest_email_normalized', $actor->normalizedEmail())
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->count();

        if ($open >= $cap) {
            throw ValidationException::withMessages([
                'guest_email' => 'You have too many bookings in progress. Please complete or cancel one before booking again.',
            ]);
        }
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

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Booking\Models\Booking;
use App\Domain\Quotation\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SRS §6.1: the reduced read model a booking access token opens. It is
 * deliberately *not* {@see BookingResource} minus a few fields — it is its
 * own allow-list, so a field added to the registered resource can never
 * leak into the guest one by default.
 *
 * Excluded on purpose: internal UUIDs (the booking number is the public
 * identifier), provider PII and contact details, the owning customer,
 * attachments, status-history actor ids, and anything audit-shaped.
 *
 * The exact key set is asserted in
 * tests/Feature/Guest/GuestBookingResourceTest.php — adding a key here
 * without updating that test is a failing build, which is the point.
 */
class GuestBookingResource extends JsonResource
{
    /**
     * @param  Booking  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return [
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'scheduled_date' => $booking->scheduled_date->toDateString(),
            'time_slot_start' => $booking->time_slot_start,
            'time_slot_end' => $booking->time_slot_end,
            'notes' => $booking->notes,
            'service' => [
                'title' => $booking->service->title,
                'pricing_type' => $booking->service->pricing_type,
                'base_price' => $booking->service->base_price->toDecimal(),
                'currency' => $booking->service->currency,
            ],
            'provider' => [
                // Display name only — no owner name, email, phone, address
                // or Connect account details.
                'display_name' => $booking->provider->legal_name,
                'rating_avg' => (float) $booking->provider->rating_avg,
            ],
            'service_address' => [
                'line1' => $booking->service_address_line1,
                'line2' => $booking->service_address_line2,
                'city' => $booking->service_address_city,
                'province' => $booking->service_address_province,
                'postal_code' => $booking->service_address_postal_code,
            ],
            'quotation' => $this->quotation($booking),
            'timeline' => $this->timeline($booking),
            'created_at' => $booking->created_at,
        ];
    }

    /**
     * The quotation the guest can currently act on, if any. Superseded
     * revisions are history, not choices, so only the live one is offered.
     *
     * @return array<string, mixed>|null
     */
    private function quotation(Booking $booking): ?array
    {
        /** @var Quotation|null $quotation */
        $quotation = $booking->quotations
            ->sortByDesc('revision_number')
            ->firstWhere(fn (Quotation $q) => $q->status !== 'superseded');

        if ($quotation === null) {
            return null;
        }

        return [
            // Needed to address POST /guest/quotations/{id}/accept|reject;
            // it is token-guarded, so it is a capability the holder already
            // has rather than an extra disclosure.
            'id' => $quotation->id,
            'labor_cost' => $quotation->labor_cost->toDecimal(),
            'materials_cost' => $quotation->materials_cost->toDecimal(),
            'additional_fees' => $quotation->additional_fees->toDecimal(),
            'platform_fee' => $quotation->platform_fee->toDecimal(),
            'tax_amount' => $quotation->tax_amount->toDecimal(),
            'discount_amount' => $quotation->discount_amount->toDecimal(),
            'total_amount' => $quotation->total_amount->toDecimal(),
            'deposit_percentage' => $quotation->deposit_percentage,
            'currency' => $quotation->currency,
            'valid_until' => $quotation->valid_until,
            'revision_number' => $quotation->revision_number,
            'status' => $quotation->status,
            'line_items' => $quotation->lineItems->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price->toDecimal(),
                'amount' => $item->amount->toDecimal(),
            ])->values()->all(),
        ];
    }

    /**
     * Status history with the actor stripped — a guest sees *that* their
     * booking moved and when, never who inside the platform moved it.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(Booking $booking): array
    {
        return $booking->statusHistory
            ->sortBy('created_at')
            ->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'occurred_at' => $entry->created_at,
            ])
            ->values()
            ->all();
    }
}

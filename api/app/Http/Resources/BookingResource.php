<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Booking\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'time_slot_start' => $this->time_slot_start,
            'time_slot_end' => $this->time_slot_end,
            'notes' => $this->notes,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'provider_completed_at' => $this->provider_completed_at,
            'service' => [
                'id' => $this->service->id,
                'title' => $this->service->title,
                'pricing_type' => $this->service->pricing_type,
                'base_price' => $this->service->base_price->toDecimal(),
                'currency' => $this->service->currency,
            ],
            'provider' => [
                'id' => $this->provider->id,
                'legal_name' => $this->provider->legal_name,
                'rating_avg' => (float) $this->provider->rating_avg,
            ],
            // A guest booking has no customer row (SRS §6.1) — the contact
            // accessors give one shape for both actor kinds without any
            // caller branching on customer_id.
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->contactName(),
                'is_guest' => $this->isGuest(),
            ],
            'address' => $this->whenLoaded('address', fn () => $this->address === null ? null : new AddressResource($this->address)),
            'service_address' => [
                'line1' => $this->service_address_line1,
                'line2' => $this->service_address_line2,
                'city' => $this->service_address_city,
                'province' => $this->service_address_province,
                'postal_code' => $this->service_address_postal_code,
            ],
            'status_history' => BookingStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'attachments' => BookingAttachmentResource::collection($this->whenLoaded('attachments')),
            'quotations' => QuotationResource::collection($this->whenLoaded('quotations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

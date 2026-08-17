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
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'address' => $this->whenLoaded('address', fn () => new AddressResource($this->address)),
            'status_history' => BookingStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'attachments' => BookingAttachmentResource::collection($this->whenLoaded('attachments')),
            'quotations' => QuotationResource::collection($this->whenLoaded('quotations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

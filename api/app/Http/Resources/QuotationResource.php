<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Quotation\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'previous_quotation_id' => $this->previous_quotation_id,
            'labor_cost' => $this->labor_cost->toDecimal(),
            'materials_cost' => $this->materials_cost->toDecimal(),
            'additional_fees' => $this->additional_fees->toDecimal(),
            'platform_fee' => $this->platform_fee->toDecimal(),
            'tax_amount' => $this->tax_amount->toDecimal(),
            'discount_amount' => $this->discount_amount->toDecimal(),
            'total_amount' => $this->total_amount->toDecimal(),
            'currency' => $this->currency,
            'valid_until' => $this->valid_until,
            'revision_number' => $this->revision_number,
            'status' => $this->status,
            'line_items' => QuotationLineItemResource::collection($this->whenLoaded('lineItems')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

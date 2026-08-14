<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Quotation\Models\QuotationLineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationLineItem
 */
class QuotationLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price->toDecimal(),
            'amount' => $this->amount->toDecimal(),
            'sort_order' => $this->sort_order,
        ];
    }
}

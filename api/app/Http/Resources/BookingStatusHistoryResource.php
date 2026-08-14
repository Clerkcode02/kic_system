<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Booking\Models\BookingStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingStatusHistory
 */
class BookingStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'changed_by' => $this->changed_by,
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Domain\Booking\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'address_id' => ['required', 'uuid', 'exists:addresses,id'],
            // Mirrors CreateBookingRequest::assertNotInThePast — the Action
            // is still the source of truth (it also checks the time slot
            // against "now" when the date is today).
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot_start' => ['required', 'date_format:H:i:s'],
            'time_slot_end' => ['required', 'date_format:H:i:s', 'after:time_slot_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

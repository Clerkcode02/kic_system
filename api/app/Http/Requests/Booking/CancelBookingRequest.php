<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('booking')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // CancelBooking::handle() is the source of truth for "required
            // when the actor is the provider" — the FormRequest can't know
            // which side of the booking is calling until the Action checks.
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}

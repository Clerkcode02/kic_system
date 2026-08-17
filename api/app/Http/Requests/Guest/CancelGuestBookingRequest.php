<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization for guest routes lives entirely in `ResolveBookingActor`
 * (SRS §6.1) — by the time a FormRequest runs, the booking has already been
 * resolved from the token or the request 404'd. There is no second
 * ownership check to make here.
 */
class CancelGuestBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // CancelBooking::handle() is the source of truth for when a
            // reason is mandatory (provider-initiated cancellations), which
            // a guest never is — so it stays optional here.
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

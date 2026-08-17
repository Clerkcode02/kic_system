<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * See {@see CancelGuestBookingRequest} — authorization is the token, checked
 * by `ResolveBookingActor` before this runs.
 */
class RejectGuestQuotationRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

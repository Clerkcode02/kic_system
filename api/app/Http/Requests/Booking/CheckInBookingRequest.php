<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CheckInBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkIn', $this->route('booking')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

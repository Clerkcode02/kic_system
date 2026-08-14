<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirmCompletion', $this->route('booking')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

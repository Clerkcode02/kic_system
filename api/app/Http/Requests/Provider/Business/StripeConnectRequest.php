<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Business;

use Illuminate\Foundation\Http\FormRequest;

class StripeConnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->user()?->business;

        if ($business === null) {
            return false;
        }

        return $this->user()?->can('manage', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Domain\Booking\Models\Booking;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payable = $this->resolvePayable();

        if ($payable === null) {
            return false;
        }

        return $this->user()?->can('create', [Payment::class, $payable]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payable_type' => ['required', Rule::in(['booking', 'milestone'])],
            'payable_id' => ['required', 'uuid'],
        ];
    }

    public function resolvePayable(): Booking|Milestone|null
    {
        $type = $this->input('payable_type');
        $id = $this->input('payable_id');

        if (! is_string($type) || ! is_string($id)) {
            return null;
        }

        return match ($type) {
            'booking' => Booking::find($id),
            'milestone' => Milestone::find($id),
            default => null,
        };
    }
}

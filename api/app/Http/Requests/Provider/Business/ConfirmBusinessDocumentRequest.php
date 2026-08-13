<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Business;

use App\Domain\Business\Enums\BusinessDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ConfirmBusinessDocumentRequest extends FormRequest
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
        return [
            'file_path' => ['required', 'string', 'max:1024'],
            'document_type' => ['required', new Enum(BusinessDocumentType::class)],
            'document_number' => ['required', 'string', 'max:255'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
        ];
    }
}

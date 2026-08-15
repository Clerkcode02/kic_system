<?php

declare(strict_types=1);

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('resolve', $this->route('dispute'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'release_escrow' => ['sometimes', 'boolean'],
        ];
    }
}

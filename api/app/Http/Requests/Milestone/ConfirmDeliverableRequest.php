<?php

declare(strict_types=1);

namespace App\Http\Requests\Milestone;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->route('milestone')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file_path' => ['required', 'string', 'max:1024'],
            'mime_type' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

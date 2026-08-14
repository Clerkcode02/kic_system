<?php

declare(strict_types=1);

namespace App\Http\Requests\Milestone;

use Illuminate\Foundation\Http\FormRequest;

class RequestDeliverableUploadUrlRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:255'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Milestone;

use Illuminate\Foundation\Http\FormRequest;

class RejectMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('milestone')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

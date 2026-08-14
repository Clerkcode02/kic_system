<?php

declare(strict_types=1);

namespace App\Http\Requests\Milestone;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMilestoneRequest extends FormRequest
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
            'deliverable_ids' => ['required', 'array', 'min:1'],
            'deliverable_ids.*' => ['required', 'uuid'],
        ];
    }
}

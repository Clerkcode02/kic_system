<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'budget_min' => ['sometimes', 'numeric', 'gt:0'],
            'budget_max' => ['sometimes', 'numeric', 'gt:0'],
            'deadline' => ['sometimes', 'date', 'after:today'],
        ];
    }
}

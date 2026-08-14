<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class IndexProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'uuid', 'exists:categories,id'],
            'budget_min' => ['sometimes', 'numeric', 'gt:0'],
            'budget_max' => ['sometimes', 'numeric', 'gt:0'],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}

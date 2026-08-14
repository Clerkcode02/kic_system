<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Domain\Freelance\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            // The Action is the source of truth for "min <= max" and
            // ">0" (it needs the parsed Money values to compare precisely).
            'budget_min' => ['required', 'numeric', 'gt:0'],
            'budget_max' => ['required', 'numeric', 'gte:budget_min'],
            'deadline' => ['required', 'date', 'after:today'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Catalog;

use App\Domain\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', Category::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => ['required', 'uuid', 'exists:categories,id'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}

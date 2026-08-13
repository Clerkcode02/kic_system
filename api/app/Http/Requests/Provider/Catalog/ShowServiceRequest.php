<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ShowServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('service')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

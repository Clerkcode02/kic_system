<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Catalog;

use App\Domain\Catalog\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class ProviderServiceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->user()?->business;

        if ($business === null) {
            return false;
        }

        return $this->user()?->can('create', [Service::class, $business]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['sometimes', 'string'],
        ];
    }
}

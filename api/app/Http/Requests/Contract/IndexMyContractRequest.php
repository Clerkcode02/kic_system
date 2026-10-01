<?php

declare(strict_types=1);

namespace App\Http\Requests\Contract;

use App\Domain\Freelance\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class IndexMyContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Contract::class) ?? false;
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

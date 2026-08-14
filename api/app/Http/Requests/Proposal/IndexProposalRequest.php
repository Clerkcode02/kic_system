<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposal;

use Illuminate\Foundation\Http\FormRequest;

class IndexProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewProposals', $this->route('project')) ?? false;
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

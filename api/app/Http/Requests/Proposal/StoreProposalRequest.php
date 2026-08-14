<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposal;

use App\Domain\Freelance\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Proposal::class, $this->route('project')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proposed_amount' => ['required', 'numeric', 'gt:0'],
            'cover_letter' => ['required', 'string', 'max:5000'],
            'delivery_days' => ['required', 'integer', 'min:1'],
        ];
    }
}

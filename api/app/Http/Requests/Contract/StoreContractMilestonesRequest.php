<?php

declare(strict_types=1);

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractMilestonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('defineMilestones', $this->route('contract')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The Action is the source of truth for "sums to the contract
            // total" — it needs the parsed Money values to compare
            // precisely (StoreProjectRequest precedent).
            'milestones' => ['required', 'array', 'min:1'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.amount' => ['required', 'numeric', 'gt:0'],
            'milestones.*.due_date' => ['required', 'date'],
        ];
    }
}

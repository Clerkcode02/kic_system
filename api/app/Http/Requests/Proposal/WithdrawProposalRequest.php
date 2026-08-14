<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposal;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('withdraw', $this->route('proposal')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

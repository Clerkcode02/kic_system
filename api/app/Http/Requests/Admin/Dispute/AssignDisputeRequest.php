<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Dispute;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('assign', $this->route('dispute'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_id' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $isAdmin = User::query()
                        ->whereKey($value)
                        ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin']))
                        ->exists();

                    if (! $isAdmin) {
                        $fail('The selected admin_id must be an existing admin or super_admin user.');
                    }
                },
            ],
        ];
    }
}

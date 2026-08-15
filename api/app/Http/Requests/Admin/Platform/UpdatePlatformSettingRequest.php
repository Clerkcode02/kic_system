<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Platform;

use App\Domain\User\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::PlatformSettingsManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string'],
            'type' => ['sometimes', Rule::in(['string', 'integer', 'float', 'boolean', 'json'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}

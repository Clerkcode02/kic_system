<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Domain\Notification\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', new Enum(NotificationCategory::class)],
            'preferences.*.email' => ['sometimes', 'boolean'],
            'preferences.*.push_web' => ['sometimes', 'boolean'],
        ];
    }
}

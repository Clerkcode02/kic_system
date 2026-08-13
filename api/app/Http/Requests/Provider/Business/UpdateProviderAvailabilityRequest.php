<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider\Business;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->user()?->business;

        if ($business === null) {
            return false;
        }

        return $this->user()?->can('manage', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weekly' => ['sometimes', 'array'],
            'weekly.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'weekly.*.start_time' => ['required', 'date_format:H:i'],
            'weekly.*.end_time' => ['required', 'date_format:H:i'],
            'weekly.*.is_active' => ['sometimes', 'boolean'],

            'overrides' => ['sometimes', 'array'],
            'overrides.*.date' => ['required', 'date'],
            'overrides.*.is_blackout' => ['sometimes', 'boolean'],
            'overrides.*.start_time' => ['nullable', 'date_format:H:i'],
            'overrides.*.end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('weekly', []) as $index => $entry) {
                if (isset($entry['start_time'], $entry['end_time']) && $entry['start_time'] >= $entry['end_time']) {
                    $validator->errors()->add("weekly.{$index}.end_time", 'The end time must be after the start time.');
                }
            }

            foreach ($this->input('overrides', []) as $index => $entry) {
                $isBlackout = $entry['is_blackout'] ?? false;

                if ($isBlackout) {
                    continue;
                }

                if (empty($entry['start_time']) || empty($entry['end_time'])) {
                    $validator->errors()->add("overrides.{$index}.start_time", 'A non-blackout override requires start_time and end_time.');

                    continue;
                }

                if ($entry['start_time'] >= $entry['end_time']) {
                    $validator->errors()->add("overrides.{$index}.end_time", 'The end time must be after the start time.');
                }
            }
        });
    }
}

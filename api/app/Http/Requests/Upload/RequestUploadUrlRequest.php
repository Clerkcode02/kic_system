<?php

declare(strict_types=1);

namespace App\Http\Requests\Upload;

use App\Support\MorphResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `attachable_type` is intentionally a short allow-list, not every
 * registered morph alias — only domains without their own dedicated
 * presign flow (business documents, deliverables) belong here. Currently
 * just dispute evidence, since disputes have no other upload path.
 */
class RequestUploadUrlRequest extends FormRequest
{
    private const ALLOWED_TYPES = ['dispute'];

    public function authorize(): bool
    {
        $type = $this->input('attachable_type');
        $id = $this->input('attachable_id');

        // An unsupported type or a missing/non-existent attachable isn't an
        // authorization failure — defer to rules()/the controller so it
        // surfaces as 422/404 instead of masquerading as a 403.
        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true) || ! is_string($id)) {
            return true;
        }

        $attachable = MorphResolver::resolve($type, $id);

        if ($attachable === null) {
            return true;
        }

        return $this->user()?->can('manageEvidence', $attachable) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', Rule::in(['dispute'])],
            'attachable_id' => ['required', 'uuid'],
            'filename' => ['required', 'string', 'max:255'],
        ];
    }
}

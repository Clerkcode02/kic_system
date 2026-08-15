<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformSetting
 */
class PlatformSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->typedValue,
            'type' => $this->type,
            'description' => $this->description,
            'updated_at' => $this->updated_at,
        ];
    }
}

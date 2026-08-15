<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Platform;

use App\Domain\Platform\Events\PlatformSettingUpdated;
use App\Domain\Platform\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Platform\UpdatePlatformSettingRequest;
use App\Http\Resources\PlatformSettingResource;
use App\Support\Facades\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformSettingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('viewAny', PlatformSetting::class), 403);

        return PlatformSettingResource::collection(
            PlatformSetting::query()->orderBy('key')->get(),
        );
    }

    public function update(UpdatePlatformSettingRequest $request, string $key): PlatformSettingResource
    {
        $previousValue = PlatformSetting::query()->where('key', $key)->value('value');

        $setting = Settings::set(
            $key,
            $request->validated('value'),
            $request->validated('type'),
            $request->validated('description'),
        );

        PlatformSettingUpdated::dispatch($setting, $request->user(), $previousValue);

        return new PlatformSettingResource($setting);
    }
}

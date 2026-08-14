<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Notification;

use App\Domain\Notification\Actions\UpdateNotificationPreferences;
use App\Domain\Notification\Queries\ListNotificationPreferencesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationPreferenceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request, ListNotificationPreferencesQuery $query): AnonymousResourceCollection
    {
        return NotificationPreferenceResource::collection($query->handle($request->user()));
    }

    public function update(
        UpdateNotificationPreferencesRequest $request,
        UpdateNotificationPreferences $action,
    ): AnonymousResourceCollection {
        return NotificationPreferenceResource::collection(
            $action->handle($request->user(), $request->validated('preferences')),
        );
    }
}

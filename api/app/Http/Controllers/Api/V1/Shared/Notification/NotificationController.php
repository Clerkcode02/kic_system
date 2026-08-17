<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Notification;

use App\Domain\Notification\Queries\ListNotificationsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ReadNotificationRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request, ListNotificationsQuery $query): AnonymousResourceCollection
    {
        return NotificationResource::collection($query->handle($request->user()));
    }

    public function read(ReadNotificationRequest $request, DatabaseNotification $notification): NotificationResource
    {
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return new NotificationResource($notification);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}

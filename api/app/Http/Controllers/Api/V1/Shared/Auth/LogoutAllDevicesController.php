<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\LogoutAllDevices;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutAllDevicesController extends Controller
{
    public function __invoke(Request $request, LogoutAllDevices $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($request, $user);

        return response()->json(['message' => 'Logged out of all devices.']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\LoginUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUser $action): JsonResponse
    {
        $credentials = $action->handle(
            $request,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'data' => [
                'user' => new UserResource($credentials['user']),
                'token' => $credentials['token'],
            ],
        ]);
    }
}

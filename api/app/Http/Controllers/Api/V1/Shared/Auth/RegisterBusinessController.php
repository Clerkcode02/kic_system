<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\RegisterBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterBusinessController extends Controller
{
    public function __invoke(RegisterBusinessRequest $request, RegisterBusiness $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}

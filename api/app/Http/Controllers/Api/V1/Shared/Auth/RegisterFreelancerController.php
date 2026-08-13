<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\RegisterFreelancer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterFreelancerRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterFreelancerController extends Controller
{
    public function __invoke(RegisterFreelancerRequest $request, RegisterFreelancer $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}

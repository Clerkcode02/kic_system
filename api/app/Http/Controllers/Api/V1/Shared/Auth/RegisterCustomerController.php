<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\RegisterCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterCustomerController extends Controller
{
    public function __invoke(RegisterCustomerRequest $request, RegisterCustomer $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}

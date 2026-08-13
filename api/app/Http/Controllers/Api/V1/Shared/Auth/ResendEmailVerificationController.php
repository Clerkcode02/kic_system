<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\ResendEmailVerification;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendEmailVerificationRequest;
use Illuminate\Http\JsonResponse;

class ResendEmailVerificationController extends Controller
{
    public function __invoke(ResendEmailVerificationRequest $request, ResendEmailVerification $action): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->firstOrFail();

        $action->handle($user);

        return response()->json(['message' => 'Verification email sent.']);
    }
}

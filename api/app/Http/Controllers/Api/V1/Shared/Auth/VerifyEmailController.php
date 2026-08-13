<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Auth;

use App\Domain\User\Actions\VerifyEmail;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(string $id, string $hash, VerifyEmail $action): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        $action->handle($user);

        return response()->json(['message' => 'Email verified.']);
    }
}

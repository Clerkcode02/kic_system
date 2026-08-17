<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Dispute;

use App\Domain\Dispute\Actions\AssignDispute;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dispute\AssignDisputeRequest;
use App\Http\Resources\DisputeResource;

class AssignDisputeController extends Controller
{
    public function __invoke(AssignDisputeRequest $request, Dispute $dispute, AssignDispute $action): DisputeResource
    {
        $admin = User::query()->findOrFail($request->validated('admin_id'));

        $dispute = $action->handle($dispute, $admin, $request->user());

        return new DisputeResource($dispute);
    }
}

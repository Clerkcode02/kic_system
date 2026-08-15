<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Dispute;

use App\Domain\Dispute\Actions\RaiseDispute;
use App\Domain\Dispute\Actions\ResolveDispute;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Dispute\Queries\ListDisputesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\ResolveDisputeRequest;
use App\Http\Requests\Dispute\StoreDisputeRequest;
use App\Http\Resources\DisputeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DisputeController extends Controller
{
    public function index(Request $request, ListDisputesQuery $query): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('viewAny', Dispute::class), 403);

        return DisputeResource::collection($query->handle($request->user()));
    }

    public function show(Request $request, Dispute $dispute): DisputeResource
    {
        abort_unless($request->user()?->can('view', $dispute), 403);

        return new DisputeResource($dispute);
    }

    public function store(StoreDisputeRequest $request, RaiseDispute $action): DisputeResource
    {
        $dispute = $action->handle(
            $request->validated('disputable_type'),
            $request->validated('disputable_id'),
            $request->user(),
            $request->validated('reason'),
        );

        return new DisputeResource($dispute);
    }

    public function resolve(ResolveDisputeRequest $request, Dispute $dispute, ResolveDispute $action): DisputeResource
    {
        $dispute = $action->handle(
            $dispute,
            $request->user(),
            $request->validated('resolution_notes'),
            (bool) $request->validated('release_escrow', false),
        );

        return new DisputeResource($dispute);
    }
}

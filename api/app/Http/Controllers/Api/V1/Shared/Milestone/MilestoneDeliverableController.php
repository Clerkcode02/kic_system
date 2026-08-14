<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Milestone;

use App\Domain\Freelance\Models\Milestone;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliverableResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MilestoneDeliverableController extends Controller
{
    public function index(Request $request, Milestone $milestone): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('view', $milestone), 403);

        return DeliverableResource::collection($milestone->deliverables()->latest('submitted_at')->get());
    }
}

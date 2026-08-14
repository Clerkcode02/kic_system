<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Contract;

use App\Domain\Freelance\Models\Contract;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function show(Request $request, Contract $contract): ContractResource
    {
        abort_unless($request->user()?->can('view', $contract), 403);

        return new ContractResource($contract->load('milestones'));
    }
}

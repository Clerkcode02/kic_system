<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Audit;

use App\Domain\Audit\Queries\ListAuditLogsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request, ListAuditLogsQuery $query): AnonymousResourceCollection
    {
        return AuditLogResource::collection($query->handle($request->user(), $request->validated()));
    }
}

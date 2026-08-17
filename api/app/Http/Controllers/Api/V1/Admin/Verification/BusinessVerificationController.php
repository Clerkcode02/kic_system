<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Domain\Business\Actions\ApproveBusinessVerification;
use App\Domain\Business\Actions\GenerateBusinessDocumentUrl;
use App\Domain\Business\Actions\RejectBusinessVerification;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessDocument;
use App\Domain\Business\Queries\ListPendingBusinessesQuery;
use App\Domain\User\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Verification\BulkVerificationDecisionRequest;
use App\Http\Requests\Admin\Verification\RejectBusinessVerificationRequest;
use App\Http\Resources\BusinessVerificationResource;
use App\Support\ConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusinessVerificationController extends Controller
{
    public function index(Request $request, ListPendingBusinessesQuery $query): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can(PermissionName::VerificationView->value), 403);

        return BusinessVerificationResource::collection(
            $query->handle($request->only('status')),
        );
    }

    public function show(Request $request, Business $business): BusinessVerificationResource
    {
        abort_unless($request->user()?->can(PermissionName::VerificationView->value), 403);

        return new BusinessVerificationResource($business->load(['user', 'documents']));
    }

    public function approve(Request $request, Business $business, ApproveBusinessVerification $action): BusinessVerificationResource
    {
        abort_unless($request->user()?->can('approve', $business), 403);

        return new BusinessVerificationResource($action->handle($business));
    }

    public function reject(RejectBusinessVerificationRequest $request, Business $business, RejectBusinessVerification $action): BusinessVerificationResource
    {
        return new BusinessVerificationResource($action->handle($business, $request->validated('reason')));
    }

    public function bulkApprove(BulkVerificationDecisionRequest $request, ApproveBusinessVerification $action): JsonResponse
    {
        return $this->bulk($request->validated('ids'), fn (Business $business) => $action->handle($business));
    }

    public function bulkReject(BulkVerificationDecisionRequest $request, RejectBusinessVerification $action): JsonResponse
    {
        $reason = $request->validated('reason');

        return $this->bulk($request->validated('ids'), fn (Business $business) => $action->handle($business, $reason));
    }

    public function documentUrl(Request $request, BusinessDocument $document, GenerateBusinessDocumentUrl $action): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionName::VerificationView->value), 403);

        return response()->json(['data' => $action->handle($document)]);
    }

    /**
     * @param  list<string>  $ids
     */
    private function bulk(array $ids, callable $handle): JsonResponse
    {
        $approved = [];
        $failed = [];

        foreach ($ids as $id) {
            $business = Business::query()->find($id);

            if ($business === null) {
                $failed[] = ['id' => $id, 'reason' => 'not_found'];

                continue;
            }

            try {
                $handle($business);
                $approved[] = $id;
            } catch (ConflictException $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return response()->json(['data' => ['succeeded' => $approved, 'failed' => $failed]]);
    }
}

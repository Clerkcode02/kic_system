<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Domain\Freelance\Actions\ApproveFreelancerVerification;
use App\Domain\Freelance\Actions\GeneratePortfolioItemUrl;
use App\Domain\Freelance\Actions\RejectFreelancerVerification;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\PortfolioItem;
use App\Domain\Freelance\Queries\ListPendingFreelancersQuery;
use App\Domain\User\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Verification\BulkVerificationDecisionRequest;
use App\Http\Requests\Admin\Verification\RejectFreelancerVerificationRequest;
use App\Http\Resources\FreelancerVerificationResource;
use App\Support\ConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FreelancerVerificationController extends Controller
{
    public function index(Request $request, ListPendingFreelancersQuery $query): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can(PermissionName::VerificationView->value), 403);

        return FreelancerVerificationResource::collection(
            $query->handle($request->only('status')),
        );
    }

    public function show(Request $request, FreelancerProfile $freelancer): FreelancerVerificationResource
    {
        abort_unless($request->user()?->can(PermissionName::VerificationView->value), 403);

        return new FreelancerVerificationResource($freelancer->load(['user', 'portfolioItems', 'skills']));
    }

    public function approve(Request $request, FreelancerProfile $freelancer, ApproveFreelancerVerification $action): FreelancerVerificationResource
    {
        abort_unless($request->user()?->can(PermissionName::FreelancersApprove->value), 403);

        return new FreelancerVerificationResource($action->handle($freelancer));
    }

    public function reject(RejectFreelancerVerificationRequest $request, FreelancerProfile $freelancer, RejectFreelancerVerification $action): FreelancerVerificationResource
    {
        return new FreelancerVerificationResource($action->handle($freelancer, $request->validated('reason')));
    }

    public function bulkApprove(BulkVerificationDecisionRequest $request, ApproveFreelancerVerification $action): JsonResponse
    {
        return $this->bulk($request->validated('ids'), fn (FreelancerProfile $freelancer) => $action->handle($freelancer));
    }

    public function bulkReject(BulkVerificationDecisionRequest $request, RejectFreelancerVerification $action): JsonResponse
    {
        $reason = $request->validated('reason');

        return $this->bulk($request->validated('ids'), fn (FreelancerProfile $freelancer) => $action->handle($freelancer, $reason));
    }

    public function portfolioItemUrl(Request $request, PortfolioItem $portfolioItem, GeneratePortfolioItemUrl $action): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionName::VerificationView->value), 403);

        return response()->json(['data' => $action->handle($portfolioItem)]);
    }

    /**
     * @param  list<string>  $ids
     */
    private function bulk(array $ids, callable $handle): JsonResponse
    {
        $approved = [];
        $failed = [];

        foreach ($ids as $id) {
            $freelancer = FreelancerProfile::query()->find($id);

            if ($freelancer === null) {
                $failed[] = ['id' => $id, 'reason' => 'not_found'];

                continue;
            }

            try {
                $handle($freelancer);
                $approved[] = $id;
            } catch (ConflictException $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return response()->json(['data' => ['succeeded' => $approved, 'failed' => $failed]]);
    }
}

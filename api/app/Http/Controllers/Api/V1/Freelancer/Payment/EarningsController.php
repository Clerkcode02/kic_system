<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Freelancer\Payment;

use App\Domain\Payment\Queries\ListFreelancerEarningsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreelancerEarningResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EarningsController extends Controller
{
    public function index(Request $request, ListFreelancerEarningsQuery $query): AnonymousResourceCollection
    {
        $freelancer = $request->user()->freelancerProfile;

        abort_if($freelancer === null, 404);

        return FreelancerEarningResource::collection($query->handle($freelancer));
    }
}

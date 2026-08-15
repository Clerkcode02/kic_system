<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Freelancer\Dashboard;

use App\Domain\Freelance\Queries\FreelancerDashboardSummaryQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\FreelancerDashboardResource;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, FreelancerDashboardSummaryQuery $query): FreelancerDashboardResource
    {
        $freelancer = $request->user()->freelancerProfile;

        abort_if($freelancer === null, 404);

        return new FreelancerDashboardResource(collect($query->handle($freelancer)));
    }
}

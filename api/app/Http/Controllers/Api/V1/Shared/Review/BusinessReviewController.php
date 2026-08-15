<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Review;

use App\Domain\Business\Models\Business;
use App\Domain\Review\Queries\ListBusinessReviewsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusinessReviewController extends Controller
{
    public function __invoke(Business $business, ListBusinessReviewsQuery $query): AnonymousResourceCollection
    {
        return ReviewResource::collection($query->handle($business));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared\Catalog;

use App\Domain\Catalog\Queries\CategoryTreeQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(CategoryTreeQuery $query): AnonymousResourceCollection
    {
        return CategoryResource::collection($query->handle());
    }
}

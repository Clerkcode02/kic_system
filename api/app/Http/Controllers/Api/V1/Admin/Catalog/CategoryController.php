<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Domain\Catalog\Actions\CreateCategory;
use App\Domain\Catalog\Actions\DeleteCategory;
use App\Domain\Catalog\Actions\ReorderCategories;
use App\Domain\Catalog\Actions\UpdateCategory;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\AdminCategoryTreeQuery;
use App\Domain\User\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\DestroyCategoryRequest;
use App\Http\Requests\Admin\Catalog\ReorderCategoriesRequest;
use App\Http\Requests\Admin\Catalog\StoreCategoryRequest;
use App\Http\Requests\Admin\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(Request $request, AdminCategoryTreeQuery $query): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can(PermissionName::CategoriesManage->value), 403);

        return CategoryResource::collection($query->handle());
    }

    public function store(StoreCategoryRequest $request, CreateCategory $action): CategoryResource
    {
        return new CategoryResource($action->handle($request->validated()));
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): CategoryResource
    {
        return new CategoryResource($action->handle($category, $request->validated()));
    }

    public function destroy(DestroyCategoryRequest $request, Category $category, DeleteCategory $action): Response
    {
        $action->handle($category);

        return response()->noContent();
    }

    public function reorder(ReorderCategoriesRequest $request, ReorderCategories $action): JsonResponse
    {
        $action->handle($request->validated()['categories']);

        return response()->json(['message' => 'Categories reordered.']);
    }
}

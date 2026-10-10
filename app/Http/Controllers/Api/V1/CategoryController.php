<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Services\CatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::active()
                ->withCount(['products' => fn ($q) => $q->where('status', ProductStatus::Published)])
                ->orderBy('name')
                ->get()
        );
    }

    public function products(ProductIndexRequest $request, Category $category, CatalogService $catalog): AnonymousResourceCollection
    {
        abort_unless($category->is_active, 404);

        return ProductResource::collection($catalog->paginate($request->validated(), $category))
            ->additional(['category' => CategoryResource::make($category)]);
    }
}

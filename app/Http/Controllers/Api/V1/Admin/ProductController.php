<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['category', 'images', 'variants'])
            ->withSum('variants as total_stock', 'stock_quantity')
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $like)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->latest()
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request, ProductService $products): JsonResponse
    {
        $product = $products->create($request->validated(), $request->user());

        return $this->detail($product)->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return $this->detail($product);
    }

    public function update(ProductRequest $request, Product $product, ProductService $products): ProductResource
    {
        return $this->detail($products->update($product, $request->validated(), $request->user()));
    }

    /**
     * Archive rather than delete: the product leaves the storefront but order history keeps its link.
     */
    public function destroy(Product $product): ProductResource
    {
        Gate::authorize('delete', $product);

        $product->update(['status' => ProductStatus::Archived]);

        return $this->detail($product);
    }

    private function detail(Product $product): ProductResource
    {
        return ProductResource::make($product->load(['category', 'images', 'variants']))->detailed();
    }
}

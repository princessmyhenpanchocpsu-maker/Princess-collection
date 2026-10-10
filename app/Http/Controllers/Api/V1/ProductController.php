<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, CatalogService $catalog): AnonymousResourceCollection
    {
        return ProductResource::collection($catalog->paginate($request->validated()));
    }

    /**
     * Resolved through the catalogue rather than route model binding so drafts,
     * archived products and products in inactive categories all return 404.
     */
    public function show(string $slug, CatalogService $catalog): ProductResource
    {
        $product = $catalog->visibleProducts()->where('slug', $slug)->firstOrFail();
        $product->setRelation('related', $catalog->related($product));

        return ProductResource::make($product)->detailed();
    }

    public function filters(CatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->filterOptions()]);
    }
}

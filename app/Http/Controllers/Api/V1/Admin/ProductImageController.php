<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductImageController extends Controller
{
    public function store(ProductImageRequest $request, Product $product, ImageStorage $images): JsonResponse
    {
        $next = (int) $product->images()->max('sort_order') + 1;

        foreach ($request->file('images') as $file) {
            $product->images()->create([
                'image_path' => $images->store($file, "products/{$product->id}"),
                'alt_text' => $request->input('alt_text') ?: $product->name,
                'sort_order' => $next++,
            ]);
        }

        return ProductImageResource::collection($product->images()->get())->response()->setStatusCode(201);
    }

    public function reorder(Request $request, Product $product): AnonymousResourceCollection
    {
        Gate::authorize('update', $product);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $product->id)],
        ]);

        DB::transaction(function () use ($data, $product) {
            foreach ($data['order'] as $position => $id) {
                $product->images()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return ProductImageResource::collection($product->images()->get());
    }

    public function destroy(Product $product, ProductImage $image, ImageStorage $images): Response
    {
        Gate::authorize('update', $product);

        $image->delete();
        $images->delete($image->image_path);

        return response()->noContent();
    }
}

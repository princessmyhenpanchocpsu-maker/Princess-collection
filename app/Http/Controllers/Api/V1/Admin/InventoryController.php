<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustmentRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\InventoryVariantResource;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('admin');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', Rule::in(['low', 'out'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $variants = ProductVariant::query()
            ->with('product.images')
            ->whereHas('product', fn (Builder $q) => $q->where('status', '!=', ProductStatus::Archived))
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('sku', 'like', $like)
                    ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', $like)));
            })
            ->when(($filters['stock'] ?? null) === 'low', fn (Builder $q) => $q->active()->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->when(($filters['stock'] ?? null) === 'out', fn (Builder $q) => $q->where('stock_quantity', 0))
            ->orderBy('product_id')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return InventoryVariantResource::collection($variants);
    }

    public function adjust(InventoryAdjustmentRequest $request, InventoryService $inventory): JsonResponse
    {
        $variant = ProductVariant::findOrFail($request->integer('product_variant_id'));

        $movement = $inventory->adjust(
            $variant,
            $request->integer('quantity'),
            $request->enum('type', InventoryMovementType::class),
            $request->user(),
            $request->string('reason')->trim()->value(),
        );

        return response()->json(['data' => [
            'movement' => InventoryMovementResource::make($movement->load(['user', 'variant.product'])),
            'variant' => InventoryVariantResource::make($variant->load('product.images')),
        ]], 201);
    }

    public function movements(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('admin');

        $filters = $request->validate([
            'product_variant_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(InventoryMovementType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $movements = InventoryMovement::query()
            ->with([
                'user',
                'variant.product' => fn ($q) => $q->withTrashed(),
                'reference' => fn (MorphTo $morph) => $morph->constrain([Order::class => fn ($q) => $q->select('id', 'order_number')]),
            ])
            ->when($filters['product_variant_id'] ?? null, fn (Builder $q, $id) => $q->where('product_variant_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('movement_type', $type))
            ->latest()
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return InventoryMovementResource::collection($movements);
    }
}

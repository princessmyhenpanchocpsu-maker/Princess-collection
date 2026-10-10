<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): CartResource
    {
        return $this->cart($request);
    }

    public function store(CartItemRequest $request): JsonResponse
    {
        $this->carts->add($request->user(), $request->integer('product_variant_id'), $request->integer('quantity'));

        return $this->cart($request)->response()->setStatusCode(201);
    }

    public function update(CartItemRequest $request, int $item): CartResource
    {
        $this->carts->updateQuantity($this->ownItem($request, $item), $request->integer('quantity'));

        return $this->cart($request);
    }

    public function destroy(Request $request, int $item): CartResource
    {
        $this->ownItem($request, $item)->delete();

        return $this->cart($request);
    }

    /**
     * Look the line up inside the signed-in user's own cart, so another customer's item id is a 404.
     */
    private function ownItem(Request $request, int $id): CartItem
    {
        return CartItem::whereKey($id)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();
    }

    private function cart(Request $request): CartResource
    {
        return CartResource::make($this->carts->summary($request->user()));
    }
}

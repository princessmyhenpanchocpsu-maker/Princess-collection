<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($request->integer('address_id'));

        [$order, $created] = $checkout->placeOrder($user, $address, $request->string('idempotency_key')->value(), $request->input('notes'));

        // A repeated submission returns the original order with 200 instead of creating another.
        return OrderResource::make($order->load(['items.product.images', 'statusHistory.changedBy']))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }
}

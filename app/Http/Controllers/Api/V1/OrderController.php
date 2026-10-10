<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return OrderResource::collection(
            $request->user()->orders()
                ->with('items.product.images')
                ->withCount('items')
                ->latest()
                ->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 10)
        );
    }

    public function show(Request $request, string $orderNumber): OrderResource
    {
        return $this->detail($this->ownOrder($request, $orderNumber));
    }

    public function cancel(Request $request, string $orderNumber, OrderService $orders): OrderResource
    {
        $order = $this->ownOrder($request, $orderNumber);
        Gate::authorize('cancel', $order);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $orders->transition(
            $order,
            OrderStatus::Cancelled,
            $request->user(),
            filled($data['reason'] ?? null) ? "Cancelled by customer: {$data['reason']}" : 'Cancelled by customer.',
            byCustomer: true,
        );

        return $this->detail($order);
    }

    /**
     * Customers only ever look inside their own orders, so someone else's order number is a 404
     * (it does not confirm the number exists). The policy check then guards the action itself.
     */
    private function ownOrder(Request $request, string $orderNumber): Order
    {
        $order = $request->user()->orders()->where('order_number', $orderNumber)->firstOrFail();
        Gate::authorize('view', $order);

        return $order;
    }

    private function detail(Order $order): OrderResource
    {
        return OrderResource::make($order->load(['items.product.images', 'statusHistory.changedBy']));
    }
}

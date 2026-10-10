<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('order_number', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('recipient_phone', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('order_status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $q, $status) => $q->where('payment_status', $status))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->latest()
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return $this->detail($order);
    }

    public function updateStatus(OrderStatusRequest $request, Order $order, OrderService $orders): OrderResource
    {
        $status = $request->enum('status', OrderStatus::class);
        $tracking = $request->has('tracking_number') ? $request->input('tracking_number') : null;

        if ($status && $status !== $order->order_status) {
            $orders->transition($order, $status, $request->user(), $request->input('note'), $tracking);
        } elseif ($request->has('tracking_number')) {
            $orders->updateTracking($order, $tracking, $request->user());
        }

        return $this->detail($order);
    }

    private function detail(Order $order): OrderResource
    {
        return OrderResource::make($order->load(['user', 'items.product.images', 'statusHistory.changedBy']));
    }
}

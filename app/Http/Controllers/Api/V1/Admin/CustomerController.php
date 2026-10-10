<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = $this->customers()
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->when(isset($filters['is_active']), fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->latest()
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return CustomerResource::collection($customers);
    }

    public function show(int $customer): CustomerResource
    {
        $user = $this->customers()->with('addresses')->findOrFail($customer);
        Gate::authorize('view', $user);

        return CustomerResource::make($user);
    }

    /**
     * Activate or deactivate a customer account. A deactivated customer is signed out on their
     * next request and cannot log in; their orders and history are kept.
     */
    public function update(Request $request, int $customer): CustomerResource
    {
        $user = $this->customers()->findOrFail($customer);
        Gate::authorize('updateStatus', $user);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $user->forceFill(['is_active' => (bool) $data['is_active']])->save();

        return CustomerResource::make($user);
    }

    /**
     * Only customer accounts are reachable here, so an admin's id is a 404.
     */
    private function customers(): Builder
    {
        return User::query()
            ->where('role', UserRole::Customer)
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn (Builder $q) => $q->where('order_status', '!=', OrderStatus::Cancelled)], 'total_amount');
    }
}

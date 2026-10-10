<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Listing every customer's orders (admin API). Customers list their own through their relationship.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Customer self-service cancellation. Whether the status still allows it is decided by OrderService.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}

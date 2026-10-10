<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Activating or deactivating an account. Admin accounts (including your own) cannot be
     * deactivated from the dashboard, so the store can never be locked out of itself.
     */
    public function updateStatus(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $model->isAdmin();
    }
}

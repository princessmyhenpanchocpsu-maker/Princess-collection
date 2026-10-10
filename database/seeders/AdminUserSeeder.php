<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Create (or update) the administrator defined by ADMIN_EMAIL / ADMIN_PASSWORD in .env.
     */
    public function run(): void
    {
        $admin = config('store.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding the admin user.');
        }

        $user = User::firstOrNew(['email' => strtolower($admin['email'])]);

        $user->forceFill([
            'name' => $admin['name'],
            'password' => $admin['password'],
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}

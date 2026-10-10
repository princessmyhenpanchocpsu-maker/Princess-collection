<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/user/profile', [
            'name' => 'Maria Clara',
            'email' => 'clara@example.com',
            'phone' => '+639171234567',
        ])->assertOk()->assertJsonPath('data.name', 'Maria Clara');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Maria Clara',
            'email' => 'clara@example.com',
            'phone' => '+639171234567',
        ]);
    }

    public function test_a_customer_cannot_promote_themselves_through_the_profile_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/user/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.role', 'customer');

        $user->refresh();

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_a_profile_email_must_be_unique_but_may_stay_the_same(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/user/profile', [
            'name' => $user->name,
            'email' => $other->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->actingAs($user)->putJson('/api/v1/user/profile', [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertOk();
    }

    public function test_a_guest_cannot_update_a_profile(): void
    {
        $this->putJson('/api/v1/user/profile', ['name' => 'X', 'email' => 'x@example.com'])
            ->assertUnauthorized();
    }
}

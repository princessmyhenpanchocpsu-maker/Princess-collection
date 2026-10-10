<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'maria@example.com']);

        $this->postJson('/api/v1/login', ['email' => 'Maria@Example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $this->postJson('/api/v1/login', ['email' => 'maria@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'maria@example.com']);

        $this->postJson('/api/v1/login', ['email' => 'maria@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', ['email' => 'maria@example.com', 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        // Even the correct password is refused once the limit is hit.
        $this->postJson('/api/v1/login', ['email' => 'maria@example.com', 'password' => 'password'])
            ->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_a_guest_cannot_fetch_the_current_user(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_an_authenticated_user_can_fetch_their_own_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'customer');
    }

    public function test_a_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/logout')->assertNoContent();

        $this->assertGuest('web');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_a_user_deactivated_mid_session_is_locked_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/user')->assertForbidden();
        $this->assertGuest('web');
    }
}

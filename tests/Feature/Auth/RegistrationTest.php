<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '09171234567',
            'password' => 'crownjewel1',
            'password_confirmation' => 'crownjewel1',
        ], $overrides);
    }

    public function test_a_visitor_can_register_and_is_signed_in_as_a_customer(): void
    {
        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'maria@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('crownjewel1', $user->password);
        $this->assertTrue(Hash::check('crownjewel1', $user->password));
    }

    public function test_registration_ignores_attempts_to_set_role_or_active_flag(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload([
            'role' => 'admin',
            'is_active' => false,
        ]))->assertCreated()->assertJsonPath('data.role', 'customer');

        $user = User::where('email', 'maria@example.com')->firstOrFail();

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->is_active);
    }

    public function test_registration_rejects_a_duplicate_email_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $this->postJson('/api/v1/register', $this->validPayload(['email' => 'Maria@Example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_registration_validates_its_input(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '12345',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }
}

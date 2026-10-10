<?php

namespace Tests\Feature\Cart;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Maria Santos',
            'phone' => '09171234567',
            'address_line' => '12 Sampaguita Street',
            'barangay' => 'San Antonio',
            'city' => 'Pasig',
            'province' => 'Metro Manila',
            'postal_code' => '1605',
            'delivery_instructions' => 'Leave with the guard.',
        ], $overrides);
    }

    public function test_the_first_address_becomes_the_default_and_only_one_default_exists(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/api/v1/addresses', $this->payload())
            ->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');

        $this->actingAs($user)->postJson('/api/v1/addresses', $this->payload(['city' => 'Makati']))
            ->assertCreated()->assertJsonPath('data.is_default', false);

        $third = $this->actingAs($user)->postJson('/api/v1/addresses', $this->payload(['city' => 'Taguig', 'is_default' => true]))
            ->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');

        $this->assertSame([$third], $user->addresses()->where('is_default', true)->pluck('id')->all());

        $this->actingAs($user)->putJson("/api/v1/addresses/{$first}", $this->payload(['is_default' => true]))->assertOk();
        $this->assertSame([$first], $user->addresses()->where('is_default', true)->pluck('id')->all());

        $this->actingAs($user)->getJson('/api/v1/addresses')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $first);
    }

    public function test_deleting_the_default_address_promotes_another(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->create(['is_default' => true]);
        $other = Address::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson("/api/v1/addresses/{$default->id}")->assertNoContent();

        $this->assertDatabaseMissing('addresses', ['id' => $default->id]);
        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_addresses_are_validated(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/addresses', [
            'recipient_name' => '', 'phone' => '123', 'postal_code' => 'ABCD',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'recipient_name', 'phone', 'address_line', 'barangay', 'city', 'province', 'postal_code',
        ]);
    }

    public function test_a_customer_cannot_touch_another_customers_addresses(): void
    {
        $address = Address::factory()->create(['city' => 'Cebu City']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson('/api/v1/addresses')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($intruder)->getJson("/api/v1/addresses/{$address->id}")->assertForbidden();
        $this->actingAs($intruder)->putJson("/api/v1/addresses/{$address->id}", $this->payload())->assertForbidden();
        $this->actingAs($intruder)->deleteJson("/api/v1/addresses/{$address->id}")->assertForbidden();

        $this->assertSame('Cebu City', $address->fresh()->city);
    }

    public function test_guests_cannot_use_addresses(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/addresses', $this->payload())->assertUnauthorized();
    }
}

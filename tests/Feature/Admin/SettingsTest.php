<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_an_admin_can_change_shipping_settings_and_they_apply_to_new_carts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('data.shipping_fee', '120.00');

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'shipping_fee' => '85.5',
            'free_shipping_threshold' => null,
            'announcement_text' => 'Holiday sale this weekend',
        ])->assertOk()
            ->assertJsonPath('data.shipping_fee', '85.50')
            ->assertJsonPath('data.free_shipping_threshold', null)
            ->assertJsonPath('data.announcement_text', 'Holiday sale this weekend');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('data.announcement_text', 'Holiday sale this weekend');

        // No free-shipping threshold any more: a large order still pays the new fee.
        $order = $this->placeOrder(null, [[$this->makeVariant(price: '3000.00'), 1]]);
        $this->assertSame('85.50', $order->shipping_fee);
        $this->assertSame('3085.50', $order->total_amount);
    }

    public function test_settings_are_validated_and_admin_only(): void
    {
        $payload = ['shipping_fee' => '0', 'free_shipping_threshold' => null, 'announcement_text' => null];

        $this->putJson('/api/v1/admin/settings', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->putJson('/api/v1/admin/settings', $payload)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->admin()->create())->putJson('/api/v1/admin/settings', [
            'shipping_fee' => '-1', 'free_shipping_threshold' => 'abc', 'announcement_text' => str_repeat('x', 201),
        ])->assertUnprocessable()->assertJsonValidationErrors(['shipping_fee', 'free_shipping_threshold', 'announcement_text']);

        $this->getJson('/api/v1/settings')->assertJsonPath('data.shipping_fee', '120.00');
    }
}

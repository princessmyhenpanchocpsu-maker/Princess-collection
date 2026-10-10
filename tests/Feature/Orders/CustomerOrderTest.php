<?php

namespace Tests\Feature\Orders;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_a_customer_sees_only_their_own_orders(): void
    {
        $user = User::factory()->create();
        $mine = $this->placeOrder($user);
        $theirs = $this->placeOrder();

        $this->actingAs($user)->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $mine->order_number)
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonMissing(['order_number' => $theirs->order_number]);
    }

    public function test_a_customer_can_view_their_order_with_items_and_history(): void
    {
        $user = User::factory()->create();
        $order = $this->placeOrder($user);

        $this->actingAs($user)->getJson("/api/v1/orders/{$order->order_number}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.status_history.0.new_status', 'pending')
            ->assertJsonMissingPath('data.allowed_transitions')
            ->assertJsonMissingPath('data.customer');
    }

    public function test_another_customers_order_is_not_found(): void
    {
        $order = $this->placeOrder();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/api/v1/orders/{$order->order_number}")->assertNotFound();
        $this->actingAs($intruder)->postJson("/api/v1/orders/{$order->order_number}/cancel")->assertNotFound();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
    }

    public function test_guests_cannot_access_orders(): void
    {
        $order = $this->placeOrder();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->getJson("/api/v1/orders/{$order->order_number}")->assertUnauthorized();
        $this->postJson("/api/v1/orders/{$order->order_number}/cancel")->assertUnauthorized();
    }

    public function test_cancelling_a_pending_order_restores_stock_exactly_once(): void
    {
        $user = User::factory()->create();
        $shirt = $this->makeVariant(stock: 10);
        $dress = $this->makeVariant(stock: 4);
        $order = $this->placeOrder($user, [[$shirt, 3], [$dress, 1]]);

        $this->assertSame(7, $shirt->fresh()->stock_quantity);
        $this->assertSame(3, $dress->fresh()->stock_quantity);

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->order_number}/cancel", ['reason' => 'Changed my mind'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.status_history.1.previous_status', 'pending')
            ->assertJsonPath('data.status_history.1.new_status', 'cancelled')
            ->assertJsonPath('data.status_history.1.note', 'Cancelled by customer: Changed my mind');

        $this->assertSame(10, $shirt->fresh()->stock_quantity);
        $this->assertSame(4, $dress->fresh()->stock_quantity);

        $restocks = InventoryMovement::where('movement_type', InventoryMovementType::CancellationRestock)->get();
        $this->assertCount(2, $restocks);
        $this->assertSame(4, (int) $restocks->sum('quantity_change'));
        $this->assertTrue($restocks->every(fn ($m) => $m->reference_id === $order->id && $m->user_id === $user->id));

        // A second attempt is refused and must not restore stock again.
        $this->actingAs($user)->postJson("/api/v1/orders/{$order->order_number}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(10, $shirt->fresh()->stock_quantity);
        $this->assertSame(4, $dress->fresh()->stock_quantity);
        $this->assertSame(2, InventoryMovement::where('movement_type', InventoryMovementType::CancellationRestock)->count());
    }

    public function test_a_customer_can_cancel_a_confirmed_order_but_not_one_being_processed(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $service = app(OrderService::class);

        $confirmed = $this->placeOrder($user);
        $service->transition($confirmed, OrderStatus::Confirmed, $admin);

        $this->actingAs($user)->postJson("/api/v1/orders/{$confirmed->order_number}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $variant = $this->makeVariant(stock: 5);
        $processing = $this->placeOrder($user, [[$variant, 2]]);
        $service->transition($processing, OrderStatus::Confirmed, $admin);
        $service->transition($processing, OrderStatus::Processing, $admin);

        $this->actingAs($user)->getJson("/api/v1/orders/{$processing->order_number}")->assertJsonPath('data.can_cancel', false);

        $this->actingAs($user)->postJson("/api/v1/orders/{$processing->order_number}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(OrderStatus::Processing, $processing->fresh()->order_status);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
    }
}

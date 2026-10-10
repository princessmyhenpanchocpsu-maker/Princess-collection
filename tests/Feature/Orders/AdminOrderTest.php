<?php

namespace Tests\Feature\Orders;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function setStatus(Order $order, array $payload)
    {
        return $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->order_number}/status", $payload);
    }

    public function test_an_admin_can_list_search_and_filter_orders(): void
    {
        $maria = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);
        $first = $this->placeOrder($maria);
        $second = $this->placeOrder();
        $this->setStatus($second, ['status' => 'confirmed'])->assertOk();

        $list = fn (string $query) => $this->actingAs($this->admin)->getJson("/api/v1/admin/orders?{$query}")->assertOk();

        $list('')->assertJsonCount(2, 'data')->assertJsonPath('data.0.customer.email', $second->user->email);
        $list("search={$first->order_number}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $first->order_number);
        $list('search=maria@example')->assertJsonCount(1, 'data');
        $list('status=confirmed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $second->order_number);
        $list("user_id={$maria->id}")->assertJsonCount(1, 'data');
        $list('date_from='.now()->addDay()->toDateString())->assertJsonCount(0, 'data');
        $list('date_from='.now()->toDateString().'&date_to='.now()->toDateString())->assertJsonCount(2, 'data');
    }

    public function test_an_admin_can_view_any_order_with_its_allowed_transitions(): void
    {
        $order = $this->placeOrder();

        $this->actingAs($this->admin)->getJson("/api/v1/admin/orders/{$order->order_number}")
            ->assertOk()
            ->assertJsonPath('data.customer.id', $order->user_id)
            ->assertJsonPath('data.allowed_transitions', ['confirmed', 'cancelled'])
            ->assertJsonCount(1, 'data.items');
    }

    public function test_an_order_moves_through_the_full_fulfilment_path_with_history(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, ['status' => 'confirmed', 'note' => 'Verified by phone'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->setStatus($order, ['status' => 'processing'])->assertOk();
        $this->setStatus($order, ['status' => 'shipped', 'tracking_number' => 'JT123456789PH'])
            ->assertOk()
            ->assertJsonPath('data.tracking_number', 'JT123456789PH')
            ->assertJsonPath('data.payment_status', 'unpaid');
        $this->setStatus($order, ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.allowed_transitions', []);

        $history = $order->statusHistory()->get();
        $this->assertSame(
            ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            $history->map(fn ($h) => $h->new_status->value)->all(),
        );
        $this->assertSame('Verified by phone', $history[1]->note);
        $this->assertSame($this->admin->id, $history[1]->changed_by);
        $this->assertSame(OrderStatus::Shipped, $history[4]->previous_status);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, ['status' => 'shipped'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->setStatus($order, ['status' => 'delivered'])->assertUnprocessable();
        $this->setStatus($order, ['status' => 'nonsense'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->setStatus($order, [])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_a_cancelled_order_cannot_continue_and_stock_is_restored_once(): void
    {
        $variant = $this->makeVariant(stock: 10);
        $order = $this->placeOrder(null, [[$variant, 4]]);
        $this->setStatus($order, ['status' => 'confirmed'])->assertOk();
        $this->setStatus($order, ['status' => 'processing'])->assertOk();

        $this->setStatus($order, ['status' => 'cancelled', 'note' => 'Customer unreachable'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.allowed_transitions', []);

        $this->assertSame(10, $variant->fresh()->stock_quantity);

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'] as $status) {
            $response = $this->setStatus($order, ['status' => $status]);
            $status === 'cancelled' ? $response->assertOk() : $response->assertUnprocessable();
        }

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->order_status);
        $this->assertSame(10, $variant->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::where('movement_type', InventoryMovementType::CancellationRestock)->count());
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    public function test_a_delivered_order_cannot_be_cancelled(): void
    {
        $variant = $this->makeVariant(stock: 10);
        $order = $this->placeOrder(null, [[$variant, 1]]);

        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->setStatus($order, ['status' => $status])->assertOk();
        }

        $this->setStatus($order, ['status' => 'cancelled'])->assertUnprocessable();
        $this->assertSame(9, $variant->fresh()->stock_quantity);
    }

    public function test_the_tracking_number_can_be_updated_without_changing_status(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, ['tracking_number' => 'LBC-0001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tracking_number', 'LBC-0001');

        $this->assertSame(2, $order->statusHistory()->count());
    }

    public function test_customers_and_guests_cannot_use_the_order_admin_api(): void
    {
        $order = $this->placeOrder();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
        $this->patchJson("/api/v1/admin/orders/{$order->order_number}/status", ['status' => 'delivered'])->assertUnauthorized();

        // Not even the customer who owns the order may change its status or mark it paid.
        $this->actingAs($order->user);
        $this->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->getJson("/api/v1/admin/orders/{$order->order_number}")->assertForbidden();
        $this->patchJson("/api/v1/admin/orders/{$order->order_number}/status", ['status' => 'delivered'])->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }
}

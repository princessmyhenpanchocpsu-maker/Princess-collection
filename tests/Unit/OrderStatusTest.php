<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_orders_follow_the_fulfilment_path(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Processing));
        $this->assertTrue(OrderStatus::Processing->canTransitionTo(OrderStatus::Shipped));
        $this->assertTrue(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered));

        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::Shipped->canTransitionTo(OrderStatus::Pending));
    }

    public function test_cancelled_and_delivered_orders_cannot_continue(): void
    {
        foreach (OrderStatus::cases() as $next) {
            $this->assertFalse(OrderStatus::Cancelled->canTransitionTo($next));
            $this->assertFalse(OrderStatus::Delivered->canTransitionTo($next));
        }
    }

    public function test_customers_may_cancel_only_before_processing(): void
    {
        $this->assertTrue(OrderStatus::Pending->isCancellableByCustomer());
        $this->assertTrue(OrderStatus::Confirmed->isCancellableByCustomer());

        foreach ([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled] as $status) {
            $this->assertFalse($status->isCancellableByCustomer());
        }
    }
}

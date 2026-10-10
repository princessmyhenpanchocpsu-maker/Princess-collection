<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Orders are only ever written by server-side services, never from request input,
     * so nothing is mass assignable.
     */
    protected $guarded = ['*'];

    protected $attributes = [
        'shipping_fee' => 0,
        'discount_amount' => 0,
        'payment_method' => PaymentMethod::CashOnDelivery->value,
        'payment_status' => PaymentStatus::Unpaid->value,
        'order_status' => OrderStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'shipping_address_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'order_status' => OrderStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest()->orderBy('id');
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PaymentRecord::class);
    }
}

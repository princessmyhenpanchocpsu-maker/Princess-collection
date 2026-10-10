<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    /**
     * `stock_quantity` is not fillable: stock only changes through the inventory
     * service so every change gets a movement record.
     */
    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'color',
        'price_override',
        'low_stock_threshold',
        'is_active',
    ];

    protected $attributes = [
        'stock_quantity' => 0,
        'low_stock_threshold' => 5,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * The price a customer pays: the variant override, otherwise the product's base price.
     */
    protected function price(): Attribute
    {
        return Attribute::get(fn (): string => $this->price_override ?? $this->product?->base_price ?? '0.00');
    }

    /**
     * Whether a customer may buy this variant right now (stock is checked separately).
     */
    public function isPurchasable(): bool
    {
        $product = $this->product;

        return $this->is_active
            && $product !== null
            && ! $product->trashed()
            && $product->status === ProductStatus::Published
            && (bool) $product->category?->is_active;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

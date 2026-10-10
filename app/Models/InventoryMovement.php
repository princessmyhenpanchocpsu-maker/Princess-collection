<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity_change' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What caused the movement, e.g. the order for a sale or a cancellation restock.
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * The only place stock quantities change. Locks the variant row, applies the change,
     * and writes the matching movement record in the same transaction.
     *
     * @throws InsufficientStockException when the change would take stock below zero
     */
    public function adjust(
        ProductVariant $variant,
        int $quantityChange,
        InventoryMovementType $type,
        ?User $user = null,
        ?string $reason = null,
        ?Model $reference = null,
    ): InventoryMovement {
        if ($quantityChange === 0) {
            throw new InvalidArgumentException('An inventory movement must change the quantity.');
        }

        return DB::transaction(function () use ($variant, $quantityChange, $type, $user, $reason, $reference) {
            $locked = ProductVariant::whereKey($variant->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->stock_quantity + $quantityChange < 0) {
                throw new InsufficientStockException($locked, abs($quantityChange));
            }

            $locked->stock_quantity += $quantityChange;
            $locked->save();

            $variant->setRawAttributes($locked->getAttributes(), sync: true);

            $movement = new InventoryMovement;
            $movement->forceFill([
                'product_variant_id' => $locked->id,
                'user_id' => $user?->id,
                'movement_type' => $type,
                'quantity_change' => $quantityChange,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ])->save();

            return $movement;
        });
    }
}

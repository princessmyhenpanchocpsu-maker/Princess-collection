<?php

namespace App\Exceptions;

use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly ProductVariant $variant, public readonly int $requested)
    {
        parent::__construct("Not enough stock for SKU {$variant->sku}: requested {$requested}, available {$variant->stock_quantity}.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'There is not enough stock to complete this action.',
            'errors' => [
                'quantity' => ["Only {$this->variant->stock_quantity} left in stock for {$this->variant->sku}."],
            ],
        ], 422);
    }
}

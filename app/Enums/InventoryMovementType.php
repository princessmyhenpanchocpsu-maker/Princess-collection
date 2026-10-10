<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case StockIn = 'stock_in';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case CancellationRestock = 'cancellation_restock';
}

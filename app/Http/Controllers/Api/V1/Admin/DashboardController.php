<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryVariantResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function show(SalesReportService $reports): JsonResponse
    {
        Gate::authorize('admin');

        return response()->json(['data' => [
            'stats' => $reports->dashboardStats(),
            'recent_orders' => OrderResource::collection(Order::with('user')->withCount('items')->latest()->orderByDesc('id')->limit(5)->get()),
            'bestsellers' => $reports->bestsellers(5),
            'low_stock' => InventoryVariantResource::collection(
                $reports->lowStockVariants()->with('product.images')->orderBy('stock_quantity')->orderBy('id')->limit(5)->get()
            ),
        ]]);
    }
}

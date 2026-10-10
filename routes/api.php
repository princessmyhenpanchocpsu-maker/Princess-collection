<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/settings', [SettingController::class, 'show'])->name('settings.show');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/filters', [ProductController::class, 'filters'])->name('products.filters');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}/products', [CategoryController::class, 'products'])->name('categories.products');

    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::put('/user/profile', [ProfileController::class, 'update'])->name('user.profile.update');

        Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.items.update');
        Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.items.destroy');

        Route::apiResource('addresses', AddressController::class);

        Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
            Route::apiResource('categories', Admin\CategoryController::class);

            Route::apiResource('products', Admin\ProductController::class);
            Route::post('/products/{product}/images', [Admin\ProductImageController::class, 'store'])->name('products.images.store');
            Route::patch('/products/{product}/images/reorder', [Admin\ProductImageController::class, 'reorder'])->name('products.images.reorder');
            Route::delete('/products/{product}/images/{image}', [Admin\ProductImageController::class, 'destroy'])
                ->scopeBindings()->name('products.images.destroy');

            Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::patch('/orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');

            Route::get('/dashboard', [Admin\DashboardController::class, 'show'])->name('dashboard');

            Route::get('/inventory', [Admin\InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/movements', [Admin\InventoryController::class, 'movements'])->name('inventory.movements');
            Route::post('/inventory/adjustments', [Admin\InventoryController::class, 'adjust'])->name('inventory.adjust');

            Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{customer}', [Admin\CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
            Route::patch('/customers/{customer}', [Admin\CustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');

            Route::get('/reports/sales', [Admin\ReportController::class, 'sales'])->name('reports.sales');

            Route::get('/settings', [Admin\SettingController::class, 'show'])->name('settings.show');
            Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        });
    });
});

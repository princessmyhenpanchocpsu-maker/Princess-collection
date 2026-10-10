<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function show(StoreSettings $settings): JsonResponse
    {
        Gate::authorize('admin');

        return response()->json(['data' => $settings->all()]);
    }

    public function update(Request $request, StoreSettings $settings): JsonResponse
    {
        Gate::authorize('admin');

        $data = $request->validate([
            'shipping_fee' => ['required', 'decimal:0,2', 'min:0', 'max:100000'],
            // Leave empty to always charge the shipping fee.
            'free_shipping_threshold' => ['present', 'nullable', 'decimal:0,2', 'min:0', 'max:10000000'],
            'announcement_text' => ['present', 'nullable', 'string', 'max:200'],
        ]);

        $settings->update([
            'shipping_fee' => bcadd($data['shipping_fee'], '0', 2),
            'free_shipping_threshold' => filled($data['free_shipping_threshold']) ? bcadd($data['free_shipping_threshold'], '0', 2) : null,
            'announcement_text' => $data['announcement_text'],
        ]);

        return response()->json(['data' => $settings->all()]);
    }
}

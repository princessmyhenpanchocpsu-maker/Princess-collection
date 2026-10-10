<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StoreSettings;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Settings the storefront needs to display. All keys here are safe to show publicly.
     */
    public function show(StoreSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->all()]);
    }
}

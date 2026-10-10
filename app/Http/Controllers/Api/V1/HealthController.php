<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Report whether the API and its database are reachable.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = true;
        } catch (Throwable $e) {
            report($e);
            $database = false;
        }

        return response()->json([
            'status' => $database ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'database' => $database ? 'connected' : 'unavailable',
            'timestamp' => now()->toIso8601String(),
        ], $database ? 200 : 503);
    }
}

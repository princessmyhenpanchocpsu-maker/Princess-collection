<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function sales(Request $request, SalesReportService $reports): JsonResponse
    {
        Gate::authorize('admin');

        $filters = $request->validate([
            'group' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        // Defaults to the last 30 days including today.
        $to = Carbon::parse($filters['date_to'] ?? today())->endOfDay();
        $from = Carbon::parse($filters['date_from'] ?? $to->copy()->subDays(29))->startOfDay();
        $group = $filters['group'] ?? 'daily';

        return response()->json(['data' => [
            'group' => $group,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            ...$reports->sales($group, $from, $to),
            'bestsellers' => $reports->bestsellers(10, $from, $to),
        ]]);
    }
}

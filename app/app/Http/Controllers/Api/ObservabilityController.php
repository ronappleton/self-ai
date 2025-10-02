<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemMetric;
use App\Support\Observability\MetricCollector;
use Illuminate\Http\JsonResponse;

class ObservabilityController extends Controller
{
    public function __construct(private readonly MetricCollector $collector)
    {
    }

    public function __invoke(): JsonResponse
    {
        $live = $this->collector->snapshot();
        $lastRecorded = SystemMetric::query()->latest('collected_at')->first();

        return response()->json([
            'live' => $live,
            'last_recorded' => $lastRecorded?->metrics,
            'last_recorded_at' => optional($lastRecorded?->collected_at)->toIso8601String(),
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\SystemMetric;
use App\Support\Observability\MetricCollector;
use Illuminate\Console\Command;

class CollectSystemMetricsCommand extends Command
{
    protected $signature = 'observability:collect-metrics';

    protected $description = 'Capture a snapshot of system observability metrics.';

    public function handle(MetricCollector $collector): int
    {
        $metrics = $collector->snapshot();

        SystemMetric::query()->create([
            'collected_at' => now(),
            'metrics' => $metrics,
        ]);

        $this->info('Observability metrics captured.');

        return static::SUCCESS;
    }
}

<?php

use App\Http\Middleware\AuditLogMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\SecurityBaselineReportCommand::class,
        \App\Console\Commands\RunNightlyBackupsCommand::class,
        \App\Console\Commands\CollectSystemMetricsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AuditLogMiddleware::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('security:baseline-report')->dailyAt('02:00');
        $schedule->command('backups:run-nightly')->dailyAt('03:00');
        $schedule->command('observability:collect-metrics')->everyFifteenMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

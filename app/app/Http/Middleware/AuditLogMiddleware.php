<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->appendLog($request, $response);
        } catch (Throwable $exception) {
            Log::channel('stack')->error('Failed to append audit log entry', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $response;
    }

    private function appendLog(Request $request, Response $response): void
    {
        $timestamp = now();
        $actor = optional($request->user())->email ?? 'system';
        $action = sprintf('%s %s', $request->getMethod(), $request->decodedPath());
        $target = optional($request->route())->getName() ?? $request->path();

        $context = [
            'ip' => $request->ip(),
            'status' => $response->getStatusCode(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->headers->get('X-Request-Id'),
        ];

        DB::transaction(function () use ($actor, $action, $target, $context, $timestamp): void {
            $query = AuditLog::query()->latest('id');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $previousHash = $query->value('hash');

            $payload = [
                'actor' => $actor,
                'action' => $action,
                'target' => $target,
                'context' => $context,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            AuditLog::query()->create([
                'actor' => $actor,
                'action' => $action,
                'target' => $target,
                'context' => $context,
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp,
            ]);
        });
    }
}

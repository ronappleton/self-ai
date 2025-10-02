<?php

namespace App\Support\Security;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CisBaselineChecker
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function run(): array
    {
        $checks = config('security.baseline', []);
        $results = [];

        foreach ($checks as $key => $definition) {
            $results[] = $this->evaluateCheck((string) $key, $definition);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function evaluateCheck(string $key, array $definition): array
    {
        $status = 'pass';
        $details = [];

        switch ($key) {
            case 'require_app_key':
                $status = config('app.key') ? 'pass' : 'fail';
                if ($status === 'fail') {
                    $details['message'] = 'APP_KEY is empty.';
                }
                break;
            case 'disable_debug_in_production':
                if (app()->environment('production')) {
                    $status = config('app.debug') ? 'fail' : 'pass';
                    if ($status === 'fail') {
                        $details['message'] = 'APP_DEBUG must be false in production.';
                    }
                } else {
                    $status = config('app.debug') ? 'warn' : 'pass';
                    $details['message'] = 'Ensure APP_DEBUG=false before promoting to production.';
                }
                break;
            case 'https_app_url':
                $appUrl = (string) config('app.url');
                if ($appUrl === '') {
                    $status = 'warn';
                    $details['message'] = 'APP_URL is not configured.';
                } elseif (! Str::startsWith(Str::lower($appUrl), 'https://')) {
                    $status = 'warn';
                    $details['message'] = 'APP_URL should use https:// in production environments.';
                }
                break;
            case 'queue_not_sync':
                $connection = config('queue.default');
                $queueDriver = config("queue.connections.{$connection}.driver");
                if ($queueDriver === 'sync') {
                    $status = 'warn';
                    $details['message'] = 'Sync queue driver does not provide isolation. Switch to redis.';
                }
                break;
            default:
                $status = 'warn';
                $details['message'] = 'Unknown baseline rule. Verify manually.';
        }

        return [
            'key' => $key,
            'description' => Arr::get($definition, 'description', $key),
            'status' => $status,
            'remediation' => Arr::get($definition, 'remediation'),
            'details' => $details,
        ];
    }
}

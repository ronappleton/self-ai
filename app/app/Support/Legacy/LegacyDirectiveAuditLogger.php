<?php

namespace App\Support\Legacy;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class LegacyDirectiveAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(?string $actor, string $action, array $context = []): void
    {
        $timestamp = now();
        $actor = $actor ?? 'system';

        DB::transaction(function () use ($timestamp, $actor, $action, $context): void {
            $query = AuditLog::query()->latest('id');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $previousHash = $query->value('hash');
            $payload = [
                'actor' => $actor,
                'action' => $action,
                'target' => 'legacy_directive',
                'context' => $context,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            AuditLog::query()->create([
                'actor' => $actor,
                'action' => $action,
                'target' => 'legacy_directive',
                'context' => $context,
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp,
            ]);
        });
    }
}

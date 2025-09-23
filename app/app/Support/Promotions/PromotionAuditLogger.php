<?php

namespace App\Support\Promotions;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class PromotionAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(?string $actor, string $action, array $context): void
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
                'target' => 'promotion',
                'context' => $context,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            AuditLog::query()->create([
                'actor' => $actor,
                'action' => $action,
                'target' => 'promotion',
                'context' => $context,
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logDenied(?string $actor, array $context): void
    {
        $this->log($actor, 'promotion.denied', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logPromoted(?string $actor, array $context): void
    {
        $this->log($actor, 'promotion.promoted', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logRollback(?string $actor, array $context): void
    {
        $this->log($actor, 'promotion.rollback', $context);
    }
}

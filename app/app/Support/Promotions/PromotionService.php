<?php

namespace App\Support\Promotions;

use App\Models\Build;
use App\Models\Promotion;
use App\Models\User;
use App\Support\Promotions\Exceptions\BuildNotPromotableException;
use App\Support\Promotions\Exceptions\InvalidPromotionSignatureException;
use App\Support\Promotions\Exceptions\PromotionReplayException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    public function __construct(
        private readonly PromotionSignatureVerifier $signatureVerifier,
        private readonly CanaryMonitor $canaryMonitor,
        private readonly PromotionAuditLogger $auditLogger
    ) {
    }

    /**
     * @param  array{verifier_id?: string, signature?: string, nonce?: string, requested_at?: string, expires_at?: string, canary?: array{targets?: array<int, string>}}  $payload
     *
     * @throws InvalidPromotionSignatureException
     * @throws PromotionReplayException
     * @throws BuildNotPromotableException
     */
    public function promote(?User $user, Build $build, array $payload): Promotion
    {
        $signature = $this->signatureVerifier->verify($build->id, $payload);

        if ($build->status !== 'passed') {
            throw new BuildNotPromotableException('Only passed builds can be promoted.');
        }

        if (Promotion::query()->where('nonce', $signature->nonce)->exists()) {
            throw new PromotionReplayException('Nonce has already been used.');
        }

        $actor = $user?->email;

        return DB::transaction(function () use ($build, $payload, $signature, $user, $actor): Promotion {
            $promotion = Promotion::create([
                'build_id' => $build->id,
                'status' => 'pending',
                'status_reason' => null,
                'verifier_id' => $signature->verifierId,
                'nonce' => $signature->nonce,
                'signature' => $signature->signature,
                'request_payload' => [
                    'requested_at' => $signature->requestedAt->toIso8601String(),
                    'expires_at' => $signature->expiresAt->toIso8601String(),
                    'canary' => Arr::get($payload, 'canary', []),
                ],
                'canary_status' => 'pending',
                'rollback_triggered' => false,
                'requested_at' => $signature->requestedAt,
                'expires_at' => $signature->expiresAt,
                'requested_by' => $user?->id,
            ]);

            $targets = Arr::get($payload, 'canary.targets', []);
            $report = $this->canaryMonitor->run(is_array($targets) ? $targets : []);

            $promotion->fill([
                'canary_status' => $report->status,
                'canary_report' => $report->checks,
            ]);

            if ($report->passed()) {
                $promotion->status = 'promoted';
                $promotion->promoted_at = now();
            } else {
                $promotion->status = 'rolled_back';
                $promotion->status_reason = 'canary_failed';
                $promotion->rollback_triggered = true;
                $promotion->rolled_back_at = now();
            }

            $promotion->save();

            $this->updateBuildMetadata($build, $promotion);

            $context = [
                'promotion_id' => $promotion->id,
                'build_id' => $build->id,
                'rfc_id' => $build->rfc_id,
                'status' => $promotion->status,
                'canary_status' => $promotion->canary_status,
                'rollback_triggered' => $promotion->rollback_triggered,
            ];

            if ($promotion->rollback_triggered) {
                $this->auditLogger->logRollback($actor, $context);
            } else {
                $this->auditLogger->logPromoted($actor, $context);
            }

            return $promotion->fresh();
        });
    }

    public function logDenied(?User $user, Build $build, string $reason, array $payload = []): void
    {
        $context = [
            'build_id' => $build->id,
            'rfc_id' => $build->rfc_id,
            'reason' => $reason,
            'verifier_id' => Arr::get($payload, 'verifier_id'),
            'nonce' => Arr::get($payload, 'nonce'),
        ];

        $this->auditLogger->logDenied($user?->email, $context);
    }

    private function updateBuildMetadata(Build $build, Promotion $promotion): void
    {
        $metadata = $build->metadata ?? [];

        $metadata['promotion'] = [
            'promotion_id' => $promotion->id,
            'status' => $promotion->status,
            'status_reason' => $promotion->status_reason,
            'canary_status' => $promotion->canary_status,
            'promoted_at' => $promotion->promoted_at?->toIso8601String(),
            'rolled_back_at' => $promotion->rolled_back_at?->toIso8601String(),
        ];

        $build->metadata = $metadata;
        $build->save();
    }
}

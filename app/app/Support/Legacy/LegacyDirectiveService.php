<?php

namespace App\Support\Legacy;

use App\Models\LegacyDirective;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyDirectiveService
{
    public function __construct(private readonly LegacyDirectiveAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(User $user, array $payload): LegacyDirective
    {
        if (! $user->hasRole('owner')) {
            throw new RuntimeException('Only the owner may define the legacy directive.');
        }

        $directive = LegacyDirective::query()->firstOrNew(['user_id' => $user->id]);

        if (! $directive->user_id) {
            $directive->user()->associate($user);
        }

        $normalised = $this->normalise($payload, $directive);

        $directive->fill(Arr::except($normalised, ['passphrase']));

        if (array_key_exists('passphrase', $normalised) && $normalised['passphrase'] !== null) {
            $directive->passphrase_hash = Hash::make($normalised['passphrase']);
        }

        if ($directive->isDirty()) {
            if ($directive->erased_at && empty($payload['retain_erased_state'])) {
                $directive->erased_at = null;
                $directive->erased_reason = null;
            }

            if (! empty($payload['reactivate'])) {
                $directive->panic_disabled_at = null;
                $directive->panic_disabled_reason = null;
            }

            $directive->save();

            $this->auditLogger->log(
                $user->email,
                'legacy.directive.saved',
                [
                    'directive_id' => $directive->id,
                    'has_passphrase' => $directive->passphrase_hash !== null,
                    'beneficiary_count' => count($directive->beneficiaries ?? []),
                ],
            );
        }

        return $directive->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $directive = LegacyDirective::query()->where('user_id', $user->id)->first();

        if (! $directive) {
            return [];
        }

        $this->auditLogger->log($user->email, 'legacy.directive.exported', [
            'directive_id' => $directive->id,
        ]);

        return [
            'id' => $directive->id,
            'beneficiaries' => $directive->beneficiaries ?? [],
            'topics' => [
                'allow' => $directive->topics_allow ?? [],
                'deny' => $directive->topics_deny ?? [],
            ],
            'duration' => $directive->duration ?? [],
            'rate_limits' => $directive->rate_limits ?? [],
            'unlock_policy' => Arr::except($directive->unlock_policy ?? [], ['passphrase']),
            'panic_disabled_at' => optional($directive->panic_disabled_at)?->toIso8601String(),
            'erased_at' => optional($directive->erased_at)?->toIso8601String(),
        ];
    }

    public function erase(User $user, ?string $reason): LegacyDirective
    {
        $directive = LegacyDirective::query()->where('user_id', $user->id)->first();

        if (! $directive) {
            throw new RuntimeException('No legacy directive to erase.');
        }

        $directive->fill([
            'beneficiaries' => [],
            'topics_allow' => [],
            'topics_deny' => [],
            'duration' => null,
            'rate_limits' => null,
            'unlock_policy' => null,
            'passphrase_hash' => null,
        ]);

        $directive->panic_disabled_at = null;
        $directive->panic_disabled_reason = null;
        $directive->erased_at = now();
        $directive->erased_reason = $reason;
        $directive->save();

        $this->auditLogger->log($user->email, 'legacy.directive.erased', [
            'directive_id' => $directive->id,
            'reason' => $reason,
        ]);

        return $directive->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function requestUnlock(?User $actor, LegacyDirective $directive, array $payload): array
    {
        if ($directive->isErased()) {
            return [
                'status' => 'unavailable',
                'reason' => 'erased',
                'message' => 'Legacy directive has been erased and cannot be unlocked.',
            ];
        }

        if ($directive->isPanicDisabled()) {
            return [
                'status' => 'unavailable',
                'reason' => 'panic_disabled',
                'message' => 'Legacy directive is panic-disabled until reactivated by the owner.',
            ];
        }

        if (! $directive->passphrase_hash) {
            throw new RuntimeException('Legacy directive requires a passphrase before unlock requests can be processed.');
        }

        $executorName = trim((string) ($payload['executor_name'] ?? ''));
        $proofReference = isset($payload['proof_reference']) ? trim((string) $payload['proof_reference']) : null;
        $passphrase = (string) ($payload['passphrase'] ?? '');
        $confirm = (bool) ($payload['confirm'] ?? false);

        if ($executorName === '') {
            throw new RuntimeException('Executor name is required.');
        }

        if (! Hash::check($passphrase, $directive->passphrase_hash)) {
            $unlock = $directive->unlocks()->create([
                'executor_name' => $executorName,
                'status' => 'denied',
                'reason' => 'invalid_passphrase',
                'denied_at' => now(),
                'metadata' => [
                    'proof_reference' => $proofReference,
                ],
            ]);

            $this->auditLogger->log($actor?->email, 'legacy.directive.unlock.denied', [
                'directive_id' => $directive->id,
                'unlock_id' => $unlock->id,
                'executor_name' => $executorName,
                'reason' => 'invalid_passphrase',
            ]);

            return [
                'status' => 'denied',
                'reason' => 'invalid_passphrase',
                'message' => 'The provided passphrase was incorrect.',
            ];
        }

        $timeDelayHours = (int) ($directive->unlock_policy['time_delay_hours'] ?? 0);
        $availableAfter = CarbonImmutable::now()->addHours(max(0, $timeDelayHours));

        if ($confirm) {
            $pending = $directive->unlocks()
                ->where('executor_name', $executorName)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if (! $pending) {
                return [
                    'status' => 'not_found',
                    'message' => 'No pending unlock request found. Submit a request first.',
                ];
            }

            if ($pending->available_after && $pending->available_after->isFuture()) {
                return [
                    'status' => 'pending',
                    'message' => 'Unlock is still in the mandatory delay period.',
                    'available_after' => $pending->available_after->toIso8601String(),
                ];
            }

            $pending->status = 'approved';
            $pending->approved_at = now();
            $pending->save();

            $this->auditLogger->log($actor?->email, 'legacy.directive.unlock.approved', [
                'directive_id' => $directive->id,
                'unlock_id' => $pending->id,
                'executor_name' => $executorName,
            ]);

            return [
                'status' => 'approved',
                'unlock_id' => $pending->id,
            ];
        }

        $metadata = [
            'proof_reference' => $proofReference,
            'requested_by' => $actor?->email,
        ];

        if (! empty($directive->unlock_policy['proofs_required']) && $proofReference === null) {
            $metadata['missing_proof'] = true;
        }

        $unlock = $directive->unlocks()->create([
            'executor_name' => $executorName,
            'status' => 'pending',
            'available_after' => $availableAfter,
            'metadata' => $metadata,
        ]);

        $this->auditLogger->log($actor?->email, 'legacy.directive.unlock.requested', [
            'directive_id' => $directive->id,
            'unlock_id' => $unlock->id,
            'executor_name' => $executorName,
            'available_after' => $availableAfter->toIso8601String(),
        ]);

        return [
            'status' => 'pending',
            'unlock_id' => $unlock->id,
            'available_after' => $availableAfter->toIso8601String(),
        ];
    }

    public function panicDisable(User $user, LegacyDirective $directive, ?string $reason = null): LegacyDirective
    {
        if (! $user->hasRole('owner')) {
            throw new RuntimeException('Only the owner may panic-disable the directive.');
        }

        $directive->panic_disabled_at = now();
        $directive->panic_disabled_reason = $reason;
        $directive->save();

        $this->auditLogger->log($user->email, 'legacy.directive.panic_disabled', [
            'directive_id' => $directive->id,
            'reason' => $reason,
        ]);

        return $directive->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalise(array $payload, LegacyDirective $directive): array
    {
        $beneficiaries = collect($payload['beneficiaries'] ?? [])
            ->map(function ($beneficiary) {
                $name = trim((string) Arr::get($beneficiary, 'name'));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'relationship' => trim((string) Arr::get($beneficiary, 'relationship')),
                    'contact' => trim((string) Arr::get($beneficiary, 'contact')),
                    'notes' => Str::limit(trim((string) Arr::get($beneficiary, 'notes')), 200),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $allowTopics = $this->normaliseTopics(Arr::get($payload, 'topics.allow'));
        $denyTopics = $this->normaliseTopics(Arr::get($payload, 'topics.deny'));

        $duration = $this->normaliseDuration($payload['duration'] ?? []);
        $rateLimits = $this->normaliseRateLimits($payload['rate_limits'] ?? []);
        $unlockPolicy = $this->normaliseUnlockPolicy($payload['unlock_policy'] ?? []);

        $normalised = [
            'beneficiaries' => $beneficiaries,
            'topics_allow' => $allowTopics,
            'topics_deny' => $denyTopics,
            'duration' => $duration,
            'rate_limits' => $rateLimits,
            'unlock_policy' => $unlockPolicy,
        ];

        if (array_key_exists('passphrase', $payload)) {
            $normalised['passphrase'] = $payload['passphrase'] !== null
                ? (string) $payload['passphrase']
                : null;
        }

        return $normalised;
    }

    /**
     * @param  mixed  $topics
     * @return list<string>
     */
    private function normaliseTopics($topics): array
    {
        if (! is_array($topics)) {
            return [];
        }

        return collect($topics)
            ->map(fn ($topic) => trim(Str::lower((string) $topic)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $duration
     * @return array<string, mixed>|null
     */
    private function normaliseDuration($duration): ?array
    {
        if (! is_array($duration)) {
            return null;
        }

        $startsAt = Arr::get($duration, 'starts_at');
        $endsAt = Arr::get($duration, 'ends_at');
        $maxSessionMinutes = Arr::get($duration, 'max_session_minutes');
        $maxTotalHours = Arr::get($duration, 'max_total_hours');

        $payload = [
            'starts_at' => $startsAt ? CarbonImmutable::parse($startsAt)->toIso8601String() : null,
            'ends_at' => $endsAt ? CarbonImmutable::parse($endsAt)->toIso8601String() : null,
            'max_session_minutes' => $maxSessionMinutes !== null ? max(0, (int) $maxSessionMinutes) : null,
            'max_total_hours' => $maxTotalHours !== null ? max(0, (int) $maxTotalHours) : null,
        ];

        return array_filter($payload, static fn ($value) => $value !== null);
    }

    /**
     * @param  mixed  $limits
     * @return array<string, mixed>|null
     */
    private function normaliseRateLimits($limits): ?array
    {
        if (! is_array($limits)) {
            return null;
        }

        $requestsPerDay = Arr::get($limits, 'requests_per_day');
        $concurrentSessions = Arr::get($limits, 'concurrent_sessions');
        $cooldownHours = Arr::get($limits, 'cooldown_hours');

        $payload = [
            'requests_per_day' => $requestsPerDay !== null ? max(0, (int) $requestsPerDay) : null,
            'concurrent_sessions' => $concurrentSessions !== null ? max(0, (int) $concurrentSessions) : null,
            'cooldown_hours' => $cooldownHours !== null ? max(0, (int) $cooldownHours) : null,
        ];

        return array_filter($payload, static fn ($value) => $value !== null);
    }

    /**
     * @param  mixed  $policy
     * @return array<string, mixed>|null
     */
    private function normaliseUnlockPolicy($policy): ?array
    {
        if (! is_array($policy)) {
            return null;
        }

        $executor = Arr::get($policy, 'executor', []);
        $proofs = Arr::get($policy, 'proofs_required', []);

        $executorPayload = [
            'name' => trim((string) Arr::get($executor, 'name')),
            'contact' => trim((string) Arr::get($executor, 'contact')),
        ];

        $proofs = collect(is_array($proofs) ? $proofs : [])
            ->map(fn ($proof) => trim(Str::lower((string) $proof)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $policyPayload = [
            'executor' => $executorPayload,
            'proofs_required' => $proofs,
            'time_delay_hours' => max(0, (int) Arr::get($policy, 'time_delay_hours', 0)),
            'passphrase_hint' => Arr::get($policy, 'passphrase_hint'),
            'panic_contact' => Arr::get($policy, 'panic_contact'),
        ];

        return $policyPayload;
    }
}

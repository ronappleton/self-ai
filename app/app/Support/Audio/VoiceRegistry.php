<?php

namespace App\Support\Audio;

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\User;
use App\Models\Voice;
use App\Support\Audio\TtsWorkerCredentials;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VoiceRegistry
{
    public function __construct(private readonly TtsWorkerCredentials $credentials)
    {
    }

    /**
     * Register or refresh the owner voice dataset.
     */
    public function enrolOwnerVoice(
        User $user,
        UploadedFile $dataset,
        string $scriptVersion,
        string $consentScope,
        ?string $scriptText = null,
        ?string $consentNotes = null,
        ?int $sampleCount = null
    ): Voice {
        $disk = config('audio.storage_disk', 'minio');
        $now = CarbonImmutable::now();
        $runId = (string) Str::uuid();
        $directory = sprintf(
            'voice/owner/%s/%s/%s/%s',
            $now->format('Y'),
            $now->format('m'),
            $now->format('d'),
            $runId
        );

        $datasetName = $this->normaliseFilename($dataset->getClientOriginalName() ?: 'owner-voice-dataset');
        $datasetHash = hash_file('sha256', (string) $dataset->getRealPath());
        $datasetPath = $directory.'/'.$datasetName;
        Storage::disk($disk)->putFileAs($directory, $dataset, $datasetName);

        $metadata = [
            'run_id' => $runId,
            'dataset_original_name' => $dataset->getClientOriginalName(),
            'dataset_mime_type' => $dataset->getClientMimeType(),
            'script_acknowledged' => true,
        ];

        if ($scriptText !== null && $scriptText !== '') {
            $scriptPath = $directory.'/script.txt';
            Storage::disk($disk)->put($scriptPath, $scriptText);
            $metadata['script_text_preview'] = Str::limit($scriptText, 400);
            $metadata['script_text_path'] = $scriptPath;
        }

        $existing = $this->ownerVoice();

        $voice = Voice::updateOrCreate(
            ['voice_id' => $this->ownerVoiceId()],
            [
                'status' => 'active',
                'storage_disk' => $disk,
                'dataset_path' => $datasetPath,
                'dataset_sha256' => $datasetHash,
                'sample_count' => $sampleCount ?? 0,
                'script_version' => $scriptVersion,
                'consent_scope' => $consentScope,
                'metadata' => array_merge($existing?->metadata ?? [], $metadata),
                'enrolled_at' => now(),
                'enrolled_by' => $user->id,
                'disabled_at' => null,
                'disabled_reason' => null,
                'disabled_by' => null,
            ]
        );

        $this->recordConsent($user, $consentScope, $consentNotes, 'approved');
        $this->logEvent($user, 'voice.enrolled', [
            'voice_id' => $voice->voice_id,
            'dataset_path' => $datasetPath,
            'dataset_sha256' => $datasetHash,
            'script_version' => $scriptVersion,
            'sample_count' => $sampleCount ?? 0,
        ]);

        return $voice->fresh();
    }

    /**
     * Disable the owner voice and revoke worker credentials.
     */
    public function disableOwnerVoice(User $user, string $reason): ?Voice
    {
        $voice = $this->ownerVoice();

        if (! $voice) {
            return null;
        }

        if ($voice->status === 'disabled') {
            $this->credentials->rotate('kill_switch: '.$reason);

            return $voice;
        }

        $metadata = $voice->metadata ?? [];
        $metadata['kill_switch_engaged_at'] = now()->toIso8601String();
        $metadata['kill_switch_reason'] = $reason;

        $voice->fill([
            'status' => 'disabled',
            'disabled_at' => now(),
            'disabled_by' => $user->id,
            'disabled_reason' => $reason,
            'metadata' => $metadata,
        ])->save();

        $this->recordConsent($user, $voice->consent_scope, $reason, 'revoked');
        $this->writeKillSwitchFlag($reason);
        $this->credentials->rotate('kill_switch: '.$reason);
        $this->logEvent($user, 'voice.kill_switch', [
            'voice_id' => $voice->voice_id,
            'reason' => $reason,
        ]);

        return $voice->fresh();
    }

    /**
     * Return the list of supported voice identifiers.
     *
     * @return list<string>
     */
    public function availableVoiceIds(): array
    {
        $voices = ['neutral'];
        $owner = $this->ownerVoice();
        $ownerVoiceId = $this->ownerVoiceId();

        if ($owner) {
            $voices[] = $owner->voice_id;
        } elseif (! in_array($ownerVoiceId, $voices, true)) {
            $voices[] = $ownerVoiceId;
        }

        return array_values(array_unique($voices));
    }

    /**
     * Ensure the requested voice is available.
     *
     * @throws VoiceUnavailableException
     */
    public function ensureVoiceAvailable(string $voiceId): void
    {
        if ($voiceId === 'neutral') {
            return;
        }

        $owner = $this->ownerVoice();

        if (! $owner || $owner->voice_id !== $voiceId) {
            throw VoiceUnavailableException::forMissingVoice($voiceId);
        }

        if ($owner->status !== 'active') {
            throw VoiceUnavailableException::forDisabledVoice($voiceId, $owner->status);
        }
    }

    public function ownerVoice(): ?Voice
    {
        return Voice::query()->where('voice_id', $this->ownerVoiceId())->first();
    }

    private function ownerVoiceId(): string
    {
        return (string) config('audio.owner_voice.voice_id', 'owner');
    }

    private function normaliseFilename(string $name): string
    {
        $name = Str::of($name)->replaceMatches('/[^A-Za-z0-9_.-]/', '-')->lower();

        return (string) ($name === '' ? 'dataset.bin' : $name);
    }

    private function recordConsent(User $user, string $scope, ?string $notes, string $status): void
    {
        $timestamp = now();
        $attributes = [
            'source' => 'voice:owner',
            'document_id' => null,
        ];

        $values = [
            'user_id' => $user->id,
            'scope' => $scope,
            'status' => $status,
            'notes' => $notes,
            'granted_at' => $status === 'approved' ? $timestamp : null,
            'revoked_at' => $status === 'revoked' ? $timestamp : null,
        ];

        Consent::updateOrCreate($attributes, $values);
    }

    private function writeKillSwitchFlag(string $reason): void
    {
        $disk = config('audio.owner_voice.kill_switch.disk', 'local');
        $path = config('audio.owner_voice.kill_switch.flag_path', 'system/tts-owner-disabled.flag');

        $directory = Str::beforeLast($path, '/');
        if ($directory !== '' && $directory !== $path) {
            Storage::disk($disk)->makeDirectory($directory);
        }

        Storage::disk($disk)->put($path, json_encode([
            'disabled_at' => now()->toIso8601String(),
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR));
    }

    private function logEvent(?User $user, string $action, array $context): void
    {
        $timestamp = now();
        $actor = $user?->email ?? 'system';

        DB::transaction(function () use ($actor, $action, $context, $timestamp): void {
            $query = AuditLog::query()->latest('id');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $previousHash = $query->value('hash');

            $payload = [
                'actor' => $actor,
                'action' => $action,
                'target' => 'voice',
                'context' => $context,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            AuditLog::query()->create([
                'actor' => $actor,
                'action' => $action,
                'target' => 'voice',
                'context' => $context,
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp,
            ]);
        });
    }
}

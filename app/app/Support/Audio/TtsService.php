<?php

namespace App\Support\Audio;

use App\Jobs\SynthesizeSpeech;
use App\Models\AuditLog;
use App\Models\TtsRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TtsService
{
    public function __construct(
        private readonly VoiceRegistry $voiceRegistry,
        private readonly ImpersonationGuard $impersonationGuard
    ) {
    }

    /**
     * Generate speech for the provided text and return the request record.
     *
     * @throws VoiceImpersonationRejectedException
     * @throws VoiceUnavailableException
     */
    public function synthesize(string $text, string $voiceId, ?User $user = null): TtsRequest
    {
        $impersonation = $this->impersonationGuard->detect($text);
        if ($impersonation !== null) {
            $this->logImpersonationRefusal($user, $text, $voiceId, $impersonation);

            throw new VoiceImpersonationRejectedException(
                $impersonation['keyword'],
                $impersonation['message'],
                $impersonation['alternative']
            );
        }

        $this->voiceRegistry->ensureVoiceAvailable($voiceId);

        $disk = config('audio.storage_disk', 'minio');
        $now = CarbonImmutable::now();
        $runId = (string) Str::uuid();
        $directory = sprintf(
            'audio/%s/%s/%s/%s',
            $now->format('Y'),
            $now->format('m'),
            $now->format('d'),
            $runId
        );
        Storage::disk($disk)->makeDirectory($directory);

        $filename = 'speech.wav';
        $audioPath = "{$directory}/{$filename}";
        $watermarkId = (string) Str::uuid();
        $sampleRate = (int) (config('audio.tts.sample_rate', 16000));

        $request = TtsRequest::create([
            'status' => 'queued',
            'voice_id' => $voiceId,
            'text_hash' => hash('sha256', $text),
            'text' => $text,
            'audio_disk' => $disk,
            'audio_path' => $audioPath,
            'metadata' => [
                'run_id' => $runId,
                'watermark_id' => $watermarkId,
                'sample_rate' => $sampleRate,
            ],
            'requested_by' => $user?->id,
        ]);

        $dispatchMode = config('audio.tts.dispatch', 'sync');

        if ($dispatchMode === 'queue') {
            SynthesizeSpeech::dispatch($request->id);
        } else {
            SynthesizeSpeech::dispatchSync($request->id);
        }

        return $request->fresh() ?? $request;
    }

    /**
     * @param  array{keyword: string, message: string, alternative: string}  $details
     */
    private function logImpersonationRefusal(?User $user, string $text, string $voiceId, array $details): void
    {
        $timestamp = now();
        $actor = $user?->email ?? 'system';
        $context = [
            'voice_id' => $voiceId,
            'keyword' => $details['keyword'],
            'message' => $details['message'],
            'prompt_preview' => Str::limit($text, 180),
        ];

        DB::transaction(function () use ($actor, $context, $timestamp): void {
            $query = AuditLog::query()->latest('id');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $previousHash = $query->value('hash');

            $payload = [
                'actor' => $actor,
                'action' => 'voice.impersonation_refused',
                'target' => 'voice',
                'context' => $context,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            AuditLog::query()->create([
                'actor' => $actor,
                'action' => 'voice.impersonation_refused',
                'target' => 'voice',
                'context' => $context,
                'hash' => $hash,
                'previous_hash' => $previousHash,
                'created_at' => $timestamp,
            ]);
        });
    }
}

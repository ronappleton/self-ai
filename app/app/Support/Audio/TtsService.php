<?php

namespace App\Support\Audio;

use App\Jobs\SynthesizeSpeech;
use App\Models\TtsRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TtsService
{
    /**
     * Generate speech for the provided text and return the request record.
     */
    public function synthesize(string $text, string $voiceId, ?User $user = null): TtsRequest
    {
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
}

<?php

namespace App\Jobs;

use App\Models\TtsRequest;
use App\Support\Audio\TtsWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SynthesizeSpeech implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly string $ttsRequestId)
    {
        $this->onQueue(config('audio.tts.queue', 'audio-tts'));
    }

    /**
     * Execute the job.
     */
    public function handle(TtsWorkerClient $client): void
    {
        $request = TtsRequest::query()->find($this->ttsRequestId);

        if (! $request) {
            return;
        }

        if ($request->status === 'completed') {
            return;
        }

        $request->forceFill([
            'status' => 'processing',
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        try {
            $metadata = $request->metadata ?? [];
            $watermarkId = (string) ($metadata['watermark_id'] ?? Str::uuid());
            $metadata['watermark_id'] = $watermarkId;
            $sampleRate = isset($metadata['sample_rate'])
                ? (int) $metadata['sample_rate']
                : (int) (config('audio.tts.sample_rate', 16000));

            $disk = Storage::disk($request->audio_disk);
            $audioPath = $request->audio_path;
            if (! $audioPath) {
                $audioPath = sprintf('audio/%s/%s.wav', now()->format('Y/m/d'), Str::uuid());
                $request->forceFill(['audio_path' => $audioPath])->save();
            }

            $directory = Str::beforeLast($audioPath, '/');
            if ($directory !== '') {
                $disk->makeDirectory($directory);
            }

            $outputPath = $disk->path($audioPath);

            $result = $client->synthesize(
                $request->text,
                $request->voice_id,
                $outputPath,
                $watermarkId,
                $sampleRate
            );

            $metadata['worker'] = 'python-tts';
            $metadata['duration_seconds'] = Arr::get($result, 'duration_seconds');
            $metadata['sample_rate'] = Arr::get($result, 'sample_rate', $sampleRate);

            $request->forceFill([
                'status' => 'completed',
                'metadata' => $metadata,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $request->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => Str::limit($exception->getMessage(), 500),
            ])->save();

            throw $exception;
        }
    }
}

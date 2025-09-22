<?php

namespace App\Jobs;

use App\Models\AudioTranscription;
use App\Support\Audio\AsrWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessAudioTranscription implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly string $transcriptionId)
    {
        $this->onQueue(config('audio.asr.queue', 'audio-asr'));
    }

    /**
     * Execute the job.
     */
    public function handle(AsrWorkerClient $client): void
    {
        $transcription = AudioTranscription::query()->find($this->transcriptionId);

        if (! $transcription) {
            return;
        }

        if ($transcription->status === 'completed') {
            return;
        }

        $transcription->forceFill([
            'status' => 'processing',
            'failed_at' => null,
            'failure_reason' => null,
        ])->save();

        try {
            $disk = Storage::disk($transcription->input_disk);
            $audioPath = $disk->path($transcription->input_path);

            $result = $client->transcribe($audioPath);

            $transcript = (string) Arr::get($result, 'transcript', '');
            $segments = Arr::get($result, 'segments', []);
            if (! is_array($segments)) {
                $segments = [];
            }

            $durationSeconds = (float) Arr::get($result, 'duration_seconds', 0.0);
            $sampleRate = (int) Arr::get($result, 'sample_rate', 16000);

            $transcriptDisk = $transcription->transcript_disk ?: $transcription->input_disk;
            $transcriptPath = $transcription->transcript_path
                ?: Str::beforeLast($transcription->input_path, '/').'/transcript.json';

            Storage::disk($transcriptDisk)->put(
                $transcriptPath,
                json_encode([
                    'transcript' => $transcript,
                    'segments' => $segments,
                    'duration_seconds' => $durationSeconds,
                    'sample_rate' => $sampleRate,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );

            $metadata = $transcription->metadata ?? [];
            $metadata['worker'] = 'python-asr';
            $metadata['duration_seconds'] = $durationSeconds;
            $metadata['sample_rate'] = $sampleRate;

            $transcription->forceFill([
                'status' => 'completed',
                'transcript_disk' => $transcriptDisk,
                'transcript_path' => $transcriptPath,
                'transcript_text' => $transcript,
                'timings' => $segments,
                'duration_ms' => max(0, (int) round($durationSeconds * 1000)),
                'sample_rate' => $sampleRate,
                'metadata' => $metadata,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $transcription->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => Str::limit($exception->getMessage(), 500),
            ])->save();

            throw $exception;
        }
    }
}

<?php

namespace App\Support\Audio;

use App\Jobs\ProcessAudioTranscription;
use App\Models\AudioTranscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AsrService
{
    /**
     * Handle an ASR request and return the stored transcription record.
     */
    public function transcribe(UploadedFile $file, ?User $user = null): AudioTranscription
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

        $extension = $this->determineExtension($file);
        $filename = 'input'.($extension ? ".{$extension}" : '');

        Storage::disk($disk)->putFileAs($directory, $file, $filename);
        $inputPath = "{$directory}/{$filename}";

        $transcription = AudioTranscription::create([
            'status' => 'queued',
            'input_disk' => $disk,
            'input_path' => $inputPath,
            'transcript_disk' => $disk,
            'metadata' => [
                'run_id' => $runId,
                'mime_type' => $file->getClientMimeType(),
                'original_filename' => $file->getClientOriginalName(),
            ],
            'requested_by' => $user?->id,
        ]);

        $dispatchMode = config('audio.asr.dispatch', 'sync');

        if ($dispatchMode === 'queue') {
            ProcessAudioTranscription::dispatch($transcription->id);
        } else {
            ProcessAudioTranscription::dispatchSync($transcription->id);
        }

        return $transcription->fresh() ?? $transcription;
    }

    private function determineExtension(UploadedFile $file): ?string
    {
        $original = $file->getClientOriginalExtension();
        if (is_string($original) && $original !== '') {
            return strtolower($original);
        }

        $mime = $file->getClientMimeType();

        return match ($mime) {
            'audio/wav', 'audio/wave', 'audio/x-wav' => 'wav',
            'audio/opus', 'audio/ogg', 'audio/webm' => 'opus',
            'audio/flac', 'audio/x-flac' => 'flac',
            default => null,
        };
    }
}

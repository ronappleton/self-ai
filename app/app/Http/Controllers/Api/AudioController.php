<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audio\AsrService;
use App\Support\Audio\TtsService;
use App\Support\Audio\VoiceImpersonationRejectedException;
use App\Support\Audio\VoiceRegistry;
use App\Support\Audio\VoiceUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AudioController extends Controller
{
    public function __construct(
        private readonly AsrService $asrService,
        private readonly TtsService $ttsService,
        private readonly VoiceRegistry $voiceRegistry
    ) {
    }

    public function asr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => [
                'required',
                'file',
                'max:40960',
                'mimetypes:audio/wav,audio/wave,audio/x-wav,audio/opus,audio/ogg,audio/webm,audio/flac,audio/x-flac',
            ],
        ]);

        $record = $this->asrService->transcribe($validated['audio'], $request->user());

        $timings = $record->timings ?? [];
        if (! is_array($timings)) {
            $timings = [];
        }

        return response()->json([
            'status' => $record->status,
            'transcription_id' => $record->id,
            'transcript' => $record->transcript_text,
            'timings' => $timings,
            'duration_ms' => $record->duration_ms,
            'sample_rate' => $record->sample_rate,
            'storage' => [
                'audio' => $this->formatStorageUrl($record->input_disk, $record->input_path),
                'transcript' => $record->transcript_path
                    ? $this->formatStorageUrl($record->transcript_disk, $record->transcript_path)
                    : null,
            ],
        ], Response::HTTP_OK);
    }

    public function tts(Request $request): JsonResponse
    {
        $allowedVoices = $this->voiceRegistry->availableVoiceIds();

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:2000'],
            'voice' => ['sometimes', 'string', Rule::in($allowedVoices)],
        ]);

        $voice = Str::lower($validated['voice'] ?? config('audio.tts.default_voice', 'neutral'));

        try {
            $record = $this->ttsService->synthesize($validated['text'], $voice, $request->user());
        } catch (VoiceUnavailableException $exception) {
            return response()->json([
                'error' => 'voice_unavailable',
                'voice_id' => $exception->voiceId,
                'reason' => $exception->reason,
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (VoiceImpersonationRejectedException $exception) {
            return response()->json([
                'error' => 'impersonation_refused',
                'keyword' => $exception->keyword,
                'message' => $exception->getMessage(),
                'safe_alternative' => $exception->alternative,
            ], Response::HTTP_FORBIDDEN);
        }
        $metadata = $record->metadata ?? [];
        if (! is_array($metadata)) {
            $metadata = [];
        }

        return response()->json([
            'status' => $record->status,
            'tts_request_id' => $record->id,
            'voice_id' => $record->voice_id,
            'text_hash' => $record->text_hash,
            'audio_url' => $this->formatStorageUrl($record->audio_disk, $record->audio_path),
            'watermark_id' => Arr::get($metadata, 'watermark_id'),
            'metadata' => $metadata,
        ], Response::HTTP_OK);
    }

    private function formatStorageUrl(?string $disk, ?string $path): ?string
    {
        if ($disk === null || $path === null) {
            return null;
        }

        return sprintf('%s://%s', $disk, ltrim($path, '/'));
    }
}

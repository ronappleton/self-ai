<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audio\VoiceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VoiceController extends Controller
{
    public function __construct(private readonly VoiceRegistry $voiceRegistry)
    {
    }

    public function enrol(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('owner')) {
            abort(Response::HTTP_FORBIDDEN, 'Only the owner may enrol a voice.');
        }

        $validated = $request->validate([
            'dataset' => ['required', 'file', 'max:102400', 'mimetypes:application/zip,audio/wav,audio/wave,audio/x-wav,audio/flac,audio/x-flac,audio/ogg,audio/opus'],
            'script_version' => ['required', 'string', 'max:100'],
            'consent_scope' => ['required', 'string', 'max:255'],
            'consent_notes' => ['nullable', 'string', 'max:1000'],
            'script_text' => ['nullable', 'string', 'max:5000'],
            'sample_count' => ['nullable', 'integer', 'min:1', 'max:400'],
            'script_acknowledged' => ['required', 'accepted'],
        ]);

        $voice = $this->voiceRegistry->enrolOwnerVoice(
            $user,
            $validated['dataset'],
            $validated['script_version'],
            $validated['consent_scope'],
            $validated['script_text'] ?? null,
            $validated['consent_notes'] ?? null,
            $validated['sample_count'] ?? null
        );

        return response()->json([
            'voice_id' => $voice->voice_id,
            'status' => $voice->status,
            'dataset_sha256' => $voice->dataset_sha256,
            'enrolled_at' => $voice->enrolled_at?->toIso8601String(),
            'consent_scope' => $voice->consent_scope,
        ], Response::HTTP_CREATED);
    }

    public function killSwitch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('owner')) {
            abort(Response::HTTP_FORBIDDEN, 'Only the owner may disable the voice.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $voice = $this->voiceRegistry->disableOwnerVoice($user, $validated['reason'] ?? 'owner_kill_switch');

        if (! $voice) {
            return response()->json([
                'status' => 'not_enrolled',
            ]);
        }

        return response()->json([
            'voice_id' => $voice->voice_id,
            'status' => $voice->status,
            'disabled_at' => $voice->disabled_at?->toIso8601String(),
            'reason' => $voice->disabled_reason,
        ]);
    }
}

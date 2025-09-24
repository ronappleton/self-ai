<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Legacy\LegacyPreviewRateLimitException;
use App\Support\Legacy\LegacyPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LegacyPreviewController extends Controller
{
    public function __construct(private readonly LegacyPreviewService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $toneKeys = array_keys(config('legacy.tones', []));

        $validated = $request->validate([
            'session_id' => ['sometimes', 'uuid'],
            'persona_name' => ['sometimes', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'min:3'],
            'tone' => ['sometimes', 'string', Rule::in($toneKeys)],
            'redactions' => ['sometimes', 'array'],
            'redactions.memory_ids' => ['sometimes', 'array'],
            'redactions.memory_ids.*' => ['string'],
            'redactions.sources' => ['sometimes', 'array'],
            'redactions.sources.*' => ['string', 'max:255'],
            'redactions.notes' => ['sometimes', 'string', 'max:500'],
        ]);

        try {
            $result = $this->service->preview($request->user(), $validated);
        } catch (LegacyPreviewRateLimitException $exception) {
            $response = response()->json([
                'error' => 'rate_limited',
                'message' => 'The legacy preview needs a short rest before the next message.',
                'retry_after_seconds' => $exception->retryAfterSeconds(),
                'cooldown_ends_at' => $exception->cooldownEndsAt()->toIso8601String(),
            ], Response::HTTP_TOO_MANY_REQUESTS);

            $response->headers->set('Retry-After', (string) $exception->retryAfterSeconds());

            return $response;
        } catch (\RuntimeException $exception) {
            return response()->json([
                'error' => 'legacy_preview_error',
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json($result, Response::HTTP_OK);
    }
}

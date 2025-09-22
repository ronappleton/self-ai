<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Chat\ChatBudgetExceededException;
use App\Support\Chat\ChatService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chatService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $modes = array_keys(config('chat.modes', []));
        $explanationLevels = config('chat.explanation.levels', ['terse', 'detailed']);

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', $modes)],
            'prompt' => ['required', 'string', 'min:3'],
            'controls' => ['sometimes', 'array'],
            'controls.explanation' => ['sometimes', 'string', 'in:'.implode(',', $explanationLevels)],
        ]);

        try {
            $result = $this->chatService->respond(
                $request->user(),
                $validated['mode'],
                $validated['prompt'],
                Arr::get($validated, 'controls', [])
            );
        } catch (ChatBudgetExceededException $exception) {
            $type = $exception->budgetType;
            $snapshot = $exception->snapshot;
            $budget = $snapshot[$type] ?? [];
            $retryAfter = null;
            if (isset($budget['reset_at'])) {
                $resetAt = CarbonImmutable::parse($budget['reset_at']);
                $retryAfter = now()->diffInSeconds($resetAt, false);
                if ($retryAfter < 0) {
                    $retryAfter = 0;
                }
            }

            $response = response()->json([
                'error' => 'budget_exceeded',
                'budget_type' => $type,
                'message' => $exception->getMessage(),
                'budget' => $snapshot,
            ], Response::HTTP_TOO_MANY_REQUESTS);

            if ($retryAfter !== null) {
                $response->headers->set('Retry-After', (string) $retryAfter);
            }

            return $response;
        }

        return response()->json($result, Response::HTTP_OK);
    }
}

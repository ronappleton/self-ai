<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Support\Promotions\Exceptions\BuildNotPromotableException;
use App\Support\Promotions\Exceptions\InvalidPromotionSignatureException;
use App\Support\Promotions\Exceptions\PromotionReplayException;
use App\Support\Promotions\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'build_id' => ['required', 'uuid', Rule::exists('builds', 'id')],
            'verifier_id' => ['required', 'string', 'max:120'],
            'signature' => ['required', 'string', 'max:512'],
            'nonce' => ['required', 'string', 'max:120'],
            'requested_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:requested_at'],
            'canary' => ['sometimes', 'array'],
            'canary.targets' => ['sometimes', 'array'],
            'canary.targets.*' => ['string', 'max:120'],
        ]);

        $build = Build::query()->findOrFail($validated['build_id']);

        try {
            $promotion = $this->promotionService->promote($request->user(), $build, $validated);
        } catch (InvalidPromotionSignatureException $exception) {
            $this->promotionService->logDenied($request->user(), $build, 'invalid_signature', $validated);

            return response()->json([
                'error' => 'invalid_signature',
                'message' => $exception->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (PromotionReplayException $exception) {
            $this->promotionService->logDenied($request->user(), $build, 'nonce_reused', $validated);

            return response()->json([
                'error' => 'nonce_reused',
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (BuildNotPromotableException $exception) {
            $this->promotionService->logDenied($request->user(), $build, 'build_not_promotable', $validated);

            return response()->json([
                'error' => 'build_not_promotable',
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $status = $promotion->status === 'promoted'
            ? Response::HTTP_ACCEPTED
            : Response::HTTP_CONFLICT;

        return response()->json([
            'promotion_id' => $promotion->id,
            'build_id' => $promotion->build_id,
            'status' => $promotion->status,
            'status_reason' => $promotion->status_reason,
            'canary' => [
                'status' => $promotion->canary_status,
                'checks' => $promotion->canary_report,
            ],
            'rollback_triggered' => $promotion->rollback_triggered,
            'promoted_at' => $promotion->promoted_at?->toIso8601String(),
            'rolled_back_at' => $promotion->rolled_back_at?->toIso8601String(),
        ], $status);
    }
}

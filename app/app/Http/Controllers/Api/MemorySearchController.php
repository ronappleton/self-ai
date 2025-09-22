<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Memory\MemorySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemorySearchController extends Controller
{
    public function __invoke(Request $request, MemorySearchService $service): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.config('vector.search.max_limit', 20)],
            'freshness_weight' => ['sometimes', 'numeric', 'min:0'],
            'source_weights' => ['sometimes', 'array'],
            'source_weights.*' => ['numeric', 'min:0'],
        ]);

        $results = $service->search($validated['q'], [
            'limit' => $validated['limit'] ?? null,
            'freshness_weight' => $validated['freshness_weight'] ?? null,
            'source_weights' => $validated['source_weights'] ?? [],
        ]);

        return response()->json([
            'query' => $validated['q'],
            'hits' => $results,
        ], Response::HTTP_OK);
    }
}

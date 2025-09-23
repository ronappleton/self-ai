<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RfcProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RfcController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'string'],
            'risks' => ['required', 'string'],
            'tests' => ['required', 'array', 'min:1'],
            'tests.*.name' => ['required', 'string', 'max:100'],
            'tests.*.command' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'integer', 'min:1'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $rfc = RfcProposal::create([
            'title' => $validated['title'],
            'scope' => $validated['scope'],
            'risks' => $validated['risks'],
            'tests' => array_map(function (array $test): array {
                return [
                    'name' => $test['name'],
                    'command' => $test['command'],
                ];
            }, $validated['tests']),
            'budget' => $validated['budget'],
            'status' => 'draft',
            'metadata' => $validated['metadata'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'rfc_id' => $rfc->id,
            'status' => $rfc->status,
            'title' => $rfc->title,
            'scope' => $rfc->scope,
            'risks' => $rfc->risks,
            'tests' => $rfc->tests,
            'budget' => $rfc->budget,
        ], Response::HTTP_CREATED);
    }
}

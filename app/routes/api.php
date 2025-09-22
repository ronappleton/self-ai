<?php

use App\Support\Policy\PolicyVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (Request $request, PolicyVerifier $verifier) {
    $policy = $verifier->verify();

    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'app' => config('app.name'),
        'queue' => config('queue.default'),
        'policy_hash' => $policy['hash'],
        'policy_version' => $policy['version'],
    ]);
});

Route::get('/policy/verify', function (PolicyVerifier $verifier) {
    $result = $verifier->verify();

    return response()->json([
        'valid' => true,
        'policy' => $result,
    ]);
});

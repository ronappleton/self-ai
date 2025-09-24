<?php

use App\Http\Controllers\Api\AudioController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\IngestionController;
use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\Api\RfcController;
use App\Http\Controllers\Api\MemorySearchController;
use App\Http\Controllers\Api\VoiceController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\LegacyPreviewController;
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

Route::middleware('auth:sanctum')->prefix('v1')->group(function (): void {
    Route::post('/ingest/text', [IngestionController::class, 'ingestText']);
    Route::post('/ingest/file', [IngestionController::class, 'ingestFile']);
    Route::delete('/ingest/document/{document}', [IngestionController::class, 'destroyDocument']);
    Route::delete('/ingest/source/{source}', [IngestionController::class, 'destroyBySource']);
    Route::get('/memory/search', MemorySearchController::class);
    Route::post('/chat', ChatController::class);
    Route::post('/audio/asr', [AudioController::class, 'asr']);
    Route::post('/audio/tts', [AudioController::class, 'tts']);
    Route::post('/voice/enrol', [VoiceController::class, 'enrol']);
    Route::post('/voice/kill-switch', [VoiceController::class, 'killSwitch']);
    Route::post('/rfc', [RfcController::class, 'store']);
    Route::post('/build', [BuildController::class, 'store']);
    Route::get('/build/{build}', [BuildController::class, 'show']);
    Route::post('/promote', [PromotionController::class, 'store']);
    Route::post('/legacy/preview', LegacyPreviewController::class);
});

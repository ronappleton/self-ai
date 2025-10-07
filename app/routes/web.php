<?php

use App\Http\Controllers\ReviewDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/console', function () {
    return view('console', [
        'apiEndpoints' => [
            'health' => url('/api/health'),
            'chat' => url('/api/v1/chat'),
            'memorySearch' => url('/api/v1/memory/search'),
            'ingestText' => url('/api/v1/ingest/text'),
        ],
    ]);
})->name('console');

Route::middleware('auth')->prefix('review/documents')->name('review.documents.')->group(function (): void {
    Route::get('/', [ReviewDocumentController::class, 'index'])->name('index');
    Route::post('/{document}/approve', [ReviewDocumentController::class, 'approve'])->name('approve');
    Route::post('/{document}/reject', [ReviewDocumentController::class, 'reject'])->name('reject');
});

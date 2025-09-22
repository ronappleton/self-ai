<?php

use App\Http\Controllers\ReviewDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->prefix('review/documents')->name('review.documents.')->group(function (): void {
    Route::get('/', [ReviewDocumentController::class, 'index'])->name('index');
    Route::post('/{document}/approve', [ReviewDocumentController::class, 'approve'])->name('approve');
    Route::post('/{document}/reject', [ReviewDocumentController::class, 'reject'])->name('reject');
});

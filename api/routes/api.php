<?php

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ScrapeRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('scrape-requests')->group(function () {
    Route::get('/', [ScrapeRequestController::class, 'index']);
    Route::post('/', [ScrapeRequestController::class, 'store']);
    Route::get('/{id}', [ScrapeRequestController::class, 'show']);
    Route::get('/{id}/status', [ScrapeRequestController::class, 'status']);
    Route::post('/{id}/cancel', [ScrapeRequestController::class, 'cancel']);
    Route::delete('/{id}', [ScrapeRequestController::class, 'destroy']);
});

Route::prefix('leads')->group(function () {
    Route::get('/', [LeadController::class, 'index']);
    Route::get('/stats', [LeadController::class, 'stats']);
    Route::get('/export', [LeadController::class, 'export']);
    Route::get('/{id}', [LeadController::class, 'show']);
    Route::delete('/{id}', [LeadController::class, 'destroy']);
});
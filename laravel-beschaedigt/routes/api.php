<?php

use App\Http\Controllers\Api\MarketDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/market')->group(function (): void {
    Route::get('/snapshot', [MarketDashboardController::class, 'latest'])
        ->name('api.v1.market.snapshot');
    Route::get('/history', [MarketDashboardController::class, 'history'])
        ->name('api.v1.market.history');
});

<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::view('/market-overview', 'market-overview')
        ->name('market-overview');

    Route::view('/predictions', 'predictions.index')
        ->name('predictions.index');

    Route::view('/stocks', 'stocks.index')
        ->name('stocks.index');

    Route::view('/ai-status', 'ai-status')
        ->name('ai-status');
});

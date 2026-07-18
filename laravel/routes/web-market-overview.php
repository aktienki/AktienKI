<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::view('/market-overview', 'market-overview')
        ->name('market-overview');
});

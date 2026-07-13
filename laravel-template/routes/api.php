<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'service' => 'laravel',
        'status' => 'ok',
    ]));
});

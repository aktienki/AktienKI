<?php

use App\Http\Controllers\UserThemeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::patch('/settings/theme', [UserThemeController::class, 'update'])
        ->name('settings.theme.update');
});

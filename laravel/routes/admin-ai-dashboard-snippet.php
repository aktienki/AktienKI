<?php

use App\Http\Controllers\Admin\AiDashboardController;
use Illuminate\Support\Facades\Route;

/*
 * In deine bestehende authentifizierte Admin-Routengruppe übernehmen.
 * Bestehende Routen nicht löschen.
 */
Route::get(
    '/admin/ai-dashboard/data',
    AiDashboardController::class,
)->name('admin.ai-dashboard.data');

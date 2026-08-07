<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/verify-email', EmailVerificationPromptController::class)
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/beta/aktivieren', function (Request $request) {
    return view('auth.beta-activate');
})->middleware(['auth', 'verified'])->name('beta.activation');

Route::post('/beta/aktivieren', function (Request $request) {
    $request->validate(['beta_code' => ['required', 'string', 'max:100']]);
    $user = $request->user();
    $hash = data_get($user->meta, 'beta_registration.code_hash');
    if (! $hash || ! Hash::check(strtoupper(trim($request->string('beta_code')->toString())), $hash)) {
        return back()->withErrors(['beta_code' => __('Der Freischaltcode ist ungültig.')])->withInput();
    }
    $meta = $user->meta ?? [];
    data_set($meta, 'beta_registration.status', 'active');
    data_set($meta, 'beta_registration.activated_at', now()->toIso8601String());
    $proPlanId = DB::table('tariff_plans')->where('code', 'pro')->value('id');
    $phaseEnded = (bool) config('aktienki.beta.phase_ended', false);
    $trialStartsAt = $phaseEnded ? now() : null;
    $trialEndsAt = $phaseEnded ? $trialStartsAt->copy()->addMonths(3) : null;
    unset($meta['beta_registration']['code_hash'], $meta['beta_registration']['code_encrypted']);
    $user->forceFill([
        'account_status' => 'tester',
        'is_beta_tester' => true,
        'tariff_plan_id' => $proPlanId,
            'tariff_status' => 'trialing',
        'billing_cycle' => 'monthly',
            'tariff_started_at' => $trialStartsAt,
        'tariff_ends_at' => $trialEndsAt,
            'subscription_metadata' => array_merge((array) ($user->subscription_metadata ?? []), [
                'source' => 'beta_trial',
                'trial_months' => 3,
                'trial_starts_after_beta' => ! $phaseEnded,
                'trial_started_at' => $trialStartsAt?->toIso8601String(),
                'trial_ends_at' => $trialEndsAt?->toIso8601String(),
            ]),
        'meta' => $meta,
    ])->save();
    return redirect()->route('dashboard')->with('status', 'beta-activated');
})->middleware(['auth', 'verified', 'throttle:6,1'])->name('beta.activation.complete');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

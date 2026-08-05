<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $preferences = $user->preferences ?? [];

        foreach (['email_service', 'email_market_summary', 'email_price_alerts', 'email_product_updates'] as $key) {
            if (array_key_exists($key, $validated)) {
                $preferences[$key] = (bool) $validated[$key];
            }
        }

        if (isset($validated['locale'])) {
            $preferences['locale'] = $validated['locale'];
            $request->session()->put('locale', $validated['locale']);
        }

        $meta = $user->meta ?? [];

        if (isset($validated['risk_level'])) {
            data_set($meta, 'risk_profile', [
                'version' => '1.0',
                'completed_at' => now()->toIso8601String(),
                'level' => $validated['risk_level'],
                'source' => 'profile_selection',
            ]);
        }

        $user->preferences = $preferences;
        $user->meta = $meta;
        $user->save();

        // Keep the current browser session bound to the updated user. This is
        // especially important after changing identity fields such as e-mail.
        Auth::guard('web')->login($user->fresh(), Auth::guard('web')->viaRemember());

        $returnTo = $validated['return_to'] ?? null;

        if (is_string($returnTo) && $returnTo !== '') {
            $returnHost = parse_url($returnTo, PHP_URL_HOST);
            $returnScheme = parse_url($returnTo, PHP_URL_SCHEME);
            $isLocalPath = str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//');
            $isSameOriginUrl = in_array($returnScheme, ['http', 'https'], true)
                && $returnHost !== null
                && strcasecmp($returnHost, $request->getHost()) === 0;

            if ($isLocalPath || $isSameOriginUrl) {
                return Redirect::to($returnTo)->with('status', 'profile-updated');
            }
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

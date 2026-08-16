<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Models\MessagingConnection;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $countryChangeCount = (int) data_get($request->user()->preferences, 'country_code_change_count', 0);
        $countryLocked = app(PlanAccessService::class)->level($request->user()) === PlanLevel::Free
            && $countryChangeCount >= 1;

        return view('profile.edit', [
            'user' => $request->user(),
            'whatsapp' => MessagingConnection::query()->firstOrNew(['user_id' => $request->user()->id, 'provider' => 'whatsapp_cloud']),
            'countryLocked' => $countryLocked,
            'countryChangeCount' => $countryChangeCount,
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
        $requestedCountry = strtoupper((string) $validated['country_code']);
        $currentCountry = strtoupper((string) ($preferences['country_code'] ?? ''));
        $countryChanged = $currentCountry !== '' && $requestedCountry !== $currentCountry;

        if ($countryChanged && app(PlanAccessService::class)->level($user) === PlanLevel::Free) {
            $countryChangeCount = (int) ($preferences['country_code_change_count'] ?? 0);

            if ($countryChangeCount >= 1) {
                throw ValidationException::withMessages([
                    'country_code' => __('Im Free-Tarif kann das Land nach der Registrierung nur einmal geändert werden.'),
                ]);
            }

            $preferences['country_code_change_count'] = $countryChangeCount + 1;
            $preferences['country_code_changed_at'] = now()->toIso8601String();
        }

        $preferences['country_code'] = $requestedCountry;

        $allowedMobileNav = ['welcome', 'features', 'roadmap', 'dashboard', 'predictions', 'depots', ...($user->is_admin ? ['accounts'] : []), 'setup', 'news', 'pricing', 'contact', 'community'];
        if (array_key_exists('mobile_nav_order', $validated) || array_key_exists('mobile_nav_hidden', $validated)) {
            $order = json_decode((string) ($validated['mobile_nav_order'] ?? '[]'), true);
            $hidden = json_decode((string) ($validated['mobile_nav_hidden'] ?? '[]'), true);
            $order = is_array($order) ? array_values(array_unique(array_filter($order, fn ($key) => in_array($key, $allowedMobileNav, true)))) : [];
            $hidden = is_array($hidden) ? array_values(array_unique(array_filter($hidden, fn ($key) => in_array($key, $allowedMobileNav, true)))) : [];
            $preferences['mobile_navigation'] = ['order' => $order, 'hidden' => $hidden];
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

        $existingMessaging = MessagingConnection::query()->where('user_id', $user->id)->where('provider', 'whatsapp_cloud')->first();
        if ($existingMessaging || filled($request->input('whatsapp_access_token')) || filled($request->input('whatsapp_phone_number_id')) || filled($request->input('whatsapp_recipient'))) {
            $messaging = $existingMessaging ?: new MessagingConnection(['user_id' => $user->id, 'provider' => 'whatsapp_cloud']);
            $credentials = $messaging->credentials ?? [];
            foreach (['access_token' => $request->input('whatsapp_access_token'), 'phone_number_id' => $request->input('whatsapp_phone_number_id')] as $key => $value) {
                if (filled($value)) $credentials[$key] = $value;
            }
            $messaging->fill([
                'credentials' => $credentials,
                'recipient' => preg_replace('/[^0-9]/', '', (string) $request->input('whatsapp_recipient', $messaging->recipient)),
                'enabled' => $request->boolean('whatsapp_enabled'),
            ])->save();
        }

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

    public function updateTheme(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate(['theme' => ['required', 'in:light,dark']]);
        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['theme'] = $validated['theme'];
        $user->forceFill(['preferences' => $preferences])->save();

        return response()->json(['theme' => $validated['theme']]);
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

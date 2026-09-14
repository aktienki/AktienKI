<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profileLocale = data_get($user?->preferences, 'locale');
        $sessionLocale = $request->session()->get('locale');

        // A saved profile preference is the durable, cross-device source of
        // truth for a logged-in user and must win even if a different
        // browser/device session cached an older locale before the profile
        // was changed - previously the session was checked first, so a
        // stale cached value could silently keep overriding a newer profile
        // setting indefinitely. Guests (no profile) keep using whatever the
        // locale-switcher stored in their session.
        $locale = $user && in_array($profileLocale, ['de', 'en'], true)
            ? $profileLocale
            : ($sessionLocale ?? config('app.locale', 'de'));

        if (in_array($locale, ['de', 'en'], true)) {
            app()->setLocale($locale);
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}

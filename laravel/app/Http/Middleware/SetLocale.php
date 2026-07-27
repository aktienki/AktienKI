<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $profileLocale = data_get($request->user()?->preferences, 'locale');
        $locale = $request->session()->get(
            'locale',
            in_array($profileLocale, ['de', 'en'], true)
                ? $profileLocale
                : config('app.locale', 'de'),
        );

        if (in_array($locale, ['de', 'en'], true)) {
            app()->setLocale($locale);
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBetaActivated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! (bool) config('aktienki.beta.enabled', true)) {
            return $next($request);
        }

        $status = data_get($user->meta, 'beta_registration.status');
        // Accounts created before the beta rollout (and non-beta environments) remain valid.
        if ($status === null || $status === 'active') {
            return $next($request);
        }

        return redirect()->route('beta.activation');
    }
}

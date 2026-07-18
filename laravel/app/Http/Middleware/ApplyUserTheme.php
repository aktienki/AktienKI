<?php

namespace App\Http\Middleware;

use App\Enums\UiTheme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->user()?->ui_theme ?? UiTheme::Purple->value;

        if (! in_array($theme, array_column(UiTheme::cases(), 'value'), true)) {
            $theme = UiTheme::Purple->value;
        }

        View::share('activeUiTheme', $theme);

        return $next($request);
    }
}

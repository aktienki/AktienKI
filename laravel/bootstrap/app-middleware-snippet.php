<?php

/*
|--------------------------------------------------------------------------
| In bootstrap/app.php ergänzen
|--------------------------------------------------------------------------
*/

use App\Http\Middleware\ApplyUserTheme;
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        ApplyUserTheme::class,
    ]);
})

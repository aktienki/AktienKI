<?php

namespace App\Http\Controllers;

use App\Enums\UiTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserThemeController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ui_theme' => [
                'required',
                'string',
                Rule::enum(UiTheme::class),
            ],
        ]);

        $request->user()->forceFill([
            'ui_theme' => $validated['ui_theme'],
        ])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Design gespeichert.',
                'ui_theme' => $validated['ui_theme'],
            ]);
        }

        return back()->with('status', 'Design gespeichert.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class TutorialController extends Controller
{
    public function index(): View
    {
        return view('tutorial.index');
    }

    public function complete(Request $request): RedirectResponse
    {
        $preferences = (array) ($request->user()->preferences ?? []);
        data_set($preferences, 'tutorial.completed_at', now()->toIso8601String());
        $request->user()->forceFill(['preferences' => $preferences])->save();

        return back()->with('status', __('Einführung abgeschlossen.'));
    }

    public function restart(Request $request): RedirectResponse
    {
        $preferences = (array) ($request->user()->preferences ?? []);
        data_forget($preferences, 'tutorial.completed_at');
        $request->user()->forceFill(['preferences' => $preferences])->save();

        return redirect()->route('dashboard', ['tutorial' => 1]);
    }

    public function download(): Response
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'de';
        $path = public_path("docs/aktienki-user-guide-{$locale}.pdf");
        abort_unless(is_file($path), 404);

        return response()->download($path, "AktienKI-Handbuch-{$locale}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\BetaAccessRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'max:0'],
            'source' => ['nullable', 'in:contact_form,beta_request'],
        ]);

        if (($validated['source'] ?? null) === 'beta_request') {
            $emailAlreadyRequested = ContactMessage::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
                ->where('meta->source', 'beta_request')
                ->exists();

            if ($emailAlreadyRequested) {
                throw ValidationException::withMessages([
                    'email' => __('Für diese E-Mail-Adresse wurde bereits ein Beta-Zugang angefragt. Dein Platz ist reserviert; nach der Prüfung erhältst du den Registrierungscode per E-Mail.'),
                ]);
            }
        }

        $contactMessage = ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['locale' => app()->getLocale(), 'source' => $validated['source'] ?? 'contact_form'],
        ]);

        if (($validated['source'] ?? null) === 'beta_request') {
            Cache::forget('public.welcome.stats-v3');
            Notification::route('mail', (string) config('aktienki.beta.contact_email'))
                ->notify(new BetaAccessRequestNotification($contactMessage));

            return back()->with('beta_request_success', __('Vielen Dank – dein Platz für die Betaphase ist reserviert. Wir prüfen deine Anfrage und senden dir per E-Mail einen persönlichen Registrierungscode, sobald die Betaphase startet. Wir freuen uns auf deine aktive Mitarbeit.'));
        }

        return back()->with('contact_success', __('Vielen Dank. Deine Nachricht wurde erfolgreich übermittelt.'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => ['locale' => app()->getLocale(), 'source' => 'contact_form'],
        ]);

        return back()->with('contact_success', __('Vielen Dank. Deine Nachricht wurde erfolgreich übermittelt.'));
    }
}

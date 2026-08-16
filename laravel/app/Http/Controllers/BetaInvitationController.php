<?php

namespace App\Http\Controllers;

use App\Models\BetaInvitationLink;
use App\Models\ContactMessage;
use App\Notifications\BetaInvitationCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BetaInvitationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()->is_admin, 403);

        return view('beta.invitations', [
            'links' => BetaInvitationLink::query()->with('creator')->latest()->paginate(20),
            'generatedUrl' => session('beta_invitation_url'),
            'generatedToken' => session('beta_invitation_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()->is_admin, 403);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $token = $this->createUniqueCode();
        $link = BetaInvitationLink::create([
            'created_by' => $request->user()->id,
            'label' => $data['label'] ?? null,
            'token_hash' => hash('sha256', $token),
            'max_uses' => (int) $data['max_uses'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $url = route('register', ['invite' => $token]);

        return redirect()->route('beta.invitations')
            ->with('beta_invitation_url', $url)
            ->with('beta_invitation_token', $token)
            ->with('status', __('Einladungscode erstellt. Der Code wird nicht erneut angezeigt.'));
    }

    public function review(Request $request, ContactMessage $contactMessage): View
    {
        abort_unless((bool) $request->user()->is_admin, 403);
        abort_unless(data_get($contactMessage->meta, 'source') === 'beta_request', 404);

        return view('beta.review', compact('contactMessage'));
    }

    public function approve(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        abort_unless((bool) $request->user()->is_admin, 403);
        abort_unless(data_get($contactMessage->meta, 'source') === 'beta_request', 404);

        if (data_get($contactMessage->meta, 'beta_invitation.sent_at')) {
            return back()->with('status', __('Für diese Anfrage wurde bereits ein Einladungscode versendet.'));
        }

        $token = $this->createUniqueCode();
        $link = BetaInvitationLink::create([
            'created_by' => $request->user()->id,
            'label' => 'Beta-Anfrage #'.$contactMessage->id.' · '.$contactMessage->email,
            'token_hash' => hash('sha256', $token),
            'max_uses' => 1,
            'expires_at' => now()->addDays(14),
        ]);
        $registrationUrl = route('register', ['invite' => $token]);

        Notification::route('mail', [$contactMessage->email => $contactMessage->name])
            ->notify(new BetaInvitationCodeNotification($token, $registrationUrl));

        $meta = (array) $contactMessage->meta;
        data_set($meta, 'beta_invitation', [
            'link_id' => $link->id,
            'sent_at' => now()->toIso8601String(),
            'sent_by' => $request->user()->id,
            'expires_at' => $link->expires_at?->toIso8601String(),
        ]);
        $contactMessage->forceFill([
            'status' => 'handled',
            'handled_at' => now(),
            'meta' => $meta,
        ])->save();

        return back()->with('status', __('Der persönliche Einladungscode wurde an :email gesendet.', ['email' => $contactMessage->email]));
    }

    private function createUniqueCode(): string
    {
        do {
            $token = 'AKI-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        } while (BetaInvitationLink::query()->where('token_hash', hash('sha256', $token))->exists());

        return $token;
    }
}

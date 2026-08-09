<?php

namespace App\Http\Controllers;

use App\Models\BetaInvitationLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $token = Str::random(48);
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
            ->with('status', __('Einladungslink erstellt. Der Token wird nicht erneut angezeigt.'));
    }
}

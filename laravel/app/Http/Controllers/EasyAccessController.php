<?php

namespace App\Http\Controllers;

use App\Models\EasyAccessSubscriber;
use App\Models\SavedPredictionFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EasyAccessController extends Controller
{
    public function index(Request $request): View
    {
        $invite = trim((string) $request->query('invite', ''));
        $invitationValid = $invite !== ''
            && \App\Models\BetaInvitationLink::query()->where('token_hash', hash('sha256', $invite))->first()?->isUsable() === true;

        $strategies = SavedPredictionFilter::query()
            ->with('user:id,name')
            ->whereHas('user', fn ($query) => $query->where('is_admin', true))
            ->orderBy('name')
            ->get(['id', 'user_id', 'name']);

        return view('easy-access', compact('invite', 'invitationValid', 'strategies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'strategy_id' => ['required', 'integer', 'exists:saved_prediction_filters,id'],
            'investment_profile' => ['required', 'in:cautious,balanced,opportunity'],
            'accept_terms' => ['accepted'],
            'invite' => ['nullable', 'string', 'max:128'],
        ]);

        $strategy = SavedPredictionFilter::query()
            ->whereKey($data['strategy_id'])
            ->whereHas('user', fn ($query) => $query->where('is_admin', true))
            ->first();
        if (! $strategy) {
            return back()->withErrors(['strategy_id' => __('Diese Strategie ist derzeit nicht öffentlich verfügbar.')])->withInput();
        }

        EasyAccessSubscriber::query()->updateOrCreate(
            ['saved_prediction_filter_id' => $strategy->id, 'email' => strtolower(trim($data['email']))],
            [
                'investment_profile' => $data['investment_profile'],
                'accepted_terms' => true,
                'accepted_at' => now(),
                'is_active' => true,
                'unsubscribed_at' => null,
            ]
        );

        return redirect()->route('easy-access', $data['invite'] ? ['invite' => $data['invite']] : [])
            ->with('easy_access_subscribed', __('Deine Anmeldung ist gespeichert. Du erhältst ausschließlich E-Mails zu BUY- und SELL-Signalen dieser Strategie – keine Werbung.'));
    }
}

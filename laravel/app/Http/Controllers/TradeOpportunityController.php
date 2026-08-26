<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Models\EntrySignalAlert;
use App\Models\UserTradeOpportunity;
use App\Services\PlanAccessService;
use App\Services\TradeOpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TradeOpportunityController extends Controller
{
    private function authorizePro(Request $request, PlanAccessService $plans): void
    {
        abort_unless($plans->allowsTariff($request->user(), PlanLevel::Pro), 403, __('Diese Funktion ist im Pro-Tarif verfügbar.'));
    }

    public function index(Request $request, PlanAccessService $plans, TradeOpportunityService $service): View
    {
        $this->authorizePro($request, $plans);
        $service->syncForUser($request->user());
        $opportunities = UserTradeOpportunity::query()
            ->where('user_id', $request->user()->id)->where('expires_at', '>', now())
            ->whereIn('status', ['open', 'viewed', 'completed'])
            ->with(['instrument:id,symbol,name,country,sector,currency', 'prediction:id,prediction_time'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'viewed' THEN 1 ELSE 2 END")
            ->orderBy('expires_at')->paginate(30);

        return view('opportunities.index', compact('opportunities'));
    }

    public function open(Request $request, UserTradeOpportunity $opportunity, PlanAccessService $plans): RedirectResponse
    {
        $this->authorizePro($request, $plans);
        abort_unless($opportunity->user_id === $request->user()->id, 404);
        if ($opportunity->status === 'open') $opportunity->update(['status' => 'viewed', 'viewed_at' => now()]);
        return redirect()->route('stocks.show', ['symbol' => $opportunity->instrument->symbol, 'prediction' => $opportunity->prediction_id, 'return_to' => route('opportunities.index')]);
    }

    public function update(Request $request, UserTradeOpportunity $opportunity, PlanAccessService $plans): RedirectResponse
    {
        $this->authorizePro($request, $plans);
        abort_unless($opportunity->user_id === $request->user()->id, 404);
        $data = $request->validate(['status' => ['nullable', 'in:open,viewed,completed'], 'notify_on_buy' => ['nullable', 'boolean']]);
        if (isset($data['status'])) {
            $data['viewed_at'] = in_array($data['status'], ['viewed', 'completed'], true) ? ($opportunity->viewed_at ?: now()) : null;
            $data['completed_at'] = $data['status'] === 'completed' ? now() : null;
        }
        $opportunity->update($data);
        if (array_key_exists('notify_on_buy', $data)) {
            if ((bool) $data['notify_on_buy']) {
                EntrySignalAlert::query()->updateOrCreate(
                    ['user_id' => $request->user()->id, 'instrument_id' => $opportunity->instrument_id, 'status' => 'active'],
                    ['source_prediction_id' => $opportunity->prediction_id, 'initial_signal' => 'WAIT', 'notification_mode' => 'buy_only'],
                );
            } else {
                EntrySignalAlert::query()->where('user_id', $request->user()->id)
                    ->where('instrument_id', $opportunity->instrument_id)->where('status', 'active')
                    ->update(['status' => 'disabled']);
            }
        }
        return back()->with('status', __('Chance aktualisiert.'));
    }

    public function destroy(Request $request, UserTradeOpportunity $opportunity, PlanAccessService $plans): RedirectResponse
    {
        $this->authorizePro($request, $plans);
        abort_unless($opportunity->user_id === $request->user()->id, 404);
        // Keep a temporary tombstone until expiry so the same prediction is not
        // recreated by the next automatic synchronization.
        $opportunity->update(['status' => 'dismissed', 'completed_at' => now()]);
        return back()->with('status', __('Chance entfernt.'));
    }
}

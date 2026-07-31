<?php

namespace App\Http\Controllers;

use App\Models\SavedPredictionFilter;
use App\Services\SavedFilterLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SavedPredictionFilterController extends Controller
{
    public const FILTER_KEYS = [
        'q', 'country', 'exchange', 'sector', 'ai_type', 'model', 'quality_tier', 'signal',
        'score_min', 'confidence_min', 'drawdown_max', 'profit_factor_min', 'volatility_max',
        'pe_max', 'dividend_yield_min', 'market_cap_min', 'revenue_growth_min', 'hit_rate_min',
    ];

    public const FILTER_DEFAULTS = [
        'q' => '', 'country' => '', 'exchange' => '', 'sector' => '', 'ai_type' => '',
        'model' => '', 'quality_tier' => '', 'signal' => '',
        'score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => 50,
        'profit_factor_min' => 0, 'volatility_max' => 100,
        'pe_max' => 100, 'dividend_yield_min' => 0, 'market_cap_min' => 0,
        'revenue_growth_min' => -50, 'hit_rate_min' => 0,
    ];

    public function index(Request $request, SavedFilterLimitService $limits): View
    {
        $savedFilters = $request->user()->savedPredictionFilters()->with(['portfolio', 'watchlist'])->orderByDesc('updated_at')->get();
        $savedFilterLimit = $limits->limitFor($request->user());
        $portfolios = $request->user()->portfolios()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $watchlists = $request->user()->watchlists()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $emailServiceEnabled = (bool) data_get($request->user()->preferences, 'email_service', true);
        $returnFilters = (array) $request->session()->get('setup_filter_state', self::FILTER_DEFAULTS);

        return view('setup.saved-filters', compact('savedFilters', 'savedFilterLimit', 'portfolios', 'watchlists', 'emailServiceEnabled', 'returnFilters'));
    }

    public function store(Request $request, SavedFilterLimitService $limits): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'saved_filter' => ['nullable', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $editedFilter = isset($validated['saved_filter'])
            ? $user->savedPredictionFilters()->whereKey($validated['saved_filter'])->firstOrFail()
            : null;
        $existing = SavedPredictionFilter::query()
            ->where('user_id', $user->id)
            ->where('name', trim($validated['name']))
            ->when($editedFilter, fn ($query) => $query->whereKeyNot($editedFilter->id))
            ->first();

        if ($existing !== null) {
            return back()->withErrors(['saved_filter' => __('Ein Filter mit diesem Namen existiert bereits.')]);
        }

        if ($editedFilter === null && $user->savedPredictionFilters()->count() >= $limits->limitFor($user)) {
            return back()->withErrors(['saved_filter' => __('Das Limit für gespeicherte Filter in deinem Tarif ist erreicht.')]);
        }

        $filters = collect(self::FILTER_DEFAULTS)
            ->mapWithKeys(fn ($default, string $key) => [$key => $request->input($key, $default)])
            ->all();
        if ($editedFilter) {
            $editedFilter->update([
                'name' => trim($validated['name']),
                'filters' => $filters,
            ]);
        } else {
            $user->savedPredictionFilters()->create([
                'name' => trim($validated['name']),
                'filters' => $filters,
            ]);
        }

        return redirect()->route('setup.filter', $filters)
            ->with('status', __('Filter gespeichert.'));
    }

    public function destroy(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        $savedFilter->delete();

        return back()->with('status', __('Filter gelöscht.'));
    }

    public function update(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $name = trim($validated['name']);
        $duplicate = SavedPredictionFilter::query()
            ->where('user_id', $request->user()->id)
            ->where('name', $name)
            ->whereKeyNot($savedFilter->id)
            ->exists();
        if ($duplicate) {
            return back()->withErrors(['name_'.$savedFilter->id => __('Ein Filter mit diesem Namen existiert bereits.')]);
        }
        $savedFilter->update(['name' => $name]);

        return back()->with('status', __('Filter umbenannt.'));
    }

    public function link(Request $request, SavedPredictionFilter $savedFilter): RedirectResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 404);
        $validated = $request->validate([
            'linked_target' => ['required', 'string', 'max:40'],
            'email_notification_enabled' => ['required', 'boolean'],
        ]);
        $target = (string) $validated['linked_target'];
        if ((bool) $validated['email_notification_enabled'] && ! (bool) data_get($request->user()->preferences, 'email_service', true)) {
            return back()->withErrors(['email_notification' => __('Aktiviere zuerst den E-Mail-Service in deinem Profil.')]);
        }
        $portfolioId = null;
        $watchlistId = null;
        if (str_starts_with($target, 'portfolio:')) {
            $portfolioId = (int) substr($target, strlen('portfolio:'));
            abort_unless($request->user()->portfolios()->where('active', true)->whereKey($portfolioId)->exists(), 404);
        } elseif (str_starts_with($target, 'watchlist:')) {
            $watchlistId = (int) substr($target, strlen('watchlist:'));
            abort_unless($request->user()->watchlists()->where('active', true)->whereKey($watchlistId)->exists(), 404);
        } else {
            abort_unless($target === 'none', 422);
        }
        $savedFilter->update([
            'portfolio_id' => $portfolioId,
            'watchlist_id' => $watchlistId,
            'email_notification_enabled' => (bool) $validated['email_notification_enabled'],
        ]);

        return back()->with('status', __('Verknüpfung gespeichert.'));
    }
}

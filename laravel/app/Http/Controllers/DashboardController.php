<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Enums\PlanLevel;
use App\Services\PersonalizedSignalService;
use App\Services\PlanAccessService;

class DashboardController extends Controller
{
    public function updateLayout(Request $request): JsonResponse
    {
        abort_unless(app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro), 403);

        $allowed = [
            'paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'best-buy', 'best-wait',
            'watchlist-screener', 'predictions', 'smart-screener', 'market-report', 'stock-comparison',
        ];
        $validated = $request->validate([
            'tiles' => ['required', 'array', 'min:1', 'max:9'],
            'tiles.*' => ['required', 'string', 'distinct', 'in:'.implode(',', $allowed)],
        ]);

        $preferences = (array) ($request->user()->preferences ?? []);
        data_set($preferences, 'dashboard.personal_tiles', array_values($validated['tiles']));
        $request->user()->forceFill(['preferences' => $preferences])->save();

        return response()->json(['saved' => true, 'tiles' => $validated['tiles']]);
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $planAccess = app(PlanAccessService::class);
        $canUsePlus = $planAccess->allowsTariff($user, PlanLevel::Plus);
        $canUsePro = $planAccess->allowsTariff($user, PlanLevel::Pro);
        $canManageMessages = $canUsePro;
        $riskProfile = (string) data_get($user->meta, 'risk_profile.level', data_get($user->risk_profile, 'level', 'normal'));
        $strategyPortfolio = $this->strategyPortfolio((int) $user->id);
        $overview = [
            'paper_depots' => $user->portfolios()
                ->where('type', 'paper')
                ->where('active', true)
                ->when($strategyPortfolio, fn ($query) => $query->whereKeyNot($strategyPortfolio->id))
                ->count(),
            'watchlists' => $user->watchlists()->where('active', true)->count(),
            'strategies' => $user->savedPredictionFilters()->count(),
            'labels' => \App\Models\SmartSelectionLabel::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->count(),
        ];
        $communityOverview = [
            'posts' => \App\Models\CommunityPost::query()->where('is_published', true)->count(),
            'members' => \App\Models\CommunityPost::query()->where('is_published', true)->distinct('user_id')->count('user_id'),
            'recent' => \App\Models\CommunityPost::query()->where('is_published', true)->where('created_at', '>=', now()->subDays(7))->count(),
        ];
        $marketSituation = Cache::remember('dashboard.personal.market-situation', now()->addMinutes(2), fn () =>
            DB::table('daily_market_ai_analyses')
                ->orderByDesc('analysis_date')
                ->orderByDesc('id')
                ->first([
                    'analysis_date', 'headline', 'executive_summary', 'market_outlook',
                    'confidence', 'risk_level',
                ]));
        $continentPredictions = $this->continentPredictions();
        $recentSignalOverview = $this->recentSignalOverview();
        $latestStockPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->where('prediction_time', '>=', now()->subDays(2))
            ->groupBy('instrument_id');
        $personalizedSignalSql = app(PersonalizedSignalService::class)->sql('prediction', $user);
        $topStockQuery = fn () => DB::table('predictions as prediction')
            ->joinSub($latestStockPredictions, 'latest_prediction', fn ($join) =>
                $join->on('latest_prediction.prediction_id', '=', 'prediction.id'))
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->orderByRaw('COALESCE(prediction.ai_score, prediction.prediction_score, 0) DESC')
            ->orderByRaw('COALESCE(prediction.horizon_fusion_consensus_return, prediction.market_return_20d, 0) DESC');
        $topStockToday = $topStockQuery()
            ->whereRaw("UPPER({$personalizedSignalSql}) = ?", ['BUY'])
            ->first(['prediction.id as prediction_id', 'prediction.ai_score', 'prediction.prediction_score', 'prediction.horizon_fusion_consensus_return', 'prediction.market_return_20d', 'instrument.symbol', 'instrument.name']);
        $topWaitStock = $topStockQuery()
            ->whereRaw("UPPER({$personalizedSignalSql}) = ?", ['WAIT'])
            ->first(['prediction.id as prediction_id', 'prediction.ai_score', 'prediction.prediction_score', 'prediction.horizon_fusion_consensus_return', 'prediction.market_return_20d', 'instrument.symbol', 'instrument.name']);
        $messageReminders = collect()
            ->merge(
                DB::table('prediction_purchase_reminders as reminder')
                    ->join('instruments as instrument', 'instrument.id', '=', 'reminder.instrument_id')
                    ->where('reminder.user_id', $user->id)
                    ->whereIn('reminder.status', ['active', 'disabled'])
                    ->orderBy('reminder.remind_on')
                    ->limit(6)
                    ->get(['reminder.id', 'reminder.intent', 'reminder.horizon_days', 'reminder.remind_on', 'reminder.status', 'instrument.symbol', 'instrument.name'])
                    ->map(fn (object $reminder): array => [
                        'id' => $reminder->id,
                        'type' => 'prediction',
                        'symbol' => $reminder->symbol,
                        'name' => $reminder->name,
                        'label' => $reminder->intent === 'purchased' ? __('Kaufauswertung') : __('Kauferinnerung'),
                        'schedule' => \Illuminate\Support\Carbon::parse($reminder->remind_on)->format('d.m.Y'),
                        'active' => $reminder->status === 'active',
                    ])
            )
            ->merge(
                DB::table('entry_signal_alerts as alert')
                    ->join('instruments as instrument', 'instrument.id', '=', 'alert.instrument_id')
                    ->where('alert.user_id', $user->id)
                    ->whereIn('alert.status', ['active', 'disabled'])
                    ->latest('alert.created_at')
                    ->limit(6)
                    ->get(['alert.id', 'alert.notification_mode', 'alert.status', 'instrument.symbol', 'instrument.name'])
                    ->map(fn (object $alert): array => [
                        'id' => $alert->id,
                        'type' => 'signal',
                        'symbol' => $alert->symbol,
                        'name' => $alert->name,
                        'label' => __('Signalalarm'),
                        'schedule' => $alert->notification_mode === 'wait_or_buy' ? __('WAIT oder BUY') : __('Nur BUY'),
                        'active' => $alert->status === 'active',
                    ])
            )
            ->sortBy(fn (array $reminder): string => $reminder['symbol'].'-'.$reminder['label'])
            ->values();

        return view('dashboard', compact(
            'riskProfile', 'strategyPortfolio', 'overview', 'marketSituation', 'continentPredictions',
            'recentSignalOverview',
            'communityOverview',
            'messageReminders',
            'canManageMessages',
            'canUsePlus',
            'canUsePro',
            'topStockToday',
            'topWaitStock',
        ));
    }

    private function strategyPortfolio(int $userId): mixed
    {
        $portfolios = \App\Models\Portfolio::query()
            ->where('user_id', $userId)
            ->where('type', 'paper')
            ->where('active', true)
            ->whereHas('strategies')
            ->with(['cashAccount', 'strategies:id,name', 'positions'])
            ->get();

        $portfolio = $portfolios->first(fn ($candidate): bool =>
            (bool) data_get($candidate->meta, 'automation.live_enabled', false))
            ?? $portfolios->first();

        if (! $portfolio) {
            return null;
        }

        $positionsValue = $portfolio->positions->sum(fn ($position): float =>
            (float) $position->quantity * (float) ($position->current_price ?? $position->average_buy_price));
        $cash = (float) ($portfolio->cashAccount?->balance ?? 0);
        $initialCapital = max(0.0, (float) data_get($portfolio->meta, 'automation.initial_capital', 0));
        $totalValue = $positionsValue + $cash;

        $portfolio->setAttribute('dashboard_positions_value', $positionsValue);
        $portfolio->setAttribute('dashboard_cash', $cash);
        $portfolio->setAttribute('dashboard_total_value', $totalValue);
        $portfolio->setAttribute('dashboard_performance', $initialCapital > 0
            ? (($totalValue - $initialCapital) / $initialCapital) * 100
            : 0.0);

        return $portfolio;
    }

    private function continentPredictions(): array
    {
        return Cache::remember('dashboard.personal.continent-predictions-v2', now()->addMinutes(2), function (): array {
            $latestPredictionIds = DB::table('predictions')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id');
            $rows = DB::table('predictions as prediction')
                ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) =>
                    $join->on('latest_prediction.prediction_id', '=', 'prediction.id'))
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->get(['instrument.country', 'prediction.signal', 'prediction.prediction_time']);

            $continents = [
                'europe' => ['key' => 'europe', 'label' => __('Europa')],
                'north-america' => ['key' => 'north-america', 'label' => __('Nordamerika')],
                'asia-pacific' => ['key' => 'asia-pacific', 'label' => __('Asien-Pazifik')],
                'africa' => ['key' => 'africa', 'label' => __('Afrika')],
            ];

            foreach ($continents as $key => $continent) {
                $continentRows = $rows->filter(fn (object $row): bool => $this->continentFor($row->country) === $key);
                $signals = $continentRows->countBy(fn (object $row): string => strtoupper((string) $row->signal));
                $continents[$key] += [
                    'count' => $continentRows->count(),
                    'latest_at' => $continentRows->max('prediction_time'),
                    'buy' => (int) $signals->get('BUY', 0),
                    'watch' => (int) $signals->get('WATCH', 0),
                    'hold' => (int) $signals->get('HOLD', 0),
                    'sell' => (int) $signals->get('SELL', 0),
                ];
            }

            return $continents;
        });
    }

    private function continentFor(?string $country): string
    {
        $country = strtoupper(trim((string) $country));

        return match (true) {
            in_array($country, ['US', 'USA', 'UNITED STATES', 'CA', 'CAN', 'CANADA'], true) => 'north-america',
            in_array($country, ['JP', 'JPN', 'JAPAN', 'CN', 'CHN', 'CHINA', 'HK', 'HKG', 'HONG KONG', 'AU', 'AUS', 'AUSTRALIA'], true) => 'asia-pacific',
            in_array($country, ['ZA', 'ZAF', 'SOUTH AFRICA'], true) => 'africa',
            default => 'europe',
        };
    }

    private function recentSignalOverview(): array
    {
        return Cache::remember('dashboard.personal.recent-signal-overview-v3', now()->addMinutes(2), function (): array {
            $latestIds = DB::table('predictions')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id');
            $recommendations = DB::table('predictions as prediction')
                ->joinSub($latestIds, 'latest_prediction', fn ($join) =>
                    $join->on('latest_prediction.prediction_id', '=', 'prediction.id'))
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->where('prediction.prediction_time', '>=', now()->subHours(48))
                ->whereIn(DB::raw('UPPER(prediction.signal)'), ['BUY', 'SELL'])
                ->orderByDesc('prediction.prediction_time')
                ->get(['instrument.symbol', 'prediction.signal', 'prediction.prediction_time']);

            $history = DB::table('predictions as prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->where('prediction.prediction_time', '>=', now()->subDays(7))
                ->select([
                    'prediction.id', 'prediction.instrument_id', 'prediction.trained_model_id',
                    'prediction.prediction_time',
                ])
                ->selectRaw('UPPER(prediction.signal) AS current_signal');
            $sequenced = DB::query()
                ->fromSub($history, 'history')
                ->select('history.*')
                ->selectRaw('LAG(history.current_signal) OVER (
                    PARTITION BY history.instrument_id, COALESCE(history.trained_model_id, 0)
                    ORDER BY history.prediction_time, history.id
                ) AS previous_signal');
            $transitions = DB::query()
                ->fromSub($sequenced, 'signal_transition')
                ->whereNotNull('previous_signal')
                ->whereColumn('previous_signal', '<>', 'current_signal')
                ->where('prediction_time', '>=', now()->subHours(48))
                ->groupBy('previous_signal', 'current_signal')
                ->orderByDesc('transition_count')
                ->get([
                    'previous_signal', 'current_signal', DB::raw('COUNT(*) AS transition_count'),
                ]);

            return [
                'buy_count' => $recommendations->where('signal', 'BUY')->count(),
                'sell_count' => $recommendations->where('signal', 'SELL')->count(),
                'buy_symbols' => $recommendations->where('signal', 'BUY')->take(4)->pluck('symbol')->all(),
                'sell_symbols' => $recommendations->where('signal', 'SELL')->take(4)->pluck('symbol')->all(),
                'transition_count' => (int) $transitions->sum('transition_count'),
                'transitions' => $transitions->take(3)->map(fn (object $transition): array => [
                    'from' => $transition->previous_signal,
                    'to' => $transition->current_signal,
                    'count' => (int) $transition->transition_count,
                ])->all(),
            ];
        });
    }

}

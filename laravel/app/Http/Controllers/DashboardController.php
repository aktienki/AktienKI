<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\PlanLevel;
use App\Services\PersonalizedSignalService;
use App\Services\PlanAccessService;
use App\Services\StockRiskClassificationService;
use App\Models\User;

class DashboardController extends Controller
{
    public function updateLayout(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro), 403);

        $allowed = [
            'paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'best-buy', 'best-wait',
            'watchlist-screener', 'predictions', 'smart-screener', 'market-report', 'stock-comparison', 'mobile-view',
            'news',
        ];
        $validated = $request->validate([
            'tiles' => ['required', 'array', 'min:1', 'max:12'],
            'tiles.*' => ['required', 'string', 'distinct', 'in:'.implode(',', $allowed)],
        ]);
        $preferences = (array) ($request->user()->preferences ?? []);
        data_set($preferences, 'dashboard.personal_tiles', array_values($validated['tiles']));
        $request->user()->forceFill(['preferences' => $preferences])->save();

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'tiles' => $validated['tiles']]);
        }

        return redirect()->route('dashboard')->with('status', __('Der persönliche Bereich wurde gespeichert.'));
    }

    public function updateCardLayout(Request $request): JsonResponse
    {
        abort_unless(app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro), 403);

        $allowedCards = ['strategy', 'personal', 'community', 'market', 'market-summary', 'signal-cockpit', 'models', 'signals', 'earnings', 'schedule', 'mobile-view'];
        $validated = $request->validate([
            'cards' => ['required', 'array', 'min:1', 'max:11'],
            'cards.*.id' => ['required', 'string', 'distinct', 'in:'.implode(',', $allowedCards)],
            'cards.*.width' => ['required', 'integer', 'between:1,3'],
            'cards.*.height' => ['required', 'integer', 'between:1,6'],
        ]);
        $minimumHeights = [
            'strategy' => 1, 'community' => 2,
            'personal' => 6, 'market' => 2, 'signal-cockpit' => 6,
            'models' => 2, 'signals' => 2, 'earnings' => 6, 'market-summary' => 1, 'schedule' => 2, 'mobile-view' => 1,
        ];
        $fixedDimensions = [
            'strategy' => ['width' => 1, 'height' => 1],
            'community' => ['width' => 1, 'height' => 2],
            'personal' => ['width' => 1, 'height' => 6],
            'market' => ['width' => 1, 'height' => 2],
            'models' => ['width' => 1, 'height' => 2],
            'signals' => ['width' => 1, 'height' => 2],
            'earnings' => ['width' => 1, 'height' => 6],
            'market-summary' => ['width' => 1, 'height' => 1],
            'signal-cockpit' => ['width' => 1, 'height' => 6],
            'mobile-view' => ['width' => 1, 'height' => 1],
        ];
        abort_if(collect($validated['cards'])->contains(function (array $card) use ($fixedDimensions): bool {
            $fixed = $fixedDimensions[$card['id']] ?? null;
            return $fixed && ($card['width'] !== $fixed['width'] || $card['height'] !== $fixed['height']);
        }), 422, __('Eine oder mehrere Karten besitzen eine feste Größe.'));
        abort_if(collect($validated['cards'])->contains(
            fn (array $card): bool => $card['height'] < $minimumHeights[$card['id']]
        ), 422, __('Eine oder mehrere Karten sind zu niedrig für ihren Mindestinhalt.'));
        $usedGridArea = collect($validated['cards'])->sum(
            fn (array $card): int => $card['width'] * $card['height']
        );
        abort_if($usedGridArea > 27, 422, __('Die gewählten Kartengrößen passen nicht in das Raster mit drei Spalten und neun Zeilen.'));

        $preferences = (array) ($request->user()->preferences ?? []);
        data_set($preferences, 'dashboard.cards', array_values($validated['cards']));
        $request->user()->forceFill(['preferences' => $preferences])->save();

        return response()->json(['saved' => true, 'cards' => $validated['cards']]);
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $planAccess = app(PlanAccessService::class);
        $canUsePlus = $planAccess->allowsTariff($user, PlanLevel::Plus);
        $canUsePro = $planAccess->allowsTariff($user, PlanLevel::Pro);
        $canManageMessages = $canUsePro;
        $companyNewsEnabled = (bool) data_get($user->preferences, 'dashboard_company_news_enabled', true);
        $scheduleEmailsEnabled = (bool) data_get($user->preferences, 'dashboard_schedule_emails_enabled', true);
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
            'news' => \App\Models\News::query()->where('published_at', '>=', now()->subHours(24))->count(),
        ];
        $communityOverview = [
            'posts' => \App\Models\CommunityPost::query()->where('is_published', true)->count(),
            'members' => \App\Models\CommunityPost::query()->where('is_published', true)->distinct('user_id')->count('user_id'),
            'recent' => \App\Models\CommunityPost::query()->where('is_published', true)->where('created_at', '>=', now()->subDays(7))->count(),
            'news' => \App\Models\News::query()->where('published_at', '>=', now()->subDays(7))->count(),
        ];
        $marketSituation = Cache::remember('dashboard.personal.market-situation', now()->addMinutes(2), fn () =>
            DB::table('daily_market_ai_analyses')
                ->orderByDesc('analysis_date')
                ->orderByDesc('id')
                ->first([
                    'analysis_date', 'headline', 'executive_summary', 'market_outlook',
                    'confidence', 'risk_level',
                ]));
        $marketFactorSnapshot = Cache::remember('dashboard.personal.market-factors', now()->addMinutes(5), function () {
            if (! Schema::hasTable('market_factor_snapshots')) {
                return ['current' => collect(), 'history' => collect()];
            }

            $latestDate = DB::table('market_factor_snapshots')->max('trading_date');
            if (! $latestDate) {
                return ['current' => collect(), 'history' => collect()];
            }

            $current = DB::table('market_factor_snapshots')
                ->whereDate('trading_date', $latestDate)
                ->where(function ($query): void {
                    $query->where('scope_type', 'market')
                        ->orWhere(fn ($nested) => $nested->whereIn('scope_type', ['sector', 'index'])->where('scope_key', '__aggregate__'));
                })
                ->get()
                ->keyBy('scope_type');

            $history = DB::table('market_factor_snapshots')
                ->where('scope_type', 'market')
                ->where('scope_key', '__aggregate__')
                ->whereDate('trading_date', '>=', now()->subDays(20)->toDateString())
                ->orderByDesc('trading_date')
                ->limit(14)
                ->get(['trading_date', 'trend_score', 'timing_score'])
                ->reverse()
                ->values();

            return ['current' => $current, 'history' => $history];
        });
        $continentPredictions = $this->continentPredictions();
        $recentSignalOverview = $this->recentSignalOverview();
        $signalCockpit = $this->signalCockpit();
        $profileUniverseStats = $this->profileUniverseStats($user);
        $recentEarnings = Cache::remember('dashboard.personal.recent-earnings', now()->addMinutes(15), fn () =>
            DB::table('instrument_earnings as earning')
                ->join('instruments as instrument', 'instrument.id', '=', 'earning.instrument_id')
                ->where('instrument.type', 'stock')->where('instrument.is_active', true)
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
                ->where('instrument.is_german_tradeable', true)->whereNull('instrument.deleted_at')
                ->whereNotNull('earning.eps_actual')
                ->where('earning.earnings_date', '>=', today()->subDays(120))
                ->orderByDesc('earning.earnings_date')->orderByDesc('earning.id')->limit(2)
                ->get(['instrument.symbol', 'instrument.name', 'earning.earnings_date', 'earning.period', 'earning.eps_estimate', 'earning.eps_actual', 'earning.surprise_percent'])
        );
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
            ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->orderByRaw('COALESCE(prediction.ai_score, prediction.prediction_score, 0) DESC')
            ->orderByRaw('COALESCE(prediction.horizon_fusion_consensus_return, prediction.market_return_20d, 0) DESC');
        $topStockToday = $topStockQuery()
            ->whereRaw("UPPER({$personalizedSignalSql}) = ?", ['BUY'])
            ->first(['prediction.id as prediction_id', 'prediction.ai_score', 'prediction.prediction_score', 'prediction.horizon_fusion_consensus_return', 'prediction.market_return_20d', 'instrument.symbol', 'instrument.name']);
        $topWatchStock = $topStockQuery()
            ->whereRaw("UPPER({$personalizedSignalSql}) = ?", ['WATCH'])
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
                        'label' => $reminder->intent === 'purchased' ? __('SELL-Überwachung') : __('Kauferinnerung'),
                        'schedule' => __('E-Mail').' · '.\Illuminate\Support\Carbon::parse($reminder->remind_on)->format('d.m.Y'),
                        'date' => \Illuminate\Support\Carbon::parse($reminder->remind_on)->format('Y-m-d'),
                        'sort_at' => (string) $reminder->remind_on,
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
                        'label' => $alert->notification_mode === 'wait_or_buy' ? __('WAIT-Einstellung') : __('BUY-Einstellung'),
                        'schedule' => __('E-Mail').' · '.($alert->notification_mode === 'wait_or_buy' ? 'WAIT → BUY' : __('Nur BUY')),
                        'date' => null,
                        'sort_at' => '0000-00-00',
                        'active' => $alert->status === 'active',
                    ])
            )
            ->sortBy(fn (array $reminder): string => $reminder['symbol'].'-'.$reminder['label'])
            ->values();
        $corporateScheduleItems = $companyNewsEnabled
            ? DB::table('corporate_events as event')
                ->join('instruments as instrument', 'instrument.id', '=', 'event.instrument_id')
                ->where('instrument.type', 'stock')->where('instrument.is_active', true)
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
                ->where('instrument.is_german_tradeable', true)->whereNull('instrument.deleted_at')
                ->where('event.event_type', 'earnings')->whereBetween('event.event_date', [today(), today()->addDays(90)])
                ->orderBy('event.event_date')->limit(12)
                ->get(['event.id', 'event.event_date', 'event.event_time', 'event.eps_estimate', 'instrument.symbol', 'instrument.name'])
                ->map(fn (object $event): array => [
                    'type' => 'earnings', 'symbol' => $event->symbol, 'name' => $event->name,
                    'label' => __('Quartalszahlen'),
                    'schedule' => \Illuminate\Support\Carbon::parse($event->event_date)->format('d.m.Y').($event->event_time ? ' · '.__($event->event_time) : ''),
                    'sort_at' => (string) $event->event_date,
                ])
            : collect();
        $activeMessageScheduleItems = $scheduleEmailsEnabled
            ? $messageReminders->where('active', true)->sortBy('sort_at')->values()
            : collect();
        $dashboardScheduleItems = $activeMessageScheduleItems
            ->concat($corporateScheduleItems->take(max(0, 6 - $activeMessageScheduleItems->count())))
            ->take(6)
            ->values();

        return view('dashboard', compact(
            'riskProfile', 'strategyPortfolio', 'overview', 'marketSituation', 'continentPredictions',
            'marketFactorSnapshot',
            'recentSignalOverview',
            'signalCockpit',
            'profileUniverseStats',
            'recentEarnings',
            'communityOverview',
            'messageReminders',
            'dashboardScheduleItems',
            'companyNewsEnabled',
            'scheduleEmailsEnabled',
            'canManageMessages',
            'canUsePlus',
            'canUsePro',
            'topStockToday',
            'topWatchStock',
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
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
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
        return Cache::remember('dashboard.personal.recent-signal-overview-v5', now()->addMinutes(2), function (): array {
            $latestIds = DB::table('predictions')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id');
            $recommendations = DB::table('predictions as prediction')
                ->joinSub($latestIds, 'latest_prediction', fn ($join) =>
                    $join->on('latest_prediction.prediction_id', '=', 'prediction.id'))
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->where('prediction.prediction_time', '>=', now()->subHours(48))
                ->whereIn(DB::raw('UPPER(prediction.signal)'), ['BUY', 'WAIT', 'HOLD', 'SELL'])
                ->orderByDesc('prediction.prediction_time')
                ->get(['instrument.symbol', 'prediction.prediction_time', DB::raw('UPPER(prediction.signal) AS signal')]);

            return [
                'buy_count' => $recommendations->where('signal', 'BUY')->count(),
                'wait_count' => $recommendations->where('signal', 'WAIT')->count(),
                'hold_count' => $recommendations->where('signal', 'HOLD')->count(),
                'sell_count' => $recommendations->where('signal', 'SELL')->count(),
                'buy_symbols' => $recommendations->where('signal', 'BUY')->take(4)->pluck('symbol')->all(),
                'wait_symbols' => $recommendations->where('signal', 'WAIT')->take(4)->pluck('symbol')->all(),
                'hold_symbols' => $recommendations->where('signal', 'HOLD')->take(4)->pluck('symbol')->all(),
                'sell_symbols' => $recommendations->where('signal', 'SELL')->take(4)->pluck('symbol')->all(),
            ];
        });
    }

    public function signalCockpit(): array
    {
        return Cache::remember('dashboard.personal.signal-cockpit-v7', now()->addMinutes(5), function (): array {
            $latestPredictionIds = DB::table('predictions')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id');
            $topScores = DB::table('predictions as prediction')
                ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) => $join->on('latest_prediction.prediction_id', '=', 'prediction.id'))
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')->where('instrument.is_active', true)->whereNull('instrument.deleted_at')
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
                ->orderByRaw('COALESCE(prediction.ai_score, prediction.prediction_score, 0) DESC')
                ->limit(5)
                ->select(['instrument.symbol', 'instrument.name', 'prediction.id as prediction_id', 'prediction.signal', 'prediction.ai_score', 'prediction.prediction_score', 'prediction.current_price'])
                ->selectRaw('(SELECT hp.predicted_price_5d FROM predictions hp WHERE hp.instrument_id = prediction.instrument_id AND hp.prediction_horizon_minutes = 7200 AND hp.predicted_price_5d IS NOT NULL ORDER BY hp.prediction_time DESC NULLS LAST, hp.id DESC LIMIT 1) AS horizon_target_5d')
                ->selectRaw('(SELECT hp.predicted_price_10d FROM predictions hp WHERE hp.instrument_id = prediction.instrument_id AND hp.prediction_horizon_minutes = 14400 AND hp.predicted_price_10d IS NOT NULL ORDER BY hp.prediction_time DESC NULLS LAST, hp.id DESC LIMIT 1) AS horizon_target_10d')
                ->selectRaw('(SELECT hp.predicted_price_15d FROM predictions hp WHERE hp.instrument_id = prediction.instrument_id AND hp.prediction_horizon_minutes = 21600 AND hp.predicted_price_15d IS NOT NULL ORDER BY hp.prediction_time DESC NULLS LAST, hp.id DESC LIMIT 1) AS horizon_target_15d')
                ->selectRaw('(SELECT hp.predicted_price_20d FROM predictions hp WHERE hp.instrument_id = prediction.instrument_id AND hp.prediction_horizon_minutes = 28800 AND hp.predicted_price_20d IS NOT NULL ORDER BY hp.prediction_time DESC NULLS LAST, hp.id DESC LIMIT 1) AS horizon_target_20d')
                ->get()
                ->map(fn (object $row): array => [
                    'symbol' => $row->symbol, 'name' => $row->name, 'prediction_id' => $row->prediction_id,
                    'signal' => strtoupper((string) $row->signal),
                    'score' => \App\Support\AiScore::toTen(is_numeric($row->ai_score) ? $row->ai_score : $row->prediction_score),
                    'horizon_signals' => collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($row): array {
                        $current = is_numeric($row->current_price) ? (float) $row->current_price : null;
                        $field = "horizon_target_{$days}d";
                        $target = is_numeric($row->{$field}) ? (float) $row->{$field} : null;
                        $return = $current && $target !== null ? (($target / $current) - 1) * 100 : null;
                        return [$days => ['return' => $return, 'signal' => $return === null ? null : ($return > .15 ? 'UP' : ($return < -.15 ? 'DOWN' : 'FLAT'))]];
                    })->all(),
                ])->all();

            $predictionTradingDays = DB::table('predictions')
                ->selectRaw('prediction_time::date AS trading_day')->distinct()
                ->orderByDesc('trading_day')->limit(30)->pluck('trading_day');
            $predictionHistory = DB::table('predictions as prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->when($predictionTradingDays->isNotEmpty(), fn ($query) => $query->whereDate('prediction.prediction_time', '>=', $predictionTradingDays->last()))
                ->where('instrument.type', 'stock')->where('instrument.is_active', true)->whereNull('instrument.deleted_at')
                ->where(fn ($query) => $query->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep'))
                ->select(['prediction.id', 'prediction.instrument_id', 'prediction.prediction_time', 'prediction.signal', 'prediction.ai_score', 'prediction.prediction_score',
                    'prediction.current_price', 'prediction.predicted_price_5d', 'prediction.predicted_price_10d', 'prediction.predicted_price_15d', 'prediction.predicted_price_20d',
                    'instrument.symbol', 'instrument.name'])
                ->get()->groupBy('instrument_id');
            $signalChanges = $predictionHistory->flatMap(function ($rows) {
                return $rows->sortBy('prediction_time')->values()->map(function (object $row, int $index) use ($rows) {
                    $ordered = $rows->sortBy('prediction_time')->values();
                    $previous = $ordered->get($index - 1);
                    if (! $previous || strtoupper((string) $previous->signal) === strtoupper((string) $row->signal)) return null;
                    return [
                        'symbol' => $row->symbol, 'name' => $row->name, 'prediction_id' => $row->id,
                        'from' => strtoupper((string) $previous->signal), 'to' => strtoupper((string) $row->signal),
                        'at' => $row->prediction_time,
                        'score' => \App\Support\AiScore::toTen(is_numeric($row->ai_score) ? $row->ai_score : $row->prediction_score),
                        'horizons' => collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($row): array {
                            $current = is_numeric($row->current_price) ? (float) $row->current_price : null;
                            $targetField = "predicted_price_{$days}d";
                            $target = is_numeric($row->{$targetField}) ? (float) $row->{$targetField} : null;
                            return [$days => $current && $target !== null ? (($target / $current) - 1) * 100 : null];
                        })->all(),
                    ];
                })->filter();
            })->sortByDesc('at')->unique('symbol')->values()->all();

            $indicatorSignals = DB::table('chartview_signal_events as event')
                ->join('chartview_signal_statistics as statistic', 'statistic.event_key', '=', 'event.event_key')
                ->join('instruments as instrument', 'instrument.id', '=', 'event.instrument_id')
                ->leftJoinSub($latestPredictionIds, 'latest_prediction', fn ($join) => $join->on('latest_prediction.instrument_id', '=', 'event.instrument_id'))
                ->whereNotNull('event.rise_probability')
                ->where('event.tone', 'positive')
                ->orderByDesc('event.rise_probability')->orderByDesc('event.sample_size')->orderByDesc('event.bar_time')->limit(5)
                ->get(['event.bar_time', 'event.tone', 'event.rise_probability', 'event.sample_size', 'event.probability_scope', 'statistic.label_de', 'statistic.label_en', 'instrument.symbol', 'instrument.name', 'latest_prediction.prediction_id'])
                ->map(fn (object $row): array => [
                    'symbol' => $row->symbol, 'name' => $row->name, 'prediction_id' => $row->prediction_id,
                    'label' => app()->getLocale() === 'en' ? $row->label_en : $row->label_de,
                    'tone' => $row->tone, 'at' => $row->bar_time,
                    'rise_probability' => is_numeric($row->rise_probability) ? (float) $row->rise_probability : null,
                    'sample_size' => (int) $row->sample_size,
                    'probability_scope' => $row->probability_scope,
                ])->all();

            return compact('signalChanges', 'indicatorSignals');
        });
    }

    private function profileUniverseStats(User $user): array
    {
        $riskService = app(StockRiskClassificationService::class);
        $level = $riskService->userLevel($user);

        return Cache::remember("dashboard.profile-universe.{$level}.v2", now()->addMinutes(5), function () use ($user, $riskService, $level): array {
            $latestPredictionIds = DB::table('predictions')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id')
                ->groupBy('instrument_id');
            $query = DB::table('instruments as instrument')
                ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) => $join->on('latest_prediction.instrument_id', '=', 'instrument.id'))
                ->join('predictions as prediction', 'prediction.id', '=', 'latest_prediction.prediction_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at')
                ->when($level !== 'risk', fn ($builder) => $builder->where(
                    fn ($nested) => $nested->whereNull('instrument.risk_status')->orWhere('instrument.risk_status', '<>', 'sleep')
                ));
            $totalActiveCount = (clone $query)->count();
            $riskService->applyVisibility($query, $user, 'instrument.risk_status');
            $personalizedSignalSql = app(PersonalizedSignalService::class)->sql('prediction', $user);
            $rows = $query->select([
                    'prediction.id', 'prediction.instrument_id', 'prediction.ai_score', 'prediction.prediction_score',
                    'prediction.current_price', 'prediction.predicted_price_5d', 'prediction.predicted_price_10d',
                    'prediction.predicted_price_15d', 'prediction.predicted_price_20d',
                ])
                ->selectRaw("({$personalizedSignalSql}) AS personalized_signal")
                ->selectRaw('(SELECT COALESCE(previous.ai_score, previous.prediction_score) FROM predictions previous WHERE previous.instrument_id=prediction.instrument_id AND previous.id<>prediction.id ORDER BY previous.prediction_time DESC NULLS LAST, previous.id DESC LIMIT 1) AS previous_score')
                ->get();
            $scores = $rows
                ->map(fn (object $row): ?float => \App\Support\AiScore::toTen(is_numeric($row->ai_score) ? $row->ai_score : $row->prediction_score))
                ->filter(fn ($score): bool => is_numeric($score) && is_finite((float) $score));
            $candidates = $rows->map(function (object $row): ?string {
                $currentPrice = is_numeric($row->current_price) ? (float) $row->current_price : 0.0;
                if ($currentPrice <= 0) return null;
                $returns = collect([5, 10, 15, 20])->map(function (int $days) use ($row, $currentPrice): ?float {
                    $field = "predicted_price_{$days}d";
                    return is_numeric($row->{$field}) ? (((float) $row->{$field} / $currentPrice) - 1) * 100 : null;
                })->filter(fn ($value) => is_numeric($value));
                if ($returns->count() < 4) return null;
                $currentScore = \App\Support\AiScore::toTen(is_numeric($row->ai_score) ? $row->ai_score : $row->prediction_score);
                $previousScore = \App\Support\AiScore::toTen($row->previous_score);
                if ($currentScore === null || $previousScore === null || abs($currentScore - $previousScore) < .1) return null;
                $signal = strtoupper((string) $row->personalized_signal);
                if ($signal !== 'BUY' && $returns->filter(fn (float $return): bool => $return >= .25)->count() >= 3 && $currentScore > $previousScore) return 'buy';
                if ($signal !== 'SELL' && $returns->filter(fn (float $return): bool => $return <= -.25)->count() >= 3 && $currentScore < $previousScore) return 'sell';
                return null;
            })->filter();
            $ranges = [[0, 2], [2, 4], [4, 6], [6, 8], [8, 10.01]];
            $bins = collect($ranges)->map(function (array $range) use ($scores): array {
                $count = $scores->filter(fn (float $score): bool => $score >= $range[0] && $score < $range[1])->count();
                return ['label' => $range[0].'–'.min(10, $range[1]), 'count' => $count];
            })->all();

            return [
                'level' => $level,
                'active_count' => $scores->count(),
                'total_active_count' => $totalActiveCount,
                'assigned_percent' => $totalActiveCount > 0 ? round(($scores->count() / $totalActiveCount) * 100, 1) : 0,
                'transition_candidates' => $candidates->count(),
                'transition_to_buy' => $candidates->filter(fn (string $direction): bool => $direction === 'buy')->count(),
                'transition_to_sell' => $candidates->filter(fn (string $direction): bool => $direction === 'sell')->count(),
                'average_score' => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null,
                'max_bin' => max(1, ...array_column($bins, 'count')),
                'bins' => $bins,
            ];
        });
    }

}

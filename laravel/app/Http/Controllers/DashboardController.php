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
use App\Services\PlanAccessService;
use App\Services\ServingDashboardService;
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
            'news', 'chartview',
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
        $continentPredictions = $this->servingContinentPredictions();
        $recentSignalOverview = $this->servingRecentSignalOverview();
        $signalCockpit = $this->servingSignalCockpit();
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
        $servingDashboard = app(ServingDashboardService::class);
        $servingStocks = $servingDashboard->latestStocks();
        $topStockToday = $servingStocks->first(
            fn (object $stock): bool => strtoupper((string) $stock->signal) === 'BUY'
        );
        $topWatchStock = $servingStocks->first(
            fn (object $stock): bool => in_array(strtoupper((string) $stock->signal), ['WATCH', 'WAIT'], true)
        );
        $topRankedStocks = $servingStocks->take(1)->values();
        // Technical tips from the legacy prediction database must not be mixed
        // into the serving-model cards. A future serving-native technical
        // signal table can populate this collection without a compatibility join.
        $dailyTips = collect();
        $dashboardOpportunities = $canUsePro
            ? $servingDashboard->opportunities(5)
            : collect();
        $messageReminders = collect()
            ->merge(
                DB::table('prediction_purchase_reminders as reminder')
                    ->join('instruments as instrument', 'instrument.id', '=', 'reminder.instrument_id')
                    ->where('reminder.user_id', $user->id)
                    ->whereIn('reminder.status', ['active', 'disabled'])
                    ->whereDate('reminder.remind_on', '>=', today())
                    ->orderBy('reminder.remind_on')
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
                ->orderBy('event.event_date')
                ->get(['event.id', 'event.event_date', 'event.event_time', 'event.eps_estimate', 'instrument.symbol', 'instrument.name'])
                ->map(fn (object $event): array => [
                    'type' => 'earnings', 'symbol' => $event->symbol, 'name' => $event->name,
                    'label' => __('Quartalszahlen'),
                    'schedule' => \Illuminate\Support\Carbon::parse($event->event_date)->format('d.m.Y').($event->event_time ? ' · '.__($event->event_time) : ''),
                    'sort_at' => (string) $event->event_date,
                ])
            : collect();
        $allScheduleItems = $messageReminders
            ->concat($corporateScheduleItems)
            ->sortBy(fn (array $item): string => ($item['sort_at'] === '0000-00-00' ? '9999-12-31' : $item['sort_at']).'-'.$item['symbol'])
            ->values();
        $activeMessageScheduleItems = $scheduleEmailsEnabled
            ? $messageReminders->where('active', true)->sortBy('sort_at')->values()
            : collect();
        $dashboardScheduleItems = $activeMessageScheduleItems
            ->concat($corporateScheduleItems->take(max(0, 6 - $activeMessageScheduleItems->count())))
            ->take(6)
            ->values();

        $watchlistInstrumentIds = DB::table('watchlist_items as item')
            ->join('watchlists as watchlist', 'watchlist.id', '=', 'item.watchlist_id')
            ->where('watchlist.user_id', $user->id)
            ->where('watchlist.active', true)
            ->pluck('item.instrument_id');
        $portfolioInstrumentIds = DB::table('portfolio_positions as position')
            ->join('portfolios as portfolio', 'portfolio.id', '=', 'position.portfolio_id')
            ->where('portfolio.user_id', $user->id)
            ->where('portfolio.active', true)
            ->pluck('position.instrument_id');
        $personalNewsInstrumentIds = $watchlistInstrumentIds
            ->merge($portfolioInstrumentIds)
            ->unique()
            ->values();
        $newsLocale = str_starts_with(strtolower(app()->getLocale()), 'en') ? 'en' : 'de';

        $newsCenterItems = \App\Models\News::query()
            ->with('instrument:id,symbol,name,country')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays(7))
            ->whereRaw('(LOWER(language) = ? OR LOWER(language) LIKE ?)', [$newsLocale, $newsLocale.'-%'])
            ->whereIn('instrument_id', $personalNewsInstrumentIds)
            ->orderByRaw('relevance_score DESC NULLS LAST')
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();
        if ($newsCenterItems->count() < 2) {
            $fallbackNews = \App\Models\News::query()
                ->with('instrument:id,symbol,name,country')
                ->whereNotNull('published_at')
                ->where('published_at', '>=', now()->subDays(7))
                ->whereRaw('(LOWER(language) = ? OR LOWER(language) LIKE ?)', [$newsLocale, $newsLocale.'-%'])
                ->whereNotIn('id', $newsCenterItems->pluck('id'))
                ->orderByRaw('relevance_score DESC NULLS LAST')
                ->orderByDesc('published_at')
                ->limit(2 - $newsCenterItems->count())
                ->get();
            $newsCenterItems = $newsCenterItems->concat($fallbackNews)->values();
        }
        $newsCenterItems->each(function (\App\Models\News $newsItem) use ($watchlistInstrumentIds, $portfolioInstrumentIds): void {
            $newsItem->setAttribute('dashboard_sources', array_values(array_filter([
                $watchlistInstrumentIds->contains($newsItem->instrument_id) ? 'watchlist' : null,
                $portfolioInstrumentIds->contains($newsItem->instrument_id) ? 'portfolio' : null,
            ])));
        });

        return view('dashboard', compact(
            'riskProfile', 'strategyPortfolio', 'overview', 'marketSituation', 'continentPredictions',
            'marketFactorSnapshot',
            'recentSignalOverview',
            'signalCockpit',
            'profileUniverseStats',
            'recentEarnings',
            'communityOverview',
            'messageReminders',
            'allScheduleItems',
            'dashboardScheduleItems',
            'companyNewsEnabled',
            'scheduleEmailsEnabled',
            'canManageMessages',
            'canUsePlus',
            'canUsePro',
            'topStockToday',
            'topWatchStock',
            'topRankedStocks',
            'dailyTips',
            'dashboardOpportunities',
            'newsCenterItems',
        ));
    }

    private function strategyPortfolio(int $userId): mixed
    {
        $portfolios = \App\Models\Portfolio::query()
            ->where('user_id', $userId)
            ->where('type', 'paper')
            ->where('active', true)
            ->whereHas('strategies')
            ->with(['cashAccount', 'strategies:id,name', 'positions.instrument:id,symbol,name,country'])
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

    public function signalCockpit(): array
    {
        return $this->servingSignalCockpit();
    }

    private function servingContinentPredictions(): array
    {
        return Cache::remember('dashboard.serving.continent-predictions.v1', now()->addMinute(), function (): array {
            $rows = app(ServingDashboardService::class)->latestStocks();
            $continents = [
                'europe' => ['key' => 'europe', 'label' => __('Europa')],
                'north-america' => ['key' => 'north-america', 'label' => __('Nordamerika')],
                'asia-pacific' => ['key' => 'asia-pacific', 'label' => __('Asien-Pazifik')],
                'africa' => ['key' => 'africa', 'label' => __('Afrika')],
            ];

            foreach ($continents as $key => $continent) {
                $continentRows = $rows->filter(
                    fn (object $row): bool => $this->continentFor($row->country) === $key
                );
                $signals = $continentRows->countBy(
                    fn (object $row): string => strtoupper((string) $row->signal)
                );
                $continents[$key] += [
                    'count' => $continentRows->count(),
                    'latest_at' => $continentRows->max('as_of'),
                    'buy' => (int) $signals->get('BUY', 0),
                    'watch' => (int) $signals->get('WATCH', 0) + (int) $signals->get('WAIT', 0),
                    'hold' => (int) $signals->get('HOLD', 0),
                    'sell' => (int) $signals->get('SELL', 0),
                ];
            }

            return $continents;
        });
    }

    private function servingRecentSignalOverview(): array
    {
        return Cache::remember('dashboard.serving.recent-signal-overview.v1', now()->addMinute(), function (): array {
            $rows = app(ServingDashboardService::class)->latestStocks()
                ->filter(fn (object $row): bool =>
                    $row->as_of && \Illuminate\Support\Carbon::parse($row->as_of)->gte(now()->subHours(48))
                );
            $signals = $rows->groupBy(fn (object $row): string => strtoupper((string) $row->signal));
            $waitRows = collect($signals->get('WATCH', collect()))
                ->concat($signals->get('WAIT', collect()));

            return [
                'buy_count' => collect($signals->get('BUY', collect()))->count(),
                'wait_count' => $waitRows->count(),
                'hold_count' => collect($signals->get('HOLD', collect()))->count(),
                'sell_count' => collect($signals->get('SELL', collect()))->count(),
                'buy_symbols' => collect($signals->get('BUY', collect()))->take(4)->pluck('symbol')->all(),
                'wait_symbols' => $waitRows->take(4)->pluck('symbol')->all(),
                'hold_symbols' => collect($signals->get('HOLD', collect()))->take(4)->pluck('symbol')->all(),
                'sell_symbols' => collect($signals->get('SELL', collect()))->take(4)->pluck('symbol')->all(),
            ];
        });
    }

    private function servingSignalCockpit(): array
    {
        return Cache::remember('dashboard.serving.signal-cockpit.v1', now()->addMinute(), function (): array {
            $service = app(ServingDashboardService::class);
            $topScores = $service->latestStocks()->take(5)->map(fn (object $row): array => [
                'symbol' => $row->symbol,
                'name' => $row->name,
                'prediction_id' => null,
                'signal' => $row->signal,
                'score' => $row->ai_score,
                'horizon_signals' => collect($row->horizons)->map(fn (array $scope): array => [
                    'return' => $scope['return'],
                    'signal' => $scope['return'] === null
                        ? null
                        : ($scope['return'] > .15 ? 'UP' : ($scope['return'] < -.15 ? 'DOWN' : 'FLAT')),
                ])->all(),
            ])->values()->all();
            $signalChanges = $service->signalChanges();
            $indicatorSignals = [];

            return compact('topScores', 'signalChanges', 'indicatorSignals');
        });
    }

    private function profileUniverseStats(User $user): array
    {
        $riskService = app(StockRiskClassificationService::class);
        $level = $riskService->userLevel($user);

        return Cache::remember("dashboard.profile-universe.serving.{$level}.v2", now()->addMinutes(2), function () use ($level): array {
            $definitions = [
                ['key' => 'strong_sell', 'label' => 'Strong Sell', 'range' => __('Mindestens zwei SELL-Horizonte')],
                ['key' => 'sell', 'label' => 'Sell', 'range' => __('Ein bestätigter SELL-Horizont')],
                ['key' => 'hold', 'label' => 'Hold', 'range' => __('Kein eindeutiges Kauf- oder Verkaufssignal')],
                ['key' => 'buy', 'label' => 'Buy', 'range' => __('Ein bestätigter BUY-Horizont')],
                ['key' => 'strong_buy', 'label' => 'Strong Buy', 'range' => __('Mindestens zwei BUY-Horizonte')],
            ];

            try {
                $connection = DB::connection('serving');
                $rows = $connection
                    ->table('serving_active_models as active_model')
                    ->join('serving_instruments as instrument', 'instrument.id', '=', 'active_model.instrument_id')
                    ->join('serving_releases as release', 'release.id', '=', 'active_model.release_id')
                    ->where('instrument.is_active', true)
                    ->get([
                        'instrument.id as instrument_id', 'active_model.release_id',
                        'release.compact_metrics',
                    ]);
                $selectedScopes = $connection
                    ->table('serving_prediction_scopes')
                    ->where('selected_for_prediction', true)
                    ->where('prediction_enabled', true)
                    ->get(['instrument_id', 'release_id', 'horizon'])
                    ->groupBy(fn (object $scope): string => $scope->instrument_id.'|'.$scope->release_id);
                $rankedPredictions = $connection
                    ->table('serving_predictions as prediction')
                    ->join('serving_prediction_scopes as scope', function ($join): void {
                        $join->on('scope.instrument_id', '=', 'prediction.instrument_id')
                            ->on('scope.release_id', '=', 'prediction.release_id')
                            ->on('scope.horizon', '=', 'prediction.horizon');
                    })
                    ->where('scope.selected_for_prediction', true)
                    ->where('scope.prediction_enabled', true)
                    ->select([
                        'prediction.instrument_id', 'prediction.release_id',
                        'prediction.horizon', 'prediction.signal',
                    ])
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY prediction.instrument_id, prediction.release_id, prediction.horizon ORDER BY prediction.as_of DESC, prediction.id DESC) AS scope_rank');
                $latestPredictions = $connection->query()
                    ->fromSub($rankedPredictions, 'ranked_prediction')
                    ->where('scope_rank', 1)
                    ->get()
                    ->keyBy(fn (object $prediction): string => $prediction->instrument_id.'|'.$prediction->release_id.'|'.$prediction->horizon);
                $horizons = $connection
                    ->table('serving_model_horizon_status')
                    ->distinct()
                    ->orderBy('horizon')
                    ->pluck('horizon')
                    ->map(fn ($horizon): int => (int) $horizon)
                    ->values()
                    ->all();
                $eligibleConfigurations = $connection
                    ->table('serving_prediction_scopes')
                    ->count();
            } catch (\Throwable $error) {
                report($error);
                $rows = collect();
                $selectedScopes = collect();
                $latestPredictions = collect();
                $horizons = [];
                $eligibleConfigurations = 0;
            }

            $signalCounts = $rows->countBy(function (object $row) use ($selectedScopes, $latestPredictions): string {
                $releaseKey = $row->instrument_id.'|'.$row->release_id;
                $compactMetrics = is_array($row->compact_metrics)
                    ? $row->compact_metrics
                    : (array) (json_decode((string) $row->compact_metrics, true) ?: []);
                $signals = collect($selectedScopes->get($releaseKey, []))
                    ->map(function (object $scope) use ($row, $latestPredictions, $compactMetrics): string {
                        $predictionKey = $row->instrument_id.'|'.$row->release_id.'|'.$scope->horizon;
                        $storedSignal = strtoupper(trim((string) data_get($latestPredictions->get($predictionKey), 'signal', '')));

                        if ($storedSignal !== '') {
                            return match ($storedSignal) {
                                'STRONG BUY', 'STRONG_BUY' => 'BUY',
                                'STRONG SELL', 'STRONG_SELL' => 'SELL',
                                'WATCH', 'WAIT', 'NEUTRAL' => 'HOLD',
                                default => in_array($storedSignal, ['BUY', 'HOLD', 'SELL'], true) ? $storedSignal : 'HOLD',
                            };
                        }

                        $buy = data_get($compactMetrics, "horizons.{$scope->horizon}.prediction.buy");
                        $isBuy = $buy === true || $buy === 1 || $buy === '1' || strtolower((string) $buy) === 'true';

                        return $isBuy ? 'BUY' : 'HOLD';
                    });
                $buyCount = $signals->filter(fn (string $signal): bool => $signal === 'BUY')->count();
                $sellCount = $signals->filter(fn (string $signal): bool => $signal === 'SELL')->count();

                if ($buyCount >= 2 && $buyCount > $sellCount) {
                    return 'strong_buy';
                }
                if ($sellCount >= 2 && $sellCount > $buyCount) {
                    return 'strong_sell';
                }
                if ($buyCount > 0 && $sellCount === 0) {
                    return 'buy';
                }
                if ($sellCount > 0 && $buyCount === 0) {
                    return 'sell';
                }

                return 'hold';
            });
            $bins = collect($definitions)->map(fn (array $definition): array => [
                ...$definition,
                'count' => (int) $signalCounts->get($definition['key'], 0),
            ])->all();
            $activeCount = $rows->count();

            return [
                'source' => 'serving',
                'level' => $level,
                'active_count' => $activeCount,
                'total_active_count' => $activeCount,
                'assigned_percent' => $activeCount > 0 ? 100.0 : 0.0,
                'transition_candidates' => 0,
                'transition_to_buy' => (int) ($signalCounts->get('buy', 0) + $signalCounts->get('strong_buy', 0)),
                'transition_to_sell' => (int) ($signalCounts->get('sell', 0) + $signalCounts->get('strong_sell', 0)),
                'average_score' => null,
                'model_horizons' => $horizons,
                'eligible_configuration_count' => (int) $eligibleConfigurations,
                'max_bin' => max(1, ...array_column($bins, 'count')),
                'bins' => $bins,
            ];
        });
    }

}

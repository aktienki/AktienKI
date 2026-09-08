<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Models\CommunityPost;
use App\Models\Portfolio;
use App\Models\SmartSelectionLabel;
use App\Models\User;
use App\Services\PlanAccessService;
use App\Services\ServingMarketSnapshotService;
use App\Services\ServingReadService;
use App\Services\ServingScreenerService;
use App\Services\StockRiskClassificationService;
use App\Support\AiScore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function updateLayout(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro), 403);

        $allowed = [
            'paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'best-buy', 'best-wait',
            'watchlist-screener', 'predictions', 'smart-screener', 'market-report', 'mobile-view',
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
            'labels' => SmartSelectionLabel::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->count(),
            'news' => 0,
        ];
        $communityOverview = [
            'posts' => CommunityPost::query()->where('is_published', true)->count(),
            'members' => CommunityPost::query()->where('is_published', true)->distinct('user_id')->count('user_id'),
            'recent' => CommunityPost::query()->where('is_published', true)->where('created_at', '>=', now()->subDays(7))->count(),
            'news' => 0,
        ];
        $servingMarketSnapshot = app(ServingMarketSnapshotService::class)->snapshot();
        $servingMarketAnalysis = (array) ($servingMarketSnapshot['analysis'] ?? []);
        $marketSituation = (object) [
            'analysis_date' => $servingMarketSnapshot['calculation_date'] ?? null,
            'headline' => $servingMarketAnalysis['headline'] ?? null,
            'executive_summary' => $servingMarketAnalysis['summary'] ?? null,
            'market_outlook' => $servingMarketAnalysis['outlook'] ?? null,
            'confidence' => $servingMarketAnalysis['confidence'] ?? null,
            'risk_level' => $servingMarketAnalysis['riskLevel'] ?? null,
        ];
        $marketFactorSnapshot = ['current' => collect(), 'history' => collect($servingMarketSnapshot['daily_scores'] ?? [])];
        $continentPredictions = $this->continentPredictions();
        $recentSignalOverview = $this->recentSignalOverview();
        $signalCockpit = $this->signalCockpit();
        $profileUniverseStats = $this->profileUniverseStats($user);
        // Earnings are hidden until they are published to a serving table.
        // Local fundamentals must not silently leak into a remote-backed view.
        $recentEarnings = collect();
        $remoteDashboardRequest = Request::create('/screener', 'GET', ['limit' => 'all']);
        $remoteDashboardRequest->setUserResolver(fn () => $user);
        $remoteDashboardStocks = collect(app(ServingScreenerService::class)->data($remoteDashboardRequest)['stocks'])
            ->each(function (object $stock): void {
                $stock->prediction_id = null;
                $stock->ai_score = $stock->ranking_score;
                $stock->prediction_score = $stock->score_10;
                $stock->dashboard_ranking_score = $stock->ranking_score;
                $stock->confidence = $stock->confidence_percent;
                $stock->display_price = $stock->current_price;
                $stock->display_price_time = $stock->prediction_time;
                $stock->display_price_live = false;
                $stock->daily_change_percent = $stock->price_change_percent;
                $stock->horizon_fusion_consensus_return = $stock->expected_return_20d;
                $stock->market_return_20d = $stock->expected_return_20d;
            });
        $topStockToday = $remoteDashboardStocks->firstWhere('personalized_signal', 'BUY');
        $topWatchStock = $remoteDashboardStocks->firstWhere('personalized_signal', 'WATCH');
        $topRankedStocks = $remoteDashboardStocks->take(1)->values();
        // There is deliberately no local market-data fallback. The former
        // ChartView daily-tip query read local predictions and therefore could
        // disagree with the serving-backed dashboard cards.
        $dailyTips = collect();
        $dashboardOpportunities = $canUsePro
            ? $remoteDashboardStocks
                ->filter(fn (object $stock): bool => is_numeric($stock->expected_return_10d)
                    && (float) $stock->expected_return_10d < 0
                    && ((is_numeric($stock->expected_return_20d) && (float) $stock->expected_return_20d > 0)
                        || (is_numeric($stock->expected_return_40d) && (float) $stock->expected_return_40d > 0)))
                ->sortByDesc(fn (object $stock): float => max(
                    is_numeric($stock->expected_return_20d) ? (float) $stock->expected_return_20d : -999.0,
                    is_numeric($stock->expected_return_40d) ? (float) $stock->expected_return_40d : -999.0,
                ) * 1000 + (float) $stock->ranking_score)
                ->take(5)
                ->map(function (object $stock): object {
                    $longHorizon = is_numeric($stock->expected_return_40d) && (float) $stock->expected_return_40d > 0
                        && (! is_numeric($stock->expected_return_20d) || (float) $stock->expected_return_40d >= (float) $stock->expected_return_20d)
                        ? 40 : 20;

                    return (object) [
                        'status' => 'open',
                        'prediction_id' => null,
                        'instrument' => (object) [
                            'symbol' => $stock->symbol,
                            'name' => $stock->name,
                            'country' => $stock->country,
                        ],
                        'snapshot' => [
                            'short_horizon' => 10,
                            'long_horizon' => $longHorizon,
                            'returns' => [
                                10 => $stock->expected_return_10d,
                                20 => $stock->expected_return_20d,
                                40 => $stock->expected_return_40d,
                            ],
                            'score' => $stock->score_10,
                            'confidence' => $stock->confidence_percent,
                            'risk' => $stock->risk_percent,
                        ],
                    ];
                })
                ->values()
            : collect();
        $messageReminders = collect()
            ->merge(
                DB::table('prediction_purchase_reminders as reminder')
                    ->join('instruments as instrument', 'instrument.id', '=', 'reminder.instrument_id')
                    ->where('reminder.user_id', $user->id)
                    ->whereIn('reminder.status', ['active', 'disabled', 'sent'])
                    ->orderBy('reminder.remind_on')
                    ->get(['reminder.id', 'reminder.intent', 'reminder.horizon_days', 'reminder.remind_on', 'reminder.status', 'instrument.symbol', 'instrument.name'])
                    ->map(function (object $reminder): array {
                        $remindOn = Carbon::parse($reminder->remind_on)->startOfDay();

                        return [
                            'id' => $reminder->id,
                            'type' => 'prediction',
                            'symbol' => $reminder->symbol,
                            'name' => $reminder->name,
                            'label' => $reminder->intent === 'purchased' ? __('SELL-Überwachung') : __('Kauferinnerung'),
                            'schedule' => __('E-Mail').' · '.$remindOn->format('d.m.Y'),
                            'date' => $remindOn->format('Y-m-d'),
                            'sort_at' => (string) $reminder->remind_on,
                            'status' => $reminder->status,
                            'active' => $reminder->status === 'active',
                            'expired' => $remindOn->isBefore(today()),
                        ];
                    })
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
                        'status' => $alert->status,
                        'active' => $alert->status === 'active',
                        'expired' => false,
                    ])
            )
            ->sortBy(fn (array $reminder): string => $reminder['symbol'].'-'.$reminder['label'])
            ->values();
        $corporateScheduleItems = collect();
        $allScheduleItems = $messageReminders
            ->concat($corporateScheduleItems)
            ->sortBy(fn (array $item): string => ($item['sort_at'] === '0000-00-00' ? '9999-12-31' : $item['sort_at']).'-'.$item['symbol'])
            ->values();
        $activeMessageScheduleItems = $scheduleEmailsEnabled
            ? $messageReminders->where('active', true)->where('expired', false)->sortBy('sort_at')->values()
            : collect();
        $dashboardScheduleItems = $activeMessageScheduleItems
            ->concat($corporateScheduleItems->take(max(0, 6 - $activeMessageScheduleItems->count())))
            ->take(6)
            ->values();

        // News remain empty until a canonical serving-news source exists.
        $newsCenterItems = collect();

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
        $portfolios = Portfolio::query()
            ->where('user_id', $userId)
            ->where('type', 'paper')
            ->where('active', true)
            ->whereHas('strategies')
            ->with(['cashAccount', 'strategies:id,name', 'positions.instrument:id,symbol,name,country'])
            ->get();

        $portfolio = $portfolios->first(fn ($candidate): bool => (bool) data_get($candidate->meta, 'automation.live_enabled', false))
            ?? $portfolios->first();

        if (! $portfolio) {
            return null;
        }

        $positionsValue = $portfolio->positions->sum(fn ($position): float => (float) $position->quantity * (float) ($position->current_price ?? $position->average_buy_price));
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
        return Cache::remember('dashboard.personal.continent-predictions-serving-v1', now()->addMinutes(2), function (): array {
            $rows = app(ServingReadService::class)->latestPredictions()
                ->groupBy('instrument_id')
                ->map(function ($predictions): object {
                    $row = $predictions->firstWhere('horizon', 20) ?? $predictions->sortByDesc('as_of')->first();
                    $row->country = $row->country_code;
                    $row->prediction_time = $row->as_of;

                    return $row;
                });

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
        return Cache::remember('dashboard.personal.recent-signal-overview-serving-v1', now()->addMinutes(2), function (): array {
            $recommendations = app(ServingReadService::class)->signalTransitions()
                ->filter(fn (object $row): bool => Carbon::parse($row->changed_at)->gte(now()->subHours(48)))
                ->map(function (object $row): object {
                    $row->signal = $row->to_signal;
                    $row->prediction_time = $row->changed_at;

                    return $row;
                });

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
        return Cache::remember('dashboard.personal.signal-cockpit-serving-v1', now()->addMinutes(2), function (): array {
            $serving = app(ServingReadService::class);
            $predictions = $serving->latestPredictions()->groupBy('instrument_id');
            $signalChanges = $serving->signalTransitions()
                ->filter(fn (object $row): bool => Carbon::parse($row->changed_at)->gte(now()->subDays(7)))
                ->map(function (object $row) use ($predictions): array {
                    $horizons = collect($predictions->get($row->instrument_id, collect()))
                        ->mapWithKeys(fn (object $prediction): array => [
                            (int) $prediction->horizon => $prediction->expected_return_percent,
                        ]);

                    return [
                        'symbol' => $row->symbol,
                        'name' => $row->name,
                        'country' => $row->country_code,
                        'prediction_id' => null,
                        'from' => $row->from_signal,
                        'to' => $row->to_signal,
                        'at' => $row->changed_at,
                        'score' => is_numeric($row->score_at_change) ? AiScore::toTen($row->score_at_change) : null,
                        'risk' => is_numeric($row->risk_at_change) ? min(100.0, max(0.0, (float) $row->risk_at_change * 20.0)) : null,
                        'horizons' => [
                            5 => null,
                            10 => $horizons->get(10),
                            15 => null,
                            20 => $horizons->get(20),
                            40 => $horizons->get(40),
                        ],
                    ];
                })
                ->filter(fn (array $change): bool => $change['to'] !== 'SELL' || ! is_numeric($change['horizons'][20]) || $change['horizons'][20] <= 0)
                ->unique('symbol')
                ->values()
                ->all();

            // Chart indicators are only displayed once a canonical serving
            // table exists; local ChartView tables are never a fallback.
            return ['signalChanges' => $signalChanges, 'indicatorSignals' => []];
        });
    }

    private function profileUniverseStats(User $user): array
    {
        $level = app(StockRiskClassificationService::class)->userLevel($user);
        $snapshot = app(ServingMarketSnapshotService::class)->snapshot();
        $transitionStats = (array) ($snapshot['transition_stats'] ?? []);
        $distribution = (array) ($transitionStats['distribution'] ?? []);
        $counts = [
            'SELL' => (int) ($distribution['SELL'] ?? 0),
            'WAIT' => (int) ($distribution['WAIT'] ?? 0),
            'HOLD' => (int) ($distribution['HOLD'] ?? 0),
            'WATCH' => (int) ($distribution['WATCH'] ?? 0),
            'BUY' => (int) ($distribution['BUY'] ?? 0),
        ];
        $currentSignalCount = array_sum($counts);
        $activeCount = app(ServingReadService::class)->activeTradeableStockCount();
        $bins = collect([
            ['label' => 'SELL', 'range' => 'Aktuelles Verkaufssignal', 'signal' => 'SELL'],
            ['label' => 'HOLD', 'range' => 'Aktuelles Haltesignal', 'signal' => 'HOLD'],
            ['label' => 'WATCH', 'range' => 'Aktuelles Beobachtungssignal', 'signal' => 'WATCH'],
            ['label' => 'BUY', 'range' => 'Aktuelles Kaufsignal', 'signal' => 'BUY'],
        ])->map(fn (array $bin): array => [
            'label' => $bin['label'],
            'range' => $bin['range'],
            'count' => $counts[$bin['signal']],
        ])->all();

        return [
            'source' => 'serving',
            'batch_id' => $snapshot['batch_id'] ?? null,
            'calculation_date' => $snapshot['calculation_date'] ?? null,
            'level' => $level,
            'active_count' => $activeCount,
            'total_active_count' => $activeCount,
            'current_signal_count' => $currentSignalCount,
            'open_count' => max(0, $activeCount - $currentSignalCount),
            'assigned_percent' => $activeCount > 0 ? round(($currentSignalCount / $activeCount) * 100, 1) : 0.0,
            'transition_candidates' => (int) ($transitionStats['transition_count'] ?? 0),
            'transition_to_buy' => (int) ($transitionStats['positive_count'] ?? 0),
            'transition_to_sell' => (int) ($transitionStats['negative_count'] ?? 0),
            'average_score' => is_numeric(data_get($snapshot, 'assessment.score'))
                ? round((float) data_get($snapshot, 'assessment.score'), 1)
                : null,
            'max_bin' => max(1, ...array_column($bins, 'count')),
            'bins' => $bins,
        ];
    }
}

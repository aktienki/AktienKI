<?php

namespace App\Http\Controllers;

use App\Models\SmartSelectionLabel;
use App\Services\ChartPatternSignalService;
use App\Services\EarningsDriftStatsService;
use App\Services\IndexAiScoreService;
use App\Services\MarketOverviewWidgetsService;
use App\Services\MarketService;
use App\Services\PanelScoreDriftStatsService;
use App\Services\ServingMarketSnapshotService;
use App\Services\TodayHighlightsAnalysisService;
use App\Services\TodayHighlightsBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A small, standalone layout concept for the "Persönlicher Bereich" - the
 * left column keeps its fixed 1x8 icon grid, but clicking an icon now swaps
 * the main area's content instead of navigating away: each icon gets a
 * short overview of its own section, and the page defaults to a current
 * trading-opportunities overview when nothing is selected.
 */
final class DashboardConceptController extends Controller
{
    private const COUNTRY_FLAGS = [
        'DE' => '🇩🇪', 'US' => '🇺🇸', 'AT' => '🇦🇹', 'CH' => '🇨🇭', 'GB' => '🇬🇧', 'FR' => '🇫🇷',
        'NL' => '🇳🇱', 'DK' => '🇩🇰', 'SE' => '🇸🇪', 'NO' => '🇳🇴', 'FI' => '🇫🇮', 'IT' => '🇮🇹',
        'ES' => '🇪🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'HK' => '🇭🇰', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
    ];

    /** The core navigation destinations for the left column. */
    private const LEFT_COLUMN_ICONS = [
        ['today-focus', 'Heute im Fokus', 'heroicon-o-fire'],
        ['market-report', 'Aktuelle Marktlage', 'heroicon-o-globe-europe-africa'],
        ['chartview', 'ChartView', 'heroicon-o-chart-bar-square'],
        ['classic-dashboard', 'Depots', 'heroicon-o-squares-2x2'],
    ];

    public function __invoke(Request $request): View
    {
        $snapshot = app(ServingMarketSnapshotService::class)->snapshot();

        $leftIcons = collect(self::LEFT_COLUMN_ICONS)->map(fn (array $item): array => [
            'id' => $item[0],
            'label' => $item[1],
            'icon' => $item[2],
            'url' => $this->urlFor($item[0]),
        ]);

        $sections = collect([
            $this->todayFocusSection('today-focus'),
            $this->chartPatternSection('chartview'),
            $this->marketSection('market-report', $snapshot),
            $this->classicDashboardSection('classic-dashboard', $request),
        ])->map(function (array $section) use ($leftIcons): array {
            $meta = $leftIcons->firstWhere('id', $section['id']);

            return array_merge($section, [
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'url' => $meta['url'],
            ]);
        })->keyBy('id');

        return view('dashboard-concept', [
            'leftIcons' => $leftIcons,
            'sections' => $sections,
            'opportunities' => $this->opportunities($request),
        ]);
    }

    /**
     * The first thing shown on the page: full-width cards for the same
     * three-factor champion, next-best BUY candidates and recent signal
     * changes the main dashboard's cards use - each with a small
     * forecast-horizon chart and, where available, the external GPT
     * review, instead of a plain text line.
     */
    private function opportunities(Request $request): array
    {
        $dashboard = app(DashboardController::class);
        $remoteDashboardStocks = $dashboard->remoteDashboardStocks($request);
        $champion = $dashboard->championSummary($request, $remoteDashboardStocks);
        $championInstrumentId = $champion?->instrument_id !== null ? (int) $champion->instrument_id : null;

        $candidates = $remoteDashboardStocks
            ->filter(fn (object $stock): bool => strtoupper((string) ($stock->personalized_signal ?? '')) === 'BUY')
            ->reject(fn (object $stock): bool => $championInstrumentId !== null && (int) $stock->instrument_id === $championInstrumentId)
            ->sortByDesc(fn (object $stock): float => (float) ($stock->composite_score ?? $stock->ranking_score ?? 0))
            ->take(5)
            ->values();

        $signalChanges = collect($dashboard->signalCockpit()['signalChanges'] ?? [])->take(5)->values();

        return [
            'champion' => $champion ? $this->stockCard($champion, __('Champion')) : null,
            'candidates' => $candidates->map(fn (object $stock): array => $this->stockCard($stock, __('Kandidat')))->all(),
            'signalChanges' => $signalChanges->map(fn (array $change): array => $this->signalChangeCard($change))->all(),
        ];
    }

    /**
     * Normalizes a screener stock object (champion or candidate - both come
     * from the same remoteDashboardStocks() shape) into the card data the
     * view renders: header, 10/20/40-day forecast bars, and the external
     * GPT review when one exists.
     */
    private function stockCard(object $stock, string $badge): array
    {
        return [
            'badge' => $badge,
            'symbol' => $stock->symbol,
            'name' => $stock->name ?: $stock->symbol,
            'country' => $stock->country,
            'url' => route('stocks.show', ['symbol' => $stock->symbol, 'return_to' => '/dashboard/concept']),
            'compositeScore' => is_numeric($stock->composite_score ?? null) ? (float) $stock->composite_score : null,
            'horizons' => [
                '10T' => is_numeric($stock->expected_return_10d ?? null) ? (float) $stock->expected_return_10d : null,
                '20T' => is_numeric($stock->expected_return_20d ?? null) ? (float) $stock->expected_return_20d : null,
                '40T' => is_numeric($stock->expected_return_40d ?? null) ? (float) $stock->expected_return_40d : null,
            ],
            'externalConfidence' => is_numeric($stock->external_review_confidence ?? null) ? (int) $stock->external_review_confidence : null,
            'externalVerdict' => $stock->external_review_verdict ?? null,
            'externalSummary' => $stock->external_review_summary ?? null,
        ];
    }

    private function signalChangeCard(array $change): array
    {
        return [
            'badge' => ($change['from'] ?? '?').' → '.($change['to'] ?? '?'),
            'symbol' => $change['symbol'],
            'name' => $change['name'] ?: $change['symbol'],
            'country' => $change['country'] ?? null,
            'url' => route('stocks.show', ['symbol' => $change['symbol'], 'prediction' => $change['prediction_id'], 'return_to' => '/dashboard/concept']),
            'compositeScore' => null,
            'horizons' => [
                '10T' => is_numeric($change['horizons'][10] ?? null) ? (float) $change['horizons'][10] : null,
                '20T' => is_numeric($change['horizons'][20] ?? null) ? (float) $change['horizons'][20] : null,
                '40T' => is_numeric($change['horizons'][40] ?? null) ? (float) $change['horizons'][40] : null,
            ],
            'externalConfidence' => null,
            'externalVerdict' => null,
            'externalSummary' => null,
            'score' => $change['score'] ?? null,
            'risk' => $change['risk'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, string>  $items
     */
    private function listSection(string $id, $items, int $total, string $emptyText): array
    {
        return [
            'id' => $id,
            'kind' => 'list',
            'items' => $items->values()->all(),
            'total' => $total,
            'emptyText' => $emptyText,
        ];
    }

    /**
     * Carries the rest of the full Marktübersicht ("Market Command Center")
     * page too, reusing exactly the same building blocks the markets/
     * situation Livewire component (MarketData) renders:
     * - sector breadth, Chancen, Risiken, Beobachtungsliste from the same
     *   snapshot()['analysis'] payload already loaded here - no extra query;
     * - the index market tape + x-dashboard.market-atlas, via
     *   MarketOverviewWidgetsService (MarketData::databaseMarkets()/
     *   loadMacroCards() now delegate to that same service, so both places
     *   stay in sync);
     * - x-dashboard.signal-overview, via the same snapshot()['transition_stats'].
     */
    private function marketSection(string $id, array $snapshot): array
    {
        $assessment = $snapshot['assessment'] ?? null;
        $analysis = $snapshot['analysis'] ?? [];
        $metrics = $analysis['metrics'] ?? [];

        $widgets = app(MarketOverviewWidgetsService::class);
        $marketService = app(MarketService::class);
        $indexAiScores = app(IndexAiScoreService::class);

        $markets = $widgets->indexMarkets();
        $situations = collect($marketService->marketSituations($markets, $indexAiScores->scores()))->keyBy('title');
        $markets = collect($markets)
            ->map(fn (array $market): array => array_merge($market, $situations->get($market['name'], [])))
            ->all();

        return [
            'id' => $id,
            'kind' => 'market',
            'available' => (bool) ($snapshot['available'] ?? false),
            'assessment' => $assessment,
            'metrics' => array_slice($metrics, 0, 4),
            'breadth' => $analysis['breadth'] ?? null,
            'opportunities' => $analysis['opportunities'] ?? [],
            'risks' => $analysis['risks'] ?? [],
            'watchlist' => $analysis['watchlist'] ?? [],
            'markets' => $markets,
            'countryAiScores' => $indexAiScores->countryScores(),
            'signalTransitionStats' => $snapshot['transition_stats'] ?? [],
            'macroCards' => $widgets->macroCards(),
        ];
    }

    /**
     * Reuses UpcomingEventsController::upcomingEvents() - the same real,
     * daily-synced earnings dates the standalone /anstehende-news page
     * shows - for a short 6-item preview on this tab.
     */
    private function eventsSection(string $id, Request $request): array
    {
        $events = app(UpcomingEventsController::class)
            ->upcomingEvents($request, lookaheadDays: 60, limit: 6);

        return [
            'id' => $id,
            'kind' => 'events',
            'events' => $events->all(),
            'emptyText' => __('Aktuell keine anstehenden Termine im Zeitraum.'),
        ];
    }

    /**
     * For every stock with an upcoming earnings date, its own historical
     * post-earnings reaction (earnings:drift-report-by-stock's numbers) -
     * "for stocks with upcoming quarterly results, what has this specific
     * stock historically done 3 trading days after a beat vs. a miss".
     * Sorted by the soonest upcoming date first.
     */
    private function earningsDriftSection(string $id, Request $request): array
    {
        $upcoming = app(UpcomingEventsController::class)
            ->upcomingEvents($request, lookaheadDays: 90, limit: 100);

        $instrumentIds = $upcoming->pluck('instrumentId')->unique()->values()->all();
        $stats = app(EarningsDriftStatsService::class)->forInstruments($instrumentIds);
        $panelStats = app(PanelScoreDriftStatsService::class);

        $rows = $upcoming
            ->unique('instrumentId')
            ->map(function (array $event) use ($stats, $panelStats): ?array {
                $stat = $stats->get($event['instrumentId']);
                if ($stat === null) {
                    return null;
                }

                // Same stock's panel-score-vs-forward-return picture right
                // next to its earnings reaction - only the two extreme
                // deciles (where the panel-wide signal actually concentrates,
                // see panel:score-drift-report) to keep this compact.
                $decileRows = $panelStats->decileBreakdown($event['instrumentId']);
                $panelDeciles = $decileRows->whereIn('decile', [1, 10])->values();
                $panelCorrelation = $panelStats->correlation($event['instrumentId']);

                return [
                    ...$stat,
                    'nextDate' => $event['date'],
                    'url' => $event['url'],
                    'panelDeciles' => $panelDeciles->all(),
                    'panelCorrelation' => $panelCorrelation,
                ];
            })
            ->filter()
            ->take(24)
            ->values();

        $days = $rows
            ->groupBy(fn (array $row): string => Carbon::parse($row['nextDate'])->toDateString())
            ->map(fn ($rowsForDay, string $date): array => ['date' => $date, 'rows' => $rowsForDay->values()->all()])
            ->sortBy('date')
            ->values();

        return [
            'id' => $id,
            'kind' => 'earnings-drift',
            'days' => $days->all(),
            'emptyText' => __('Für keine Aktie mit bevorstehenden Quartalszahlen liegt bereits eigene Historie vor.'),
        ];
    }

    /**
     * Chart patterns and indicator transitions (golden/death cross, SMA/
     * Bollinger breaks, RSI extremes, candlestick patterns, breakouts) from
     * the most recent 1-2 trading days - see ChartPatternSignalService for
     * why this is computed live from price_bars rather than read from the
     * (currently stalled) technical_indicators-backed chartview tables.
     */
    private function chartPatternSection(string $id): array
    {
        $rows = app(ChartPatternSignalService::class)->recentEvents()
            ->take(60)
            ->map(fn (array $event): array => [
                'time' => $event['time'],
                'symbol' => $event['symbol'],
                'name' => $event['name'],
                'country_flag' => self::COUNTRY_FLAGS[$event['country']] ?? '🌐',
                'label' => $event['label'],
                'tone' => $event['tone'],
                'change_pct' => $event['change_pct'],
                'candles' => $event['candles'],
                'indicator_series' => $event['indicator_series'],
                'pattern_range' => $event['pattern_range'],
                'breakout_level' => $event['breakout_level'],
                'breakout_line_y' => $event['breakout_line_y'],
                'rise_probability_20d' => $event['rise_probability_20d'],
                'average_return_20d' => $event['average_return_20d'],
                'probability_sample_size' => $event['probability_sample_size'],
                'probability_scope' => $event['probability_scope'],
                'instrument_occurrence_count' => $event['instrument_occurrence_count'],
                'url' => route('stocks.show', ['symbol' => $event['symbol'], 'return_to' => '/dashboard/concept']),
            ])
            ->values()
            ->all();

        return [
            'id' => $id,
            'kind' => 'chart-patterns',
            'rows' => $rows,
            'emptyText' => __('Keine Chartmuster oder Indikatorübergänge in den letzten 24 Stunden.'),
        ];
    }

    private function urlFor(string $tileId): string
    {
        return match ($tileId) {
            'watchlists' => route('watchlists.index'),
            'strategies' => route('setup.saved-filters.index'),
            'labels' => route('setup.labels.index'),
            'chartview' => route('predictions.chartview-signals'),
            'market-report' => route('daily-market-analysis'),
            'signal-transitions' => route('predictions.index'),
            'upcoming-news' => route('upcoming-events.index'),
            'earnings-drift' => route('upcoming-events.index'),
            'classic-dashboard' => route('dashboard'),
            default => route('dashboard'),
        };
    }

    /**
     * Every serving-signal transition (raw signal at as_of N differs from
     * the same instrument/horizon/variant's immediately preceding as_of)
     * within the last 48h - a live audit trail of what moved and why. Reads
     * serving_predictions (10/20/40T, Standard-Ensemble/Pure TCN) restricted
     * to each instrument's champion release via serving_active_models - the
     * 2026-09-17 replacement for the retired local walk-forward pipeline
     * (predictions/trained_models, 5/10/15/20T - see
     * AutomatedPortfolioService::scan(), disabled the same day).
     */
    private function signalTransitionSection(string $id): array
    {
        $previousRawSignalSql = "(SELECT UPPER(pp.signal)
            FROM serving_predictions pp
            WHERE pp.instrument_id = prediction.instrument_id
              AND pp.horizon = prediction.horizon
              AND pp.variant = prediction.variant
              AND pp.id < prediction.id
            ORDER BY pp.as_of DESC, pp.id DESC LIMIT 1)";

        $rows = \DB::connection('serving')->table('serving_predictions as prediction')
            ->join('serving_active_models as active', fn ($join) => $join
                ->on('active.instrument_id', '=', 'prediction.instrument_id')
                ->on('active.release_id', '=', 'prediction.release_id'))
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->where('instrument.instrument_type', 'stock')
            ->where('prediction.as_of', '>=', now()->subHours(48))
            ->selectRaw("
                prediction.id, instrument.symbol, instrument.name, prediction.as_of,
                prediction.variant, prediction.horizon,
                {$previousRawSignalSql} as previous_signal,
                UPPER(prediction.signal) as raw_signal,
                prediction.confidence, prediction.risk_score, prediction.expected_return,
                prediction.compact_context
            ")
            ->get()
            ->filter(fn (object $row): bool => $row->previous_signal !== null && $row->previous_signal !== $row->raw_signal)
            ->sortByDesc('as_of')
            ->take(100)
            ->map(function (object $row): array {
                $context = json_decode((string) $row->compact_context, true) ?? [];

                return [
                    'time' => $row->as_of,
                    'symbol' => $row->symbol,
                    'name' => $row->name,
                    'variant' => $row->variant === 'pure_tcn' ? 'Pure TCN' : 'Standard-Ensemble',
                    'horizon_days' => (int) $row->horizon,
                    'previous_signal' => $row->previous_signal,
                    'raw_signal' => $row->raw_signal,
                    'confidence' => is_numeric($row->confidence) ? (float) $row->confidence : null,
                    'risk' => is_numeric($row->risk_score) ? (float) $row->risk_score : null,
                    'expected_return' => is_numeric($row->expected_return) ? (float) $row->expected_return * 100 : null,
                    'quality_gate_passed' => data_get($context, 'quality_gate.passed'),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $id,
            'kind' => 'signal-transitions',
            'rows' => $rows,
            'emptyText' => __('Keine Signalübergänge in den letzten 48 Stunden.'),
        ];
    }

    /**
     * Reads the static JSON snapshot news:export-recent-json writes (via
     * news:sync-press-releases -> Twelve Data press_releases). Kept as a
     * plain file rather than a live query so the "News" tab stays fast and
     * the exact exported snapshot is inspectable independent of the DB.
     */
    private function newsSection(string $id): array
    {
        $filePath = storage_path('app/cache/recent_news.json');
        $items = [];
        $generatedAt = null;

        if (file_exists($filePath)) {
            $payload = json_decode(file_get_contents($filePath), true);
            if (is_array($payload)) {
                $generatedAt = $payload['generated_at'] ?? null;
                $items = collect($payload['items'] ?? [])->take(30)->map(fn (array $item): array => [
                    ...$item,
                    'url' => filled($item['symbol'] ?? null)
                        ? route('stocks.show', ['symbol' => $item['symbol'], 'return_to' => '/dashboard/concept'])
                        : null,
                ])->all();
            }
        }

        return [
            'id' => $id,
            'kind' => 'news',
            'items' => $items,
            'generated_at' => $generatedAt,
        ];
    }

    private function loadCachedInsights(array $highlights): ?array
    {
        $filePath = storage_path('app/cache/today_highlights.json');
        if (! file_exists($filePath)) {
            return null;
        }

        try {
            $mtime = filemtime($filePath);
            if ($mtime < now()->startOfDay()->timestamp) {
                return null;
            }

            $cached = json_decode(file_get_contents($filePath), true);
            if (! is_array($cached) || ! isset($cached['insights'], $cached['data_snapshot'])) {
                return null;
            }

            // The cache is only valid for the exact highlight data it was
            // generated from - a stale or manually re-run cache (e.g. from
            // testing against a different date) must never be shown
            // alongside today's actual (possibly empty) highlight cards.
            $currentSnapshot = array_map(fn (array $h) => $h['data'], $highlights);
            if ($cached['data_snapshot'] !== $currentSnapshot) {
                return null;
            }

            return $cached['insights'];
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Reuses DashboardController::buildViewData() - the exact same data the
     * real /dashboard page computes - instead of recomputing any of it
     * differently here. Only the small layout-helper closures/values that
     * dashboard.blade.php itself defines inline (card order/visibility/size,
     * the country-flag lookup) are not part of that data and are rebuilt
     * here in a simplified, always-visible form: this concept preview does
     * not support the real dashboard's per-user card drag/resize
     * customization, it just shows every card in its default place.
     */
    private function todayFocusSection(string $id): array
    {
        // TEMPORARY DEMO OVERRIDE: showing yesterday's complete trading day
        // so the user can see a fully-populated example. Revert to
        // now()->toDateString() afterwards.
        $date = now()->subDay()->toDateString();
        $highlights = app(TodayHighlightsBuilder::class)->build($date)['highlights'];

        $insights = $this->loadCachedInsights($highlights)
            ?? app(TodayHighlightsAnalysisService::class)->analyzeHighlights($highlights);

        $highlights = array_map(function ($h, $idx) use ($insights, $date) {
            // The 5th "Indikatoren" card is structured data, not a
            // signal needing an AI narrative, so it has no insight key.
            $keys = ['top_signal_insight', 'swing_insight', 'surprise_insight', 'trend_switch_insight'];
            $h['insight'] = $insights[$keys[$idx] ?? null] ?? '';
            // Every highlight in this batch describes the same trading
            // day - shown small top-right on each card so it stays
            // obvious this is (currently) yesterday's data, not today's.
            $h['date'] = $date;

            return $h;
        }, $highlights, array_keys($highlights));

        // A trading day without e.g. any SELL->BUY flip is normal, not
        // broken - don't show a "Keine Daten heute" card for it, only the
        // highlights that actually have something to show.
        $highlights = array_values(array_filter(
            $highlights,
            fn (array $h): bool => ($h['kind'] ?? null) === 'indicators' ? $h['indicators'] !== null : $h['data'] !== null,
        ));

        return [
            'id' => $id,
            'kind' => 'today-focus',
            'highlights' => $highlights,
        ];
    }

    private function stockOfTheDaySection(string $id, Request $request): array
    {
        $today = now()->toDateString();

        $predictions = \DB::connection('serving')->table('serving_predictions as sp')
            ->select([
                'sp.instrument_id',
                'sp.horizon',
                'sp.expected_return',
                'sp.calibrated_score',
                'sp.risk_score',
            ])
            ->whereDate('sp.created_at', $today)
            ->get();

        if ($predictions->isEmpty()) {
            return [
                'id' => $id,
                'kind' => 'stock-of-day',
                'available' => false,
            ];
        }

        $byInstrument = $predictions->groupBy('instrument_id')->map(function ($group) {
            $maxReturn = $group->max(fn ($p) => (float) ($p->expected_return ?? 0));
            $score = $group->first()->calibrated_score ?? null;
            $risk = $group->first()->risk_score ?? null;

            return [
                'max_return' => $maxReturn,
                'score' => $score,
                'risk' => $risk,
                'horizons' => $group->keyBy('horizon')->mapWithKeys(fn ($p, $h) => [(int) $h.'T' => (float) ($p->expected_return ?? 0) * 100]),
            ];
        })->sortByDesc('max_return')->first();

        if (! $byInstrument) {
            return [
                'id' => $id,
                'kind' => 'stock-of-day',
                'available' => false,
            ];
        }

        $instrumentId = $predictions->groupBy('instrument_id')->sortByDesc(fn ($g) => $g->max(fn ($p) => (float) ($p->expected_return ?? 0)))->keys()->first();
        $instrument = \DB::table('instruments')->where('id', $instrumentId)->select(['symbol', 'name', 'country'])->first();

        if (! $instrument) {
            return [
                'id' => $id,
                'kind' => 'stock-of-day',
                'available' => false,
            ];
        }

        return [
            'id' => $id,
            'kind' => 'stock-of-day',
            'available' => true,
            'symbol' => $instrument->symbol,
            'name' => $instrument->name ?: $instrument->symbol,
            'country' => $instrument->country,
            'url' => route('stocks.show', ['symbol' => $instrument->symbol, 'return_to' => '/dashboard/concept']),
            'currentPrice' => null,
            'compositeScore' => is_numeric($byInstrument['score']) ? (float) $byInstrument['score'] : null,
            'riskScore' => is_numeric($byInstrument['risk']) ? (float) $byInstrument['risk'] : null,
            'horizons' => [
                '10T' => $byInstrument['horizons']['10T'] ?? null,
                '20T' => $byInstrument['horizons']['20T'] ?? null,
                '40T' => $byInstrument['horizons']['40T'] ?? null,
            ],
        ];
    }

    private function classicDashboardSection(string $id, Request $request): array
    {
        $data = app(DashboardController::class)->buildViewData($request);

        return [
            'id' => $id,
            'kind' => 'classic-dashboard',
            'viewData' => array_merge($data, [
                'dashboardCardSize' => fn (string $cardId): string => '2',
                'dashboardCardOrder' => fn (string $cardId): int => 0,
                'dashboardCardVisible' => fn (string $cardId): bool => true,
                'dashboardMarketVisible' => true,
                'dashboardCountryFlags' => [
                    'DE' => '🇩🇪', 'US' => '🇺🇸', 'AT' => '🇦🇹', 'CH' => '🇨🇭', 'GB' => '🇬🇧', 'FR' => '🇫🇷',
                    'NL' => '🇳🇱', 'DK' => '🇩🇰', 'SE' => '🇸🇪', 'NO' => '🇳🇴', 'FI' => '🇫🇮', 'IT' => '🇮🇹',
                    'ES' => '🇪🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'HK' => '🇭🇰', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
                ],
                // dashboard.blade.php derives these inline from $marketSituation
                // rather than returning them from buildViewData() - the
                // right-column partial's market-outlook card needs the same
                // two booleans.
                'marketOutlookIsNeutral' => mb_strtolower(trim((string) ($data['marketSituation']?->market_outlook ?? ''))) === 'neutral',
                'marketRiskIsHigh' => mb_strtolower(trim((string) ($data['marketSituation']?->risk_level ?? ''))) === 'high',
            ]),
        ];
    }
}

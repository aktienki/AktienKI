<?php

namespace App\Http\Controllers;

use App\Models\SmartSelectionLabel;
use App\Models\User;
use App\Services\EarningsDriftStatsService;
use App\Services\PanelScoreDriftStatsService;
use App\Services\ServingMarketSnapshotService;
use Illuminate\Http\Request;
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
    /** The core navigation destinations for the left column. */
    private const LEFT_COLUMN_ICONS = [
        ['today-focus', 'Heute im Fokus', 'heroicon-o-fire'],
        ['classic-dashboard', 'Dashboard', 'heroicon-o-squares-2x2'],
        ['watchlists', 'Watchlists', 'heroicon-o-star'],
        ['strategies', 'Strategien', 'heroicon-o-adjustments-horizontal'],
        ['labels', 'Labels', 'heroicon-o-tag'],
        ['chartview', 'ChartView', 'heroicon-o-chart-bar-square'],
        ['watchlist-screener', 'Watchlist im Screener', 'heroicon-o-funnel'],
        ['predictions', 'Prognosetabelle', 'heroicon-o-table-cells'],
        ['smart-screener', 'Smart Screener', 'heroicon-o-magnifying-glass'],
        ['market-report', 'Aktuelle Marktlage', 'heroicon-o-globe-europe-africa'],
        ['stock-of-day', 'Aktie des Tages', 'heroicon-o-sparkles'],
        ['upcoming-news', 'Anstehende News', 'heroicon-o-calendar-days'],
        ['earnings-drift', 'Quartalszahlen-Historie', 'heroicon-o-chart-bar'],
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $snapshot = app(ServingMarketSnapshotService::class)->snapshot();

        $leftIcons = collect(self::LEFT_COLUMN_ICONS)->map(fn (array $item): array => [
            'id' => $item[0],
            'label' => $item[1],
            'icon' => $item[2],
            'url' => $this->urlFor($item[0]),
        ]);

        $sections = collect([
            $this->todayFocusSection('today-focus'),
            $this->listSection(
                'watchlists',
                $user->watchlists()->orderByDesc('is_default')->orderBy('name')->limit(5)->pluck('name'),
                $user->watchlists()->count(),
                __('Noch keine Watchlist angelegt.'),
            ),
            $this->listSection(
                'strategies',
                $user->savedPredictionFilters()->orderByDesc('id')->limit(5)->pluck('name'),
                $user->savedPredictionFilters()->count(),
                __('Noch keine Strategie gespeichert.'),
            ),
            $this->listSection(
                'labels',
                SmartSelectionLabel::query()->where('user_id', $user->id)->orderByDesc('id')->limit(5)->pluck('name'),
                SmartSelectionLabel::query()->where('user_id', $user->id)->count(),
                __('Noch kein Label angelegt.'),
            ),
            $this->ctaSection('chartview', __('Chartmuster und Signale in der interaktiven Chartansicht erkunden.')),
            $this->ctaSection('watchlist-screener', __('Die eigene Watchlist mit den Screener-Filtern kombinieren.')),
            $this->ctaSection('predictions', __('Alle aktuellen KI-Prognosen in der vollständigen Tabelle ansehen.')),
            $this->ctaSection('smart-screener', __('Aktien nach eigenen Kriterien filtern und sortieren.')),
            $this->marketSection('market-report', $snapshot, $user),
            $this->stockOfTheDaySection('stock-of-day', $request),
            $this->eventsSection('upcoming-news', $request),
            $this->earningsDriftSection('earnings-drift', $request),
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

    private function ctaSection(string $id, string $description): array
    {
        return [
            'id' => $id,
            'kind' => 'cta',
            'description' => $description,
        ];
    }

    /**
     * Also folds in the Screener/dashboard's "Aktuelle Remote-Aktien" stock
     * count + signal-distribution card (DashboardController::
     * profileUniverseStats(), the same data the real dashboard and the
     * classic-dashboard tab show) - not just the market-wide score/summary
     * text this section used to show alone.
     */
    private function marketSection(string $id, array $snapshot, User $user): array
    {
        $assessment = $snapshot['assessment'] ?? null;
        $metrics = $snapshot['analysis']['metrics'] ?? [];

        return [
            'id' => $id,
            'kind' => 'market',
            'available' => (bool) ($snapshot['available'] ?? false),
            'assessment' => $assessment,
            'metrics' => array_slice($metrics, 0, 4),
            'profileUniverseStats' => app(DashboardController::class)->profileUniverseStats($user),
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
            ->take(8)
            ->values();

        return [
            'id' => $id,
            'kind' => 'earnings-drift',
            'rows' => $rows->all(),
            'emptyText' => __('Für keine Aktie mit bevorstehenden Quartalszahlen liegt bereits eigene Historie vor.'),
        ];
    }

    private function urlFor(string $tileId): string
    {
        return match ($tileId) {
            'watchlists' => route('watchlists.index'),
            'strategies' => route('setup.saved-filters.index'),
            'labels' => route('setup.labels.index'),
            'chartview' => route('predictions.chartview-signals'),
            'watchlist-screener' => route('screener.index', ['bestand' => 'watchlists']),
            'predictions' => route('predictions.index'),
            'smart-screener' => route('screener.index'),
            'market-report' => route('daily-market-analysis'),
            'upcoming-news' => route('upcoming-events.index'),
            'earnings-drift' => route('upcoming-events.index'),
            'classic-dashboard' => route('dashboard'),
            default => route('dashboard'),
        };
    }

    private function loadCachedInsights(): ?array
    {
        $filePath = storage_path('app/cache/today_highlights.json');
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            $mtime = filemtime($filePath);
            if ($mtime < now()->startOfDay()->timestamp) {
                return null;
            }

            $json = file_get_contents($filePath);
            return json_decode($json, true) ?: null;
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
        $topSignal = null;
        $swingStock = null;
        $surpriseSignal = null;
        $trendSwitch = null;

        // 1. Top-Signal: Best BUY today by expected return (from materialized view)
        $topBuy = \DB::table('today_highlights_mv')
            ->where('serving_signal', 'BUY')
            ->orderByDesc('expected_return')
            ->select('instrument_id', 'expected_return', 'symbol', 'name')
            ->first();

        if ($topBuy) {
            $topSignal = [
                'symbol' => $topBuy->symbol,
                'name' => $topBuy->name,
                'return' => (float)($topBuy->expected_return ?? 0) * 100,
                'url' => route('stocks.show', ['symbol' => $topBuy->symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        // 2. Grösster Swing: Best/worst held position by performance
        $holdingSwings = \DB::table('portfolio_positions as pp')
            ->join('instruments as i', 'i.id', '=', 'pp.instrument_id')
            ->select([
                'i.symbol',
                'i.name',
                'pp.current_price',
                'pp.average_buy_price',
                \DB::raw('(pp.current_price - pp.average_buy_price) / NULLIF(pp.average_buy_price, 0) * 100 as perf_pct'),
            ])
            ->where('pp.quantity', '>', 0)
            ->orderByDesc(\DB::raw('ABS((pp.current_price - pp.average_buy_price) / NULLIF(pp.average_buy_price, 0) * 100)'))
            ->first();

        if ($holdingSwings) {
            $swingStock = [
                'symbol' => $holdingSwings->symbol,
                'name' => $holdingSwings->name,
                'perf_pct' => (float)$holdingSwings->perf_pct,
                'url' => route('stocks.show', ['symbol' => $holdingSwings->symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        // 3. Überraschung: BUY signal when yesterday was SELL/HOLD
        $surprise = \DB::table('today_highlights_mv')
            ->where('serving_signal', 'BUY')
            ->whereIn('predictions_signal', ['SELL', 'HOLD'])
            ->orderByDesc('expected_return')
            ->select('instrument_id', 'expected_return', 'symbol', 'name')
            ->first();

        if ($surprise) {
            $surpriseSignal = [
                'symbol' => $surprise->symbol,
                'name' => $surprise->name,
                'return' => (float)($surprise->expected_return ?? 0) * 100,
                'url' => route('stocks.show', ['symbol' => $surprise->symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        // 4. Trendwechsel: SELL yesterday → BUY today (from materialized view)
        $trendSwitch_row = \DB::table('today_highlights_mv')
            ->where('predictions_signal', 'SELL')
            ->where('serving_signal', 'BUY')
            ->select('symbol', 'name')
            ->first();

        if ($trendSwitch_row) {
            $trendSwitch = [
                'symbol' => $trendSwitch_row->symbol,
                'name' => $trendSwitch_row->name,
                'url' => route('stocks.show', ['symbol' => $trendSwitch_row->symbol, 'return_to' => '/dashboard/concept']),
            ];
        }

        $highlights = [
            [
                'label' => __('Top-Signal'),
                'subtitle' => __('Beste neue BUY-Empfehlung'),
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'emerald',
                'data' => $topSignal ? sprintf('%s +%.1f%%', $topSignal['symbol'], $topSignal['return']) : null,
                'url' => $topSignal['url'] ?? null,
            ],
            [
                'label' => __('Grösster Swing'),
                'subtitle' => __('Positionäre Performance heute'),
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'orange',
                'data' => $swingStock ? sprintf('%s %+.1f%%', $swingStock['symbol'], $swingStock['perf_pct']) : null,
                'url' => $swingStock['url'] ?? null,
            ],
            [
                'label' => __('Überraschung'),
                'subtitle' => __('Signal gegen den Trend'),
                'icon' => 'heroicon-o-bolt',
                'color' => 'yellow',
                'data' => $surpriseSignal ? sprintf('%s (war HOLD)', $surpriseSignal['symbol']) : null,
                'url' => $surpriseSignal['url'] ?? null,
            ],
            [
                'label' => __('Trendwechsel'),
                'subtitle' => __('Von SELL zu BUY geflipped'),
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'cyan',
                'data' => $trendSwitch ? $trendSwitch['symbol'] : null,
                'url' => $trendSwitch['url'] ?? null,
            ],
        ];

        $insights = $this->loadCachedInsights()
            ?? app(\App\Services\TodayHighlightsAnalysisService::class)->analyzeHighlights($highlights);

        return [
            'id' => $id,
            'kind' => 'today-focus',
            'highlights' => array_map(function ($h, $idx) use ($insights) {
                $keys = ['top_signal_insight', 'swing_insight', 'surprise_insight', 'trend_switch_insight'];
                $h['insight'] = $insights[$keys[$idx]] ?? '';
                return $h;
            }, $highlights, array_keys($highlights)),
            'analogs' => [],
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
                'horizons' => $group->keyBy('horizon')->mapWithKeys(fn ($p, $h) => [(int)$h.'T' => (float)($p->expected_return ?? 0) * 100]),
            ];
        })->sortByDesc('max_return')->first();

        if (!$byInstrument) {
            return [
                'id' => $id,
                'kind' => 'stock-of-day',
                'available' => false,
            ];
        }

        $instrumentId = $predictions->groupBy('instrument_id')->sortByDesc(fn ($g) => $g->max(fn ($p) => (float) ($p->expected_return ?? 0)))->keys()->first();
        $instrument = \DB::table('instruments')->where('id', $instrumentId)->select(['symbol', 'name', 'country'])->first();

        if (!$instrument) {
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

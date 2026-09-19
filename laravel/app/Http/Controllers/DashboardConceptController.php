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
use App\Services\ServingScreenerService;
use App\Services\TodayHighlightsAnalysisService;
use App\Services\TodayHighlightsBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A small, standalone layout concept for the "Persönlicher Bereich" - the
 * left column keeps its fixed icon grid, but clicking an icon now swaps
 * the main area's content instead of navigating away: each icon gets a
 * short overview of its own section, defaulting to "Heute im Fokus".
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
        ['termine', 'Termine', 'heroicon-o-calendar-days'],
        ['market-report', 'Aktuelle Marktlage', 'heroicon-o-globe-europe-africa'],
        ['chartview', 'ChartView', 'heroicon-o-chart-bar-square'],
        ['pattern-analysis', 'Muster & Wahrscheinlichkeiten', 'heroicon-o-puzzle-piece'],
        ['opportunities-risks', 'Chancen & Risiken', 'heroicon-o-scale'],
        ['classic-dashboard', 'Depots', 'heroicon-o-squares-2x2'],
    ];

    /** event_key => [label, tone] - mirrors ChartPatternSignalService's own
     *  SQL VALUES(...) label mapping (see recentEvents()) so the ranking
     *  here uses the exact same German labels the ChartView cards show. */
    private const PATTERN_EVENT_LABELS = [
        'golden_cross' => ['Golden Cross: SMA 50 über SMA 200', 'positive'],
        'death_cross' => ['Death Cross: SMA 50 unter SMA 200', 'negative'],
        'price_above_sma50' => ['Kurs über SMA 50', 'positive'],
        'price_below_sma50' => ['Kurs unter SMA 50', 'negative'],
        'rsi_oversold' => ['RSI überverkauft', 'positive'],
        'rsi_overbought' => ['RSI überkauft', 'negative'],
        'resistance_breakout' => ['Widerstand überschritten', 'positive'],
        'support_breakdown' => ['Unterstützung unterschritten', 'negative'],
        'pattern_bullish_engulfing' => ['Chartmuster: Bullish Engulfing', 'positive'],
        'pattern_bearish_engulfing' => ['Chartmuster: Bearish Engulfing', 'negative'],
        'pattern_bullish_pin_bar' => ['Chartmuster: Bullish Pin Bar', 'positive'],
        'pattern_bearish_pin_bar' => ['Chartmuster: Bearish Pin Bar', 'negative'],
        'pattern_upside_breakout' => ['Chartmuster: 20-Tage-Ausbruch nach oben', 'positive'],
        'pattern_downside_breakout' => ['Chartmuster: 20-Tage-Ausbruch nach unten', 'negative'],
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
            $this->todayFocusSection('today-focus', $request),
            $this->terminSection('termine', $request),
            $this->chartPatternSection('chartview'),
            $this->patternAnalysisSection('pattern-analysis'),
            $this->marketSection('market-report', $snapshot),
            $this->opportunitiesRisksSection('opportunities-risks', $snapshot),
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
        ]);
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
     * The full-length version of the market section's Chancen/Risiken/
     * Beobachtungsliste preview (there capped at 5 each) - same snapshot(),
     * no extra query, just the uncapped opportunitiesFull/risksFull/
     * watchlistFull keys ServingMarketSnapshotService::analysis() computes
     * alongside the top-5 ones.
     */
    private function opportunitiesRisksSection(string $id, array $snapshot): array
    {
        $external = $this->externalMarketAnalysis();
        if ($external) {
            return [
                'id' => $id,
                'kind' => 'opportunities-risks',
                'available' => true,
                'source' => 'external',
                'date' => $external['date'],
                'opportunities' => $external['opportunities'],
                'risks' => $external['risks'],
                'watchlist' => $external['watchlist'],
            ];
        }

        $analysis = $snapshot['analysis'] ?? [];

        return [
            'id' => $id,
            'kind' => 'opportunities-risks',
            'available' => (bool) ($snapshot['available'] ?? false),
            'source' => 'internal',
            'date' => $analysis['date'] ?? null,
            'opportunities' => $analysis['opportunitiesFull'] ?? [],
            'risks' => $analysis['risksFull'] ?? [],
            'watchlist' => $analysis['watchlistFull'] ?? [],
        ];
    }

    /**
     * The daily web-researched market report (GenerateDailyMarketReport,
     * OpenAI with a live web_search tool - real citations, not just this
     * app's own data) - preferred over the rule-based internal snapshot's
     * Chancen/Risiken/Beobachtungsliste when available, same fallback
     * MarketData::loadExternalMarketAnalysis() was built for (that call site
     * has since gone missing there - the method itself is still intact, this
     * just calls the same query directly rather than depending on a Livewire
     * component's private method).
     *
     * @return array{date: string, opportunities: list<string>, risks: list<string>, watchlist: list<string>}|null
     */
    private function externalMarketAnalysis(): ?array
    {
        $analysis = \DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first();
        if (! $analysis) {
            return null;
        }

        $raw = is_string($analysis->raw_response ?? null)
            ? json_decode($analysis->raw_response, true)
            : (array) ($analysis->raw_response ?? []);
        if (! data_get($raw, 'external_research', false)) {
            return null;
        }

        $decode = fn (mixed $value): array => is_array($value) ? $value : (is_array($decoded = json_decode((string) $value, true)) ? $decoded : []);

        return [
            'date' => (string) $analysis->analysis_date,
            'opportunities' => $decode($analysis->opportunities),
            'risks' => $decode($analysis->risks),
            // Unlike opportunities/risks (plain strings), watchlist entries
            // are {symbol, reason} objects - flatten to the same "Symbol:
            // Begründung" shape the rest of this tile's lists use.
            'watchlist' => collect($decode($analysis->watchlist))
                ->map(fn ($item): string => is_array($item)
                    ? trim(($item['symbol'] ?? '').': '.($item['reason'] ?? ''), ': ')
                    : (string) $item)
                ->values()->all(),
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
        // Same 0-100 composite score and cross-sectional panel percentile/
        // decile shown on the screener and stock detail page - keyed once
        // per request instead of per row.
        $scoreUniverse = app(ServingScreenerService::class)->currentStocks()->keyBy('instrument_id');

        $rows = app(ChartPatternSignalService::class)->recentEvents()
            ->take(60)
            ->map(function (array $event) use ($scoreUniverse): array {
                $scored = $scoreUniverse->get($event['instrument_id']);

                return [
                    'time' => $event['time'],
                    'symbol' => $event['symbol'],
                    'name' => $event['name'],
                    'country_flag' => self::COUNTRY_FLAGS[$event['country']] ?? '🌐',
                    'label' => $event['label'],
                    'tone' => $event['tone'],
                    'change_pct' => $event['change_pct'],
                    'candles' => $event['candles'],
                    'indicator_series' => $event['indicator_series'],
                    'overlays' => $event['overlays'],
                    'pattern_range' => $event['pattern_range'],
                    'breakout_level' => $event['breakout_level'],
                    'breakout_line_y' => $event['breakout_line_y'],
                    'rise_probability_20d' => $event['rise_probability_20d'],
                    'average_return_20d' => $event['average_return_20d'],
                    'probability_sample_size' => $event['probability_sample_size'],
                    'probability_scope' => $event['probability_scope'],
                    'instrument_occurrence_count' => $event['instrument_occurrence_count'],
                    'score' => $scored?->composite_score !== null ? (int) $scored->composite_score : null,
                    'panel_percentile' => $scored?->panel_percentile !== null ? (int) $scored->panel_percentile : null,
                    'panel_decile' => $scored?->panel_decile !== null ? (int) $scored->panel_decile : null,
                    'url' => route('stocks.show', ['symbol' => $event['symbol'], 'return_to' => '/dashboard/concept']),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $id,
            'kind' => 'chart-patterns',
            'rows' => $rows,
            'emptyText' => __('Keine Chartmuster oder Indikatorübergänge in den letzten 24 Stunden.'),
        ];
    }

    /**
     * Surfaces the statistical "has this happened before, and what tended
     * to follow" methodology already built for ChartView, EarningsDrift and
     * the panel model - as rankings/aggregates instead of per-event cards,
     * so a pattern's overall track record is visible without having to spot
     * a live occurrence first. Every number here is plain SQL/statistics
     * (rise_probability, avg forward return), never an LLM guess.
     */
    private function patternAnalysisSection(string $id): array
    {
        // Same minimum-sample-size discipline established earlier this
        // session for the serving-model profit-factor analysis: a handful
        // of occurrences produces an unstable rise_probability, so patterns
        // below this threshold are dropped rather than ranked alongside
        // well-sampled ones.
        $minSampleSize = 30;

        $chartPatterns = \DB::table('chartview_signal_statistics')
            ->where('sample_size', '>=', $minSampleSize)
            ->get(['event_key', 'rise_probability', 'average_return', 'sample_size'])
            ->map(function (object $row): array {
                [$label, $tone] = self::PATTERN_EVENT_LABELS[$row->event_key] ?? [$row->event_key, 'neutral'];

                return [
                    'event_key' => $row->event_key,
                    'label' => $label,
                    'tone' => $tone,
                    'rise_probability' => round((float) $row->rise_probability, 1),
                    'average_return' => is_numeric($row->average_return) ? round((float) $row->average_return, 1) : null,
                    'sample_size' => (int) $row->sample_size,
                ];
            })
            ->sortByDesc(fn (array $row): float => abs($row['rise_probability'] - 50))
            ->take(8)
            ->values()
            ->all();

        // Explicit, actionable candidates for the next ~48h: patterns that
        // actually just triggered (not the abstract ranking above), kept
        // only when their own historical track record clears a real edge
        // (>=60%, same minimum sample as the ranking) - not every fresh
        // signal, just the ones worth acting on. Reuses recentEvents()'s
        // existing 1-day window widened to 2 days (its own days param,
        // added for this) rather than a separate detection query.
        $minOpportunityProbability = 60.0;
        $opportunities = app(ChartPatternSignalService::class)->recentEvents(2)
            ->map(function (array $event): array {
                $isBearish = $event['tone'] === 'negative';
                $scenarioProbability = $event['rise_probability_20d'] === null
                    ? null
                    : ($isBearish ? 100 - $event['rise_probability_20d'] : $event['rise_probability_20d']);

                return [
                    'symbol' => $event['symbol'],
                    'name' => $event['name'],
                    'country_flag' => self::COUNTRY_FLAGS[$event['country']] ?? '🌐',
                    'label' => $event['label'],
                    'tone' => $event['tone'],
                    'time' => $event['time'],
                    'sample_size' => $event['probability_sample_size'],
                    'scenario_probability' => $scenarioProbability,
                    'url' => route('stocks.show', ['symbol' => $event['symbol'], 'return_to' => '/dashboard/concept']),
                ];
            })
            ->filter(fn (array $row): bool => $row['scenario_probability'] !== null
                && $row['scenario_probability'] >= $minOpportunityProbability
                && $row['sample_size'] !== null && $row['sample_size'] >= $minSampleSize)
            ->sortByDesc('scenario_probability')
            ->take(10)
            ->values()
            ->all();

        // "Quartalszahlen in den nächsten 48h - wie reagiert der Markt
        // normalerweise, Enttäuschungs-/Überraschungspotenzial?": the event
        // itself is forward-looking (corporate_events), the answer is this
        // stock's own historical beat/miss reaction (earnings_price_reactions
        // via EarningsDriftStatsService) - no minimum sample here, since
        // "kaum Historie vorhanden" is itself useful information for an
        // imminent report, not something to hide.
        $upcomingEarnings = \App\Models\CorporateEvent::query()
            ->with('instrument:id,symbol,name')
            ->where('event_type', 'earnings')
            ->whereBetween('event_date', [now()->toDateString(), now()->addHours(48)->toDateString()])
            ->get()
            ->filter(fn (\App\Models\CorporateEvent $event): bool => $event->instrument !== null);
        $earningsHistory = app(EarningsDriftStatsService::class)
            ->forInstruments($upcomingEarnings->pluck('instrument_id')->unique()->all());
        $upcomingEarningsWithHistory = $upcomingEarnings
            ->map(function (\App\Models\CorporateEvent $event) use ($earningsHistory): array {
                $history = $earningsHistory->get($event->instrument_id);

                return [
                    'symbol' => $event->instrument->symbol,
                    'name' => $event->instrument->name,
                    'date' => Carbon::parse($event->event_date)->format('d.m.Y'),
                    'post3dBeat' => $history && $history['post3dBeat'] !== null ? round($history['post3dBeat'], 1) : null,
                    'post3dMiss' => $history && $history['post3dMiss'] !== null ? round($history['post3dMiss'], 1) : null,
                    'n' => $history['n'] ?? 0,
                    'url' => route('stocks.show', ['symbol' => $event->instrument->symbol, 'return_to' => '/dashboard/concept']),
                ];
            })
            ->sortByDesc(fn (array $row): float => max(abs($row['post3dBeat'] ?? 0), abs($row['post3dMiss'] ?? 0)))
            ->take(10)
            ->values()
            ->all();

        $panelDeciles = app(PanelScoreDriftStatsService::class)->decileBreakdown()
            ->map(fn (array $row): array => [
                'decile' => $row['decile'],
                'avg_forward_return' => round($row['avgForwardReturn'] * 100, 1),
                'sample_size' => $row['n'],
            ])
            ->all();
        $maxAbsPanelReturn = max(1.0, collect($panelDeciles)->map(fn (array $row) => abs($row['avg_forward_return']))->max() ?: 1.0);

        $earningsDrift = app(EarningsDriftStatsService::class)->forAllStocks(minSample: 5)
            ->map(fn (array $row): array => [
                'symbol' => $row['symbol'],
                'name' => $row['name'],
                'n' => $row['n'],
                // return_post_3d/post_3d are already stored as percent
                // values (e.g. -9.03 = -9.03%), not decimal fractions -
                // no *100 here, unlike the panel model's fwd_ret_20d below.
                'post3dBeat' => $row['post3dBeat'] !== null ? round($row['post3dBeat'], 1) : null,
                'post3dMiss' => $row['post3dMiss'] !== null ? round($row['post3dMiss'], 1) : null,
            ])
            ->sortByDesc(fn (array $row): float => max(abs($row['post3dBeat'] ?? 0), abs($row['post3dMiss'] ?? 0)))
            ->take(8)
            ->values()
            ->all();

        $sectorScores = app(ServingScreenerService::class)->currentStocks()
            ->filter(fn (object $stock): bool => filled($stock->sector) && $stock->composite_score !== null)
            ->groupBy('sector')
            ->map(fn (Collection $stocks, string $sector): array => [
                'sector' => $sector,
                'avg_score' => round($stocks->avg('composite_score'), 1),
                'stock_count' => $stocks->count(),
            ])
            ->filter(fn (array $row): bool => $row['stock_count'] >= 5)
            ->sortByDesc('avg_score')
            ->values()
            ->all();

        return [
            'id' => $id,
            'kind' => 'pattern-analysis',
            'chartPatterns' => $chartPatterns,
            'opportunities' => $opportunities,
            'upcomingEarnings' => $upcomingEarningsWithHistory,
            'panelDeciles' => $panelDeciles,
            'maxAbsPanelReturn' => $maxAbsPanelReturn,
            'earningsDrift' => $earningsDrift,
            'sectorScores' => $sectorScores,
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
            'opportunities-risks' => route('daily-market-analysis'),
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
    private function todayFocusSection(string $id, Request $request): array
    {
        // TEMPORARY DEMO OVERRIDE: showing yesterday's complete trading day
        // so the user can see a fully-populated example. Revert to
        // now()->toDateString() afterwards.
        $date = now()->subDay()->toDateString();

        $dashboard = app(DashboardController::class);
        $stocks = $dashboard->remoteDashboardStocks($request);
        $champion = $dashboard->championSummary($request, $stocks);
        $championIsConfirmed = $champion !== null;

        // No stock currently clears the full three-factor bar most days -
        // championRanking() requires the external review to match the
        // stock's exact CURRENT serving_batch_id, which a slower web-
        // research review often lags behind. Rather than show an empty
        // hero slot then, fall back in two steps: first to a real BUY with
        // a completed NO_OBJECTION review (even if from a slightly earlier
        // batch - it's still an actually-confirmed pick, just not
        // re-verified against today's exact batch), then to the best
        // composite_score stock overall if even that doesn't exist. The
        // card marks both fallback cases explicitly instead of implying
        // full confirmation.
        $champion ??= $stocks
            ->sortByDesc(fn (object $s): array => [
                (($s->personalized_signal ?? null) === 'BUY'
                    && ($s->external_review_status ?? null) === 'completed'
                    && ($s->external_review_verdict ?? null) === 'NO_OBJECTION') ? 1 : 0,
                is_numeric($s->composite_score ?? null) ? (float) $s->composite_score : (float) ($s->ranking_score ?? -1),
            ])
            ->first();

        $championInstrumentId = is_numeric($champion->instrument_id ?? null) ? (int) $champion->instrument_id : null;

        $highlights = app(TodayHighlightsBuilder::class)->build($date, $championInstrumentId)['highlights'];

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
            'champion' => $champion ? $this->buildChampionCard($champion, $date, $championIsConfirmed) : null,
            'highlights' => $highlights,
            'opportunities' => $this->tradingOpportunities($request, $date, $stocks, $champion),
        ];
    }

    /**
     * Earnings + planned-sell events, with the per-event reminder toggle -
     * moved out of "Heute im Fokus" into its own left-nav destination so it
     * isn't squeezed into a small tile (was narrowed to 7 days/6 items for
     * that reason; now shows the full underlying 21-day window). Same data
     * source as classicDashboardSection() (DashboardController::
     * buildViewData()'s strategyPositionEvents).
     */
    private function terminSection(string $id, Request $request): array
    {
        $events = app(DashboardController::class)->buildViewData($request)['strategyPositionEvents']->values();

        if ($events->isNotEmpty()) {
            $symbolToInstrumentId = \DB::table('instruments')
                ->whereIn('symbol', $events->pluck('symbol')->unique())
                ->pluck('id', 'symbol');
            $userId = $request->user()?->id;
            $reminderStates = $userId
                ? \App\Models\CalendarEventReminder::query()
                    ->where('user_id', $userId)
                    ->get(['event_type', 'reference_id', 'enabled'])
                    ->keyBy(fn ($r) => $r->event_type.':'.$r->reference_id)
                : collect();

            $events = $events->map(function (array $event) use ($symbolToInstrumentId, $reminderStates): array {
                // upcomingPositionEvents() ids are "earnings-{corporate_event_id}"
                // / "exit-{position_id}" - the numeric suffix is the reference_id
                // calendar_event_reminders needs, and 'exit' maps to the
                // reminder's own 'sell' event_type.
                $referenceId = (int) \Illuminate\Support\Str::afterLast($event['id'], '-');
                $reminderType = $event['type'] === 'sell' ? 'sell' : 'earnings';
                $reminder = $reminderStates->get($reminderType.':'.$referenceId);
                $event['reference_id'] = $referenceId;
                $event['instrument_id'] = $symbolToInstrumentId->get($event['symbol']);
                $event['reminder_type'] = $reminderType;
                $event['reminder_enabled'] = $reminder?->enabled ?? false;

                return $event;
            });
        }

        return [
            'id' => $id,
            'kind' => 'termine',
            'events' => $events->all(),
        ];
    }

    /**
     * Up to 4 picks, each in the same highlight-card shape as "Heute im
     * Fokus" - reuses DashboardController::championRanking() (externally
     * GPT-confirmed BUY + panel coverage, same composite score the screener
     * and main dashboard show), so these never disagree with those pages
     * about which stocks are "best". That gate is fairly strict and often
     * has fewer than 4 qualifying stocks, so the candidate pool continues
     * into the next-highest composite_score stocks instead of stopping at
     * an empty slot - "best available", not "no data", for a "show 4 if you
     * can" section. Walks the combined, ranked candidate list one at a time
     * and skips any stock whose closest historical analog (see
     * buildOpportunityCard()) resolved negatively - a pick history says lost
     * last time isn't "best available" just because nothing else qualified;
     * skip it and keep going down the list instead. Rendered as its own
     * paragraph, separate from the highlight grid.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * $champion is the single 3-factor champion already shown as its own
     * full-width hero card above - excluded here so it isn't duplicated as
     * "Opportunity #1" too; the next-best alternatives fill its spot.
     */
    private function tradingOpportunities(Request $request, string $date, Collection $stocks, ?object $champion, int $limit = 4): array
    {
        $dashboard = app(DashboardController::class);
        $champions = $dashboard->championRanking($request, $stocks);
        $excludeSymbol = $champion ? strtoupper((string) $champion->symbol) : null;

        $championSymbols = $champions->map(fn (object $s): string => strtoupper((string) $s->symbol))->all();
        $fallback = $stocks
            ->reject(fn (object $s): bool => in_array(strtoupper((string) $s->symbol), $championSymbols, true))
            ->sortByDesc(fn (object $s): float => is_numeric($s->composite_score ?? null) ? (float) $s->composite_score : (float) ($s->ranking_score ?? -1));
        $candidates = $champions->concat($fallback)
            ->reject(fn (object $s): bool => $excludeSymbol !== null && strtoupper((string) $s->symbol) === $excludeSymbol)
            ->values();

        $cards = [];
        foreach ($candidates as $stock) {
            if (count($cards) >= $limit) {
                break;
            }

            $card = $this->buildOpportunityCard($stock, $date, count($cards) + 1);
            if (($card['analog']['outcome_pct'] ?? 0) < 0) {
                continue;
            }

            $cards[] = $card;
        }

        return $cards;
    }

    private function buildOpportunityCard(object $stock, string $date, int $rank): array
    {
        $country = strtoupper((string) ($stock->country ?? ''));
        $expectedReturn = is_numeric($stock->expected_return_20d ?? null) ? (float) $stock->expected_return_20d : null;

        // findAnalog() and aggregate() both compare against
        // walk_forward_backtest_trades.predicted_return, a fraction
        // (0.03 = 3%), whereas expected_return_20d here is a whole percent
        // (2.5 = 2.5%) - divide by 100 to match their scale.
        $comparisonReturn = $expectedReturn !== null ? $expectedReturn / 100 : null;
        $analog = is_numeric($stock->instrument_id ?? null) && $comparisonReturn !== null
            ? app(\App\Services\TodayHighlightsBuilder::class)->findAnalog((int) $stock->instrument_id, $stock->symbol, $comparisonReturn, $date)
            : null;
        // A single nearest case is anecdotal; this aggregates the 20
        // closest-magnitude historical BUY signals (across ALL stocks) into
        // a win-rate/average outcome instead - an independent "does history
        // back this up" read shown next to composite_score, not folded
        // into it.
        $historicalStats = $comparisonReturn !== null
            ? app(\App\Services\HistoricalAnalogStatsService::class)->aggregate($comparisonReturn, 20, $date)
            : null;

        return [
            'label' => __('Trading Opportunities'),
            'subtitle' => __('Bestätigter Kauf #:rank heute', ['rank' => $rank]),
            'icon' => 'heroicon-o-bolt',
            'color' => 'amber',
            // Top-right date stamp, same spot as the other highlights - the
            // signal's own prediction_time, not the generic "which trading
            // day is this batch of cards showing" $date used elsewhere.
            'date' => isset($stock->prediction_time) ? Carbon::parse($stock->prediction_time)->toDateString() : null,
            'insight' => '',
            'data' => $stock->symbol,
            'name' => $stock->name ?? $stock->symbol,
            'url' => route('stocks.show', ['symbol' => $stock->symbol, 'return_to' => '/dashboard/concept']),
            'details' => [
                'country_flag' => self::COUNTRY_FLAGS[$country] ?? '🌐',
                'sector' => $stock->sector ?? null,
                'current_price' => is_numeric($stock->current_price ?? null) ? (float) $stock->current_price : null,
                'currency' => $stock->currency ?? null,
                'risk' => is_numeric($stock->risk_percent ?? null) ? round((float) $stock->risk_percent / 10, 1) : null,
                'confidence' => is_numeric($stock->confidence_percent ?? null) ? round((float) $stock->confidence_percent) : null,
            ],
            'analog' => $analog,
            'historicalStats' => $historicalStats,
            'compositeScore' => is_numeric($stock->composite_score ?? null) ? (float) $stock->composite_score : null,
            'externalReview' => ($stock->external_review_status ?? null) === 'completed' ? [
                'verdict' => $stock->external_review_verdict,
                'summary' => $stock->external_review_summary,
                'confidence' => $stock->external_review_confidence,
                'positive_factors' => $stock->external_review_positive_factors ?? [],
                'risk_factors' => $stock->external_review_risk_factors ?? [],
                'researched_at' => $stock->external_review_researched_at ?? $stock->external_review_triggered_at ?? null,
            ] : null,
            'metric_label' => __('Erwartete Rendite (20T)'),
            'metric_value' => $expectedReturn !== null ? sprintf('%+.1f%%', $expectedReturn) : null,
        ];
    }

    /**
     * The single best three-factor champion (internal ranking + external
     * web-research confirmation + panel-model coverage - see
     * DashboardController::championRanking()), shown as a full-width hero
     * above the rest of Heute im Fokus with more depth (chart, indicators,
     * external review inline) than the compact highlight cards.
     */
    private function buildChampionCard(object $champion, string $date, bool $isConfirmed = true): array
    {
        $country = strtoupper((string) ($champion->country ?? ''));
        $expectedReturn = is_numeric($champion->expected_return_20d ?? null) ? (float) $champion->expected_return_20d : null;
        $instrumentId = is_numeric($champion->instrument_id ?? null) ? (int) $champion->instrument_id : null;
        $builder = app(\App\Services\TodayHighlightsBuilder::class);

        return [
            'label' => $isConfirmed ? __('3-Faktor-Champion') : __('Bester Titel nach Score'),
            'subtitle' => $isConfirmed
                ? __('Intern, extern und über das Panel-Modell bestätigt')
                : __('Kein Titel erfüllt aktuell alle 3 Faktoren – bester Titel nach internem Score'),
            'icon' => $isConfirmed ? 'heroicon-o-trophy' : 'heroicon-o-star',
            'color' => $isConfirmed ? 'emerald' : 'cyan',
            'date' => isset($champion->prediction_time) ? Carbon::parse($champion->prediction_time)->toDateString() : null,
            'name' => $champion->name ?? $champion->symbol,
            'url' => route('stocks.show', ['symbol' => $champion->symbol, 'return_to' => '/dashboard/concept']),
            'details' => [
                'country_flag' => self::COUNTRY_FLAGS[$country] ?? '🌐',
                'sector' => $champion->sector ?? null,
                'current_price' => is_numeric($champion->current_price ?? null) ? (float) $champion->current_price : null,
                'currency' => $champion->currency ?? null,
            ],
            'metric_label' => __('Erwartete Rendite (20T)'),
            'metric_value' => $expectedReturn !== null ? sprintf('%+.1f%%', $expectedReturn) : null,
            'factors' => [
                'internal_score' => is_numeric($champion->composite_score ?? null) ? (float) $champion->composite_score : null,
                'external_confidence' => is_numeric($champion->external_review_confidence ?? null) ? (float) $champion->external_review_confidence : null,
                // The confidence figure alone doesn't say whether the
                // external review was actually favorable - a CAUTION/
                // OBJECTION verdict can still carry a high confidence score,
                // so the card needs the verdict too to color that tile
                // honestly instead of implying "confirmed" either way.
                'external_verdict' => ($champion->external_review_status ?? null) === 'completed' ? $champion->external_review_verdict : null,
                'panel_percentile' => is_numeric($champion->panel_percentile ?? null) ? (float) $champion->panel_percentile : null,
            ],
            'indicators' => $instrumentId ? $builder->technicalIndicators($instrumentId) : null,
            'sparkline' => $instrumentId ? $builder->recentSparkline($instrumentId) : null,
            'externalReview' => ($champion->external_review_status ?? null) === 'completed' ? [
                'verdict' => $champion->external_review_verdict,
                'summary' => $champion->external_review_summary,
                'confidence' => $champion->external_review_confidence,
                'positive_factors' => $champion->external_review_positive_factors ?? [],
                'risk_factors' => $champion->external_review_risk_factors ?? [],
                'researched_at' => $champion->external_review_researched_at ?? $champion->external_review_triggered_at ?? null,
            ] : null,
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

<?php

namespace App\Http\Controllers;

use App\Models\SmartSelectionLabel;
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
        ['watchlists', 'Watchlists', 'heroicon-o-star'],
        ['strategies', 'Strategien', 'heroicon-o-adjustments-horizontal'],
        ['labels', 'Labels', 'heroicon-o-tag'],
        ['chartview', 'ChartView', 'heroicon-o-chart-bar-square'],
        ['watchlist-screener', 'Watchlist im Screener', 'heroicon-o-funnel'],
        ['predictions', 'Prognosetabelle', 'heroicon-o-table-cells'],
        ['smart-screener', 'Smart Screener', 'heroicon-o-magnifying-glass'],
        ['market-report', 'Aktuelle Marktlage', 'heroicon-o-globe-europe-africa'],
        ['upcoming-news', 'Anstehende News', 'heroicon-o-calendar-days'],
        ['earnings-drift', 'Quartalszahlen-Historie', 'heroicon-o-chart-bar'],
        ['classic-dashboard', 'Klassisches Dashboard', 'heroicon-o-squares-2x2'],
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
            $this->marketSection('market-report', $snapshot),
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

    private function marketSection(string $id, array $snapshot): array
    {
        $assessment = $snapshot['assessment'] ?? null;
        $metrics = $snapshot['analysis']['metrics'] ?? [];

        return [
            'id' => $id,
            'kind' => 'market',
            'available' => (bool) ($snapshot['available'] ?? false),
            'assessment' => $assessment,
            'metrics' => array_slice($metrics, 0, 4),
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
            ]),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SmartSelectionLabel;
use App\Services\ServingMarketSnapshotService;
use Illuminate\Http\Request;
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
    /** The 8 core navigation destinations for the left column. */
    private const LEFT_COLUMN_ICONS = [
        ['watchlists', 'Watchlists', 'heroicon-o-star'],
        ['strategies', 'Strategien', 'heroicon-o-adjustments-horizontal'],
        ['labels', 'Labels', 'heroicon-o-tag'],
        ['chartview', 'ChartView', 'heroicon-o-chart-bar-square'],
        ['watchlist-screener', 'Watchlist im Screener', 'heroicon-o-funnel'],
        ['predictions', 'Prognosetabelle', 'heroicon-o-table-cells'],
        ['smart-screener', 'Smart Screener', 'heroicon-o-magnifying-glass'],
        ['market-report', 'Aktuelle Marktlage', 'heroicon-o-globe-europe-africa'],
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
     * @param  \Illuminate\Support\Collection<int, string>  $items
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
            default => route('dashboard'),
        };
    }
}

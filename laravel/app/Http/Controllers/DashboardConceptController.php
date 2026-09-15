<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\SmartSelectionLabel;
use App\Services\ServingMarketSnapshotService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A small, standalone layout concept for the "Persönlicher Bereich" - the
 * left column keeps its fixed 1x8 icon grid, but clicking an icon now swaps
 * the main area's content instead of navigating away: each icon gets a
 * short overview of its own section, and the page defaults to a Musterdepot
 * summary when nothing is selected.
 */
final class DashboardConceptController extends Controller
{
    /**
     * The 8 core navigation destinations for the left column. Musterdepot
     * is deliberately excluded here - it's the page's default view instead
     * of being reduced to an icon among the other 8.
     */
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

        $portfolio = $user->portfolios()
            ->where('type', 'paper')
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $depot = null;
        if ($portfolio instanceof Portfolio) {
            $positions = $portfolio->positions;
            $depot = [
                'id' => $portfolio->id,
                'name' => $portfolio->name,
                'currency' => $portfolio->currency,
                'cashBalance' => (float) ($portfolio->cashAccount?->balance ?? 0),
                'positionCount' => $positions->count(),
                'positionsValue' => $positions->sum(fn ($position): float => (float) ($position->quantity ?? 0)
                    * (float) ($position->current_price ?? $position->average_buy_price ?? 0)),
            ];
        }

        return view('dashboard-concept', [
            'leftIcons' => $leftIcons,
            'sections' => $sections,
            'depot' => $depot,
            'opportunities' => $this->opportunities($request, $snapshot),
        ]);
    }

    /**
     * The first thing shown on the page (before Musterdepot): the same
     * three-factor champion and recent signal changes the main dashboard's
     * cards use, plus the broader market-snapshot "opportunities" list -
     * unlike the champion, that list does not require external confirmation
     * and panel coverage at once, so it stays populated even when the
     * strict champion pool is empty.
     */
    private function opportunities(Request $request, array $snapshot): array
    {
        $dashboard = app(DashboardController::class);
        $champion = $dashboard->championSummary($request);
        $signalChanges = collect($dashboard->signalCockpit()['signalChanges'] ?? [])->take(5)->values();

        $marketOpportunities = collect($snapshot['analysis']['opportunities'] ?? [])
            ->take(5)
            ->map(function (string $line): array {
                // Lines are always "Name (SYMBOL): ...", produced by
                // ServingMarketSnapshotService::stockLine() - parse the
                // symbol back out so each one can link to its stock page.
                preg_match('/\(([^)]+)\):/', $line, $match);

                return ['text' => $line, 'symbol' => $match[1] ?? null];
            })
            ->values();

        return [
            'champion' => $champion,
            'signalChanges' => $signalChanges->all(),
            'marketOpportunities' => $marketOpportunities->all(),
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

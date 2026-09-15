<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A small, standalone layout concept for the "Persönlicher Bereich" -
 * left: a single-column stack of 8 navigation icons, middle: the user's
 * paper portfolio (Musterdepot) shown as a real summary card instead of
 * one more icon+count tile among many.
 */
final class DashboardConceptController extends Controller
{
    /**
     * The 8 core navigation destinations for the left column. Musterdepot
     * is deliberately excluded here - it gets its own, richer card instead
     * of being reduced to an icon.
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

        $leftIcons = collect(self::LEFT_COLUMN_ICONS)->map(fn (array $item): array => [
            'id' => $item[0],
            'label' => $item[1],
            'icon' => $item[2],
            'url' => $this->urlFor($item[0]),
        ]);

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
            'depot' => $depot,
        ]);
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

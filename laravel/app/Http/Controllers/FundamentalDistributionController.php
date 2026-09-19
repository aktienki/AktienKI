<?php

namespace App\Http\Controllers;

use App\Services\FundamentalHeatmapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Same universe/filters as FundamentalScreenerController's overview, but
 * each of the 4 metrics gets its own 1D decile bar chart + slider instead
 * of pairing them into 2D heatmap grids - a copy of that page's filter/
 * table plumbing with the visualization swapped out.
 */
final class FundamentalDistributionController extends Controller
{
    public function __invoke(Request $request, FundamentalHeatmapService $heatmaps): View
    {
        $capGroup = $request->query('cap');
        $capGroup = in_array($capGroup, array_keys(FundamentalHeatmapService::CAP_GROUPS), true) ? $capGroup : null;
        $sector = $request->query('sector') ?: null;
        $country = $request->query('country') ?: null;
        $region = $request->query('region');
        $region = isset(FundamentalHeatmapService::REGIONS[$region]) ? $region : null;
        $search = $request->query('q');

        $metricRanges = [];
        $metricParams = ['pe' => 'trailing_pe', 'dy' => 'dividend_yield', 'roe' => 'return_on_equity', 'eg' => 'earnings_growth', 'mc' => 'market_cap'];
        foreach ($metricParams as $param => $key) {
            $min = $request->query("{$param}_min");
            $max = $request->query("{$param}_max");
            $range = [];
            if (is_numeric($min)) {
                $range['min'] = $key === 'market_cap' ? (float) $min * 1_000_000_000 : (float) $min;
            }
            if (is_numeric($max)) {
                $range['max'] = $key === 'market_cap' ? (float) $max * 1_000_000_000 : (float) $max;
            }
            if ($range !== []) {
                $metricRanges[$key] = $range;
            }
        }

        $histograms = $heatmaps->buildHistograms($capGroup, $sector, $country, $region, $metricRanges, $search);
        $filterOptions = $heatmaps->filterOptions();
        $table = $heatmaps->table(
            $capGroup,
            (string) $request->query('sort', 'market_cap'),
            (string) $request->query('dir', 'desc'),
            $search,
            max(1, (int) $request->query('page', 1)),
            50,
            $sector,
            $country,
            $region,
            $metricRanges,
        );

        return view('screener.fundamental-distribution', [
            'histograms' => $histograms,
            'capGroup' => $capGroup,
            'sector' => $sector,
            'country' => $country,
            'region' => $region,
            'filterOptions' => $filterOptions,
            'table' => $table,
            'tableSearch' => (string) $request->query('q', ''),
            'metricRangeParams' => collect($metricParams)->mapWithKeys(fn ($fundamentalKey, $param) => [
                $param => ['min' => $request->query("{$param}_min"), 'max' => $request->query("{$param}_max")],
            ])->all(),
        ]);
    }
}

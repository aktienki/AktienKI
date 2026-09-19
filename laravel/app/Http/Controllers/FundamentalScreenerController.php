<?php

namespace App\Http\Controllers;

use App\Services\EarningsCalendarService;
use App\Services\EarningsQuarterCardService;
use App\Services\FundamentalHeatmapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class FundamentalScreenerController extends Controller
{
    public function __invoke(Request $request, EarningsQuarterCardService $cards, FundamentalHeatmapService $heatmaps, EarningsCalendarService $calendar): View
    {
        $requestedSymbol = strtoupper(trim((string) $request->query('symbol', '')));

        $selected = $requestedSymbol !== ''
            ? DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')
                ->whereRaw('UPPER(symbol) = ?', [$requestedSymbol])->first(['id', 'symbol', 'name'])
            : null;

        $years = [];
        $ratios = null;
        $kennzahlenTrend = [];
        $nextEarnings = null;
        $capGroup = $request->query('cap');
        $capGroup = in_array($capGroup, array_keys(FundamentalHeatmapService::CAP_GROUPS), true) ? $capGroup : null;
        $sector = $request->query('sector') ?: null;
        $country = $request->query('country') ?: null;
        $region = $request->query('region');
        $region = isset(FundamentalHeatmapService::REGIONS[$region]) ? $region : null;
        $search = $request->query('q');

        // Real-value min/max per metric, set by dragging a heatmap/
        // distribution axis line (released -> page reload). market cap
        // travels in Mrd. in the URL for readability, converted back to
        // raw currency here.
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

        // Heatmaps/Verteilung/Termine are universe-wide views, reachable
        // from the left nav regardless of whether a stock is selected - not
        // just on the bare overview - so all 3 are always built.
        $panels = $heatmaps->build($capGroup, $sector, $country, $region, $metricRanges, $search);
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
        $calendarData = $calendar->upcoming();

        if ($selected) {
            $years = $cards->forInstrument($selected->id);
            $kennzahlenTrend = $cards->kennzahlenTrend($selected->id);
            $nextEarnings = $calendar->nextForInstrument($selected->id);

            $fundamental = DB::table('instrument_fundamentals')->where('instrument_id', $selected->id)
                ->orderByDesc('snapshot_date')->orderByDesc('id')->first([
                    'trailing_pe', 'dividend_yield', 'market_cap', 'revenue_growth', 'snapshot_date',
                ]);
            if ($fundamental) {
                $ratios = [
                    'trailing_pe' => $fundamental->trailing_pe !== null ? (float) $fundamental->trailing_pe : null,
                    'dividend_yield' => FundamentalHeatmapService::normalizeYieldPercent($fundamental->dividend_yield),
                    'market_cap' => $fundamental->market_cap !== null ? (float) $fundamental->market_cap : null,
                    'revenue_growth' => $fundamental->revenue_growth !== null ? (float) $fundamental->revenue_growth * 100 : null,
                    'snapshot_date' => $fundamental->snapshot_date,
                ];
            }
        }

        return view('screener.fundamental', [
            'selected' => $selected,
            'years' => $years,
            'ratios' => $ratios,
            'kennzahlenTrend' => $kennzahlenTrend,
            'nextEarnings' => $nextEarnings,
            'calendarData' => $calendarData,
            'panels' => $panels,
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

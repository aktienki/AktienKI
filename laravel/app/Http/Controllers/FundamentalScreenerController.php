<?php

namespace App\Http\Controllers;

use App\Services\EarningsQuarterCardService;
use App\Services\FundamentalHeatmapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class FundamentalScreenerController extends Controller
{
    public function __invoke(Request $request, EarningsQuarterCardService $cards, FundamentalHeatmapService $heatmaps): View
    {
        $requestedSymbol = strtoupper(trim((string) $request->query('symbol', '')));

        $selected = $requestedSymbol !== ''
            ? DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')
                ->whereRaw('UPPER(symbol) = ?', [$requestedSymbol])->first(['id', 'symbol', 'name'])
            : null;

        $years = [];
        $ratios = null;
        $panels = [];
        $capGroup = $request->query('cap');
        $capGroup = in_array($capGroup, array_keys(FundamentalHeatmapService::CAP_GROUPS), true) ? $capGroup : null;
        $sector = $request->query('sector') ?: null;
        $country = $request->query('country') ?: null;
        $region = $request->query('region');
        $region = isset(FundamentalHeatmapService::REGIONS[$region]) ? $region : null;

        $table = [];
        $filterOptions = ['sectors' => [], 'countries' => []];

        if (! $selected) {
            $panels = $heatmaps->build($capGroup, $sector, $country, $region);
            $filterOptions = $heatmaps->filterOptions();
            $table = $heatmaps->table(
                $capGroup,
                (string) $request->query('sort', 'market_cap'),
                (string) $request->query('dir', 'desc'),
                $request->query('q'),
                max(1, (int) $request->query('page', 1)),
                50,
                $sector,
                $country,
                $region,
            );
        }

        if ($selected) {
            $years = $cards->forInstrument($selected->id);

            $fundamental = DB::table('instrument_fundamentals')->where('instrument_id', $selected->id)
                ->orderByDesc('snapshot_date')->orderByDesc('id')->first([
                    'trailing_pe', 'dividend_yield', 'market_cap', 'revenue_growth', 'snapshot_date',
                ]);
            if ($fundamental) {
                $ratios = [
                    'trailing_pe' => $fundamental->trailing_pe !== null ? (float) $fundamental->trailing_pe : null,
                    'dividend_yield' => $fundamental->dividend_yield !== null ? (float) $fundamental->dividend_yield * 100 : null,
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
            'panels' => $panels,
            'capGroup' => $capGroup,
            'sector' => $sector,
            'country' => $country,
            'region' => $region,
            'filterOptions' => $filterOptions,
            'table' => $table,
            'tableSearch' => (string) $request->query('q', ''),
        ]);
    }
}

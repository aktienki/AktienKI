<?php

namespace App\Http\Controllers;

use App\Livewire\Dashboard\MarketData;
use Illuminate\View\View;

class MarketDeepAnalysisController extends Controller
{
    public function __invoke(): View
    {
        $minimumCountryStocks = 10;
        $marketData = app(MarketData::class);
        $countrySummaries = method_exists($marketData, 'loadCountryHorizonSummaries')
            ? $marketData->loadCountryHorizonSummaries($minimumCountryStocks)
            : [];

        return view('markets.deep-analysis', [
            'macroCards' => method_exists($marketData, 'loadIndexComparisonCards')
                ? $marketData->loadIndexComparisonCards()
                : [],
            'monthlyBacktestAiScores' => $marketData->loadMonthlyBacktestAiScores(),
            'countrySummaries' => $countrySummaries,
            'countryCards' => method_exists($marketData, 'loadCountryComparisonCards')
                ? $marketData->loadCountryComparisonCards($countrySummaries, $minimumCountryStocks)
                : [],
            'minimumCountryStocks' => $minimumCountryStocks,
        ]);
    }
}

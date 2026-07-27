<?php

namespace App\Livewire\Dashboard;

use App\Models\Prediction;
use App\Services\YahooFinanceService;
use Livewire\Component;

class LiveMarketDashboard extends Component
{
    public function render(YahooFinanceService $yahoo)
    {
        $marketSymbols = [
            'DAX' => '^GDAXI',
            'NASDAQ' => '^IXIC',
            'S&P 500' => '^GSPC',
            'Gold' => 'GC=F',
            'Öl' => 'CL=F',
        ];

        $markets = collect($marketSymbols)
            ->map(fn ($symbol, $name) => [
                'name' => $name,
                'quote' => $yahoo->quote($symbol),
            ])
            ->values();

        $topSignals = Prediction::query()
            ->with('company')
            ->orderByDesc('prediction_score')
            ->limit(5)
            ->get()
            ->map(function ($prediction) use ($yahoo) {
                $prediction->live_quote = $prediction->company?->symbol
                    ? $yahoo->quote($prediction->company->symbol)
                    : null;

                return $prediction;
            });

        return view('livewire.dashboard.live-market-dashboard', [
            'markets' => $markets,
            'topSignals' => $topSignals,
        ]);
    }
}
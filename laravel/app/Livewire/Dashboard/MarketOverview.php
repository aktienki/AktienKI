<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class MarketOverview extends Component
{
    public array $markets = [];

    public function mount(): void
    {
        $this->markets = [

            [
                'symbol' => '^GDAXI',
                'name' => 'DAX',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => '^GSPC',
                'name' => 'S&P 500',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => '^IXIC',
                'name' => 'NASDAQ',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => '^DJI',
                'name' => 'Dow Jones',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => 'GC=F',
                'name' => 'Gold',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => 'CL=F',
                'name' => 'Oil',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => 'EURUSD=X',
                'name' => 'EUR/USD',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => '^VIX',
                'name' => 'VIX',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => '^TNX',
                'name' => 'US 10Y',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

            [
                'symbol' => 'BTC-USD',
                'name' => 'Bitcoin',
                'value' => null,
                'change' => null,
                'changePercent' => null,
            ],

        ];
    }

    public function render()
    {
        return view('livewire.dashboard.market-overview');
    }
}

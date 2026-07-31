<?php

namespace App\Livewire\Dashboard;

use App\Services\TwelveDataService;
use Livewire\Component;

class MarketTickerCards extends Component
{
    public array $items = [];

    public function mount(TwelveDataService $marketData): void
    {
        $this->items = [
            $this->item($marketData, '^GDAXI', 'DAX', 'Index'),
            $this->item($marketData, 'EURUSD=X', 'EUR/USD', 'FX'),
            $this->item($marketData, '^DJI', 'Dow Jones', 'Index'),
            $this->item($marketData, 'GC=F', 'Gold', 'Spot'),
        ];
    }

    private function item(TwelveDataService $marketData, string $symbol, string $label, string $type): array
    {
        $quote = $marketData->quote($symbol);

        return [
            'symbol' => $symbol,
            'label' => $label,
            'type' => $type,
            'price' => $quote['price'] ?? null,
            'change' => null,
            'change_percent' => $quote['change_percent'] ?? null,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.market-ticker-cards');
    }
}

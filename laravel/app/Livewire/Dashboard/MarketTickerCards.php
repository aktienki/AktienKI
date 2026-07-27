<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Http;
use Livewire\Component;

class MarketTickerCards extends Component
{
    public array $items = [];

    public function mount(): void
    {
        $this->items = [
            $this->quote('^GDAXI', 'DAX', 'Index'),
            $this->quote('EURUSD=X', 'EUR/USD', 'FX'),
            $this->quote('^DJI', 'Dow Jones', 'Index'),
            $this->quote('GC=F', 'Gold', 'Future'),
        ];
    }

    private function quote(string $symbol, string $label, string $type): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 AktienKI Dashboard',
            ])->timeout(6)->get(
                'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($symbol),
                [
                    'range' => '2d',
                    'interval' => '1d',
                ]
            );

            $result = $response->json('chart.result.0');
            $meta = $result['meta'] ?? [];

            $price = $meta['regularMarketPrice'] ?? null;
            $previous = $meta['previousClose'] ?? null;

            $change = ($price !== null && $previous)
                ? (float) $price - (float) $previous
                : null;

            $changePercent = ($change !== null && $previous)
                ? ($change / (float) $previous) * 100
                : null;

            return [
                'symbol' => $symbol,
                'label' => $label,
                'type' => $type,
                'price' => $price,
                'change' => $change,
                'change_percent' => $changePercent,
            ];
        } catch (\Throwable) {
            return [
                'symbol' => $symbol,
                'label' => $label,
                'type' => $type,
                'price' => null,
                'change' => null,
                'change_percent' => null,
            ];
        }
    }

    public function render()
    {
        return view('livewire.dashboard.market-ticker-cards');
    }
}

<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class MarketOverview extends Component
{
    public $quotes;
    public array $analysis = [];

    public function mount(DashboardService $dashboardService): void
    {
        $this->quotes = $dashboardService->marketQuotes(8);
        $this->analysis = $dashboardService->marketAnalysis();
    }

    public function render()
    {
        return view('livewire.dashboard.market-overview');
    }
}

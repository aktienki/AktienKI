<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class TopSellSignals extends Component
{
    public $items;

    public function mount(DashboardService $dashboardService): void
    {
        $this->items = $dashboardService->topSellSignals(10);
    }

    public function render()
    {
        return view('livewire.dashboard.top-sell-signals');
    }
}

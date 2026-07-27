<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class HighRiskSignals extends Component
{
    public $items;

    public function mount(DashboardService $dashboardService): void
    {
        $this->items = $dashboardService->highRiskSignals(10);
    }

    public function render()
    {
        return view('livewire.dashboard.high-risk-signals');
    }
}

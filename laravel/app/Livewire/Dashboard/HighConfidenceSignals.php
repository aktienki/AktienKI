<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class HighConfidenceSignals extends Component
{
    public $items;

    public function mount(DashboardService $dashboardService): void
    {
        $this->items = $dashboardService->highConfidenceSignals(10);
    }

    public function render()
    {
        return view('livewire.dashboard.high-confidence-signals');
    }
}

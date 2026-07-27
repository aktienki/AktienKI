<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class Stats extends Component
{
    public array $stats = [];

    public function mount(DashboardService $dashboardService): void
    {
        $this->stats = $dashboardService->stats();
    }

    public function render()
    {
        return view('livewire.dashboard.stats');
    }
}

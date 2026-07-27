<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class KpiOverview extends Component
{
    public array $kpis = [];

    public function mount(DashboardService $dashboardService): void
    {
        $this->kpis = $dashboardService->kpis();
    }

    public function render()
    {
        return view('livewire.dashboard.kpi-overview');
    }
}

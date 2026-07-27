<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class LatestPredictionRun extends Component
{
    public $run;

    public function mount(DashboardService $dashboardService): void
    {
        $this->run = $dashboardService->latestPredictionRun();
    }

    public function render()
    {
        return view('livewire.dashboard.latest-prediction-run');
    }
}

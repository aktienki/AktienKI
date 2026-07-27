<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class PredictionList extends Component
{
    public $predictions;

    public function mount(DashboardService $dashboardService): void
    {
        $this->predictions = $dashboardService->latestPredictions(12);
    }

    public function render()
    {
        return view('livewire.dashboard.prediction-list');
    }
}

<?php

namespace App\Livewire\Dashboard;

use App\Services\PredictionService;
use Livewire\Component;

class TopSignals extends Component
{
    public array $signals = [];

    public function mount(PredictionService $predictionService): void
    {
        $this->signals = $predictionService->topSignals();
    }

    public function refreshSignals(PredictionService $predictionService): void
    {
        $this->signals = $predictionService->topSignals();
    }

    public function render(PredictionService $predictionService)
    {
        $this->signals = $predictionService->topSignals();

        return view('livewire.dashboard.top-signals');
    }
}
<?php

namespace App\Livewire\Dashboard;

use App\Models\Prediction;
use Livewire\Component;

class TopSignals extends Component
{
    public $signals = [];

    public function mount(): void
    {
        $this->loadSignals();
    }

    public function loadSignals(): void
    {
        $this->signals = Prediction::query()
            ->with('instrument')
            ->orderByDesc('ai_score')
            ->limit(5)
            ->get();
    }

    public function refreshSignals(): void
    {
        $this->loadSignals();
    }

    public function render()
    {
        return view('livewire.dashboard.top-signals');
    }
}

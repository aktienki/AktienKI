<?php

namespace App\Livewire\Dashboard;

use App\Models\ModelRun;
use App\Models\Prediction;
use App\Services\TwelveDataService;
use Livewire\Component;

class TopBuySignals extends Component
{
    public function render(TwelveDataService $yahoo)
    {
        $lastRun = ModelRun::query()
            ->latest('finished_at')
            ->latest('updated_at')
            ->first();

        $topSignals = Prediction::query()
            ->with('company')
            ->orderByDesc('prediction_score')
            ->limit(5)
            ->get()
            ->map(function ($prediction) use ($yahoo) {
                $prediction->live_quote = $prediction->company?->symbol
                    ? $yahoo->quote($prediction->company->symbol)
                    : null;

                return $prediction;
            });

        return view('livewire.dashboard.top-signals', [
            'topSignals' => $topSignals,
            'lastRun' => $lastRun,
        ]);
    }
}

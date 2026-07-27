<?php

namespace App\Livewire\Dashboard;

use App\Models\Watchlist as WatchlistModel;
use Livewire\Component;

class Watchlist extends Component
{
    public $items;

    public function mount(): void
    {
        $watchlist = WatchlistModel::with(['companies.latestPrediction'])
            ->where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->latest()
            ->first();

        $this->items = $watchlist?->companies ?? collect();
    }

    public function render()
    {
        return view('livewire.dashboard.watchlist');
    }
}

<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Livewire\Component;

class News extends Component
{
    public $items;

    public function mount(DashboardService $dashboardService): void
    {
        $this->items = $dashboardService->latestNews(8);
    }

    public function render()
    {
        return view('livewire.dashboard.news');
    }
}

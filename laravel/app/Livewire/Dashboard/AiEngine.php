<?php

// app/Livewire/Dashboard/AiEngine.php

namespace App\Livewire\Dashboard;

use App\Models\ModelRun;
use Livewire\Component;

class AiEngine extends Component
{
    public array $cards = [];

    public function mount(): void
    {
        $this->load();
    }

    public function refreshData(): void
    {
        $this->load();
    }

    protected function load(): void
    {
        $run = ModelRun::query()
            ->latest('updated_at')
            ->first();

        $metrics = $run?->metrics ?? [];

        $total = (int) ($run->companies_total ?? 0);
        $success = (int) ($run->companies_success ?? 0);
        $failed = (int) ($run->companies_failed ?? 0);

        $processed = $success + $failed;

        $progress = $total > 0
            ? round(($processed / $total) * 100)
            : 0;

        $mode = strtolower($run->type ?? 'idle');

        $modeColor = match ($mode) {
            'prediction' => 'green',
            'training' => 'violet',
            'updating' => 'amber',
            default => 'red',
        };

        $this->cards = [

            [
                'title' => 'Engine',
                'value' => $run ? 'Online' : 'Offline',
                'status' => $run?->status ?? 'offline',
                'percent' => $run ? 100 : 0,
                'color' => $run ? 'green' : 'red',
            ],

            [
                'title' => 'KI Modus',
                'value' => ucfirst($mode),
                'status' => $run?->status ?? '',
                'percent' => 100,
                'color' => $modeColor,
            ],

            [
                'title' => 'Job',
                'value' => number_format($processed,0,',','.')
                    .' / '.
                    number_format($total,0,',','.'),
                'status' => $progress.' %',
                'percent' => $progress,
                'color' => 'violet',
            ],

            [
                'title' => 'Modelle',
                'value' =>
                    ($metrics['models']
                    ?? $metrics['model_count']
                    ?? 0)
                    .' aktiv',

                'status' =>
                    'Hitrate '.
                    (
                        isset($metrics['hitrate'])
                            ? number_format($metrics['hitrate']*100,1,',','.')
                            : '—'
                    ).' %',

                'percent' =>
                    isset($metrics['hitrate'])
                        ? round($metrics['hitrate']*100)
                        : 0,

                'color' => 'green',
            ],

            [
                'title' => 'Letzter Run',
                'value' =>
                    $run?->finished_at
                        ? $run->finished_at->diffForHumans()
                        : '—',

                'status' =>
                    $run?->finished_at
                        ? $run->finished_at->format('d.m.Y H:i')
                        : 'Kein Run',

                'percent' => 100,

                'color' => 'violet',
            ],

        ];
    }

    public function render()
    {
        return view(
            'livewire.dashboard.ai-engine',
            [
                'engine' => $this->cards,
            ]
        );
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class PredictionReminderChart
{
    /** @param array<int, float> $history @param array<int, float|null> $forecasts */
    public function render(array $history, array $forecasts): string
    {
        $enginePath = (string) config('aktienki.python_engine.path');
        $python = $enginePath.'/.venv/bin/python';
        if (! is_executable($python)) $python = base_path('../python-engine/.venv/bin/python');
        $result = Process::path(base_path())->input(json_encode([
            'history' => array_values($history), 'forecasts' => $forecasts,
        ], JSON_THROW_ON_ERROR))->timeout(30)->run([$python, base_path('scripts/render_prediction_reminder_chart.py')]);
        if (! $result->successful() || $result->output() === '') {
            throw new RuntimeException('Prediction reminder chart could not be rendered: '.trim($result->errorOutput()));
        }
        return $result->output();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\PredictionPurchaseReminder;
use App\Services\NormalizedScoreExitService;
use Illuminate\Console\Command;

final class EvaluateNormalizedScoreExits extends Command
{
    protected $signature = 'exits:evaluate-normalized-scores {--limit=5000}';
    protected $description = 'Bewertet offene Positionen über normierte, bestätigte KI-Score-Exits.';

    public function handle(NormalizedScoreExitService $service): int
    {
        $counts = ['HOLD' => 0, 'WARNING' => 0, 'EXIT' => 0, 'NO_DATA' => 0];
        PredictionPurchaseReminder::query()->whereIn('status', ['active', 'sent'])->where('intent', 'purchased')
            ->whereIn('exit_state', ['monitoring', 'exit_warning', 'exit_recommended'])
            ->limit(max(1, (int) $this->option('limit')))->each(function ($position) use ($service, &$counts): void {
                $decision = $service->evaluate($position)['decision'] ?? 'NO_DATA';
                $counts[$decision] = ($counts[$decision] ?? 0) + 1;
            });
        $this->info(collect($counts)->map(fn ($count, $name) => "{$name}={$count}")->implode(' '));
        return self::SUCCESS;
    }
}

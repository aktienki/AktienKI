<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\ActionScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateActionScores extends Command
{
    protected $signature = 'scores:recalculate {--all : Recalculate every latest analyzed stock} {--instrument= : Restrict to one instrument id}';
    protected $description = 'Recalculate the unified actionable AI score from the latest completed walk-forward evidence';

    public function handle(ActionScoreService $service): int
    {
        $latest = DB::table('predictions')->selectRaw('MAX(id) AS id')->groupBy('instrument_id');
        $query = Prediction::query()->whereIn('id', $latest);
        if ($instrument = $this->option('instrument')) {
            $query->where('instrument_id', (int) $instrument);
        }
        if (! $this->option('all')) {
            $query->where(function ($query): void {
                $query->whereNull('action_score_calculated_at')
                    ->orWhere('action_score_version', '!=', ActionScoreService::VERSION)
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')->from('walk_forward_backtest_runs as score_run')
                            ->join('walk_forward_backtest_trades as score_trade', 'score_trade.run_id', '=', 'score_run.id')
                            ->whereColumn('score_trade.instrument_id', 'predictions.instrument_id')
                            ->where('score_run.status', 'completed')
                            ->whereColumn('score_run.finished_at', '>', 'predictions.action_score_calculated_at');
                    });
            });
        }

        $updated = 0;
        $query->orderBy('id')->chunkById(100, function ($predictions) use ($service, &$updated): void {
            foreach ($predictions as $prediction) {
                $updated += $service->persist($prediction) ? 1 : 0;
            }
        });
        $this->info("Action scores updated: {$updated}");

        return self::SUCCESS;
    }
}

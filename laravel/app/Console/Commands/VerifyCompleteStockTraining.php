<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyCompleteStockTraining extends Command
{
    protected $signature = 'training:verify-complete {symbols*} {--feature-version=triple_daily_macro_v1}';
    protected $description = 'Prüft Training, Horizon-Fusion und Walk-Forward für alle vier Horizonte.';

    private const HORIZONS = [5 => 7200, 10 => 14400, 15 => 21600, 20 => 28800];

    public function handle(): int
    {
        $symbols = collect($this->argument('symbols'))->map(fn ($symbol) => strtoupper((string) $symbol))->unique()->values();
        $instruments = DB::table('instruments')->whereIn(DB::raw('UPPER(symbol)'), $symbols)->pluck('id', 'symbol');
        $errors = [];

        foreach ($symbols as $symbol) {
            $instrumentId = $instruments->get($symbol);
            if (! $instrumentId) {
                $errors[] = "{$symbol}: Instrument fehlt";
                continue;
            }

            foreach (self::HORIZONS as $days => $minutes) {
                $prediction = DB::table('predictions as prediction')
                    ->join('trained_models as model', 'model.id', '=', 'prediction.trained_model_id')
                    ->where('prediction.instrument_id', $instrumentId)
                    ->where('prediction.prediction_horizon_minutes', $minutes)
                    ->where('model.feature_set_version', (string) $this->option('feature-version'))
                    ->orderByDesc('prediction.prediction_time')->orderByDesc('prediction.id')
                    ->first(['prediction.horizon_fusion_noise_passed', 'prediction.horizon_fusion_stability_passed']);
                if (! $prediction) {
                    $errors[] = "{$symbol}: {$days}d-Prediction fehlt";
                } elseif ($prediction->horizon_fusion_noise_passed === null || $prediction->horizon_fusion_stability_passed === null) {
                    $errors[] = "{$symbol}: {$days}d-Filterprüfung fehlt";
                }

                $runId = DB::table('walk_forward_backtest_runs')->where('status', 'completed')
                    ->where('horizon_days', $days)->orderByDesc('id')->value('id');
                if (! $runId || ! DB::table('walk_forward_backtest_scores')->where('run_id', $runId)
                    ->where('instrument_id', $instrumentId)->exists()) {
                    $errors[] = "{$symbol}: {$days}d-Walk-Forward fehlt";
                }
            }
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('Gesamtpipeline unvollständig.');
            return self::FAILURE;
        }

        $this->info("Gesamtpipeline vollständig: {$symbols->count()} Instrumente.");
        return self::SUCCESS;
    }
}

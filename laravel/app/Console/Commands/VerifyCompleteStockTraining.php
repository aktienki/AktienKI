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
                $modelExists = DB::table('trained_models')->where('instrument_id', $instrumentId)
                    ->where('prediction_horizon_minutes', $minutes)->whereNull('deleted_at')
                    ->where('feature_set_version', (string) $this->option('feature-version'))->exists();
                if (! $modelExists) $errors[] = "{$symbol}: {$days}d-Modell fehlt";

                $runId = DB::table('walk_forward_backtest_runs as run')
                    ->join('walk_forward_backtest_trades as trade', 'trade.run_id', '=', 'run.id')
                    ->where('run.status', 'completed')->where('run.horizon_days', $days)
                    ->where('trade.instrument_id', $instrumentId)
                    ->orderByDesc('run.finished_at')->orderByDesc('run.id')->value('run.id');
                if (! $runId) {
                    $errors[] = "{$symbol}: {$days}d-Walk-Forward fehlt";
                }
            }

            $phaseFilter = DB::table('market_context_predictions')
                ->where('scope_type', 'stock_phase20')
                ->where('scope_key', (string) $instrumentId)
                ->whereRaw("meta->>'source' = ?", ['pytorch_stock_three_phase_gru_20t'])
                ->orderByDesc('prediction_date')->first();
            if (! $phaseFilter) {
                $errors[] = "{$symbol}: PyTorch-Phasenfilter fehlt";
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

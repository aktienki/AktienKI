<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BackfillHistoricalNoiseScores extends Command
{
    protected $signature = 'predictions:backfill-noise {symbol} {--run5=} {--run10=} {--run15=} {--run20=}';
    protected $description = 'Berechnet den Point-in-Time-Noise-Score aus vier gespeicherten Walk-Forward-Prognosereihen.';

    public function handle(): int
    {
        $instrumentId = DB::table('instruments')->where('symbol', strtoupper((string) $this->argument('symbol')))->value('id');
        if (! $instrumentId) { $this->error('Aktie nicht gefunden.'); return self::FAILURE; }
        $runs = [5 => (int) $this->option('run5'), 10 => (int) $this->option('run10'), 15 => (int) $this->option('run15'), 20 => (int) $this->option('run20')];
        if (in_array(0, $runs, true)) { $this->error('Alle vier Run-IDs sind erforderlich.'); return self::FAILURE; }
        $series = [];
        foreach ($runs as $days => $runId) {
            $series[$days] = DB::table('walk_forward_horizon_forecasts')->where('run_id', $runId)->where('instrument_id', $instrumentId)
                ->where('horizon_days', $days)->pluck('predicted_return', 'signal_date')->map(fn ($value): float => (float) $value)->all();
        }
        $dates = array_values(array_intersect(...array_map('array_keys', $series)));
        sort($dates); $rows = []; $now = now();
        foreach ($dates as $date) {
            $points = [0 => 0.0, 5 => $series[5][$date] * 100, 10 => $series[10][$date] * 100, 15 => $series[15][$date] * 100, 20 => $series[20][$date] * 100];
            $positive = 0.0; $negative = 0.0;
            foreach ([[0,5],[5,10],[10,15],[15,20]] as [$from,$to]) {
                [$pos,$neg] = $this->segment($points[$from], $points[$to], $to - $from); $positive += $pos; $negative += $neg;
            }
            $net = $positive + $negative;
            $rows[] = ['instrument_id' => $instrumentId, 'signal_date' => $date,
                'return_5d' => $series[5][$date], 'return_10d' => $series[10][$date], 'return_15d' => $series[15][$date], 'return_20d' => $series[20][$date],
                'positive_area' => $positive, 'negative_area' => $negative, 'net_area' => $net,
                'score' => 50 + 50 * tanh($net / 100), 'passed' => $net > 0, 'calculation_version' => 'noise-score-tanh-v1',
                'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 500) as $chunk) DB::table('historical_noise_scores')->upsert(
            $chunk, ['instrument_id', 'signal_date', 'calculation_version'], ['return_5d','return_10d','return_15d','return_20d','positive_area','negative_area','net_area','score','passed','updated_at']
        );
        $this->info(count($rows).' Noise-Scores gespeichert.'); return self::SUCCESS;
    }

    private function segment(float $start, float $end, float $width): array
    {
        if ($start >= 0 && $end >= 0) return [($start + $end) * $width / 2, 0.0];
        if ($start <= 0 && $end <= 0) return [0.0, ($start + $end) * $width / 2];
        $crossing = $width * abs($start) / (abs($start) + abs($end));
        return $start > 0 ? [$start * $crossing / 2, $end * ($width - $crossing) / 2] : [$end * ($width - $crossing) / 2, $start * $crossing / 2];
    }
}

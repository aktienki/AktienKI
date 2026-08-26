<?php

namespace App\Console\Commands;

use App\Services\HistoricalActionScoreService;
use App\Support\SignalQualityCalibration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CalibrateIndexStockThresholds extends Command
{
    protected $signature = 'thresholds:calibrate-index
        {index? : Optionales Symbol oder Name des Heimatindex}
        {--horizon=20}
        {--dry-run}
        {--instrument=}
        {--recalibrate : Eine bereits eingefrorene Kalibrierung dieser Version bewusst ersetzen}
        {--minimum-calibration-events=10}
        {--target-calibration-events=20}
        {--minimum-validation-events=10}
        {--validation-years=3 : Vollständig unangetasteter Out-of-Sample-Zeitraum}';

    protected $description = 'Calibrate a separate point-in-time action-score threshold for every index member.';

    private const VERSION = 'historical-action-v5-per-stock-before-context-filters';

    private const MINIMUM_LONG_CONTEXT_PROBABILITY = 0.45;

    private const QUALITY_MINIMUM_PROFIT_FACTOR = 1.50;

    private const SOLID_MINIMUM_PROFIT_FACTOR = 1.25;

    private const BASIC_MINIMUM_PROFIT_FACTOR = 1.10;

    public function handle(HistoricalActionScoreService $scoreService): int
    {
        $indexArgument = trim((string) ($this->argument('index') ?? ''));
        $index = $indexArgument === '' ? null : DB::table('market_indices')
            ->where('symbol', $indexArgument)
            ->orWhereRaw('LOWER(name) = LOWER(?)', [$indexArgument])
            ->first(['id', 'symbol', 'name']);
        if ($indexArgument !== '' && ! $index) {
            $this->error('Index nicht gefunden.');
            return self::FAILURE;
        }
        if (! $index && ! $this->option('instrument')) {
            $this->error('Ohne Heimatindex ist --instrument erforderlich.');
            return self::FAILURE;
        }
        $horizon = (int) $this->option('horizon');
        $memberIds = $index
            ? DB::table('index_memberships')->where('market_index_id', $index->id)
                ->whereNull('removed_at')->distinct()->pluck('instrument_id')
                ->when($this->option('instrument'), fn (Collection $ids) => $ids->filter(
                    fn ($id) => (int) $id === (int) $this->option('instrument')
                ))->values()
            : collect([(int) $this->option('instrument')]);
        $summary = ['activated' => 0, 'experimental' => 0, 'insufficient_data' => 0, 'missing_run' => 0];

        foreach ($memberIds as $instrumentId) {
            $instrument = DB::table('instruments')->where('id', $instrumentId)->first(['id', 'symbol', 'name']);
            $existingFrozenCalibration = DB::table('stock_individual_thresholds')
                ->where('instrument_id', $instrumentId)->where('horizon_days', $horizon)
                ->where('algorithm_version', self::VERSION)->exists();
            if ($existingFrozenCalibration && ! $this->option('recalibrate')) {
                $this->line("{$instrument->symbol}: bestehende Forward-Kalibrierung bleibt eingefroren");
                continue;
            }
            $runId = DB::table('walk_forward_backtest_runs as run')
                ->join('walk_forward_backtest_trades as trade', 'trade.run_id', '=', 'run.id')
                ->where('run.status', 'completed')->where('run.horizon_days', $horizon)
                ->where('trade.instrument_id', $instrumentId)
                ->orderByRaw('(run.test_end - run.test_start) DESC')
                ->orderByDesc('run.finished_at')->orderByDesc('run.id')->value('run.id');
            if (! $runId) {
                $summary['missing_run']++;
                $this->warn("{$instrument->symbol}: kein abgeschlossener {$horizon}T-Lauf");
                continue;
            }

            $rows = DB::table('walk_forward_backtest_trades as trade')
                ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
                ->where('trade.run_id', $runId)->where('trade.instrument_id', $instrumentId)
                ->orderBy('trade.signal_date')->select(['trade.*'])
                ->selectRaw("(SELECT jsonb_agg(jsonb_build_object('macd', phase.macd_histogram, 'stochastic', phase.stochastic_k) ORDER BY phase.bar_time DESC) FROM (SELECT indicator.bar_time, indicator.macd_histogram, indicator.stochastic_k FROM technical_indicators AS indicator WHERE indicator.instrument_id = trade.instrument_id AND indicator.interval = '1d' AND indicator.bar_time < trade.signal_date::timestamp + INTERVAL '1 day' AND indicator.macd_histogram IS NOT NULL AND indicator.stochastic_k IS NOT NULL ORDER BY indicator.bar_time DESC LIMIT 2) AS phase) AS market_phase_points")
                ->get();
            if ($rows->count() < 30) {
                $this->store($instrumentId, $horizon, 'insufficient_data', null, $rows->count(), 0, 0, false, $runId, null);
                $summary['insufficient_data']++;
                continue;
            }

            // Calibrate the stock-specific raw action-score threshold before
            // phase, index, sector and 60T context filters are applied. Those
            // filters are downstream signal confirmations/vetos and must not
            // reduce the calibration sample.
            $scored = $scoreService->score($rows)->values();
            if ($scored->count() < 30) {
                $this->store($instrumentId, $horizon, 'insufficient_data', null, $scored->count(), 0, 0, false, $runId, [
                    'reason' => 'insufficient_raw_action_score_history',
                ]);
                $summary['insufficient_data']++;
                $this->warn("{$instrument->symbol}: zu wenig historische Rohscore-Daten");
                continue;
            }
            $lastDate = CarbonImmutable::parse((string) $scored->max('signal_date'));
            $validationYears = max(1, (int) $this->option('validation-years'));
            $split = $lastDate->subYears($validationYears)->toDateString();
            $candidates = collect(range(35, 80))->map(function (int $threshold) use ($scored, $split): array {
                $entries = $this->entries($scored, $threshold);
                $calibration = $this->stats($entries->filter(fn ($row) => (string) $row->signal_date < $split));
                $validation = $this->stats($entries->filter(fn ($row) => (string) $row->signal_date >= $split));
                return compact('threshold', 'calibration', 'validation') + ['overall' => $this->stats($entries)];
            });
            $minimumCalibration = max(10, (int) $this->option('minimum-calibration-events'));
            $targetCalibration = max($minimumCalibration, (int) $this->option('target-calibration-events'));
            $rank = fn (Collection $rows): Collection => $rows->sortByDesc(fn (array $row): float =>
                (float) ($row['calibration']['hit_rate'] ?? 0)
                + min(5.0, (float) ($row['calibration']['profit_factor'] ?? 0)) * 10
                + max(-5.0, min(5.0, (float) ($row['calibration']['average_return_percent'] ?? 0))) * 3
            );
            $standard = $rank($candidates->filter(fn (array $row): bool =>
                $row['calibration']['trades'] >= $targetCalibration
                && ($row['calibration']['profit_factor'] ?? 0) >= self::SOLID_MINIMUM_PROFIT_FACTOR
                && ($row['calibration']['average_return_percent'] ?? -1) > 0
            ));
            $provisional = $rank($candidates->filter(fn (array $row): bool =>
                $row['calibration']['trades'] >= $minimumCalibration
                && $row['calibration']['trades'] < $targetCalibration
                && ($row['calibration']['profit_factor'] ?? 0) >= self::SOLID_MINIMUM_PROFIT_FACTOR
                && ($row['calibration']['hit_rate'] ?? 0) >= 65
                && ($row['calibration']['average_return_percent'] ?? -1) > 0
            ));
            // A weak raw result must never stop the downstream context-filter
            // evaluation. If no strong threshold exists, retain the best
            // exploratory threshold and let phase/index/sector/60T/noise decide.
            $exploratory = $rank($candidates->filter(fn (array $row): bool =>
                $row['calibration']['trades'] >= $minimumCalibration
            ));
            $sparse = $candidates->filter(fn (array $row): bool => $row['calibration']['trades'] > 0)
                ->sortByDesc(fn (array $row): float =>
                    ((int) $row['calibration']['trades'] * 1000)
                    + (float) ($row['calibration']['hit_rate'] ?? 0)
                );
            $best = $standard->first() ?? $provisional->first() ?? $exploratory->first() ?? $sparse->first() ?? $candidates->first();
            $calibrationEvidence = $standard->isNotEmpty() ? 'standard'
                : ($provisional->isNotEmpty() ? 'provisional'
                : ($exploratory->isNotEmpty() ? 'exploratory' : 'sparse'));
            if (! $best) {
                $this->store($instrumentId, $horizon, 'insufficient_data', null, $rows->count(), 0, 0, false, $runId, ['split' => $split]);
                $summary['insufficient_data']++;
                $this->warn("{$instrument->symbol}: keine belastbare Schwelle");
                continue;
            }

            $validation = $best['validation'];
            $validationPassed = $validation['trades'] >= (int) $this->option('minimum-validation-events')
                && ($validation['profit_factor'] ?? 0) >= self::SOLID_MINIMUM_PROFIT_FACTOR
                && ($validation['average_return_percent'] ?? -1) > 0
                && ($validation['hit_rate'] ?? 0) >= 60;
            $rawQualityClass = $this->qualityClass($validation);
            $status = 'pending_context_filters';
            $quality = SignalQualityCalibration::calculate($best['overall'], $validation, false);
            $result = [
                'source_run_id' => (int) $runId,
                'source_score_version' => HistoricalActionScoreService::VERSION,
                'selection' => 'raw_action_score_before_context_filters',
                'split' => $split,
                'validation_years' => $validationYears,
                'calibration_evidence' => $calibrationEvidence,
                'minimum_calibration_events' => $minimumCalibration,
                'target_calibration_events' => $targetCalibration,
                'primary_horizon_days' => $horizon,
                'other_horizons_role' => 'confirmation_only',
                'one_plus_requires_all_four_positive' => true,
                'minimum_action_score' => (float) $best['threshold'],
                'minimum_ai_score' => $best['threshold'] / 10,
                'calibration' => $best['calibration'],
                'validation' => $validation,
                'overall' => $best['overall'],
                'raw_pre_filter_quality_class' => $rawQualityClass,
                'raw_pre_filter_release_candidate' => $validationPassed,
                'continue_to_context_filters' => true,
                'release_policy' => [
                    'minimum_raw_class' => 'solid',
                    'minimum_raw_hit_rate' => 60,
                    'quality_class_is_final_after_context_filters' => true,
                    'classes' => [
                        'quality' => ['minimum_hit_rate' => 65, 'minimum_profit_factor' => self::QUALITY_MINIMUM_PROFIT_FACTOR, 'minimum_trades' => 20, 'positive_average_return' => true],
                        'solid' => ['minimum_hit_rate' => 60, 'minimum_profit_factor' => self::SOLID_MINIMUM_PROFIT_FACTOR, 'minimum_trades' => 10, 'positive_average_return' => true],
                        'basic' => ['minimum_hit_rate' => 55, 'minimum_profit_factor' => self::BASIC_MINIMUM_PROFIT_FACTOR, 'minimum_trades' => 10, 'positive_average_return' => true],
                    ],
                ],
                'signal_quality' => $quality,
                'long_horizon_60t_role' => 'filter_only',
                'long_horizon_60t_policy' => [
                    'required_contexts' => ['index60', 'sector60'],
                    'index_symbol' => $index?->symbol,
                    'long_entry_veto_below_probability' => self::MINIMUM_LONG_CONTEXT_PROBABILITY,
                    'point_in_time' => true,
                    'missing_context' => 'document_and_skip_filter',
                    'applied_after_threshold_calibration' => true,
                ],
            ];
            $this->store($instrumentId, $horizon, $status, $best['threshold'] / 10,
                $best['overall']['trades'], $best['calibration']['trades'], $validation['trades'],
                $validationPassed, $runId, $result);
            $summary[$validationPassed ? 'activated' : 'experimental']++;
            $this->line(sprintf('%s: %.1f | Kal. %d / Val. %d | %s', $instrument->symbol,
                $best['threshold'] / 10, $best['calibration']['trades'], $validation['trades'], $status));
        }

        $this->info(json_encode($summary, JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function entries(Collection $rows, float $threshold): Collection
    {
        $previous = false;
        return $rows->filter(function ($row) use ($threshold, &$previous): bool {
            $accepted = (float) $row->historical_action_score >= $threshold;
            $entry = $accepted && ! $previous;
            $previous = $accepted;
            return $entry;
        })->values();
    }

    private function stats(Collection $rows): array
    {
        $returns = $rows->filter(fn ($row) => is_numeric($row->net_return ?? null))->map(fn ($row) => (float) $row->net_return);
        $wins = $returns->filter(fn ($v) => $v > 0)->sum();
        $losses = abs($returns->filter(fn ($v) => $v < 0)->sum());
        return [
            'trades' => $returns->count(),
            'hit_rate' => $returns->isEmpty() ? null : round(100 * $returns->filter(fn ($v) => $v > 0)->count() / $returns->count(), 2),
            'profit_factor' => $losses > 0 ? round($wins / $losses, 3) : ($wins > 0 ? 999.0 : null),
            'average_return_percent' => $returns->isEmpty() ? null : round(100 * $returns->avg(), 3),
        ];
    }

    private function qualityClass(array $stats): string
    {
        $trades = (int) ($stats['trades'] ?? 0);
        $hitRate = (float) ($stats['hit_rate'] ?? 0);
        $profitFactor = (float) ($stats['profit_factor'] ?? 0);
        $averageReturn = (float) ($stats['average_return_percent'] ?? 0);

        if ($trades >= 20 && $hitRate >= 65 && $profitFactor >= self::QUALITY_MINIMUM_PROFIT_FACTOR && $averageReturn > 0) return 'quality';
        if ($trades >= 10 && $hitRate >= 60 && $profitFactor >= self::SOLID_MINIMUM_PROFIT_FACTOR && $averageReturn > 0) return 'solid';
        if ($trades >= 10 && $hitRate >= 55 && $profitFactor >= self::BASIC_MINIMUM_PROFIT_FACTOR && $averageReturn > 0) return 'basic';

        return 'unqualified';
    }

    private function store(int $instrumentId, int $horizon, string $status, ?float $threshold,
        int $events, int $calibrationEvents, int $validationEvents, bool $validationPassed,
        int $runId, ?array $result): void
    {
        if ($this->option('dry-run')) return;
        $now = now();
        $payload = $result ?? ['source_run_id' => $runId];
        DB::table('stock_individual_thresholds')->updateOrInsert([
            'instrument_id' => $instrumentId, 'horizon_days' => $horizon, 'algorithm_version' => self::VERSION,
        ], [
            'status' => $status, 'minimum_phase_probability' => null, 'minimum_ai_score' => $threshold,
            'event_count' => $events, 'calibration_event_count' => $calibrationEvents,
            'validation_event_count' => $validationEvents, 'validation_passed' => $validationPassed,
            'validation_year' => (int) now()->subYears(max(1, (int) $this->option('validation-years')))->format('Y'), 'phase_result' => null,
            'score_result' => json_encode($payload, JSON_THROW_ON_ERROR), 'phase_matrix' => null, 'score_matrix' => null,
            'source_report_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'calculated_at' => $now, 'activated_at' => $validationPassed ? $now : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}

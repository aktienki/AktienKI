<?php

namespace App\Console\Commands;

use App\Services\HistoricalActionScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EvaluateStockContextFilters extends Command
{
    protected $signature = 'thresholds:evaluate-context-filters
        {instrument : Instrument-ID oder Symbol}
        {--index= : Heimatindex-Symbol}
        {--minimum-context-probability=0.45}
        {--minimum-events=1}
        {--dry-run}';

    protected $description = 'Validiert Postfilter ohne Verschlechterung gegenüber der individuellen Rohschwelle.';

    private const VERSION = 'historical-action-v5-per-stock-before-context-filters';

    public function handle(HistoricalActionScoreService $scoreService): int
    {
        $argument = (string) $this->argument('instrument');
        $instrument = DB::table('instruments')->where('id', is_numeric($argument) ? (int) $argument : -1)
            ->orWhereRaw('UPPER(symbol) = UPPER(?)', [$argument])->first(['id','symbol','sector','meta']);
        if (!$instrument) { $this->error('Aktie nicht gefunden.'); return self::FAILURE; }

        $threshold = DB::table('stock_individual_thresholds')
            ->where('instrument_id', $instrument->id)->where('horizon_days', 20)
            ->where('algorithm_version', self::VERSION)->orderByDesc('calculated_at')->orderByDesc('id')->first();
        if (!$threshold || !is_numeric($threshold->minimum_ai_score)) {
            $this->error('Individuelle Rohschwelle fehlt.'); return self::FAILURE;
        }
        $payload = json_decode((string) $threshold->score_result, true) ?: [];
        $runId = (int) ($payload['source_run_id'] ?? 0);
        $split = (string) ($payload['split'] ?? '');
        if (!$runId || !$split) { $this->error('Kalibrierungslauf oder OOS-Split fehlt.'); return self::FAILURE; }

        $indexQuery = DB::table('index_memberships as membership')
            ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
            ->where('membership.instrument_id', $instrument->id)->whereNull('membership.removed_at');
        if ($this->option('index')) $indexQuery->where('market_index.symbol', (string) $this->option('index'));
        $indexId = (int) $indexQuery->value('membership.market_index_id');
        $minimumProbability = (float) $this->option('minimum-context-probability');

        $rows = DB::table('walk_forward_backtest_trades as trade')
            ->where('trade.run_id', $runId)->where('trade.instrument_id', $instrument->id)
            ->orderBy('trade.signal_date')->select(['trade.*'])
            ->selectRaw("(SELECT jsonb_agg(jsonb_build_object('macd', phase.macd_histogram, 'stochastic', phase.stochastic_k) ORDER BY phase.bar_time DESC) FROM (SELECT indicator.bar_time, indicator.macd_histogram, indicator.stochastic_k FROM technical_indicators AS indicator WHERE indicator.instrument_id = trade.instrument_id AND indicator.interval = '1d' AND indicator.bar_time < trade.signal_date::timestamp + INTERVAL '1 day' AND indicator.macd_histogram IS NOT NULL AND indicator.stochastic_k IS NOT NULL ORDER BY indicator.bar_time DESC LIMIT 2) AS phase) AS market_phase_points")
            ->selectRaw("(SELECT context.score / 10.0 FROM market_context_predictions context WHERE context.scope_type='index60' AND context.scope_key=? AND context.prediction_date<=trade.signal_date ORDER BY context.prediction_date DESC,context.id DESC LIMIT 1) AS index60_probability", [(string) $indexId])
            ->selectRaw("(SELECT context.score / 10.0 FROM market_context_predictions context WHERE context.scope_type='sector60' AND context.scope_key=? AND context.prediction_date<=trade.signal_date ORDER BY context.prediction_date DESC,context.id DESC LIMIT 1) AS sector60_probability", [(string) $instrument->sector])
            ->selectRaw("(SELECT noise.score FROM historical_noise_scores noise WHERE noise.instrument_id=trade.instrument_id AND noise.signal_date<=trade.signal_date ORDER BY noise.signal_date DESC,noise.id DESC LIMIT 1) AS noise_score")
            ->get();
        $scored = $scoreService->score($rows)->values();
        $cutoff = (float) $threshold->minimum_ai_score * 10;
        $filters = [
            'index60' => fn (object $row): bool => is_numeric($row->index60_probability) && (float) $row->index60_probability >= $minimumProbability,
            'sector60' => fn (object $row): bool => is_numeric($row->sector60_probability) && (float) $row->sector60_probability >= $minimumProbability,
            'noise' => fn (object $row): bool => is_numeric($row->noise_score) && (float) $row->noise_score >= 50,
        ];
        // Postfilters deliberately reduce the number of signals. A sparse
        // filtered sample is an evidence label, never a reason to revoke a
        // valid raw model or to discard an otherwise non-degrading filter.
        $minimumEvents = max(1, (int) $this->option('minimum-events'));
        $baseline = $this->evaluate($scored, $cutoff, [], $filters, $split);
        $candidates = collect();
        $filterNames = array_keys($filters);
        for ($mask = 1; $mask < (1 << count($filterNames)); $mask++) {
            $selected = collect($filterNames)->filter(fn ($_name, int $i): bool => ($mask & (1 << $i)) !== 0)->values()->all();
            $candidate = $this->evaluate($scored, $cutoff, $selected, $filters, $split);
            if ($candidate['calibration']['trades'] < $minimumEvents || $candidate['oos']['trades'] < $minimumEvents) continue;
            if (!$this->noHarm($candidate['calibration'], $baseline['calibration'])) continue;
            $candidates->push($candidate);
        }
        $ranked = $candidates->sortByDesc(fn (array $candidate): float =>
            (float) ($candidate['calibration']['hit_rate'] ?? 0)
            + min(5, (float) ($candidate['calibration']['profit_factor'] ?? 0)) * 10
            + (float) ($candidate['calibration']['average_return_percent'] ?? 0) * 3
        );
        $selected = $ranked->first(fn (array $candidate): bool => $this->noHarm($candidate['oos'], $baseline['oos'])) ?? $baseline;
        $postFilterQualityClass = $this->qualityClass($selected['oos']);
        $postFilterEvidence = $this->evidenceClass((int) ($selected['oos']['trades'] ?? 0));
        $rawQualityClass = (string) ($payload['raw_pre_filter_quality_class'] ?? 'unqualified');
        $rawReleased = filter_var($payload['raw_pre_filter_release_candidate'] ?? false, FILTER_VALIDATE_BOOL)
            || in_array($rawQualityClass, ['quality', 'solid'], true);
        $postFilterReleased = in_array($postFilterQualityClass, ['quality','solid'], true);
        // Context filters are confirmations/vetos for individual signals. They
        // may improve the final class, but sparse or weaker filtered samples
        // must never revoke an already validated, profitable raw WF model.
        $released = $rawReleased || $postFilterReleased;
        $qualityClass = $postFilterReleased ? $postFilterQualityClass : $rawQualityClass;
        $status = $released ? $qualityClass.'_active' : $qualityClass.'_documented';

        $evaluation = [
            'version' => 'post-filter-no-harm-v1', 'split' => $split,
            'baseline' => $baseline, 'selected' => $selected,
            'tested_combinations' => $candidates->count(),
            'quality_class' => $qualityClass, 'post_filter_quality_class' => $postFilterQualityClass,
            'post_filter_evidence' => $postFilterEvidence,
            'raw_release_preserved' => $rawReleased && !$postFilterReleased,
            'released' => $released,
            'rules' => [
                'minimum_events' => $minimumEvents,
                'low_trade_count_is_evidence_only' => true,
                'no_harm_metrics' => ['hit_rate','profit_factor','average_return_percent','maximum_drawdown_percent'],
                'post_filters_cannot_revoke_validated_raw_model' => true,
            ],
            'unavailable_filters' => [
                'stock_phase20_history' => 'keine historische Point-in-Time-Reihe',
                'index60' => $scored->contains(fn ($row) => is_numeric($row->index60_probability)) ? null : 'kein Heimatindex oder keine historische Indexreihe; Filter übersprungen',
                'sector60' => $scored->contains(fn ($row) => is_numeric($row->sector60_probability)) ? null : 'kein Sektor oder keine historische Sektorreihe; Filter übersprungen',
                'noise' => $scored->contains(fn ($row) => is_numeric($row->noise_score)) ? null : 'keine vollständige 5T/10T/15T/20T-Reihe',
            ],
        ];
        $payload['post_filter_evaluation'] = $evaluation;
        $payload['final_quality_class'] = $qualityClass;
        if (!$this->option('dry-run')) {
            DB::transaction(function () use ($threshold, $instrument, $payload, $status, $released, $qualityClass): void {
                DB::table('stock_individual_thresholds')->where('id', $threshold->id)->update([
                    'status' => $status, 'validation_passed' => $released,
                    'activated_at' => $released ? now() : null,
                    'score_result' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
                $meta = is_string($instrument->meta) ? (json_decode($instrument->meta, true) ?: []) : (array) $instrument->meta;
                $meta['model_quality_class'] = $qualityClass;
                $meta['model_quality_policy'] = 'post-filter-no-harm-v1';
                $meta['model_quality_updated_at'] = now()->toIso8601String();
                if ($released) unset($meta['deactivated_reason'], $meta['deactivated_at']);
                DB::table('instruments')->where('id', $instrument->id)->update([
                    'is_active' => $released, 'meta' => json_encode($meta, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ]);
            });
        }
        $this->info(json_encode(['symbol'=>$instrument->symbol,'status'=>$status,'baseline'=>$baseline,'selected'=>$selected], JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }

    private function evaluate(Collection $rows, float $cutoff, array $selectedFilters, array $filters, string $split): array
    {
        $previous = false;
        $entries = $rows->filter(function (object $row) use ($cutoff, $selectedFilters, $filters, &$previous): bool {
            $accepted = (float) $row->historical_action_score >= $cutoff;
            foreach ($selectedFilters as $filter) $accepted = $accepted && $filters[$filter]($row);
            $entry = $accepted && !$previous; $previous = $accepted; return $entry;
        })->values();
        return [
            'filters' => $selectedFilters,
            'calibration' => $this->stats($entries->filter(fn ($row) => (string) $row->signal_date < $split)),
            'oos' => $this->stats($entries->filter(fn ($row) => (string) $row->signal_date >= $split)),
        ];
    }

    private function stats(Collection $rows): array
    {
        $returns = $rows->filter(fn ($row) => is_numeric($row->net_return ?? null))->map(fn ($row) => (float) $row->net_return);
        $wins = $returns->filter(fn ($v) => $v > 0)->sum(); $losses = abs($returns->filter(fn ($v) => $v < 0)->sum());
        $equity = 1.0; $peak = 1.0; $maximumDrawdown = 0.0;
        foreach ($returns as $return) {
            $equity *= max(0.000001, 1.0 + (float) $return);
            $peak = max($peak, $equity);
            $maximumDrawdown = max($maximumDrawdown, 100 * ($peak - $equity) / $peak);
        }
        return ['trades'=>$returns->count(),
            'hit_rate'=>$returns->isEmpty()?null:round(100*$returns->filter(fn($v)=>$v>0)->count()/$returns->count(),2),
            'profit_factor'=>$losses>0?round($wins/$losses,3):($wins>0?999.0:null),
            'average_return_percent'=>$returns->isEmpty()?null:round(100*$returns->avg(),3),
            'maximum_drawdown_percent'=>$returns->isEmpty()?null:round($maximumDrawdown,3)];
    }

    private function noHarm(array $candidate, array $baseline): bool
    {
        foreach (['hit_rate','profit_factor','average_return_percent'] as $metric) {
            if (!is_numeric($candidate[$metric] ?? null) || !is_numeric($baseline[$metric] ?? null)) return false;
            if ((float) $candidate[$metric] + 1e-9 < (float) $baseline[$metric]) return false;
        }
        if (!is_numeric($candidate['maximum_drawdown_percent'] ?? null)
            || !is_numeric($baseline['maximum_drawdown_percent'] ?? null)
            || (float) $candidate['maximum_drawdown_percent'] > (float) $baseline['maximum_drawdown_percent'] + 1e-9) return false;
        return true;
    }

    private function qualityClass(array $stats): string
    {
        $n=(int)($stats['trades']??0); $hit=(float)($stats['hit_rate']??0); $pf=(float)($stats['profit_factor']??0); $avg=(float)($stats['average_return_percent']??0);
        if ($n>=20 && $hit>=65 && $pf>=1.5 && $avg>0) return 'quality';
        if ($n>=10 && $hit>=60 && $pf>=1.5 && $avg>0) return 'solid';
        if ($n>=1 && $hit>=55 && $pf>=1.2 && $avg>0) return 'basic';
        return 'unqualified';
    }

    private function evidenceClass(int $trades): string
    {
        if ($trades >= 20) return 'high';
        if ($trades >= 10) return 'medium';
        if ($trades >= 1) return 'low';
        return 'none';
    }
}

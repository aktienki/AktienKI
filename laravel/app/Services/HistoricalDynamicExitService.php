<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HistoricalDynamicExitService
{
    public const AUTOMATIC_VARIANTS = [
        'auto_exit_fixed_20d' => ['fixed_20d' => true],
        'auto_exit_dynamic_horizon' => ['dynamic_horizon' => true],
        'auto_exit_support_stop' => ['support_stop' => true],
        'auto_exit_resistance_trailing' => ['resistance_trailing_stop' => true],
        'auto_exit_signal_change' => ['signal_change_exit' => true],
        'auto_entry_wait_5d' => ['entry_wait_5d' => true, 'fixed_20d' => true],
    ];
    private array $barsCache = [];
    private array $predictionCache = [];
    private array $horizonCache = [];
    private array $levelCache = [];

    public function optimize(int $runId, string $goal = 'maximize_performance', string $riskProfile = 'normal'): array
    {
        $this->clearCaches();
        $trades = DB::table('backtest_trades')->where('backtest_run_id', $runId)->orderBy('entry_date')->orderBy('id')->get();
        if ($trades->isEmpty()) return ['rules' => [], 'variants_checked' => 0, 'metrics' => []];
        $ruleKeys = ['fixed_20d', 'dynamic_horizon', 'support_stop', 'resistance_trailing_stop', 'entry_wait_5d'];
        $best = null;
        $comparison = [];
        for ($mask = 0; $mask < 32; $mask++) {
            $rules = collect($ruleKeys)->mapWithKeys(fn (string $key, int $bit): array => [$key => (bool) ($mask & (1 << $bit))])->all();
            $results = $trades->map(fn (object $trade) => $this->evaluate($trade, $rules))->filter()->values();
            if ($results->count() < 10) continue;
            $split = max(1, (int) floor($results->count() * .7));
            $training = $this->metrics($results->take($split));
            $validation = $this->metrics($results->slice($split));
            if ($validation['average_return'] <= 0 || $validation['profit_factor'] < 1) continue;
            $robustness = min($training['average_return'], $validation['average_return'])
                - abs($training['average_return'] - $validation['average_return']) * .35;
            $score = match ($goal) {
                'reduce_drawdown' => $robustness * 25 + $validation['profit_factor'] * 4 - $validation['max_drawdown'] * 3,
                'fewer_trades' => $robustness * 35 + $validation['profit_factor'] * 5 - $validation['early_exits'] * .05,
                default => $robustness * 100 + $validation['profit_factor'] * 8 - $validation['max_drawdown'] * ($riskProfile === 'cautious' ? 1.5 : .5),
            };
            $comparison[] = ['rules' => $rules, 'training' => $training, 'validation' => $validation, 'score' => $score];
            if ($best === null || $score > $best['score']) $best = compact('rules', 'training', 'validation', 'score');
        }
        $best ??= ['rules' => ['fixed_20d'=>true,'dynamic_horizon'=>false,'support_stop'=>false,'resistance_trailing_stop'=>false], 'training'=>[], 'validation'=>[], 'score'=>0.0];
        $applied = $this->apply($runId, $best['rules']);

        return [...$best, ...$applied, 'comparison' => collect($comparison)->sortByDesc('score')->values()->all(),
            'variants_checked' => 32, 'method' => 'chronological_70_30_robustness'];
    }

    public function apply(int $runId, array $rules): array
    {
        if ($this->barsCache === []) $this->clearCaches();
        if (! collect($rules)->contains(true)) return ['trades' => 0, 'changed' => 0];
        $trades = DB::table('backtest_trades')->where('backtest_run_id', $runId)->orderBy('id')->get();
        $changed = 0;
        foreach ($trades as $trade) {
            $result = $this->evaluate($trade, $rules);
            if ($result === null) continue;
            $metadata = is_string($trade->metadata) ? (json_decode($trade->metadata, true) ?: []) : (array) $trade->metadata;
            DB::table('backtest_trades')->where('id', $trade->id)->update([
                'exit_date' => $result['exit_date'], 'exit_price' => $result['exit_price'],
                'gross_return' => $result['gross_return'],
                'net_return' => $result['gross_return'] - (float) $trade->transaction_cost,
                'max_drawdown' => $result['max_drawdown'],
                'metadata' => json_encode([...$metadata, 'dynamic_exit' => $result['details']], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $changed++;
        }
        return ['trades' => $trades->count(), 'changed' => $changed];
    }

    public function compareAll(int $runId): array
    {
        $this->clearCaches();
        DB::table('backtest_strategy_trades')->where('backtest_run_id', $runId)
            ->whereIn('strategy', array_keys(self::AUTOMATIC_VARIANTS))->delete();
        $trades = DB::table('backtest_trades')->where('backtest_run_id', $runId)->orderBy('id')->get();
        $summary = [];
        foreach (self::AUTOMATIC_VARIANTS as $strategy => $rules) {
            $rows = [];
            foreach ($trades as $trade) {
                $result = $this->evaluate($trade, $rules);
                if ($result === null) continue;
                $rows[] = [
                    'backtest_run_id' => $runId,
                    'backtest_trade_id' => $trade->id,
                    'instrument_id' => $trade->instrument_id,
                    'strategy' => $strategy,
                    'entry_date' => data_get($result, 'details.effective_entry_date', $trade->entry_date),
                    'exit_date' => $result['exit_date'],
                    'entry_price' => $result['entry_price'],
                    'exit_price' => $result['exit_price'],
                    'gross_return' => $result['gross_return'],
                    'max_drawdown' => $result['max_drawdown'],
                    'metadata' => json_encode(['engine' => 'automatic_strategy_comparison_v1', 'rules' => $rules, 'details' => $result['details']], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ];
                if (count($rows) >= 500) { DB::table('backtest_strategy_trades')->insert($rows); $rows = []; }
            }
            if ($rows !== []) DB::table('backtest_strategy_trades')->insert($rows);
            $summary[$strategy] = DB::table('backtest_strategy_trades')->where('backtest_run_id', $runId)->where('strategy', $strategy)->count();
        }
        return $summary;
    }

    private function evaluate(object $trade, array $rules): ?array
    {
        $entry = (float) $trade->entry_price;
        if ($entry <= 0) return null;
        $originalEntryDate = (string) $trade->entry_date;
        $entryDate = $originalEntryDate;
        $entryWaitDays = 0;
        if ($rules['entry_wait_5d'] ?? false) {
            $prediction = $this->predictionAt((int) $trade->instrument_id, $originalEntryDate);
            $expected = collect([5, 10, 15, 20])->map(function (int $days) use ($prediction): ?float {
                $current = (float) ($prediction?->current_price ?? 0);
                $target = (float) data_get($prediction, 'predicted_price_'.$days.'d', 0);
                return $current > 0 && $target > 0 ? ($target - $current) / $current : null;
            })->filter(fn ($value): bool => $value !== null)->max();
            if (is_numeric($expected) && $expected > 0) {
                $bars = $this->bars((int) $trade->instrument_id)->filter(fn (object $bar): bool => $bar->date >= $originalEntryDate)->take(6)->values();
                $reference = (float) ($prediction?->current_price ?: $entry);
                $eligible = $bars->search(fn (object $bar): bool => (((float) $bar->close - $reference) / max(.0001, $reference)) <= (float) $expected);
                if ($eligible === false) return null;
                $entryWaitDays = (int) $eligible;
                $entryDate = substr((string) $bars[$eligible]->bar_time, 0, 10);
                $entry = (float) $bars[$eligible]->close;
            }
        }
        $endDate = (string) $trade->exit_date;
        if ($rules['fixed_20d'] ?? false) {
            $fixedBar = $this->bars((int) $trade->instrument_id)->filter(fn (object $bar): bool => $bar->date >= $entryDate)->values()->get(19);
            if ($fixedBar?->bar_time) $endDate = min($endDate, substr((string) $fixedBar->bar_time, 0, 10));
        }
        if ($rules['dynamic_horizon'] ?? false) {
            $peak = $this->horizonAt((int) $trade->instrument_id, $originalEntryDate);
            if ($peak?->exit_date) $endDate = min($endDate, (string) $peak->exit_date);
        }
        if ($rules['signal_change_exit'] ?? false) {
            $signalChange = $this->signalChangeAfter((int) $trade->instrument_id, $entryDate);
            if ($signalChange !== null) $endDate = min($endDate, $signalChange);
        }
        $history = $this->bars((int) $trade->instrument_id)->filter(fn (object $bar): bool => $bar->date <= $endDate)->values();
        $entryIndex = $history->search(fn (object $bar): bool => $bar->date >= $entryDate);
        if ($entryIndex === false) return null;
        $stop = null;
        $reason = ($rules['signal_change_exit'] ?? false) && $endDate === ($signalChange ?? null)
            ? 'signal_change'
            : (($rules['dynamic_horizon'] ?? false) ? 'dynamic_horizon' : 'scheduled_exit');
        $exitBar = $history->last(); $runningLow = $entry;
        for ($index = (int) $entryIndex; $index < $history->count(); $index++) {
            $bar = $history[$index];
            $runningLow = min($runningLow, (float) $bar->low);
            $levels = $this->levelsAt((int) $trade->instrument_id, $history, $index, (float) $bar->close);
            if (($rules['support_stop'] ?? false) && is_numeric($levels['support'])) {
                $supportStop = (float) $levels['support'] * .99;
                $stop = $stop === null ? $supportStop : max($stop, $supportStop);
            }
            if (($rules['resistance_trailing_stop'] ?? false) && (float) $bar->close > $entry
                && is_numeric($levels['resistance']) && (float) $bar->high >= (float) $levels['resistance']) {
                $stop = max($stop ?? 0, (float) $levels['resistance'] * .99);
                $reason = 'resistance_stop_armed';
            }
            if ($stop !== null && (float) $bar->low <= $stop) {
                $exitBar = $bar; $reason = 'dynamic_stop_triggered';
                $exitPrice = min((float) $bar->open, $stop);
                break;
            }
            $exitBar = $bar; $exitPrice = (float) $bar->close;
        }
        $exitPrice ??= (float) $exitBar->close;
        return [
            'entry_price' => $entry,
            'exit_date' => substr((string) $exitBar->bar_time, 0, 10), 'exit_price' => $exitPrice,
            'gross_return' => ($exitPrice - $entry) / $entry,
            'net_return' => (($exitPrice - $entry) / $entry) - (float) ($trade->transaction_cost ?? 0),
            'max_drawdown' => ($runningLow - $entry) / $entry,
            'details' => ['reason' => $reason, 'stop_price' => $stop, 'rules' => $rules,
                'original_entry_date' => $originalEntryDate, 'effective_entry_date' => $entryDate, 'entry_wait_days' => $entryWaitDays],
        ];
    }

    private function metrics(Collection $results): array
    {
        $returns = $results->pluck('net_return')->map(fn ($value): float => (float) $value);
        $wins = $returns->filter(fn (float $value): bool => $value > 0)->sum();
        $losses = abs((float) $returns->filter(fn (float $value): bool => $value < 0)->sum());
        return [
            'trades' => $results->count(),
            'average_return' => (float) $returns->avg() * 100,
            'profit_factor' => $losses > 0 ? $wins / $losses : ($wins > 0 ? 99.0 : 0.0),
            'hit_rate' => $results->count() ? $returns->filter(fn (float $value): bool => $value > 0)->count() / $results->count() * 100 : 0,
            'max_drawdown' => abs((float) $results->pluck('max_drawdown')->min()) * 100,
            'early_exits' => $results->filter(fn (array $result): bool => data_get($result, 'details.reason') === 'dynamic_stop_triggered')->count(),
        ];
    }

    private function levels(Collection $bars, float $current): array
    {
        if ($bars->count() < 7) return ['support' => null, 'resistance' => null];
        $range = max(.01, (float) $bars->max('high') - (float) $bars->min('low'));
        $tolerance = max($range * .012, $current * .006);
        $lows = []; $highs = [];
        for ($i = 2; $i < $bars->count() - 2; $i++) {
            if ((float) $bars[$i]->low <= (float) $bars[$i-1]->low && (float) $bars[$i]->low <= (float) $bars[$i-2]->low && (float) $bars[$i]->low <= (float) $bars[$i+1]->low && (float) $bars[$i]->low <= (float) $bars[$i+2]->low) $lows[]=(float)$bars[$i]->low;
            if ((float) $bars[$i]->high >= (float) $bars[$i-1]->high && (float) $bars[$i]->high >= (float) $bars[$i-2]->high && (float) $bars[$i]->high >= (float) $bars[$i+1]->high && (float) $bars[$i]->high >= (float) $bars[$i+2]->high) $highs[]=(float)$bars[$i]->high;
        }
        $zone = function(array $values, bool $above) use($tolerance,$current): ?float {
            $groups=[]; foreach($values as $value){$key=collect($groups)->search(fn($g)=>abs($g[0]-$value)<=$tolerance); if($key===false)$groups[]=[$value];else$groups[$key][]=$value;}
            return collect($groups)->filter(fn($g)=>count($g)>=2)->map(fn($g)=>array_sum($g)/count($g))->filter(fn($v)=>$above?$v>=$current:$v<=$current)->sortBy(fn($v)=>abs($v-$current))->first();
        };
        return ['support'=>$zone($lows,false),'resistance'=>$zone($highs,true)];
    }

    private function clearCaches(): void
    {
        $this->barsCache = $this->predictionCache = $this->horizonCache = $this->levelCache = [];
    }

    private function bars(int $instrumentId): Collection
    {
        return $this->barsCache[$instrumentId] ??= DB::table('price_bars')
            ->where('instrument_id', $instrumentId)->where('interval', '1d')->orderBy('bar_time')
            ->get(['bar_time', 'open', 'high', 'low', 'close'])->map(function (object $bar): object {
                $bar->date = substr((string) $bar->bar_time, 0, 10);
                return $bar;
            });
    }

    private function predictionAt(int $instrumentId, string $date): ?object
    {
        if (! array_key_exists($instrumentId, $this->predictionCache)) {
            $this->predictionCache[$instrumentId] = DB::table('predictions')->where('instrument_id', $instrumentId)
                ->orderBy('prediction_time')->get(['prediction_time', 'signal', 'current_price', 'predicted_price_5d',
                    'predicted_price_10d', 'predicted_price_15d', 'predicted_price_20d']);
        }
        return $this->predictionCache[$instrumentId]->last(fn (object $prediction): bool => substr((string) $prediction->prediction_time, 0, 10) <= $date);
    }

    private function signalChangeAfter(int $instrumentId, string $entryDate): ?string
    {
        $this->predictionAt($instrumentId, $entryDate);

        $prediction = $this->predictionCache[$instrumentId]->first(
            fn (object $prediction): bool => substr((string) $prediction->prediction_time, 0, 10) > $entryDate
                && strtoupper((string) ($prediction->signal ?? '')) !== 'BUY'
        );

        return $prediction === null ? null : substr((string) $prediction->prediction_time, 0, 10);
    }

    private function horizonAt(int $instrumentId, string $date): ?object
    {
        if (! array_key_exists($instrumentId, $this->horizonCache)) {
            $this->horizonCache[$instrumentId] = DB::table('walk_forward_backtest_trades')
                ->where('instrument_id', $instrumentId)->whereIn('horizon_days', [5, 10, 15, 20])
                ->whereNotNull('predicted_return')->orderByDesc('predicted_return')->get(['signal_date', 'exit_date', 'predicted_return'])
                ->groupBy(fn (object $row): string => substr((string) $row->signal_date, 0, 10));
        }
        return $this->horizonCache[$instrumentId]->get($date)?->first();
    }

    private function levelsAt(int $instrumentId, Collection $history, int $index, float $current): array
    {
        $key = $instrumentId.':'.$index;
        return $this->levelCache[$key] ??= $this->levels($history->slice(max(0, $index - 180), min(180, $index))->values(), $current);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\YahooIndexService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SignalTransitionController extends Controller
{
    public function index(Request $request, YahooIndexService $yahooIndexService, bool $backtestReference = false): View
    {
        if (! $backtestReference) {
            return $this->portfolioPerformance($request);
        }
        // A deliberately simple, comparable portfolio backtest: only the
        // latest completed 20-day walk-forward run and at most ten concurrent
        // positions. Competing signals are ranked by their historical score.
        $latestRunId = DB::table('walk_forward_backtest_runs')
            ->where('status', 'completed')
            ->where('horizon_days', 20)
            ->orderByDesc('id')
            ->value('id');
        $backtestPeriod = DB::table('walk_forward_backtest_trades')
            ->where('run_id', $latestRunId)
            ->where('horizon_days', 20)
            ->selectRaw('MIN(signal_date) AS starts_at, MAX(exit_date) AS ends_at')
            ->first();
        $backtestStart = Carbon::parse($backtestPeriod?->starts_at ?? now()->subYears(3)->toDateString())->startOfDay();
        $backtestEnd = Carbon::parse($backtestPeriod?->ends_at ?? now()->toDateString())->endOfDay();
        $days = $backtestStart->diffInDays($backtestEnd);

        $backtestQuery = DB::table('walk_forward_backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('walk_forward_backtest_scores as backtest_score', function ($join): void {
                $join->on('backtest_score.run_id', '=', 'trade.run_id')
                    ->on('backtest_score.instrument_id', '=', 'trade.instrument_id')
                    ->on('backtest_score.horizon_days', '=', 'trade.horizon_days')
                    ->where('backtest_score.position_side', '=', 'long');
            })
            ->where('trade.run_id', $latestRunId)
            ->where('trade.horizon_days', 20)
            ->whereRaw("UPPER(trade.signal) = 'BUY'")
            ->whereBetween('trade.signal_date', [$backtestStart->toDateString(), $backtestEnd->toDateString()])
            ->select([
                'trade.id', 'trade.instrument_id', 'trade.signal_date as prediction_time',
                'trade.exit_date as validated_at', 'trade.signal', 'trade.entry_price as current_price',
                'trade.exit_price', 'trade.net_return', 'trade.predicted_return', 'trade.horizon_days',
                'trade.validation_direction_accuracy', 'trade.validation_profit_factor',
                'trade.validation_trade_count', 'instrument.symbol', 'instrument.name', 'instrument.currency',
                'instrument.country', 'instrument.meta',
                'backtest_score.buy_score as historical_ai_score',
            ]);

        $candidates = $backtestQuery->clone()->orderBy('trade.signal_date')->orderBy('trade.id')->get()
            ->map(function (object $row): object {
                $row->performance_percent = is_numeric($row->net_return) ? (float) $row->net_return * 100 : null;
                $row->score_at_signal = is_numeric($row->historical_ai_score) ? (float) $row->historical_ai_score : null;
                $row->confidence_at_signal = is_numeric($row->validation_direction_accuracy)
                    ? (float) $row->validation_direction_accuracy * 100 : null;
                $row->risk_at_signal = null;
                $row->previous_signal = '—';
                $row->closed = $row->validated_at !== null;
                return $row;
            })->filter(fn (object $row): bool => $row->closed && $row->performance_percent !== null);

        $activePositions = collect();
        $closed = collect();
        foreach ($candidates->groupBy(fn (object $row): string => substr((string) $row->prediction_time, 0, 10)) as $signalDate => $dailyCandidates) {
            $activePositions = $activePositions->filter(
                fn (object $position): bool => substr((string) $position->validated_at, 0, 10) > $signalDate
            )->values();
            $activeInstrumentIds = $activePositions->pluck('instrument_id')->map(fn ($id): int => (int) $id)->all();
            foreach ($dailyCandidates->sortByDesc(fn (object $row): float => (float) ($row->score_at_signal ?? -INF)) as $candidate) {
                if ($activePositions->count() >= 10) break;
                if (in_array((int) $candidate->instrument_id, $activeInstrumentIds, true)) continue;
                $closed->push($candidate);
                $activePositions->push($candidate);
                $activeInstrumentIds[] = (int) $candidate->instrument_id;
            }
        }

        $stockPerformance = $closed->groupBy('instrument_id')->map(function ($trades): array {
            $trades = $trades->sortBy('prediction_time')->values();
            $equity = 1.0;
            $peak = 1.0;
            $maxDrawdown = 0.0;
            foreach ($trades as $trade) {
                $equity *= max(0.0, 1 + ((float) $trade->performance_percent / 100));
                $peak = max($peak, $equity);
                $drawdownPercent = $peak > 0 ? (($equity / $peak) - 1) * 100 : -100.0;
                $maxDrawdown = max(-100.0, min($maxDrawdown, $drawdownPercent));
            }
            $instrument = $trades->first();
            $meta = is_string($instrument->meta ?? null) ? json_decode($instrument->meta, true) : ($instrument->meta ?? []);

            return [
                'instrument_id' => (int) $instrument->instrument_id,
                'symbol' => (string) $instrument->symbol,
                'name' => (string) $instrument->name,
                'country' => strtoupper((string) ($instrument->country ?: '')),
                'logo_url' => data_get($meta, 'logo_url') ?? data_get($meta, 'logo') ?? data_get($meta, 'branding.logo_url'),
                'trades' => $trades->count(),
                'gross_profit' => (float) $trades->filter(fn (object $trade): bool => $trade->performance_percent > 0)->sum('performance_percent'),
                'gross_loss' => (float) $trades->filter(fn (object $trade): bool => $trade->performance_percent < 0)->sum('performance_percent'),
                'total' => (float) $trades->sum('performance_percent'),
                'average' => (float) $trades->avg('performance_percent'),
                'max_drawdown' => $maxDrawdown,
            ];
        })->sortByDesc('total')->values();
        $equity = 1.0; $peak = 1.0; $grossWins = 0.0; $grossLosses = 0.0;
        $chartData = ['performance' => [], 'profit_factor' => [], 'drawdown' => [], 'dated_performance' => []];
        foreach ($closed->sortBy('prediction_time')->values() as $trade) {
            $value = (float) $trade->performance_percent;
            $equity *= max(0.0, 1 + ($value / 1000));
            $peak = max($peak, $equity);
            if ($value > 0) $grossWins += $value;
            if ($value < 0) $grossLosses += abs($value);
            $chartData['performance'][] = round(($equity - 1) * 100, 4);
            $chartData['dated_performance'][substr((string) $trade->validated_at, 0, 10)] = round(($equity - 1) * 100, 4);
            $chartData['profit_factor'][] = $grossLosses > 0 ? round($grossWins / $grossLosses, 4) : ($grossWins > 0 ? 1.0 : 0.0);
            $chartData['drawdown'][] = round(max(-100.0, (($equity / $peak) - 1) * 100), 4);
        }
        $demo = $request->boolean('demo');
        if ($demo && count($chartData['performance']) < 2) {
            // Explicit preview only; these values are never stored or included in statistics.
            $chartData = [
                'performance' => [0.0, 1.8, 0.9, 3.4, 2.6, 5.1, 4.2, 7.0, 6.4, 8.1],
                'profit_factor' => [0.0, 1.35, 1.12, 1.58, 1.42, 1.73, 1.61, 1.88, 1.77, 1.95],
                'drawdown' => [0.0, 0.0, -0.9, 0.0, -0.8, 0.0, -0.9, 0.0, -0.6, 0.0],
                'dated_performance' => [],
            ];
        }
        $stats = (object) [
            'transitions' => $closed->count(),
            'closed' => $closed->count(),
            'wins' => $closed->filter(fn (object $row): bool => $row->performance_percent > 0)->count(),
            'losses' => $closed->filter(fn (object $row): bool => $row->performance_percent < 0)->count(),
            'win_rate' => $closed->isNotEmpty() ? ($closed->filter(fn (object $row): bool => $row->performance_percent > 0)->count() / $closed->count()) * 100 : 0,
            'average' => $closed->isNotEmpty() ? $closed->avg('performance_percent') / 10 : 0,
            'profit_per_trade' => $closed->isNotEmpty() ? $closed->avg('performance_percent') : 0,
            'total' => $chartData['performance'] !== [] ? (float) end($chartData['performance']) : 0,
            'annual_performance' => $chartData['performance'] !== [] && $days > 0
                ? (pow(max(0.0, 1 + ((float) end($chartData['performance']) / 100)), 365.25 / $days) - 1) * 100
                : 0,
            'profit_factor' => $grossLosses > 0 ? $grossWins / $grossLosses : ($grossWins > 0 ? null : 0),
            'max_drawdown' => collect($chartData['drawdown'])->min() ?? 0,
            'best' => ($closed->max('performance_percent') ?? 0) / 10,
            'worst' => ($closed->min('performance_percent') ?? 0) / 10,
        ];

        $benchmarkDefinitions = collect([
            ['symbol' => '^GDAXI', 'label' => 'DAX', 'color' => '#f59e0b'],
            ['symbol' => '^GSPC', 'label' => 'S&P 500', 'color' => '#8b5cf6'],
            ['symbol' => '^IXIC', 'label' => 'Nasdaq', 'color' => '#22c55e'],
        ]);
        $benchmarkInstruments = DB::table('instruments')
            ->whereIn('symbol', $benchmarkDefinitions->pluck('symbol'))
            ->pluck('id', 'symbol');
        $benchmarkBars = DB::table('price_bars')
            ->whereIn('instrument_id', $benchmarkInstruments->values())
            ->where('interval', '1d')
            ->whereBetween('bar_time', [$backtestStart, $backtestEnd])
            ->where('close', '>', 0)
            ->orderBy('bar_time')
            ->get(['instrument_id', 'bar_time', 'close'])
            ->groupBy('instrument_id');
        $daxInstrumentId = $benchmarkInstruments->get('^GDAXI');
        if ($daxInstrumentId && ($benchmarkBars->get($daxInstrumentId, collect())->first()?->bar_time ?? null) > $backtestStart) {
            $daxHistory = collect($yahooIndexService->dailyHistory('^GDAXI', '3y'))
                ->filter(fn (array $bar): bool => Carbon::createFromTimestampUTC($bar['timestamp'])->between($backtestStart, $backtestEnd))
                ->map(fn (array $bar): object => (object) [
                    'instrument_id' => $daxInstrumentId,
                    'bar_time' => Carbon::createFromTimestampUTC($bar['timestamp'])->toDateTimeString(),
                    'close' => $bar['adjusted_close'] ?? $bar['close'],
                ])->values();
            if ($daxHistory->isNotEmpty()) $benchmarkBars->put($daxInstrumentId, $daxHistory);
        }
        $benchmarkData = $benchmarkDefinitions->map(function (array $definition) use ($benchmarkInstruments, $benchmarkBars, $chartData): array {
            $instrumentId = $benchmarkInstruments->get($definition['symbol']);
            $bars = $instrumentId ? $benchmarkBars->get($instrumentId, collect())->values() : collect();
            $start = $bars->isNotEmpty() ? (float) $bars->first()->close : 0.0;
            $strategyByDate = collect($chartData['dated_performance']);
            $lastStrategyValue = 0.0;
            $timeline = $start > 0 ? $bars->map(function (object $bar) use ($start, $strategyByDate, &$lastStrategyValue): array {
                $date = substr((string) $bar->bar_time, 0, 10);
                if ($strategyByDate->has($date)) $lastStrategyValue = (float) $strategyByDate->get($date);
                return [
                    'date' => $date,
                    'strategy' => round($lastStrategyValue, 4),
                    'benchmark' => round((((float) $bar->close / $start) - 1) * 100, 4),
                ];
            })->values() : collect();
            $values = $timeline->pluck('benchmark')->all();
            $strategyValues = $timeline->pluck('strategy')->all();

            return $definition + [
                'dates' => $timeline->pluck('date')->all(),
                'values' => $values,
                'performance' => $values !== [] ? (float) end($values) : null,
                'strategy_values' => $strategyValues,
                'strategy_performance' => $strategyValues !== [] ? (float) end($strategyValues) : null,
            ];
        });

        $groupStats = function ($trades, string $label): array {
            $trades = $trades->values();
            $wins = $trades->filter(fn (object $row): bool => $row->performance_percent > 0);
            $grossProfit = $wins->sum('performance_percent');
            $grossLoss = abs($trades->filter(fn (object $row): bool => $row->performance_percent < 0)->sum('performance_percent'));
            return [
                'label' => $label,
                'trades' => $trades->count(),
                'hit_rate' => $trades->isNotEmpty() ? ($wins->count() / $trades->count()) * 100 : null,
                'average' => $trades->isNotEmpty() ? $trades->avg('performance_percent') : null,
                'total' => $trades->sum('performance_percent'),
                'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : ($grossProfit > 0 ? null : 0),
            ];
        };
        $signalStats = collect(['BUY'])->map(fn (string $signal): array =>
            $groupStats($closed, $signal)
        );
        $horizonStats = collect([20])->map(fn (int $horizon): array =>
            $groupStats($closed->filter(fn (object $row): bool => $row->horizon_days === $horizon), $horizon.' Tage')
        );

        $scoreRanges = collect([
            ['label' => '< 4', 'min' => 0.0, 'max' => 4.0],
            ['label' => '4–5', 'min' => 4.0, 'max' => 5.0],
            ['label' => '5–6', 'min' => 5.0, 'max' => 6.0],
            ['label' => '6–7', 'min' => 6.0, 'max' => 7.0],
            ['label' => '7–8', 'min' => 7.0, 'max' => 8.0],
            ['label' => '8–10', 'min' => 8.0, 'max' => 10.01],
        ]);
        $scoreStats = $scoreRanges->map(function (array $range) use ($closed): array {
            $trades = $closed->filter(fn (object $row): bool => is_numeric($row->score_at_signal)
                && (float) $row->score_at_signal >= $range['min']
                && (float) $row->score_at_signal < $range['max'])
                ->sortBy('prediction_time')->values();
            $wins = $trades->filter(fn (object $row): bool => $row->performance_percent > 0)->count();
            $equity = 1.0; $peak = 1.0; $drawdowns = [];
            foreach ($trades as $trade) {
                $equity *= max(0.0, 1 + ((float) $trade->performance_percent / 100));
                $peak = max($peak, $equity);
                $drawdowns[] = max(-100.0, (($equity / $peak) - 1) * 100);
            }

            return [
                'label' => $range['label'],
                'trades' => $trades->count(),
                'performance' => $trades->isNotEmpty() ? (float) $trades->avg('performance_percent') : null,
                'hit_rate' => $trades->isNotEmpty() ? ($wins / $trades->count()) * 100 : null,
                'average_drawdown' => $drawdowns !== [] ? (float) collect($drawdowns)->avg() : null,
            ];
        });

        $snapshot = [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'period' => ['from' => $backtestStart->toDateString(), 'to' => $backtestEnd->toDateString()],
            'rules' => ['horizon_days' => 20, 'maximum_positions' => 10, 'position_weight_percent' => 10],
            'statistics' => [
                'trades' => $stats->transitions,
                'evaluated' => $stats->closed,
                'hit_rate_percent' => round($stats->win_rate, 2),
                'profit_per_trade_percent' => round($stats->profit_per_trade, 2),
                'annual_performance_percent' => round($stats->annual_performance, 2),
                'maximum_drawdown_percent' => round($stats->max_drawdown, 2),
            ],
            'benchmarks' => $benchmarkData->map(fn (array $benchmark): array => [
                'symbol' => $benchmark['symbol'], 'label' => $benchmark['label'],
                'performance_percent' => $benchmark['performance'],
                'strategy_performance_percent' => $benchmark['strategy_performance'],
            ])->values()->all(),
        ];
        Storage::disk('public')->put('statistics/trade-performance-backtest.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return view('predictions.trade-performance-backtest', compact('stockPerformance', 'stats', 'days', 'backtestStart', 'backtestEnd', 'chartData', 'benchmarkData', 'demo', 'signalStats', 'horizonStats', 'scoreStats'));
    }

    private function portfolioPerformance(Request $request): View
    {
        $portfolios = DB::table('portfolios')->where('user_id', $request->user()->id)
            ->where('type', 'paper')->where('active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'currency']);
        $selectedPortfolioId = (int) $request->query('portfolio', $portfolios->first()?->id ?? 0);
        if (! $portfolios->contains('id', $selectedPortfolioId)) $selectedPortfolioId = (int) ($portfolios->first()?->id ?? 0);
        $transactions = $selectedPortfolioId ? DB::table('portfolio_transactions as transaction')
            ->join('instruments as instrument', 'instrument.id', '=', 'transaction.instrument_id')
            ->where('transaction.portfolio_id', $selectedPortfolioId)->where('transaction.type', 'sell')
            ->orderBy('transaction.transaction_date')->orderBy('transaction.id')
            ->get(['transaction.*', 'instrument.symbol', 'instrument.name', 'instrument.country', 'instrument.meta']) : collect();
        $trades = $transactions->map(function (object $transaction): array {
            $meta = is_string($transaction->meta) ? (json_decode($transaction->meta, true) ?: []) : (array) $transaction->meta;
            return [
                'id' => $transaction->id, 'instrument_id' => $transaction->instrument_id,
                'symbol' => $transaction->symbol, 'name' => $transaction->name, 'country' => $transaction->country,
                'logo_url' => data_get($meta, 'logo_url'), 'date' => substr((string) $transaction->transaction_date, 0, 10),
                'profit' => (float) ($meta['realized_profit'] ?? 0),
                'return' => is_numeric($meta['performance_percent'] ?? null) ? (float) $meta['performance_percent'] : 0.0,
                'currency' => $transaction->currency,
            ];
        });
        $equity = 1.0; $peak = 1.0; $maxDrawdown = 0.0; $curve = [];
        foreach ($trades as $trade) {
            $equity *= max(0.0, 1 + ($trade['return'] / 100)); $peak = max($peak, $equity);
            $maxDrawdown = min($maxDrawdown, max(-100.0, (($equity / $peak) - 1) * 100));
            $curve[] = ['date' => $trade['date'], 'value' => round(($equity - 1) * 100, 4)];
        }
        $firstDate = $trades->first()['date'] ?? null; $lastDate = $trades->last()['date'] ?? null;
        $periodDays = $firstDate && $lastDate ? max(1, Carbon::parse($firstDate)->diffInDays(Carbon::parse($lastDate))) : 0;
        $totalPerformance = ($equity - 1) * 100;
        $stats = [
            'trades' => $trades->count(), 'hit_rate' => $trades->isNotEmpty() ? $trades->where('return', '>', 0)->count() / $trades->count() * 100 : 0,
            'profit_per_trade' => $trades->avg('return') ?? 0, 'realized_profit' => $trades->sum('profit'),
            'annual_performance' => $periodDays > 0 ? (pow(max(0, 1 + $totalPerformance / 100), 365.25 / $periodDays) - 1) * 100 : $totalPerformance,
            'max_drawdown' => $maxDrawdown, 'total_performance' => $totalPerformance,
        ];
        $stockPerformance = $trades->groupBy('instrument_id')->map(function ($rows): array {
            $first = $rows->first(); $equity = 1.0; $peak = 1.0; $maxDrawdown = 0.0;
            foreach ($rows as $row) { $equity *= max(0, 1 + $row['return'] / 100); $peak = max($peak, $equity); $maxDrawdown = min($maxDrawdown, (($equity / $peak) - 1) * 100); }
            return $first + ['trades' => $rows->count(), 'gross_profit' => $rows->where('return', '>', 0)->sum('return'), 'gross_loss' => $rows->where('return', '<', 0)->sum('return'), 'total' => ($equity - 1) * 100, 'average' => $rows->avg('return'), 'max_drawdown' => $maxDrawdown];
        })->sortByDesc('total')->values();

        return view('predictions.signal-history', compact('portfolios', 'selectedPortfolioId', 'trades', 'curve', 'stats', 'stockPerformance'));
    }
}

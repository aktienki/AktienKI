<?php

namespace App\Http\Controllers;

use App\Jobs\RunFilteredBacktest;
use App\Services\PersonalizedSignalService;
use App\Services\SavedFilterLimitService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\Response;

final class PredictionController extends Controller
{
    public function backtestTrades(Request $request): View
    {
        $selectedUserBacktest = $this->requestedUserBacktestRun($request);
        $isUserBacktestResult = $selectedUserBacktest !== null
            && in_array($selectedUserBacktest->status, ['completed', 'completed_with_errors'], true);
        $backtestRunId = $this->selectedBacktestRunId($request);
        $eligibleInstruments = $isUserBacktestResult
            ? null
            : $this->eligibleBacktestInstruments($backtestRunId, $request);
        $scoreBucket = max(0, min(9, $request->integer('score_bucket')));
        $confidenceBucket = max(0, min(9, $request->integer('confidence_bucket')));
        $sortColumns = [
            'entry' => 'trade.entry_date',
            'stock' => 'instrument.symbol',
            'exchange' => 'exchange.code',
            'entry_price' => 'trade.entry_price',
            'exit_price' => 'trade.exit_price',
            'return' => 'trade.net_return',
            'drawdown' => 'trade.max_drawdown',
            'score' => 'trade.ki_score',
            'confidence' => 'trade.confidence',
            'model' => 'model_definition.public_alias',
        ];
        $sort = array_key_exists((string) $request->query('sort'), $sortColumns)
            ? (string) $request->query('sort')
            : 'entry';
        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';
        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $latestTechnicalIndicators = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, MAX(id) AS technical_id')
            ->groupBy('instrument_id');

        $query = DB::table('backtest_trades as trade')
            ->join('backtest_runs as run', 'run.id', '=', 'trade.backtest_run_id')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'trade.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trade.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoinSub($latestTechnicalIndicators, 'latest_technical', fn ($join) =>
                $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
            ->where('trade.backtest_run_id', $backtestRunId)
            ->when($eligibleInstruments !== null, fn (Builder $query) =>
                $query->whereIn('trade.instrument_id', $eligibleInstruments))
            ->whereRaw('LEAST(9, GREATEST(0, FLOOR(trade.ki_score)))::integer = ?', [$scoreBucket])
            ->whereRaw('LEAST(9, GREATEST(0, FLOOR(trade.confidence / 10)))::integer = ?', [$confidenceBucket])
            ->when($request->integer('instrument_id') > 0, fn (Builder $query) =>
                $query->where('trade.instrument_id', $request->integer('instrument_id')))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when($request->filled('country'), fn (Builder $query) =>
                $query->where('instrument.country', strtoupper(trim((string) $request->query('country')))))
            ->when($request->filled('sector'), fn (Builder $query) =>
                $query->where('instrument.sector', trim((string) $request->query('sector'))))
            ->when($request->filled('exchange'), fn (Builder $query) =>
                $query->where('exchange.code', strtoupper(trim((string) $request->query('exchange')))))
            ->when(in_array($request->query('ai_type'), ['horizon', 'pulse'], true), fn (Builder $query) =>
                $query->where('trade.ai_type', $request->query('ai_type')))
            ->when($request->integer('model') > 0, fn (Builder $query) =>
                $query->where('trade.model_definition_id', $request->integer('model')))
            ->when(in_array(strtoupper((string) $request->query('signal')), ['BUY', 'SELL'], true), fn (Builder $query) =>
                $query->where('trade.signal', strtoupper((string) $request->query('signal'))))
            ->when($request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->where('trade.ki_score', '>=', max(0, min(10, (float) $request->query('score_min')))))
            ->when($request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->where('trade.confidence', '>=', max(0, min(100, (float) $request->query('confidence_min')))))
            ->when($request->filled('volatility_max') && is_numeric($request->query('volatility_max')) && (float) $request->query('volatility_max') < 100, fn (Builder $query) =>
                $query->where('technical.volatility_20', '<=', max(0, (float) $request->query('volatility_max')) / 100));

        $trades = $query
            ->select([
                'trade.id',
                'trade.entry_date',
                'trade.exit_date',
                'trade.signal',
                'trade.entry_price',
                'trade.exit_price',
                'trade.net_return',
                'trade.max_drawdown',
                'trade.ki_score',
                'trade.confidence',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.currency',
                'exchange.code as exchange_code',
                'model_definition.public_alias as model_alias',
                'run.id as run_id',
            ])
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('instrument.symbol')
            ->get();

        $normalizedPosition = 1000.0;
        $tradeStats = (object) [
            'absolute_profit' => $trades->sum(fn (object $trade): float =>
                (float) $trade->net_return * $normalizedPosition),
            'average_performance' => $trades->isNotEmpty()
                ? $trades->avg(fn (object $trade): float => (float) $trade->net_return) * 100
                : 0.0,
            'winning_trades' => $trades->filter(fn (object $trade): bool =>
                (float) $trade->net_return > 0)->count(),
            'normalized_position' => $normalizedPosition,
        ];

        return view('predictions.backtest-trades', compact(
            'trades',
            'tradeStats',
            'scoreBucket',
            'confidenceBucket',
            'sort',
            'direction',
        ));
    }

    public function heatmap(Request $request): View
    {
        $predictionView = $this->index($request);
        $predictionData = $predictionView->getData();

        return view('predictions.heatmap', array_intersect_key($predictionData, array_flip([
            'heatmap',
            'heatmapSummary',
            'countries',
            'exchanges',
            'sectors',
            'aiTypes',
            'models',
            'qualityTiers',
            'signals',
            'validationStates',
        ])));
    }

    public function filterSetup(Request $request): View
    {
        $filterState = collect(SavedPredictionFilterController::FILTER_DEFAULTS)
            ->mapWithKeys(fn ($default, string $key) => [$key => $request->query($key, $default)])
            ->all();
        $request->session()->put('setup_filter_state', $filterState);

        $predictionView = $this->index($request);
        $predictionData = $predictionView->getData();
        $data = array_intersect_key($predictionData, array_flip([
            'heatmap',
            'heatmapSummary',
            'countries',
            'exchanges',
            'sectors',
            'aiTypes',
            'models',
            'qualityTiers',
            'signals',
            'validationStates',
        ]));
        $data['setupMode'] = true;
        $data['activeBacktestRun'] = $this->requestedUserBacktestRun($request);
        $data['savedFilters'] = $request->user()->savedPredictionFilters()->orderBy('name')->get();
        $data['savedFilterLimit'] = app(SavedFilterLimitService::class)->limitFor($request->user());
        $data['editingSavedFilter'] = $request->integer('saved_filter') > 0
            ? $request->user()->savedPredictionFilters()->whereKey($request->integer('saved_filter'))->first()
            : null;

        return view('predictions.heatmap', $data);
    }

    public function startFilteredBacktest(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:8'],
            'exchange' => ['nullable', 'string', 'max:32'],
            'sector' => ['nullable', 'string', 'max:120'],
            'ai_type' => ['nullable', 'in:horizon,pulse'],
            'model' => ['nullable', 'integer', 'min:1'],
            'quality_tier' => ['nullable', 'in:top,strong,solid,test,unqualified'],
            'signal' => ['nullable', 'in:BUY,WATCH,HOLD,SELL'],
            'score_min' => ['nullable', 'numeric', 'between:0,10'],
            'confidence_min' => ['nullable', 'numeric', 'between:0,100'],
            'drawdown_max' => ['nullable', 'numeric', 'between:0,50'],
            'profit_factor_min' => ['nullable', 'numeric', 'between:0,3'],
            'volatility_max' => ['nullable', 'numeric', 'between:0,100'],
            'pe_max' => ['nullable', 'numeric', 'between:0,100'],
            'dividend_yield_min' => ['nullable', 'numeric', 'between:0,10'],
            'market_cap_min' => ['nullable', 'numeric', 'between:0,3000'],
            'revenue_growth_min' => ['nullable', 'numeric', 'between:-50,100'],
            'hit_rate_min' => ['nullable', 'numeric', 'between:0,100'],
            'initial_capital' => ['required', 'numeric', 'between:1000,1000000'],
            'max_positions' => ['required', 'integer', 'between:1,50'],
            'trade_cost' => ['required', 'numeric', 'between:0,1000'],
        ]);
        $initialCapital = (float) $filters['initial_capital'];
        $maxPositions = (int) $filters['max_positions'];
        $tradeCost = (float) $filters['trade_cost'];
        $sourceRun = DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            ->orderByDesc('id')
            ->first();
        abort_if($sourceRun === null, 422, __('Es ist kein abgeschlossener Drei-Jahres-Ausgangslauf vorhanden.'));

        $publicId = (string) Str::uuid();
        $settings = [
            'run_type' => 'user_filter',
            'initiated_by_user_id' => $request->user()->id,
            'source_run_id' => $sourceRun->id,
            'lookback_years' => 3,
            'entry' => 'BUY signal',
            'exit' => 'close after 20 trading days',
            'selection_filters' => $filters,
            'selection_metrics' => [
                'drawdown' => 'maximum per instrument over source period',
                'profit_factor' => 'aggregate per instrument over source period',
            ],
            'capital' => [
                'initial' => $initialCapital,
                'position' => $initialCapital / $maxPositions,
                'currency' => 'EUR',
                'max_parallel_positions' => $maxPositions,
                'trade_cost_eur' => $tradeCost,
            ],
        ];
        $runId = DB::table('backtest_runs')->insertGetId([
            'public_id' => $publicId,
            'status' => 'queued',
            'strategy' => 'filtered_horizon_20d',
            'timeframe' => '1d',
            'horizon_days' => 20,
            'started_at' => now(),
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RunFilteredBacktest::dispatch($runId, (int) $sourceRun->id, $filters);
        $this->startBacktestWorker();

        return redirect()->route('setup.filter', array_merge($filters, ['backtest_run' => $publicId]))
            ->with('status', __('Der Drei-Jahres-Backtest wurde gestartet.'));
    }

    public function filteredBacktestResult(Request $request, string $publicId): JsonResponse
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->first();
        abort_if($run === null, 404);

        $period = DB::table('backtest_trades')->where('backtest_run_id', $run->id)
            ->selectRaw('MIN(entry_date) AS starts_at, MAX(exit_date) AS ends_at')
            ->first();
        if ($period?->starts_at === null || $period?->ends_at === null) {
            return response()->json(['strategy' => [], 'benchmark' => [], 'strategy_performance' => 0]);
        }

        $runSettings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
        $initialCapital = max(1000.0, (float) data_get($runSettings, 'capital.initial', 10000));
        $maxPositions = max(1, (int) data_get($runSettings, 'capital.max_parallel_positions', 10));
        $positionCapital = $initialCapital / $maxPositions;
        $tradeCost = max(0.0, (float) data_get($runSettings, 'capital.trade_cost_eur', 10));
        // backtest_trades is the complete, persisted 20-day baseline. The
        // strategy table contains supplemental exits and may be incomplete
        // when an instrument has gaps in its local OHLC history.
        $candidateQuery = DB::table('backtest_trades')
            ->where('backtest_run_id', $run->id)
            ->orderBy('entry_date')
            ->orderByDesc('ki_score')
            ->orderByDesc('confidence');
        $candidates = $candidateQuery->orderBy('id')->get([
            'entry_date', 'exit_date', 'gross_return', 'max_drawdown',
        ]);
        $cash = $initialCapital;
        $openPositions = [];
        $executed = collect();
        foreach ($candidates as $trade) {
            foreach ($openPositions as $key => $position) {
                if ($position['exit_date'] > (string) $trade->entry_date) continue;
                $cash += $position['capital'] * (1 + $position['return']);
                unset($openPositions[$key]);
            }
            if (count($openPositions) >= $maxPositions || $cash <= $tradeCost) continue;
            $allocatedCapital = min($positionCapital, $cash);
            $trade->allocated_capital = $allocatedCapital;
            $trade->net_return_after_cost = (float) $trade->gross_return - ($tradeCost / $allocatedCapital);
            $cash -= $allocatedCapital;
            $openPositions[] = [
                'exit_date' => (string) $trade->exit_date,
                'capital' => $allocatedCapital,
                'return' => $trade->net_return_after_cost,
            ];
            $executed->push($trade);
        }

        $events = [];
        foreach ($executed as $trade) {
            $events[(string) $trade->entry_date]['entries'][] = (float) $trade->allocated_capital;
            $events[(string) $trade->exit_date]['exits'][] = [
                'capital' => (float) $trade->allocated_capital,
                'return' => (float) $trade->net_return_after_cost,
            ];
        }
        $capitalBinding = $this->capitalBinding(
            $events,
            (string) $period->starts_at,
            (string) $period->ends_at,
            $initialCapital,
        );
        ksort($events);
        $cash = $initialCapital;
        $invested = 0.0;
        $strategy = [['x' => strtotime((string) $period->starts_at) * 1000, 'y' => round($initialCapital, 2)]];
        foreach ($events as $date => $event) {
            foreach ($event['exits'] ?? [] as $exit) {
                $invested -= $exit['capital'];
                $cash += $exit['capital'] * (1 + $exit['return']);
            }
            foreach ($event['entries'] ?? [] as $capital) {
                $cash -= $capital;
                $invested += $capital;
            }
            $strategy[] = [
                'x' => strtotime($date) * 1000,
                'y' => round($cash + $invested, 2),
            ];
        }
        $periodEnd = strtotime((string) $period->ends_at) * 1000;
        if ($strategy[array_key_last($strategy)]['x'] < $periodEnd) {
            $strategy[] = ['x' => $periodEnd, 'y' => (float) $strategy[array_key_last($strategy)]['y']];
        }
        $strategyValue = (float) ($strategy[array_key_last($strategy)]['y'] ?? $initialCapital);
        $netReturn = fn (object $trade): float => (float) $trade->net_return_after_cost;
        $winningReturn = $executed->sum(fn (object $trade): float => max(0, $netReturn($trade)));
        $losingReturn = abs($executed->sum(fn (object $trade): float => min(0, $netReturn($trade))));

        $benchmarkBars = DB::table('price_bars as bar')
            ->join('instruments as instrument', 'instrument.id', '=', 'bar.instrument_id')
            ->where('instrument.symbol', '^GSPC')
            ->where('bar.interval', '1d')
            ->whereDate('bar.bar_time', '>=', $period->starts_at)
            ->whereDate('bar.bar_time', '<=', $period->ends_at)
            ->selectRaw('DISTINCT ON (DATE(bar.bar_time)) bar.bar_time, COALESCE(bar.adjusted_close, bar.close) AS close')
            ->orderByRaw('DATE(bar.bar_time), bar.bar_time DESC')
            ->get();
        $benchmarkCoversStart = $benchmarkBars->isNotEmpty()
            && strtotime((string) $benchmarkBars->first()->bar_time) <= strtotime((string) $period->starts_at.' +10 days');
        $benchmarkStart = $benchmarkCoversStart ? (float) $benchmarkBars->first()->close : 0.0;
        $benchmark = $benchmarkStart > 0
            ? $benchmarkBars->map(fn (object $bar): array => [
                'x' => strtotime((string) $bar->bar_time) * 1000,
                'y' => round(((float) $bar->close / $benchmarkStart) * $initialCapital, 2),
            ])->values()->all()
            : [];
        $benchmarkDrawdown = $this->maximumSeriesDrawdown($benchmark);

        $winnerCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', 'winner_runner')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['entry_date', 'exit_date', 'gross_return', 'max_drawdown']);
        $winner = $this->simulatePortfolio($winnerCandidates, $initialCapital, $maxPositions, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $targetCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', 'prediction_target')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['entry_date', 'exit_date', 'gross_return', 'max_drawdown']);
        $target = $this->simulatePortfolio($targetCandidates, $initialCapital, $maxPositions, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $backtestMonths = max(
            1.0,
            (strtotime((string) $period->ends_at) - strtotime((string) $period->starts_at)) / (86400 * 30.4375),
        );

        return response()->json([
            'strategy' => $strategy,
            'winner_runner' => $winner['series'],
            'prediction_target' => $target['series'],
            'benchmark' => $benchmark,
            'strategy_performance' => round((($strategyValue / $initialCapital) - 1) * 100, 2),
            'benchmark_performance' => $benchmark !== [] ? round((((float) end($benchmark)['y'] / $initialCapital) - 1) * 100, 2) : null,
            'winner_runner_performance' => $winner['performance'],
            'winner_runner_final_capital' => $winner['final'],
            'winner_runner_executed_trades' => $winner['executed'],
            'winner_runner_skipped_trades' => $winner['skipped'],
            'prediction_target_performance' => $target['performance'],
            'prediction_target_final_capital' => $target['final'],
            'prediction_target_executed_trades' => $target['executed'],
            'prediction_target_skipped_trades' => $target['skipped'],
            'backtest_months' => round($backtestMonths, 2),
            'trades_per_month' => round($executed->count() / $backtestMonths, 2),
            'winner_runner_trades_per_month' => round($winner['executed'] / $backtestMonths, 2),
            'prediction_target_trades_per_month' => round($target['executed'] / $backtestMonths, 2),
            'initial_capital' => $initialCapital,
            'final_capital' => $strategyValue,
            'executed_trades' => $executed->count(),
            'skipped_trades' => $candidates->count() - $executed->count(),
            'trade_cost' => $tradeCost,
            'total_costs' => round($executed->count() * $tradeCost, 2),
            'hit_rate' => $executed->isNotEmpty()
                ? round(($executed->filter(fn (object $trade): bool => $netReturn($trade) > 0)->count() / $executed->count()) * 100, 2)
                : 0,
            'profit_factor' => $losingReturn > 0 ? round($winningReturn / $losingReturn, 3) : null,
            'max_drawdown' => round($executed->max(fn (object $trade): float => abs((float) $trade->max_drawdown)) * 100, 2),
            'portfolio_max_drawdown' => $this->maximumSeriesDrawdown($strategy),
            'benchmark_max_drawdown' => $benchmarkDrawdown,
            'winner_runner_max_drawdown' => $winner['max_drawdown'],
            'prediction_target_max_drawdown' => $target['max_drawdown'],
            'average_capital_binding' => $capitalBinding['average'],
            'maximum_capital_binding' => $capitalBinding['maximum'],
            'winner_runner_average_capital_binding' => $winner['average_capital_binding'],
            'winner_runner_maximum_capital_binding' => $winner['maximum_capital_binding'],
            'prediction_target_average_capital_binding' => $target['average_capital_binding'],
            'prediction_target_maximum_capital_binding' => $target['maximum_capital_binding'],
        ]);
    }

    public function filteredBacktestStatus(Request $request, string $publicId): JsonResponse
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->first(['status', 'instruments_total', 'instruments_completed', 'trades_count', 'error_message']);
        abort_if($run === null, 404);

        return response()->json([
            'status' => $run->status,
            'finished' => in_array($run->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true),
            'instruments_total' => (int) $run->instruments_total,
            'instruments_completed' => (int) $run->instruments_completed,
            'trades' => (int) $run->trades_count,
            'error' => $run->status === 'failed' ? $run->error_message : null,
        ]);
    }

    public function cancelFilteredBacktest(Request $request, string $publicId): RedirectResponse
    {
        DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'cancelled',
                'finished_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);

        $query = $request->query();
        unset($query['backtest_run']);

        return redirect()->route('setup.filter', $query)
            ->with('status', __('Der Backtest wurde abgebrochen.'));
    }

    public function downloadFilteredBacktestReport(Request $request, string $publicId): Response
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->first();
        abort_if($run === null, 404);
        $result = $this->filteredBacktestResult($request, $publicId)->getData(true);
        $settings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
        $modelStatistics = $this->backtestModelStatistics((int) $run->id);
        $modelExitMatrix = $this->backtestModelExitMatrix((int) $run->id);
        $chart = $this->reportChart([
            '20 Tage' => ['color' => '#14b8a6', 'points' => $result['strategy']],
            'Winner Runner' => ['color' => '#6366f1', 'points' => $result['winner_runner']],
            'Prognoseziel' => ['color' => '#e11d48', 'points' => $result['prediction_target']],
            'S&P 500' => ['color' => '#d97706', 'points' => $result['benchmark']],
        ]);
        $html = view('predictions.backtest-report', compact('run', 'result', 'settings', 'chart', 'modelStatistics', 'modelExitMatrix'))->render();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="aKI-Backtest-'.$publicId.'.pdf"',
        ]);
    }

    private function backtestModelStatistics(int $runId)
    {
        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');

        return DB::table('backtest_trades as trade')
            ->leftJoin('model_definitions as model', 'model.id', '=', 'trade.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trade.trained_model_id'))
            ->leftJoin('model_quality_rankings as quality', 'quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as tier', 'tier.id', '=', 'quality.tier_id')
            ->where('trade.backtest_run_id', $runId)
            ->groupBy('trade.model_definition_id', 'model.public_alias', 'tier.code', 'tier.name')
            ->selectRaw("COALESCE(NULLIF(model.public_alias, ''), 'Unbekannt') AS model_name")
            ->selectRaw("COALESCE(tier.name, 'Nicht qualifiziert') AS quality_tier")
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('AVG(CASE WHEN trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(trade.net_return) * 100 AS average_return')
            ->selectRaw('SUM(CASE WHEN trade.net_return > 0 THEN trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN trade.net_return < 0 THEN trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(trade.max_drawdown)) * 100 AS max_drawdown')
            ->selectRaw('MIN(trade.entry_date) AS first_trade')
            ->selectRaw('MAX(trade.exit_date) AS last_trade')
            ->orderByDesc('trades')
            ->get();
    }

    private function backtestModelExitMatrix(int $runId)
    {
        return DB::table('backtest_strategy_trades as strategy_trade')
            ->join('backtest_trades as trade', 'trade.id', '=', 'strategy_trade.backtest_trade_id')
            ->leftJoin('model_definitions as model', 'model.id', '=', 'trade.model_definition_id')
            ->where('strategy_trade.backtest_run_id', $runId)
            ->whereIn('strategy_trade.strategy', ['fixed_20d', 'winner_runner', 'prediction_target'])
            ->groupBy('trade.model_definition_id', 'model.public_alias', 'strategy_trade.strategy')
            ->selectRaw("COALESCE(NULLIF(model.public_alias, ''), 'Unbekannt') AS model_name")
            ->addSelect('strategy_trade.strategy')
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('AVG(CASE WHEN strategy_trade.gross_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(strategy_trade.gross_return) * 100 AS average_return')
            ->orderBy('model_name')
            ->orderBy('strategy_trade.strategy')
            ->get()
            ->groupBy('model_name');
    }

    public function index(Request $request): View
    {
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
        $sortColumns = [
            'time' => 'prediction.prediction_time',
            'stock' => 'instrument.symbol',
            'model' => 'model_definition.public_alias',
            'signal' => 'personalized_signal',
            'price' => 'prediction.current_price',
            'return_5d' => 'expected_return_5d',
            'return_20d' => 'expected_return_20d',
            'score' => 'score_10',
            'confidence' => 'confidence_percent',
            'risk' => 'risk_percent',
            'quality' => 'prediction.quality_band',
            'validation' => 'prediction.validated_at',
        ];
        $sort = array_key_exists((string) $request->query('sort'), $sortColumns)
            ? (string) $request->query('sort')
            : 'time';
        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';

        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');

        $historicalBaseQuery = fn (): Builder => DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at');

        $baseQuery = fn (): Builder => $historicalBaseQuery()
            ->whereRaw('prediction.id = (
                SELECT latest_prediction.id
                FROM predictions AS latest_prediction
                WHERE latest_prediction.instrument_id = prediction.instrument_id
                ORDER BY latest_prediction.prediction_time DESC NULLS LAST, latest_prediction.id DESC
                LIMIT 1
            )');

        $scoreSql = '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)';
        $confidenceSql = '(CASE WHEN prediction.confidence <= 1 THEN prediction.confidence * 100 ELSE prediction.confidence END)';
        $minimumQualityTiers = [
            'top' => ['top'],
            'strong' => ['top', 'strong'],
            'solid' => ['top', 'strong', 'solid'],
            'test' => ['top', 'strong', 'solid', 'test'],
        ];

        $applyFilters = function (Builder $query, ?string $excluded = null) use ($request, $signalSql, $scoreSql, $confidenceSql, $minimumQualityTiers): Builder {
            $qualityTier = (string) $request->query('quality_tier');

            return $query
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when($excluded !== 'country' && $request->filled('country'), fn (Builder $query) =>
                $query->where('instrument.country', strtoupper(trim((string) $request->query('country')))))
            ->when($excluded !== 'sector' && $request->filled('sector'), fn (Builder $query) =>
                $query->where('instrument.sector', trim((string) $request->query('sector'))))
            ->when($excluded !== 'exchange' && $request->filled('exchange'), fn (Builder $query) =>
                $query->where('exchange.code', strtoupper(trim((string) $request->query('exchange')))))
            ->when($excluded !== 'ai_type' && in_array($request->query('ai_type'), ['horizon', 'pulse'], true), fn (Builder $query) =>
                $query->where('prediction.ai_type', $request->query('ai_type')))
            ->when($excluded !== 'model' && $request->integer('model') > 0, fn (Builder $query) =>
                $query->where('trained_model.model_definition_id', $request->integer('model')))
            ->when($excluded !== 'quality_tier' && in_array($qualityTier, ['top', 'strong', 'solid', 'test'], true), fn (Builder $query) =>
                $query->whereIn('quality_tier.code', $minimumQualityTiers[$qualityTier]))
            ->when($excluded !== 'quality_tier' && $qualityTier === 'unqualified', fn (Builder $query) =>
                $query->whereNull('quality_tier.code'))
            ->when($excluded !== 'signal' && in_array(strtoupper((string) $request->query('signal')), ['SELL', 'HOLD', 'WATCH', 'BUY'], true), fn (Builder $query) =>
                $query->whereRaw("({$signalSql}) = ?", [strtoupper((string) $request->query('signal'))]))
            ->when($excluded !== 'validation' && $request->query('validation') === 'validated', fn (Builder $query) =>
                $query->whereNotNull('prediction.validated_at'))
            ->when($excluded !== 'validation' && $request->query('validation') === 'pending', fn (Builder $query) =>
                $query->whereNull('prediction.validated_at'))
            ->when($excluded !== 'score' && $request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->whereRaw("{$scoreSql} >= ?", [max(0, min(10, (float) $request->query('score_min')))]))
            ->when($excluded !== 'confidence' && $request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->whereRaw("{$confidenceSql} >= ?", [max(0, min(100, (float) $request->query('confidence_min')))]));
        };

        $query = $applyFilters($baseQuery())
            ->select([
                'prediction.id',
                'prediction.instrument_id',
                'prediction.prediction_time',
                'prediction.interval',
                'prediction.ai_type',
                'prediction.current_price',
                'prediction.predicted_price_5d',
                'prediction.predicted_price_20d',
                'prediction.confidence',
                'prediction.risk_score',
                'prediction.drawdown_risk_factor',
                'prediction.prediction_score',
                'prediction.quality_band',
                'prediction.validated_at',
                'prediction.direction_correct',
                'prediction.actual_return',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.sector',
                'instrument.currency',
                'exchange.code as exchange_code',
                'model_definition.public_alias as model_alias',
                'model_quality.quality_score as model_quality_score',
                'model_quality.eligible as model_quality_eligible',
                'quality_tier.code as model_quality_tier_code',
                'quality_tier.name as model_quality_tier_name',
            ])
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->selectRaw('((prediction.predicted_price_5d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_5d')
            ->selectRaw('((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_20d')
            ->selectRaw("{$scoreSql} AS score_10")
            ->selectRaw("{$confidenceSql} AS confidence_percent")
            ->selectRaw('(CASE WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1 THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100 ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) END) AS risk_percent');

        $query
            ->orderBy($sortColumns[$sort], $direction)
            ->orderByDesc('prediction.id');

        $predictions = $query->get();

        // Keep the summary cards in sync with exactly the same filters as the table.
        $summary = $applyFilters($baseQuery())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT prediction.instrument_id) AS instruments')
            ->selectRaw('COUNT(prediction.validated_at) AS validated')
            ->selectRaw('MIN(trained_model.trained_at) AS oldest_training')
            ->first();

        $aiTypes = $applyFilters($baseQuery(), 'ai_type')
            ->whereNotNull('prediction.ai_type')
            ->distinct()
            ->orderBy('prediction.ai_type')
            ->pluck('prediction.ai_type');

        $models = $applyFilters($baseQuery(), 'model')
            ->whereNotNull('model_definition.public_alias')
            ->where('model_definition.public_alias', '<>', '')
            ->select('model_definition.id', 'model_definition.public_alias')
            ->distinct()
            ->orderBy('model_definition.public_alias')
            ->get();

        $qualityTiers = $applyFilters($baseQuery(), 'quality_tier')
            ->selectRaw("COALESCE(quality_tier.code, 'unqualified') AS code")
            ->selectRaw("COALESCE(quality_tier.name, 'Nicht qualifiziert') AS name")
            ->distinct()
            ->get()
            ->sortBy(fn (object $tier): int => array_search($tier->code, ['top', 'strong', 'solid', 'test', 'unqualified'], true))
            ->values();

        $signals = $applyFilters($baseQuery(), 'signal')
            ->selectRaw("({$signalSql}) AS available_signal")
            ->distinct()
            ->orderBy('available_signal')
            ->pluck('available_signal')
            ->map(fn ($signal) => strtoupper((string) $signal))
            ->filter(fn (string $signal) => in_array($signal, ['SELL', 'HOLD', 'WATCH', 'BUY'], true));

        $validationStates = $applyFilters($baseQuery(), 'validation')
            ->selectRaw("CASE WHEN prediction.validated_at IS NULL THEN 'pending' ELSE 'validated' END AS validation_state")
            ->distinct()
            ->orderBy('validation_state')
            ->pluck('validation_state');

        $countries = $applyFilters($baseQuery(), 'country')
            ->whereNotNull('instrument.country')
            ->where('instrument.country', '<>', '')
            ->distinct()
            ->orderBy('instrument.country')
            ->pluck('instrument.country');
        $sectors = $applyFilters($baseQuery(), 'sector')
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->distinct()
            ->orderBy('instrument.sector')
            ->pluck('instrument.sector');
        $exchanges = $applyFilters($baseQuery(), 'exchange')
            ->whereNotNull('exchange.code')
            ->where('exchange.code', '<>', '')
            ->select('exchange.code', 'exchange.name')
            ->distinct()
            ->orderBy('exchange.code')
            ->get();

        $tradeScoreBucketSql = 'LEAST(9, GREATEST(0, FLOOR(backtest_trade.ki_score)))::integer';
        $tradeConfidenceBucketSql = 'LEAST(9, GREATEST(0, FLOOR(backtest_trade.confidence / 10)))::integer';
        $latestFundamentalIds = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');
        $latestTechnicalIds = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, MAX(id) AS technical_id')
            ->groupBy('instrument_id');
        $fundamentalNumber = static fn (string $key): string =>
            "(CASE WHEN NULLIF(fundamental.data::jsonb->>'{$key}', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'{$key}')::numeric END)";
        $selectedUserBacktest = $this->requestedUserBacktestRun($request);
        $isUserBacktestResult = $selectedUserBacktest !== null
            && in_array($selectedUserBacktest->status, ['completed', 'completed_with_errors'], true);
        $backtestRunId = $this->selectedBacktestRunId($request);
        $eligibleInstruments = $isUserBacktestResult
            ? null
            : $this->eligibleBacktestInstruments($backtestRunId, $request);
        $heatmapQuery = DB::table('backtest_trades as backtest_trade')
            ->join('backtest_runs as backtest_run', 'backtest_run.id', '=', 'backtest_trade.backtest_run_id')
            ->join('instruments as instrument', 'instrument.id', '=', 'backtest_trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'backtest_trade.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'backtest_trade.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->leftJoinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
            ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->leftJoinSub($latestTechnicalIds, 'latest_technical', fn ($join) =>
                $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
            ->where('backtest_trade.backtest_run_id', $backtestRunId)
            ->when($eligibleInstruments !== null, fn (Builder $query) =>
                $query->whereIn('backtest_trade.instrument_id', $eligibleInstruments))
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            })
            ->when($request->filled('country'), fn (Builder $query) =>
                $query->where('instrument.country', strtoupper(trim((string) $request->query('country')))))
            ->when($request->filled('sector'), fn (Builder $query) =>
                $query->where('instrument.sector', trim((string) $request->query('sector'))))
            ->when($request->filled('exchange'), fn (Builder $query) =>
                $query->where('exchange.code', strtoupper(trim((string) $request->query('exchange')))))
            ->when(in_array($request->query('ai_type'), ['horizon', 'pulse'], true), fn (Builder $query) =>
                $query->where('backtest_trade.ai_type', $request->query('ai_type')))
            ->when($request->integer('model') > 0, fn (Builder $query) =>
                $query->where('backtest_trade.model_definition_id', $request->integer('model')))
            ->when(in_array((string) $request->query('quality_tier'), ['top', 'strong', 'solid', 'test'], true), fn (Builder $query) =>
                $query->whereIn('quality_tier.code', $minimumQualityTiers[(string) $request->query('quality_tier')]))
            ->when($request->query('quality_tier') === 'unqualified', fn (Builder $query) =>
                $query->whereNull('quality_tier.code'))
            ->when(in_array(strtoupper((string) $request->query('signal')), ['SELL', 'HOLD', 'WATCH', 'BUY'], true), fn (Builder $query) =>
                $query->where('backtest_trade.signal', strtoupper((string) $request->query('signal'))))
            ->when($request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->where('backtest_trade.ki_score', '>=', max(0, min(10, (float) $request->query('score_min')))))
            ->when($request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->where('backtest_trade.confidence', '>=', max(0, min(100, (float) $request->query('confidence_min')))))
            ->when($request->filled('volatility_max') && is_numeric($request->query('volatility_max')) && (float) $request->query('volatility_max') < 100, fn (Builder $query) =>
                $query->where('technical.volatility_20', '<=', max(0, (float) $request->query('volatility_max')) / 100))
            ->when($request->filled('pe_max') && is_numeric($request->query('pe_max')) && (float) $request->query('pe_max') < 100, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $request->query('pe_max')]))
            ->when($request->filled('dividend_yield_min') && is_numeric($request->query('dividend_yield_min')) && (float) $request->query('dividend_yield_min') > 0, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('dividendYield').' >= ?', [(float) $request->query('dividend_yield_min') / 100]))
            ->when($request->filled('market_cap_min') && is_numeric($request->query('market_cap_min')) && (float) $request->query('market_cap_min') > 0, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $request->query('market_cap_min') * 1_000_000_000]))
            ->when($request->filled('revenue_growth_min') && is_numeric($request->query('revenue_growth_min')) && (float) $request->query('revenue_growth_min') > -50, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $request->query('revenue_growth_min') / 100]));

        $heatmapSummary = (clone $heatmapQuery)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw("MAX(COALESCE(NULLIF(backtest_run.settings->>'lookback_years', '')::numeric * 12, 36)) AS backtest_months")
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(backtest_trade.max_drawdown)) * 100 AS drawdown')
            ->first();

        $heatmap = $heatmapQuery
            ->selectRaw("{$tradeScoreBucketSql} AS score_bucket")
            ->selectRaw("{$tradeConfidenceBucketSql} AS confidence_bucket")
            ->selectRaw('COUNT(*) AS samples')
            ->selectRaw("COUNT(*)::numeric / MAX(COALESCE(NULLIF(backtest_run.settings->>'lookback_years', '')::numeric * 12, 36)) AS trades_per_month")
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(backtest_trade.net_return) * 100 AS average_return')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(backtest_trade.max_drawdown)) * 100 AS drawdown')
            ->groupByRaw("{$tradeScoreBucketSql}, {$tradeConfidenceBucketSql}")
            ->get()
            ->keyBy(fn ($row) => $row->score_bucket.'-'.$row->confidence_bucket);

        $heatmapSummary->trades_per_month = (float) ($heatmapSummary->trades ?? 0)
            / max(1.0, (float) ($heatmapSummary->backtest_months ?? 36));

        $userWatchlists = DB::table('watchlists')
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $watchlistMemberships = $userWatchlists->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->get(['instrument_id', 'watchlist_id'])
                ->groupBy('instrument_id')
                ->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id));

        return view('predictions.index', compact(
            'predictions',
            'summary',
            'aiTypes',
            'models',
            'qualityTiers',
            'signals',
            'validationStates',
            'countries',
            'sectors',
            'exchanges',
            'heatmap',
            'heatmapSummary',
            'userWatchlists',
            'watchlistMemberships',
            'sort',
            'direction',
        ));
    }

    private function selectedBacktestRunId(Request $request): int
    {
        $requested = $this->requestedUserBacktestRun($request);
        if ($requested !== null && in_array($requested->status, ['completed', 'completed_with_errors'], true)) {
            return (int) $requested->id;
        }

        return (int) DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            ->orderByDesc('id')
            ->value('id');
    }

    private function startBacktestWorker(): void
    {
        $worker = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'queue:work',
            '--once',
            '--queue=default',
            '--timeout=1200',
            '--tries=1',
        ], base_path());
        $worker->setTimeout(null);
        $worker->disableOutput();
        $worker->start(null, ['create_new_console' => true]);
    }

    private function requestedUserBacktestRun(Request $request): ?object
    {
        if (! $request->filled('backtest_run') || $request->user() === null) {
            return null;
        }

        return DB::table('backtest_runs')
            ->where('public_id', (string) $request->query('backtest_run'))
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->first();
    }

    private function eligibleBacktestInstruments(int $runId, Request $request): ?Builder
    {
        $drawdownMaximum = $request->filled('drawdown_max') && is_numeric($request->query('drawdown_max'))
            ? (float) $request->query('drawdown_max')
            : 50.0;
        $profitFactorMinimum = $request->filled('profit_factor_min') && is_numeric($request->query('profit_factor_min'))
            ? (float) $request->query('profit_factor_min')
            : 0.0;
        $hitRateMinimum = $request->filled('hit_rate_min') && is_numeric($request->query('hit_rate_min'))
            ? (float) $request->query('hit_rate_min')
            : 0.0;
        if ($drawdownMaximum >= 50 && $profitFactorMinimum <= 0 && $hitRateMinimum <= 0) {
            return null;
        }

        return DB::table('backtest_trades as eligibility_trade')
            ->where('eligibility_trade.backtest_run_id', $runId)
            ->where('eligibility_trade.entry_date', '>=', now()->subYears(3)->toDateString())
            ->groupBy('eligibility_trade.instrument_id')
            ->select('eligibility_trade.instrument_id')
            ->when($drawdownMaximum < 50, fn (Builder $query) =>
                $query->havingRaw('MAX(ABS(eligibility_trade.max_drawdown)) <= ?', [max(0, $drawdownMaximum) / 100]))
            ->when($profitFactorMinimum > 0, fn (Builder $query) =>
                $query->havingRaw(
                    'COALESCE(SUM(CASE WHEN eligibility_trade.net_return > 0 THEN eligibility_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN eligibility_trade.net_return < 0 THEN eligibility_trade.net_return ELSE 0 END)), 0), 999999) >= ?',
                    [min(3, $profitFactorMinimum)],
                ))
            ->when($hitRateMinimum > 0, fn (Builder $query) =>
                $query->havingRaw(
                    'AVG(CASE WHEN eligibility_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 >= ?',
                    [min(100, $hitRateMinimum)],
                ));
    }

    private function simulatePortfolio(
        $candidates,
        float $initialCapital,
        int $maxPositions,
        float $tradeCost,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): array
    {
        $positionCapital = $initialCapital / max(1, $maxPositions);
        $cash = $initialCapital;
        $open = [];
        $executed = collect();
        foreach ($candidates as $trade) {
            foreach ($open as $key => $position) {
                if ($position['exit_date'] > (string) $trade->entry_date) continue;
                $cash += $position['capital'] * (1 + $position['return']);
                unset($open[$key]);
            }
            if (count($open) >= $maxPositions || $cash <= $tradeCost) continue;
            $allocatedCapital = min($positionCapital, $cash);
            $return = (float) $trade->gross_return - ($tradeCost / $allocatedCapital);
            $trade->allocated_capital = $allocatedCapital;
            $trade->net_return_after_cost = $return;
            $cash -= $allocatedCapital;
            $open[] = ['exit_date' => (string) $trade->exit_date, 'capital' => $allocatedCapital, 'return' => $return];
            $executed->push($trade);
        }
        $events = [];
        foreach ($executed as $trade) {
            $events[(string) $trade->entry_date]['entries'][] = (float) $trade->allocated_capital;
            $events[(string) $trade->exit_date]['exits'][] = [
                'capital' => (float) $trade->allocated_capital,
                'return' => (float) $trade->net_return_after_cost,
            ];
        }
        $capitalBinding = $periodStart !== null && $periodEnd !== null
            ? $this->capitalBinding($events, $periodStart, $periodEnd, $initialCapital)
            : ['average' => 0.0, 'maximum' => 0.0];
        ksort($events);
        $cash = $initialCapital;
        $invested = 0.0;
        $series = $periodStart !== null
            ? [['x' => strtotime($periodStart) * 1000, 'y' => round($initialCapital, 2)]]
            : [];
        foreach ($events as $date => $event) {
            foreach ($event['exits'] ?? [] as $exit) {
                $invested -= $exit['capital'];
                $cash += $exit['capital'] * (1 + $exit['return']);
            }
            foreach ($event['entries'] ?? [] as $capital) {
                $cash -= $capital;
                $invested += $capital;
            }
            $series[] = ['x' => strtotime($date) * 1000, 'y' => round($cash + $invested, 2)];
        }
        if ($periodEnd !== null && $series !== []) {
            $periodEndTimestamp = strtotime($periodEnd) * 1000;
            if ($series[array_key_last($series)]['x'] < $periodEndTimestamp) {
                $series[] = ['x' => $periodEndTimestamp, 'y' => (float) $series[array_key_last($series)]['y']];
            }
        }
        $final = (float) ($series[array_key_last($series)]['y'] ?? $initialCapital);

        return [
            'series' => $series,
            'performance' => round((($final / $initialCapital) - 1) * 100, 2),
            'final' => round($final, 2),
            'executed' => $executed->count(),
            'skipped' => $candidates->count() - $executed->count(),
            'average_capital_binding' => $capitalBinding['average'],
            'maximum_capital_binding' => $capitalBinding['maximum'],
            'max_drawdown' => $this->maximumSeriesDrawdown($series),
        ];
    }

    private function maximumSeriesDrawdown(array $series): float
    {
        $peak = 0.0;
        $maximumDrawdown = 0.0;
        foreach ($series as $point) {
            $value = (float) ($point['y'] ?? 0);
            if ($value <= 0) continue;
            $peak = max($peak, $value);
            if ($peak > 0) {
                $maximumDrawdown = max($maximumDrawdown, (($peak - $value) / $peak) * 100);
            }
        }

        return round($maximumDrawdown, 2);
    }

    private function capitalBinding(array $events, string $periodStart, string $periodEnd, float $initialCapital): array
    {
        $start = strtotime($periodStart);
        $end = strtotime($periodEnd);
        if ($start === false || $end === false || $end <= $start || $initialCapital <= 0) {
            return ['average' => 0.0, 'maximum' => 0.0];
        }

        ksort($events);
        $cursor = $start;
        $invested = 0.0;
        $maximumInvested = 0.0;
        $capitalSeconds = 0.0;
        foreach ($events as $date => $event) {
            $timestamp = max($start, min($end, (int) strtotime((string) $date)));
            $capitalSeconds += $invested * max(0, $timestamp - $cursor);
            foreach ($event['exits'] ?? [] as $exit) {
                $invested = max(0.0, $invested - (float) $exit['capital']);
            }
            foreach ($event['entries'] ?? [] as $capital) {
                $invested += (float) $capital;
            }
            $maximumInvested = max($maximumInvested, $invested);
            $cursor = $timestamp;
        }
        $capitalSeconds += $invested * max(0, $end - $cursor);

        return [
            'average' => round(($capitalSeconds / (($end - $start) * $initialCapital)) * 100, 2),
            'maximum' => round(($maximumInvested / $initialCapital) * 100, 2),
        ];
    }

    private function reportChart(array $series): array
    {
        $all = collect($series)->flatMap(fn (array $item) => $item['points']);
        if ($all->isEmpty()) return ['series' => [], 'min' => 0, 'max' => 0, 'from' => null, 'to' => null];
        $minX = (float) $all->min('x');
        $maxX = (float) $all->max('x');
        $minY = (float) $all->min('y');
        $maxY = (float) $all->max('y');
        $padding = max(100.0, ($maxY - $minY) * 0.08);
        $minY -= $padding;
        $maxY += $padding;
        $width = 700.0;
        $height = 235.0;
        $paths = [];
        foreach ($series as $name => $item) {
            $path = collect($item['points'])->map(function (array $point, int $index) use ($minX, $maxX, $minY, $maxY, $width, $height): string {
                $x = 45 + (($point['x'] - $minX) / max(1, $maxX - $minX)) * $width;
                $y = 15 + $height - (($point['y'] - $minY) / max(1, $maxY - $minY)) * $height;
                return ($index === 0 ? 'M' : 'L').number_format($x, 2, '.', '').' '.number_format($y, 2, '.', '');
            })->implode(' ');
            $paths[] = ['name' => $name, 'color' => $item['color'], 'path' => $path];
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="790" height="270" viewBox="0 0 790 270">';
        $svg .= '<rect width="790" height="270" fill="#f8fafc"/>';
        for ($line = 0; $line <= 4; $line++) {
            $y = 15 + ($line * 58.75);
            $label = number_format($maxY - (($maxY - $minY) * $line / 4), 0, ',', '.');
            $svg .= '<line x1="45" y1="'.$y.'" x2="745" y2="'.$y.'" stroke="#d8e1ea" stroke-width="1"/>';
            $svg .= '<text x="40" y="'.($y + 3).'" text-anchor="end" font-family="DejaVu Sans" font-size="8" fill="#718096">'.$label.'</text>';
        }
        foreach ($paths as $path) {
            $svg .= '<path d="'.$path['path'].'" fill="none" stroke="'.$path['color'].'" stroke-width="2.2"/>';
        }
        $svg .= '<text x="45" y="264" font-family="DejaVu Sans" font-size="8" fill="#718096">'.date('d.m.Y', (int) ($minX / 1000)).'</text>';
        $svg .= '<text x="745" y="264" text-anchor="end" font-family="DejaVu Sans" font-size="8" fill="#718096">'.date('d.m.Y', (int) ($maxX / 1000)).'</text></svg>';

        return [
            'series' => $paths,
            'min' => $minY,
            'max' => $maxY,
            'from' => date('d.m.Y', (int) ($minX / 1000)),
            'to' => date('d.m.Y', (int) ($maxX / 1000)),
            'image' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ];
    }
}

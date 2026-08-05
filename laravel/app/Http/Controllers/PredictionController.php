<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Jobs\RunFilteredBacktest;
use App\Services\PythonEngineJobDispatcher;
use App\Services\PlanAccessService;
use App\Services\PersonalizedSignalService;
use App\Services\SavedFilterLimitService;
use App\Services\UserQualityGateService;
use App\Services\YahooIndexService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class PredictionController extends Controller
{
    public function backtestTrades(Request $request): View
    {
        $modelIds = $this->requestedModelIds($request);
        $selectedUserBacktest = $this->requestedUserBacktestRun($request);
        $isUserBacktestResult = $selectedUserBacktest !== null
            && in_array($selectedUserBacktest->status, ['completed', 'completed_with_errors'], true);
        $backtestRunId = $this->selectedBacktestRunId($request);
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
            'model' => 'model_quality.quality_score',
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
            ->when($modelIds !== [], fn (Builder $query) =>
                $query->whereIn('trade.model_definition_id', $modelIds))
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
            'summary',
            'countries',
            'exchanges',
            'sectors',
            'aiTypes',
            'models',
            'qualityTiers',
            'signals',
            'validationStates',
            'rangeMaxima',
        ])));
    }

    public function filterSetup(Request $request): View
    {
        $personalGate = app(UserQualityGateService::class)->rules($request->user());
        if ($request->query('gate_mode') === 'personal' && $personalGate !== null) {
            $this->applyPersonalQualityGate($request, $personalGate);
        }
        $filterState = collect(SavedPredictionFilterController::FILTER_DEFAULTS)
            ->mapWithKeys(fn ($default, string $key) => [$key => $request->query($key, $default)])
            ->all();
        $request->session()->put('setup_filter_state', $filterState);

        $request->attributes->set('heatmap_only', true);
        $predictionView = $this->index($request);
        $predictionData = $predictionView->getData();
        $data = array_intersect_key($predictionData, array_flip([
            'heatmap',
            'heatmapSummary',
            'summary',
            'countries',
            'exchanges',
            'sectors',
            'aiTypes',
            'models',
            'qualityTiers',
            'signals',
            'validationStates',
            'rangeMaxima',
        ]));
        $data['setupMode'] = true;
        $data['activeBacktestRun'] = $this->requestedUserBacktestRun($request);
        $data['savedFilters'] = $request->user()->savedPredictionFilters()->orderBy('name')->get();
        $data['savedFilterLimit'] = app(SavedFilterLimitService::class)->limitFor($request->user());
        $data['editingSavedFilter'] = $request->integer('saved_filter') > 0
            ? $request->user()->savedPredictionFilters()->whereKey($request->integer('saved_filter'))->first()
            : null;
        $data['hasPersonalQualityGate'] = $personalGate !== null;

        return view('predictions.heatmap', $data);
    }

    public function qualitySetup(Request $request): View
    {
        $view = $this->filterSetup($request);
        $data = $view->getData();
        $data['qualitySetupMode'] = true;

        return view('predictions.heatmap', $data);
    }

    public function shortStrategySetup(Request $request): View
    {
        $personalGate = app(UserQualityGateService::class)->rules($request->user());
        if ($request->query('gate_mode') === 'personal' && $personalGate !== null) {
            $this->applyPersonalQualityGate($request, $personalGate);
        }
        $request->merge(['signal' => 'SELL']);
        $request->attributes->set('heatmap_only', true);
        $predictionView = $this->index($request);
        $predictionData = $predictionView->getData();
        $data = array_intersect_key($predictionData, array_flip([
            'heatmap', 'heatmapSummary', 'summary', 'countries', 'exchanges', 'sectors',
            'aiTypes', 'models', 'qualityTiers', 'signals', 'validationStates',
            'rangeMaxima',
        ]));
        $data['setupMode'] = true;
        $data['shortMode'] = true;
        $data['activeBacktestRun'] = null;
        $data['savedFilters'] = collect();
        $data['savedFilterLimit'] = 0;
        $data['editingSavedFilter'] = null;
        $data['hasPersonalQualityGate'] = $personalGate !== null;

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
            'model' => ['nullable', 'array', 'max:20'],
            'model.*' => ['integer', 'min:1', 'distinct'],
            'quality_tier' => ['nullable', 'in:top,strong,solid,test,unqualified'],
            'signal' => ['nullable', 'in:BUY,WATCH,HOLD,SELL'],
            'score_min' => ['nullable', 'numeric', 'between:0,10'],
            'confidence_min' => ['nullable', 'numeric', 'between:0,100'],
            'drawdown_max' => ['nullable', 'numeric', 'between:0,100'],
            'profit_factor_min' => ['nullable', 'numeric', 'between:0,10'],
            'volatility_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'pe_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'dividend_yield_min' => ['nullable', 'numeric', 'between:0,1000000'],
            'market_cap_min' => ['nullable', 'numeric', 'between:0,1000000000'],
            'revenue_growth_min' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'hit_rate_min' => ['nullable', 'numeric', 'between:0,100'],
            'risk_max' => ['nullable', 'numeric', 'between:0,100'],
            'predicted_return_min' => ['nullable', 'numeric', 'between:-50,100'],
            'minimum_trades' => ['nullable', 'integer', 'between:1,10000'],
            'positive_prediction_required' => ['nullable', 'boolean'],
            'ensemble_veto_required' => ['nullable', 'boolean'],
            'gate_mode' => ['nullable', 'in:system,personal'],
            'quality_setup' => ['nullable', 'boolean'],
            'sector_score_rotation' => ['nullable', 'boolean'],
            'index_score_rotation' => ['nullable', 'boolean'],
            'exit_strategy' => ['nullable', 'in:fixed_20d,winner_runner,prediction_target,buy_and_hold'],
            'initial_capital' => ['required', 'numeric', 'between:1000,1000000'],
            'max_positions' => ['required', 'integer', 'between:1,50'],
            'position_factor' => ['required', 'integer', 'between:1,50', 'lte:max_positions'],
            'trade_cost' => ['required', 'numeric', 'between:0,1000'],
        ]);
        $returnRoute = ! empty($filters['quality_setup']) ? 'setup.quality' : 'setup.filter';
        if (($filters['gate_mode'] ?? 'system') === 'personal') {
            $personalGate = app(UserQualityGateService::class)->rules($request->user());
            abort_if($personalGate === null, 403, __('Dein persönliches Quality Gate ist nicht verfügbar.'));
            $filters = $this->mergePersonalQualityGate($filters, $personalGate);
        }
        $initialCapital = (float) $filters['initial_capital'];
        $maxPositions = (int) $filters['max_positions'];
        $positionFactor = ($filters['exit_strategy'] ?? null) === 'buy_and_hold' ? 1 : (int) $filters['position_factor'];
        $filters['position_factor'] = $positionFactor;
        $tradeCost = (float) $filters['trade_cost'];
        $activeRun = DB::table('backtest_runs')
            ->whereIn('status', ['queued', 'running'])
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->orderByDesc('id')
            ->first();
        if ($activeRun !== null) {
            return redirect()->route($returnRoute, array_merge($filters, ['backtest_run' => $activeRun->public_id]))
                ->with('status', __('Ein Backtest läuft bereits.'));
        }
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
            'entry' => 'BUY signal with positive predicted return',
            'exit' => 'close after 20 trading days',
            'selection_filters' => $filters,
            'selection_metrics' => [
                'drawdown' => 'maximum per instrument over source period',
                'profit_factor' => 'aggregate per instrument over source period',
            ],
            'capital' => [
                'initial' => $initialCapital,
                'position' => $initialCapital / $maxPositions,
                'position_factor' => $positionFactor,
                'maximum_position' => ($initialCapital / $maxPositions) * $positionFactor,
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

        if (config('aktienki.python_engine.backtests', false)) {
            app(PythonEngineJobDispatcher::class)->dispatchFilteredBacktest(
                (int) $request->user()->id,
                $runId,
                (int) $sourceRun->id,
                $filters,
                $settings,
            );
        } else {
            RunFilteredBacktest::dispatch($runId, (int) $sourceRun->id, $filters);
        }

        return redirect()->route($returnRoute, array_merge($filters, ['backtest_run' => $publicId]))
            ->with('status', __('Der Drei-Jahres-Backtest wurde gestartet.'));
    }

    private function applyPersonalQualityGate(Request $request, array $rules): void
    {
        // The setup table reads its state from query parameters. Resolve the
        // personal rules into that same bag so live refreshes retain them.
        $request->query->add($this->mergePersonalQualityGate($request->query(), $rules));
    }

    private function mergePersonalQualityGate(array $filters, array $rules): array
    {
        return array_merge($filters, [
            'gate_mode' => 'personal',
            'quality_tier' => $rules['minimum_tier'] ?? 'strong',
            'score_min' => $rules['score_min'] ?? 0,
            'confidence_min' => $rules['confidence_min'] ?? 0,
            'risk_max' => $rules['risk_max'] ?? 100,
            'predicted_return_min' => $rules['predicted_return_min'] ?? -50,
            'drawdown_max' => min(50, (float) ($rules['drawdown_max'] ?? 50)),
            'profit_factor_min' => min(10, (float) ($rules['profit_factor_min'] ?? 0)),
            'hit_rate_min' => $rules['hit_rate_min'] ?? 0,
            'minimum_trades' => $rules['minimum_trades'] ?? 1,
            'positive_prediction_required' => ($rules['positive_prediction_required'] ?? false) ? 1 : 0,
            'ensemble_veto_required' => ($rules['ensemble_veto_required'] ?? false) ? 1 : 0,
        ]);
    }

    public function filteredBacktestResult(Request $request, string $publicId, YahooIndexService $indices): JsonResponse
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->first();
        abort_if($run === null, 404);

        $tradePeriod = DB::table('backtest_trades')->where('backtest_run_id', $run->id)
            ->selectRaw('MIN(entry_date) AS starts_at, MAX(exit_date) AS ends_at')
            ->first();
        if ($tradePeriod?->starts_at === null || $tradePeriod?->ends_at === null) {
            return response()->json(['strategy' => [], 'benchmark' => [], 'strategy_performance' => 0]);
        }

        $runSettings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
        $lookbackYears = max(1, min(10, (int) data_get($runSettings, 'lookback_years', 3)));
        $runStartedAt = \Illuminate\Support\Carbon::parse($run->started_at)->utc();
        $period = (object) [
            'starts_at' => $runStartedAt->copy()->subYears($lookbackYears)->toDateString(),
            'ends_at' => $runStartedAt->toDateString(),
        ];
        $initialCapital = max(1000.0, (float) data_get($runSettings, 'capital.initial', 10000));
        $maxPositions = max(1, (int) data_get($runSettings, 'capital.max_parallel_positions', 5));
        $positionFactor = max(1, min($maxPositions, (int) data_get($runSettings, 'capital.position_factor', 1)));
        $basePositionCapital = $initialCapital / $maxPositions;
        $positionCapital = $basePositionCapital * $positionFactor;
        $tradeCost = max(0.0, (float) data_get($runSettings, 'capital.trade_cost_eur', 10));
        // backtest_trades is the complete, persisted 20-day baseline. The
        // strategy table contains supplemental exits and may be incomplete
        // when an instrument has gaps in its local OHLC history.
        $candidateQuery = DB::table('backtest_trades')
            ->where('backtest_run_id', $run->id)
            ->whereDate('entry_date', '>=', $period->starts_at)
            ->whereDate('exit_date', '<=', $period->ends_at)
            ->orderBy('entry_date')
            ->orderByDesc('ki_score')
            ->orderByDesc('confidence');
        $candidates = $candidateQuery->orderBy('id')->get([
            'id', 'instrument_id', 'model_definition_id', 'trained_model_id',
            'entry_date', 'exit_date', 'entry_price', 'gross_return', 'max_drawdown',
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
            if (count($openPositions) >= $maxPositions || $cash + 0.00001 < $basePositionCapital) continue;
            $availableFactor = (int) floor(($cash + 0.00001) / $basePositionCapital);
            $appliedFactor = min($positionFactor, $availableFactor);
            if ($appliedFactor < 1) continue;
            $allocatedCapital = $basePositionCapital * $appliedFactor;
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
        $winnerTrades = $executed->filter(fn (object $trade): bool => $netReturn($trade) > 0)->count();
        $loserTrades = $executed->filter(fn (object $trade): bool => $netReturn($trade) < 0)->count();
        $averageWinningReturn = $winnerTrades > 0
            ? (float) $executed->filter(fn (object $trade): bool => $netReturn($trade) > 0)->avg($netReturn)
            : 0.0;
        $averageLosingReturn = $loserTrades > 0
            ? abs((float) $executed->filter(fn (object $trade): bool => $netReturn($trade) < 0)->avg($netReturn))
            : 0.0;
        $averageGainFactor = $averageLosingReturn > 0 ? $averageWinningReturn / $averageLosingReturn : null;
        $winLossRatio = $loserTrades > 0 ? $winnerTrades / $loserTrades : null;
        $totalInvestmentDays = $executed->sum(fn (object $trade): int => (int) max(
            0,
            \Illuminate\Support\Carbon::parse($trade->entry_date)->diffInDays(\Illuminate\Support\Carbon::parse($trade->exit_date)),
        ));
        $minimumTradeDrawdown = $executed->isNotEmpty()
            ? (float) $executed->min(fn (object $trade): float => abs((float) $trade->max_drawdown)) * 100
            : 0.0;
        $averageTradeReturn = $executed->isNotEmpty() ? (float) $executed->avg($netReturn) * 100 : 0.0;
        $executedTradeIds = $executed->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $predictionReachedTrades = $executedTradeIds === []
            ? 0
            : DB::table('backtest_strategy_trades as target_trade')
                ->join('backtest_trades as base_trade', 'base_trade.id', '=', 'target_trade.backtest_trade_id')
                ->where('target_trade.backtest_run_id', $run->id)
                ->where('target_trade.strategy', 'prediction_target')
                ->whereIn('target_trade.backtest_trade_id', $executedTradeIds)
                ->whereRaw('target_trade.gross_return >= base_trade.predicted_return - 0.000001')
                ->count();

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
        if (! $benchmarkCoversStart) {
            $remoteBenchmarkBars = collect($indices->dailyHistory('^GSPC', $lookbackYears.'y'))
                ->filter(fn (array $bar): bool => $bar['timestamp'] >= strtotime((string) $period->starts_at)
                    && $bar['timestamp'] <= strtotime((string) $period->ends_at.' 23:59:59'))
                ->map(fn (array $bar): object => (object) [
                    'bar_time' => \Illuminate\Support\Carbon::createFromTimestampUTC($bar['timestamp']),
                    'close' => $bar['adjusted_close'] ?? $bar['close'],
                ])
                ->values();
            if ($remoteBenchmarkBars->isNotEmpty()) {
                $benchmarkBars = $remoteBenchmarkBars;
            }
        }
        $benchmarkStart = $benchmarkBars->isNotEmpty() ? (float) $benchmarkBars->first()->close : 0.0;
        $benchmark = $benchmarkStart > 0
            ? $benchmarkBars->map(fn (object $bar): array => [
                'x' => strtotime((string) $bar->bar_time) * 1000,
                'y' => round(((float) $bar->close / $benchmarkStart) * $initialCapital, 2),
            ])->values()->all()
            : [];
        $benchmarkStartCapital = $benchmark !== [] ? (float) $benchmark[0]['y'] : null;
        $benchmarkFinalCapital = $benchmark !== [] ? (float) $benchmark[array_key_last($benchmark)]['y'] : null;
        $benchmarkProfit = $benchmarkStartCapital !== null && $benchmarkFinalCapital !== null
            ? $benchmarkFinalCapital - $benchmarkStartCapital
            : null;
        $benchmarkPerformance = $benchmarkStartCapital !== null && $benchmarkStartCapital > 0 && $benchmarkProfit !== null
            ? round(($benchmarkProfit / $benchmarkStartCapital) * 100, 2)
            : null;
        $benchmarkDrawdown = $this->maximumSeriesDrawdown($benchmark);

        $winnerCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', 'winner_runner')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['entry_date', 'exit_date', 'gross_return', 'max_drawdown']);
        $winner = $this->simulatePortfolio($winnerCandidates, $initialCapital, $maxPositions, $positionFactor, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $winnerGross = $this->simulatePortfolio($winnerCandidates->map(fn (object $trade): object => clone $trade), $initialCapital, $maxPositions, $positionFactor, 0, (string) $period->starts_at, (string) $period->ends_at);
        $targetCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', 'prediction_target')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['entry_date', 'exit_date', 'gross_return', 'max_drawdown']);
        $target = $this->simulatePortfolio($targetCandidates, $initialCapital, $maxPositions, $positionFactor, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $targetGross = $this->simulatePortfolio($targetCandidates->map(fn (object $trade): object => clone $trade), $initialCapital, $maxPositions, $positionFactor, 0, (string) $period->starts_at, (string) $period->ends_at);
        $adaptiveCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', 'adaptive_rotation_20d')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['entry_date', 'exit_date', 'gross_return', 'max_drawdown', 'metadata']);
        $adaptive = $this->simulatePortfolio($adaptiveCandidates, $initialCapital, $maxPositions, $positionFactor, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $adaptiveGross = $this->simulatePortfolio($adaptiveCandidates->map(fn (object $trade): object => clone $trade), $initialCapital, $maxPositions, $positionFactor, 0, (string) $period->starts_at, (string) $period->ends_at);
        $fixedGross = $this->simulatePortfolio($candidates->map(fn (object $trade): object => clone $trade), $initialCapital, $maxPositions, $positionFactor, 0, (string) $period->starts_at, (string) $period->ends_at);
        $buyAndHold = $this->simulateBuyAndHold(
            $candidates,
            $initialCapital,
            $maxPositions,
            $tradeCost,
            (string) $period->starts_at,
            (string) $period->ends_at,
        );
        $backtestMonths = max(
            1.0,
            (strtotime((string) $period->ends_at) - strtotime((string) $period->starts_at)) / (86400 * 30.4375),
        );

        return response()->json([
            'period_start' => strtotime((string) $period->starts_at) * 1000,
            'period_end' => strtotime((string) $period->ends_at) * 1000,
            'strategy' => $strategy,
            'winner_runner' => $winner['series'],
            'prediction_target' => $target['series'],
            'adaptive_rotation' => $adaptive['series'],
            'buy_and_hold' => $buyAndHold['series'],
            'benchmark' => $benchmark,
            'strategy_chart' => $this->performanceSeries($strategy, $initialCapital),
            'winner_runner_chart' => $this->performanceSeries($winner['series'], $initialCapital),
            'prediction_target_chart' => $this->performanceSeries($target['series'], $initialCapital),
            'adaptive_rotation_chart' => $this->performanceSeries($adaptive['series'], $initialCapital),
            'buy_and_hold_chart' => $this->performanceSeries($buyAndHold['series'], $initialCapital),
            'benchmark_chart' => $this->performanceSeries($benchmark, $initialCapital),
            'strategy_performance' => round((($strategyValue / $initialCapital) - 1) * 100, 2),
            'strategy_gross_performance' => $fixedGross['performance'],
            'benchmark_start_capital' => $benchmarkStartCapital,
            'benchmark_final_capital' => $benchmarkFinalCapital,
            'benchmark_profit' => $benchmarkProfit !== null ? round($benchmarkProfit, 2) : null,
            'benchmark_performance' => $benchmarkPerformance,
            'winner_runner_performance' => $winner['performance'],
            'winner_runner_gross_performance' => $winnerGross['performance'],
            'winner_runner_final_capital' => $winner['final'],
            'winner_runner_executed_trades' => $winner['executed'],
            'winner_runner_skipped_trades' => $winner['skipped'],
            'prediction_target_performance' => $target['performance'],
            'prediction_target_gross_performance' => $targetGross['performance'],
            'prediction_target_final_capital' => $target['final'],
            'prediction_target_executed_trades' => $target['executed'],
            'prediction_target_skipped_trades' => $target['skipped'],
            'adaptive_rotation_performance' => $adaptive['performance'],
            'adaptive_rotation_gross_performance' => $adaptiveGross['performance'],
            'adaptive_rotation_final_capital' => $adaptive['final'],
            'adaptive_rotation_executed_trades' => $adaptive['executed'],
            'adaptive_rotation_skipped_trades' => max(0, $candidates->count() - $adaptive['executed']),
            'buy_and_hold_performance' => $buyAndHold['performance'],
            'buy_and_hold_final_capital' => $buyAndHold['final'],
            'buy_and_hold_executed_trades' => $buyAndHold['executed'],
            'buy_and_hold_entry_at' => $buyAndHold['entry_at'],
            'buy_and_hold_trades_per_month' => round($buyAndHold['executed'] / $backtestMonths, 2),
            'buy_and_hold_max_drawdown' => $buyAndHold['max_drawdown'],
            'backtest_months' => round($backtestMonths, 2),
            'trades_per_month' => round($executed->count() / $backtestMonths, 2),
            'winner_runner_trades_per_month' => round($winner['executed'] / $backtestMonths, 2),
            'prediction_target_trades_per_month' => round($target['executed'] / $backtestMonths, 2),
            'adaptive_rotation_trades_per_month' => round($adaptive['executed'] / $backtestMonths, 2),
            'initial_capital' => $initialCapital,
            'position_factor' => $positionFactor,
            'position_factor_usage' => $this->positionFactorUsage($executed, $basePositionCapital),
            'winner_runner_position_factor_usage' => $winner['position_factor_usage'],
            'prediction_target_position_factor_usage' => $target['position_factor_usage'],
            'adaptive_rotation_position_factor_usage' => $adaptive['position_factor_usage'],
            'model_statistics' => $this->executedModelStatistics($executed),
            'final_capital' => $strategyValue,
            'executed_trades' => $executed->count(),
            'prediction_reached_trades' => $predictionReachedTrades,
            'winner_trades' => $winnerTrades,
            'loser_trades' => $loserTrades,
            'average_gain_factor' => $averageGainFactor !== null ? round($averageGainFactor, 3) : null,
            'win_loss_ratio' => $winLossRatio !== null ? round($winLossRatio, 3) : null,
            'total_investment_days' => $totalInvestmentDays,
            'minimum_trade_drawdown' => round($minimumTradeDrawdown, 2),
            'average_trade_return' => round($averageTradeReturn, 2),
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
            'adaptive_rotation_max_drawdown' => $adaptive['max_drawdown'],
            'average_capital_binding' => $capitalBinding['average'],
            'maximum_capital_binding' => $capitalBinding['maximum'],
            'winner_runner_average_capital_binding' => $winner['average_capital_binding'],
            'winner_runner_maximum_capital_binding' => $winner['maximum_capital_binding'],
            'prediction_target_average_capital_binding' => $target['average_capital_binding'],
            'prediction_target_maximum_capital_binding' => $target['maximum_capital_binding'],
            'adaptive_rotation_average_capital_binding' => $adaptive['average_capital_binding'],
            'adaptive_rotation_maximum_capital_binding' => $adaptive['maximum_capital_binding'],
        ]);
    }

    public function filteredBacktestStatus(Request $request, string $publicId): JsonResponse
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->first(['id', 'status', 'instruments_total', 'instruments_completed', 'trades_count', 'error_message']);
        abort_if($run === null, 404);

        $engineJob = DB::table('python_engine_jobs')
            ->where('backtest_run_id', $run->id)
            ->latest('id')
            ->first(['status', 'progress', 'error_message']);

        return response()->json([
            'status' => $run->status,
            'finished' => in_array($run->status, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true),
            'instruments_total' => (int) $run->instruments_total,
            'instruments_completed' => (int) $run->instruments_completed,
            'trades' => (int) $run->trades_count,
            'progress' => (int) ($engineJob?->progress ?? 0),
            'worker_status' => $engineJob?->status,
            'error' => $run->status === 'failed' ? ($engineJob?->error_message ?: $run->error_message) : null,
        ]);
    }

    public function cancelFilteredBacktest(Request $request, string $publicId): RedirectResponse
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['queued', 'running'])
            ->first(['id']);

        if ($run !== null) {
            $directory = storage_path('app/backtest-cancellations');
            File::ensureDirectoryExists($directory);
            File::put($directory.'/'.$run->id, now()->toIso8601String());

            DB::table('backtest_runs')
            ->where('id', $run->id)
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'cancelled',
                'finished_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);

            DB::table('python_engine_jobs')
                ->where('backtest_run_id', $run->id)
                ->whereIn('status', ['queued', 'running'])
                ->update([
                    'status' => 'cancelled',
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $query = $request->except(['_token', 'backtest_run']);

        return redirect()->route($request->boolean('quality_setup') ? 'setup.quality' : 'setup.filter', $query)
            ->with('status', __('Der Backtest wurde abgebrochen.'));
    }

    public function downloadFilteredBacktestReport(Request $request, string $publicId, YahooIndexService $indices): Response
    {
        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->first();
        abort_if($run === null, 404);
        $result = $this->filteredBacktestResult($request, $publicId, $indices)->getData(true);
        $settings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
        $modelStatistics = collect($result['model_statistics'] ?? [])->map(fn (array $model): object => (object) $model);
        $modelExitMatrix = $this->backtestModelExitMatrix((int) $run->id);
        $backtestStocks = $this->backtestStockStatistics((int) $run->id);
        $adaptiveStatistics = $this->backtestAdaptiveStatistics((int) $run->id);
        $chart = $this->reportChart([
            '20 Tage' => ['color' => '#14b8a6', 'points' => $result['strategy_chart']],
            'Winner Runner' => ['color' => '#6366f1', 'points' => $result['winner_runner_chart']],
            'Prognoseziel' => ['color' => '#e11d48', 'points' => $result['prediction_target_chart']],
            'Adaptive Rotation' => ['color' => '#22c55e', 'points' => $result['adaptive_rotation_chart']],
            'S&P 500 (+'.number_format((float) $result['benchmark_performance'], 2, ',', '.').' %)' => ['color' => '#d97706', 'points' => $result['benchmark_chart']],
            'Buy and Hold' => ['color' => '#38bdf8', 'points' => $result['buy_and_hold_chart']],
        ]);
        $html = view('predictions.backtest-report', compact('run', 'result', 'settings', 'chart', 'modelStatistics', 'modelExitMatrix', 'backtestStocks', 'adaptiveStatistics'))->render();
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
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
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

    private function executedModelStatistics($executed)
    {
        $modelIds = collect($executed)->pluck('model_definition_id')->filter()->unique()->values();
        $trainedModelIds = collect($executed)->pluck('trained_model_id')->filter()->unique()->values();
        $aliases = $modelIds->isEmpty()
            ? collect()
            : DB::table('model_definitions')->whereIn('id', $modelIds)->pluck('public_alias', 'id');
        $qualityTiers = $trainedModelIds->isEmpty()
            ? collect()
            : DB::table('model_quality_rankings as quality')
                ->leftJoin('model_quality_tiers as tier', 'tier.id', '=', 'quality.tier_id')
                ->whereIn('quality.trained_model_id', $trainedModelIds)
                ->orderByDesc('quality.id')
                ->get(['quality.trained_model_id', 'tier.name'])
                ->unique('trained_model_id')
                ->pluck('name', 'trained_model_id');

        return collect($executed)
            ->groupBy(fn (object $trade): string => (int) ($trade->model_definition_id ?? 0).':'.(int) ($trade->trained_model_id ?? 0))
            ->map(function ($trades) use ($aliases, $qualityTiers): array {
                $first = $trades->first();
                $deployedCapital = (float) $trades->sum(fn (object $trade): float => (float) ($trade->allocated_capital ?? 0));
                $profits = $trades->map(fn (object $trade): float =>
                    (float) ($trade->allocated_capital ?? 0) * (float) ($trade->net_return_after_cost ?? 0));
                $positiveProfit = (float) $profits->filter(fn (float $profit): bool => $profit > 0)->sum();
                $negativeProfit = abs((float) $profits->filter(fn (float $profit): bool => $profit < 0)->sum());

                return [
                    'model_name' => $aliases[(int) ($first->model_definition_id ?? 0)] ?? 'Unbekannt',
                    'quality_tier' => $qualityTiers[(int) ($first->trained_model_id ?? 0)] ?? 'Nicht qualifiziert',
                    'trades' => $trades->count(),
                    'deployed_capital' => $deployedCapital,
                    'hit_rate' => $trades->isNotEmpty()
                        ? ($trades->filter(fn (object $trade): bool => (float) ($trade->net_return_after_cost ?? 0) > 0)->count() / $trades->count()) * 100
                        : 0,
                    'average_return' => $deployedCapital > 0 ? ($profits->sum() / $deployedCapital) * 100 : 0,
                    'profit_factor' => $negativeProfit > 0 ? $positiveProfit / $negativeProfit : null,
                    'max_drawdown' => (float) $trades->max(fn (object $trade): float => abs((float) ($trade->max_drawdown ?? 0))) * 100,
                    'first_trade' => $trades->min('entry_date'),
                    'last_trade' => $trades->max('exit_date'),
                ];
            })
            ->sortByDesc('trades')
            ->values();
    }

    private function backtestModelExitMatrix(int $runId)
    {
        return DB::table('backtest_strategy_trades as strategy_trade')
            ->join('backtest_trades as trade', 'trade.id', '=', 'strategy_trade.backtest_trade_id')
            ->leftJoin('model_definitions as model', 'model.id', '=', 'trade.model_definition_id')
            ->where('strategy_trade.backtest_run_id', $runId)
            ->whereIn('strategy_trade.strategy', ['fixed_20d', 'winner_runner', 'prediction_target', 'adaptive_rotation_20d'])
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

    private function backtestStockStatistics(int $runId)
    {
        return DB::table('backtest_trades as trade')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->where('trade.backtest_run_id', $runId)
            ->groupBy('trade.instrument_id', 'instrument.symbol', 'instrument.name', 'instrument.country', 'exchange.code')
            ->select('instrument.symbol', 'instrument.name', 'instrument.country')
            ->selectRaw("COALESCE(exchange.code, '—') AS exchange")
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('AVG(CASE WHEN trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(trade.net_return) * 100 AS average_return')
            ->orderBy('instrument.symbol')
            ->get();
    }

    private function backtestAdaptiveStatistics(int $runId): object
    {
        $totalCandidates = DB::table('backtest_trades')->where('backtest_run_id', $runId)->count();
        $adaptiveQuery = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $runId)
            ->where('strategy', 'adaptive_rotation_20d');
        $eligible = (clone $adaptiveQuery)->count();
        $regimes = (clone $adaptiveQuery)
            ->selectRaw("COALESCE(metadata->>'regime', 'unbekannt') AS regime, COUNT(*) AS trades")
            ->groupByRaw("COALESCE(metadata->>'regime', 'unbekannt')")
            ->pluck('trades', 'regime');
        $sectors = (clone $adaptiveQuery)
            ->whereRaw("metadata->>'regime' = 'weak'")
            ->whereRaw("COALESCE(metadata->>'sector', '') <> ''")
            ->selectRaw("metadata->>'sector' AS sector, COUNT(*) AS trades")
            ->groupByRaw("metadata->>'sector'")
            ->orderByDesc('trades')
            ->limit(8)
            ->get();
        $sectorOverweights = (clone $adaptiveQuery)
            ->whereRaw("COALESCE((metadata->>'sector_overweight')::boolean, false) = true")
            ->count();
        $indexOverweights = (clone $adaptiveQuery)
            ->whereRaw("COALESCE((metadata->>'index_overweight')::boolean, false) = true")
            ->count();

        return (object) [
            'candidates' => $totalCandidates,
            'eligible' => $eligible,
            'rejected' => max(0, $totalCandidates - $eligible),
            'normal' => (int) ($regimes['normal'] ?? 0),
            'weak' => (int) ($regimes['weak'] ?? 0),
            'sectors' => $sectors,
            'sector_overweights' => $sectorOverweights,
            'index_overweights' => $indexOverweights,
        ];
    }

    public function index(Request $request): View
    {
        $heatmapOnly = $request->attributes->getBoolean('heatmap_only');

        if (! $request->query->has('signal')) {
            $request->query->set('signal', 'BUY');
        }

        $modelIds = $this->requestedModelIds($request);
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
        $canUseSmartLabels = ! $heatmapOnly
            && app(PlanAccessService::class)->allows($request->user(), PlanLevel::Premium);
        $smartLabels = $canUseSmartLabels
            ? DB::table('smart_selection_labels')
                ->where('user_id', $request->user()->id)
                ->where('tariff_plan_id', $request->user()->tariff_plan_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'icon', 'criteria'])
            : collect();
        $selectedSmartLabelId = $smartLabels->contains('id', $request->integer('smart_label'))
            ? $request->integer('smart_label')
            : null;
        $labelBacktestRunId = (int) DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            ->orderByDesc('id')
            ->value('id');
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
        $instrumentBacktestStats = DB::table('backtest_trades')
            ->where('backtest_run_id', $labelBacktestRunId)
            ->groupBy('instrument_id')
            ->select('instrument_id')
            ->selectRaw('MAX(ABS(max_drawdown)) * 100 AS drawdown_percent')
            ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate');
        $latestTechnicalIndicators = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->groupBy('instrument_id')
            ->selectRaw('instrument_id, MAX(id) AS technical_id');

        $historicalBaseQuery = fn (): Builder => DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
            ->leftJoinSub($instrumentBacktestStats, 'backtest_stat', fn ($join) =>
                $join->on('backtest_stat.instrument_id', '=', 'instrument.id'))
            ->leftJoinSub($latestTechnicalIndicators, 'latest_technical', fn ($join) =>
                $join->on('latest_technical.instrument_id', '=', 'instrument.id'))
            ->leftJoin('technical_indicators as technical', 'technical.id', '=', 'latest_technical.technical_id')
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
        $predictedReturnSql = '((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100';
        $minimumQualityTiers = [
            'top' => ['top'],
            'strong' => ['top', 'strong'],
            'solid' => ['top', 'strong', 'solid'],
            'test' => ['top', 'strong', 'solid', 'test'],
        ];

        $applyFilters = function (Builder $query, ?string $excluded = null, bool $includeNumericFilters = true) use ($request, $signalSql, $scoreSql, $confidenceSql, $predictedReturnSql, $minimumQualityTiers, $modelIds): Builder {
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
            ->when($excluded !== 'model' && $modelIds !== [], fn (Builder $query) =>
                $query->whereIn('trained_model.model_definition_id', $modelIds))
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
            ->when($includeNumericFilters && $excluded !== 'score' && $request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->whereRaw("{$scoreSql} >= ?", [max(0, min(10, (float) $request->query('score_min')))]))
            ->when($includeNumericFilters && $excluded !== 'confidence' && $request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->whereRaw("{$confidenceSql} >= ?", [max(0, min(100, (float) $request->query('confidence_min')))]))
            ->when($includeNumericFilters && $request->filled('drawdown_max') && is_numeric($request->query('drawdown_max')) && (float) $request->query('drawdown_max') < 50, fn (Builder $query) =>
                $query->where('backtest_stat.drawdown_percent', '<=', max(0, min(50, (float) $request->query('drawdown_max')))))
            ->when($includeNumericFilters && $request->filled('profit_factor_min') && is_numeric($request->query('profit_factor_min')) && (float) $request->query('profit_factor_min') > 0, fn (Builder $query) =>
                $query->where('backtest_stat.profit_factor', '>=', max(0, min(10, (float) $request->query('profit_factor_min')))))
            ->when($includeNumericFilters && $request->filled('hit_rate_min') && is_numeric($request->query('hit_rate_min')) && (float) $request->query('hit_rate_min') > 0, fn (Builder $query) =>
                $query->where('backtest_stat.hit_rate', '>=', max(0, min(100, (float) $request->query('hit_rate_min')))))
            ->when($includeNumericFilters && $request->filled('volatility_max') && is_numeric($request->query('volatility_max')) && (float) $request->query('volatility_max') < 100, fn (Builder $query) =>
                $query->whereRaw('technical.volatility_20 * 100 <= ?', [max(0, min(100, (float) $request->query('volatility_max')))]))
            ->when($includeNumericFilters && $request->filled('predicted_return_min') && is_numeric($request->query('predicted_return_min')), fn (Builder $query) =>
                $query->whereRaw("{$predictedReturnSql} >= ?", [max(-50, min(100, (float) $request->query('predicted_return_min')))]));
        };

        if (! $heatmapOnly) {
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
        $predictionInstrumentIds = $predictions->pluck('instrument_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $smartLabelStats = $predictionInstrumentIds->isEmpty() || $smartLabels->isEmpty()
            ? collect()
            : DB::table('backtest_trades as label_trade')
                ->where('label_trade.backtest_run_id', $labelBacktestRunId)
                ->whereIn('label_trade.instrument_id', $predictionInstrumentIds)
                ->groupBy('label_trade.instrument_id')
                ->select('label_trade.instrument_id')
                ->selectRaw('MAX(ABS(label_trade.max_drawdown)) * 100 AS drawdown')
                ->selectRaw('SUM(CASE WHEN label_trade.net_return > 0 THEN label_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN label_trade.net_return < 0 THEN label_trade.net_return ELSE 0 END)), 0) AS profit_factor')
                ->get()->keyBy('instrument_id');
        $latestTechnicalIdsForLabels = $predictionInstrumentIds->isEmpty() || $smartLabels->isEmpty()
            ? collect()
            : DB::table('technical_indicators')
                ->where('interval', '1d')
                ->whereIn('instrument_id', $predictionInstrumentIds)
                ->groupBy('instrument_id')
                ->selectRaw('instrument_id, MAX(id) AS technical_id')
                ->pluck('technical_id', 'instrument_id');
        $smartLabelVolatility = $latestTechnicalIdsForLabels->isEmpty()
            ? collect()
            : DB::table('technical_indicators')->whereIn('id', $latestTechnicalIdsForLabels->values())
                ->pluck('volatility_20', 'instrument_id');

        $predictions = $predictions->map(function (object $prediction) use ($smartLabels, $selectedSmartLabelId, $smartLabelStats, $smartLabelVolatility): object {
            $score = is_numeric($prediction->score_10) ? (float) $prediction->score_10 : null;
            $confidence = is_numeric($prediction->confidence_percent) ? (float) $prediction->confidence_percent : null;
            $predictedReturn = is_numeric($prediction->expected_return_20d) ? (float) $prediction->expected_return_20d : null;
            $backtestStats = $smartLabelStats->get((int) $prediction->instrument_id);
            $drawdown = is_numeric($backtestStats?->drawdown) ? (float) $backtestStats->drawdown : null;
            $profitFactor = is_numeric($backtestStats?->profit_factor) ? (float) $backtestStats->profit_factor : null;
            $rawVolatility = $smartLabelVolatility->get((int) $prediction->instrument_id);
            $volatility = is_numeric($rawVolatility) ? (float) $rawVolatility * 100 : null;
            $isBuy = strtoupper((string) $prediction->personalized_signal) === 'BUY';

            $matching = $smartLabels->filter(function (object $label) use ($isBuy, $score, $confidence, $predictedReturn, $drawdown, $profitFactor, $volatility): bool {
                if (! $isBuy) return false;
                $criteria = is_string($label->criteria) ? (json_decode($label->criteria, true) ?: []) : (array) $label->criteria;
                if ($score === null || $score < (float) ($criteria['score_min'] ?? 0)) return false;
                if ($confidence === null || $confidence < (float) ($criteria['confidence_min'] ?? 0)) return false;
                if ($predictedReturn === null || $predictedReturn < (float) ($criteria['predicted_return_min'] ?? -20)) return false;
                if ((float) ($criteria['drawdown_max'] ?? 50) < 50 && ($drawdown === null || $drawdown > (float) $criteria['drawdown_max'])) return false;
                if ((float) ($criteria['profit_factor_min'] ?? 0) > 0 && ($profitFactor === null || $profitFactor < (float) $criteria['profit_factor_min'])) return false;
                if ((float) ($criteria['volatility_max'] ?? 100) < 100 && ($volatility === null || $volatility > (float) $criteria['volatility_max'])) return false;
                return true;
            })->map(fn (object $label): array => [
                'id' => (int) $label->id,
                'name' => $label->name,
                'color' => $label->color,
                'icon' => $label->icon ?: 'sparkles',
            ])->values();

            $prediction->smart_labels = $matching->all();
            return $prediction;
        });
        if ($selectedSmartLabelId !== null) {
            $predictions = $predictions->filter(fn (object $prediction): bool => collect($prediction->smart_labels)->contains('id', $selectedSmartLabelId))->values();
        }
        } else {
            $predictions = collect();
        }

        // Keep the summary cards in sync with exactly the same filters as the table.
        $summary = $heatmapOnly ? (object) [
            'total' => 0,
            'instruments' => 0,
            'validated' => 0,
            'oldest_training' => null,
        ] : $applyFilters($baseQuery())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT prediction.instrument_id) AS instruments')
            ->selectRaw('COUNT(prediction.validated_at) AS validated')
            ->selectRaw('MIN(trained_model.trained_at) AS oldest_training')
            ->first();
        if ($selectedSmartLabelId !== null) {
            $summary->total = $predictions->count();
            $summary->instruments = $predictions->pluck('instrument_id')->unique()->count();
            $summary->validated = $predictions->whereNotNull('validated_at')->count();
        }

        $aiTypes = $applyFilters($baseQuery(), 'ai_type', false)
            ->whereNotNull('prediction.ai_type')
            ->distinct()
            ->orderBy('prediction.ai_type')
            ->pluck('prediction.ai_type');

        $models = $applyFilters($baseQuery(), 'model', false)
            ->whereNotNull('model_definition.public_alias')
            ->where('model_definition.public_alias', '<>', '')
            ->select('model_definition.id', 'model_definition.public_alias')
            ->distinct()
            ->orderBy('model_definition.public_alias')
            ->get();

        $qualityTiers = $applyFilters($baseQuery(), 'quality_tier', false)
            ->selectRaw("COALESCE(quality_tier.code, 'unqualified') AS code")
            ->selectRaw("COALESCE(quality_tier.name, 'Nicht qualifiziert') AS name")
            ->distinct()
            ->get()
            ->sortBy(fn (object $tier): int => array_search($tier->code, ['top', 'strong', 'solid', 'test', 'unqualified'], true))
            ->values();

        $signals = $applyFilters($baseQuery(), 'signal', false)
            ->selectRaw("({$signalSql}) AS available_signal")
            ->distinct()
            ->orderBy('available_signal')
            ->pluck('available_signal')
            ->map(fn ($signal) => strtoupper((string) $signal))
            ->filter(fn (string $signal) => in_array($signal, ['SELL', 'HOLD', 'WATCH', 'BUY'], true));

        $validationStates = $applyFilters($baseQuery(), 'validation', false)
            ->selectRaw("CASE WHEN prediction.validated_at IS NULL THEN 'pending' ELSE 'validated' END AS validation_state")
            ->distinct()
            ->orderBy('validation_state')
            ->pluck('validation_state');

        $countries = $applyFilters($baseQuery(), 'country', false)
            ->whereNotNull('instrument.country')
            ->where('instrument.country', '<>', '')
            ->distinct()
            ->orderBy('instrument.country')
            ->pluck('instrument.country');
        $sectors = $applyFilters($baseQuery(), 'sector', false)
            ->whereNotNull('instrument.sector')
            ->where('instrument.sector', '<>', '')
            ->distinct()
            ->orderBy('instrument.sector')
            ->pluck('instrument.sector');
        $exchanges = $applyFilters($baseQuery(), 'exchange', false)
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
        $rangeSource = DB::table('backtest_trades as range_trade')
            ->join('instruments as range_instrument', 'range_instrument.id', '=', 'range_trade.instrument_id')
            ->leftJoinSub($latestFundamentalIds, 'range_latest_fundamental', fn ($join) =>
                $join->on('range_latest_fundamental.instrument_id', '=', 'range_instrument.id'))
            ->leftJoin('instrument_fundamentals as range_fundamental', 'range_fundamental.id', '=', 'range_latest_fundamental.fundamental_id')
            ->leftJoinSub($latestTechnicalIds, 'range_latest_technical', fn ($join) =>
                $join->on('range_latest_technical.instrument_id', '=', 'range_instrument.id'))
            ->leftJoin('technical_indicators as range_technical', 'range_technical.id', '=', 'range_latest_technical.technical_id')
            ->where('range_trade.backtest_run_id', $backtestRunId)
            ->where('range_instrument.is_active', true)
            ->whereNull('range_instrument.deleted_at');
        $rangeFundamentalNumber = static fn (string $key): string =>
            "(CASE WHEN NULLIF(range_fundamental.data::jsonb->>'{$key}', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (range_fundamental.data::jsonb->>'{$key}')::numeric END)";
        $rangeValues = (clone $rangeSource)
            ->selectRaw('MAX(range_trade.ki_score) AS score')
            ->selectRaw('MAX(range_trade.confidence) AS confidence')
            ->selectRaw('MAX(ABS(range_trade.max_drawdown)) * 100 AS drawdown')
            ->selectRaw('MAX(range_trade.predicted_return) * 100 AS predicted_return')
            ->selectRaw('MAX(range_technical.volatility_20) * 100 AS volatility')
            ->selectRaw('MAX('.$rangeFundamentalNumber('trailingPE').') AS pe')
            ->selectRaw('MAX('.$rangeFundamentalNumber('dividendYield').') AS dividend_yield')
            ->selectRaw('MAX('.$rangeFundamentalNumber('marketCap').') / 1000000000 AS market_cap')
            ->selectRaw('MAX('.$rangeFundamentalNumber('revenueGrowth').') * 100 AS revenue_growth')
            ->first();
        $instrumentRangeStats = DB::table('backtest_trades as range_stat_trade')
            ->join('instruments as range_stat_instrument', 'range_stat_instrument.id', '=', 'range_stat_trade.instrument_id')
            ->where('range_stat_trade.backtest_run_id', $backtestRunId)
            ->where('range_stat_instrument.is_active', true)
            ->whereNull('range_stat_instrument.deleted_at')
            ->groupBy('range_stat_trade.instrument_id')
            ->selectRaw('AVG(CASE WHEN range_stat_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN range_stat_trade.net_return > 0 THEN range_stat_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN range_stat_trade.net_return < 0 THEN range_stat_trade.net_return ELSE 0 END)), 0) AS profit_factor');
        $instrumentRangeValues = DB::query()->fromSub($instrumentRangeStats, 'range_stats')
            ->selectRaw('MAX(hit_rate) AS hit_rate, MAX(profit_factor) AS profit_factor')
            ->first();
        $ceilTo = static fn (mixed $value, float $step, float $fallback): float => is_numeric($value)
            ? max($step, ceil((float) $value / $step) * $step)
            : $fallback;
        $rangeMaxima = [
            'score' => $ceilTo($rangeValues?->score, .5, 10),
            'confidence' => $ceilTo($rangeValues?->confidence, 5, 100),
            'drawdown' => $ceilTo($rangeValues?->drawdown, 5, 50),
            'profit_factor' => 10.0,
            'volatility' => $ceilTo($rangeValues?->volatility, 5, 100),
            'predicted_return' => $ceilTo($rangeValues?->predicted_return, .5, 20),
            'pe' => $ceilTo($rangeValues?->pe, 1, 100),
            'dividend_yield' => $ceilTo($rangeValues?->dividend_yield, .1, 10),
            'market_cap' => $ceilTo($rangeValues?->market_cap, 25, 3000),
            'revenue_growth' => $ceilTo($rangeValues?->revenue_growth, 1, 100),
            'hit_rate' => $ceilTo($instrumentRangeValues?->hit_rate, 5, 100),
        ];
        $eligibleInstruments = $isUserBacktestResult
            ? null
            : $this->eligibleBacktestInstruments($backtestRunId, $request, $rangeMaxima);
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
            ->when($modelIds !== [], fn (Builder $query) =>
                $query->whereIn('backtest_trade.model_definition_id', $modelIds))
            ->when(in_array((string) $request->query('quality_tier'), ['top', 'strong', 'solid', 'test'], true), fn (Builder $query) =>
                $query->whereIn('quality_tier.code', $minimumQualityTiers[(string) $request->query('quality_tier')]))
            ->when($request->query('quality_tier') === 'unqualified', fn (Builder $query) =>
                $query->whereNull('quality_tier.code'))
            ->when(in_array(strtoupper((string) $request->query('signal')), ['SELL', 'HOLD', 'WATCH', 'BUY'], true), fn (Builder $query) =>
                $query->where('backtest_trade.signal', strtoupper((string) $request->query('signal'))))
            ->when($request->filled('volatility_max') && is_numeric($request->query('volatility_max')) && (float) $request->query('volatility_max') < $rangeMaxima['volatility'], fn (Builder $query) =>
                $query->where('technical.volatility_20', '<=', max(0, (float) $request->query('volatility_max')) / 100))
            ->when($request->filled('pe_max') && is_numeric($request->query('pe_max')) && (float) $request->query('pe_max') < $rangeMaxima['pe'], fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $request->query('pe_max')]))
            ->when($request->filled('dividend_yield_min') && is_numeric($request->query('dividend_yield_min')) && (float) $request->query('dividend_yield_min') > 0, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('dividendYield').' >= ?', [(float) $request->query('dividend_yield_min')]))
            ->when($request->filled('market_cap_min') && is_numeric($request->query('market_cap_min')) && (float) $request->query('market_cap_min') > 0, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('marketCap').' >= ?', [(float) $request->query('market_cap_min') * 1_000_000_000]))
            ->when($request->filled('revenue_growth_min') && is_numeric($request->query('revenue_growth_min')) && (float) $request->query('revenue_growth_min') > -50, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('revenueGrowth').' >= ?', [(float) $request->query('revenue_growth_min') / 100]));

        // Score and confidence define the selected area for the summary and
        // the simulation. They must not remove the historical values from
        // the remaining heatmap cells, which stay visible for comparison.
        $heatmapSummaryQuery = (clone $heatmapQuery)
            ->when($request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->where('backtest_trade.ki_score', '>=', max(0, min(10, (float) $request->query('score_min')))))
            ->when($request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->where('backtest_trade.confidence', '>=', max(0, min(100, (float) $request->query('confidence_min')))))
            ->when($request->filled('predicted_return_min') && is_numeric($request->query('predicted_return_min')), fn (Builder $query) =>
                $query->where('backtest_trade.predicted_return', '>=', max(-50, min(100, (float) $request->query('predicted_return_min'))) / 100));

        $heatmapSummary = $heatmapSummaryQuery
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('COUNT(DISTINCT backtest_trade.instrument_id) AS instruments')
            ->selectRaw("MAX(COALESCE(NULLIF(backtest_run.settings->>'lookback_years', '')::numeric * 12, 36)) AS backtest_months")
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(backtest_trade.max_drawdown)) * 100 AS drawdown')
            ->selectRaw('AVG(technical.volatility_20) * 100 AS volatility')
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
            ->selectRaw('AVG(technical.volatility_20) * 100 AS volatility')
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
            'rangeMaxima',
            'userWatchlists',
            'watchlistMemberships',
            'sort',
            'direction',
            'canUseSmartLabels',
            'smartLabels',
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

    private function requestedModelIds(Request $request): array
    {
        $models = $request->query('model', []);

        return collect(is_array($models) ? $models : [$models])
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function eligibleBacktestInstruments(int $runId, Request $request, array $rangeMaxima = []): ?Builder
    {
        $drawdownMaximum = $request->filled('drawdown_max') && is_numeric($request->query('drawdown_max'))
            ? (float) $request->query('drawdown_max')
            : (float) ($rangeMaxima['drawdown'] ?? 50.0);
        $profitFactorMinimum = $request->filled('profit_factor_min') && is_numeric($request->query('profit_factor_min'))
            ? (float) $request->query('profit_factor_min')
            : 0.0;
        $hitRateMinimum = $request->filled('hit_rate_min') && is_numeric($request->query('hit_rate_min'))
            ? (float) $request->query('hit_rate_min')
            : 0.0;
        if ($drawdownMaximum >= (float) ($rangeMaxima['drawdown'] ?? 50.0) && $profitFactorMinimum <= 0 && $hitRateMinimum <= 0) {
            return null;
        }

        return DB::table('backtest_trades as eligibility_trade')
            ->where('eligibility_trade.backtest_run_id', $runId)
            ->where('eligibility_trade.entry_date', '>=', now()->subYears(3)->toDateString())
            ->groupBy('eligibility_trade.instrument_id')
            ->select('eligibility_trade.instrument_id')
            ->when($drawdownMaximum < (float) ($rangeMaxima['drawdown'] ?? 50.0), fn (Builder $query) =>
                $query->havingRaw('MAX(ABS(eligibility_trade.max_drawdown)) <= ?', [max(0, $drawdownMaximum) / 100]))
            ->when($profitFactorMinimum > 0, fn (Builder $query) =>
                $query->havingRaw(
                    'COALESCE(SUM(CASE WHEN eligibility_trade.net_return > 0 THEN eligibility_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN eligibility_trade.net_return < 0 THEN eligibility_trade.net_return ELSE 0 END)), 0), 999999) >= ?',
                    [$profitFactorMinimum],
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
        int $positionFactor,
        float $tradeCost,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): array
    {
        $basePositionCapital = $initialCapital / max(1, $maxPositions);
        $positionCapital = $basePositionCapital * max(1, min($maxPositions, $positionFactor));
        $cash = $initialCapital;
        $open = [];
        $executed = collect();
        foreach ($candidates as $trade) {
            if ($periodStart !== null && (string) $trade->entry_date < $periodStart) continue;
            if ($periodEnd !== null && (string) $trade->exit_date > $periodEnd) continue;
            foreach ($open as $key => $position) {
                if ($position['exit_date'] > (string) $trade->entry_date) continue;
                $cash += $position['capital'] * (1 + $position['return']);
                unset($open[$key]);
            }
            if (count($open) >= $maxPositions || $cash + 0.00001 < $basePositionCapital) continue;
            $metadata = is_string($trade->metadata ?? null)
                ? (json_decode($trade->metadata, true) ?: [])
                : (array) ($trade->metadata ?? []);
            $allocationWeight = max(1.0, min(1.5, (float) ($metadata['allocation_weight'] ?? 1.0)));
            $requestedFactor = max(1.0, min((float) $maxPositions, $positionFactor * $allocationWeight));
            $factorStep = $allocationWeight > 1.0 ? 0.5 : 1.0;
            $availableFactor = floor((($cash + 0.00001) / $basePositionCapital) / $factorStep) * $factorStep;
            $appliedFactor = min($requestedFactor, $availableFactor);
            if ($appliedFactor < 1.0) continue;
            $allocatedCapital = $basePositionCapital * $appliedFactor;
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
            'position_factor_usage' => $this->positionFactorUsage($executed, $basePositionCapital),
        ];
    }

    private function positionFactorUsage($trades, float $basePositionCapital): array
    {
        if ($basePositionCapital <= 0) {
            return [];
        }

        return collect($trades)
            ->groupBy(function (object $trade) use ($basePositionCapital): string {
                $factor = round(max(0.0, (float) ($trade->allocated_capital ?? 0)) / $basePositionCapital, 2);

                return number_format($factor, $factor === floor($factor) ? 0 : 2, '.', '');
            })
            ->map(fn ($group): int => $group->count())
            ->sortKeysUsing(fn (string $left, string $right): int => (float) $left <=> (float) $right)
            ->all();
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
        $cash = $initialCapital;
        $invested = 0.0;
        $maximumBinding = 0.0;
        $bindingSeconds = 0.0;
        $binding = static fn (float $invested, float $cash): float => min(
            100.0,
            max(0.0, ($invested / max(0.00001, $cash + $invested)) * 100),
        );
        foreach ($events as $date => $event) {
            $rawTimestamp = (int) strtotime((string) $date);
            if ($rawTimestamp > $end) break;
            $timestamp = max($start, $rawTimestamp);
            $bindingSeconds += $binding($invested, $cash) * max(0, $timestamp - $cursor);
            foreach ($event['exits'] ?? [] as $exit) {
                $capital = (float) $exit['capital'];
                $invested = max(0.0, $invested - $capital);
                $cash += $capital * (1 + (float) ($exit['return'] ?? 0.0));
            }
            foreach ($event['entries'] ?? [] as $capital) {
                $allocated = min((float) $capital, max(0.0, $cash));
                $cash -= $allocated;
                $invested += $allocated;
            }
            $maximumBinding = max($maximumBinding, $binding($invested, $cash));
            $cursor = $timestamp;
        }
        $bindingSeconds += $binding($invested, $cash) * max(0, $end - $cursor);

        return [
            'average' => round(min(100.0, $bindingSeconds / ($end - $start)), 2),
            'maximum' => round(min(100.0, $maximumBinding), 2),
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
        $padding = max(2.0, ($maxY - $minY) * 0.08);
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
            $label = number_format($maxY - (($maxY - $minY) * $line / 4), 1, ',', '.').' %';
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

    private function performanceSeries(array $series, float $initialCapital): array
    {
        if ($initialCapital <= 0) return [];

        return collect($series)->map(static fn (array $point): array => [
            'x' => (int) $point['x'],
            'y' => round((((float) $point['y'] / $initialCapital) - 1) * 100, 2),
        ])->values()->all();
    }

    private function simulateBuyAndHold(
        $candidates,
        float $initialCapital,
        int $maxPositions,
        float $tradeCost,
        string $periodStart,
        string $periodEnd,
    ): array {
        $allocation = $initialCapital / max(1, $maxPositions);
        $positions = collect($candidates)
            ->filter(fn (object $trade): bool => (float) ($trade->entry_price ?? 0) > 0)
            ->unique(fn (object $trade): int => (int) $trade->instrument_id)
            ->take($maxPositions)
            ->map(function (object $trade) use ($allocation, $tradeCost): array {
                $invested = max(0.0, $allocation - $tradeCost);
                return [
                    'instrument_id' => (int) $trade->instrument_id,
                    'entry_date' => (string) $trade->entry_date,
                    'entry_price' => (float) $trade->entry_price,
                    'allocation' => $allocation,
                    'units' => $invested / (float) $trade->entry_price,
                ];
            })->values();
        if ($positions->isEmpty()) {
            return ['series' => [], 'performance' => 0.0, 'final' => $initialCapital, 'executed' => 0, 'max_drawdown' => 0.0, 'entry_at' => null];
        }

        $databaseBars = DB::table('price_bars')
            ->whereIn('instrument_id', $positions->pluck('instrument_id'))
            ->where('interval', '1d')
            ->where('bar_time', '>=', $periodStart.' 00:00:00+00')
            ->where('bar_time', '<=', $periodEnd.' 23:59:59+00')
            ->selectRaw('instrument_id, bar_time, COALESCE(adjusted_close, close) AS close')
            ->orderBy('bar_time')
            ->get();
        // Older filtered runs can predate the locally retained daily bars.
        // Their persisted entry prices provide real historical observations
        // and prevent the Buy-and-Hold curve from appearing to start years late.
        $positionIds = $positions->pluck('instrument_id')->flip();
        $persistedTradeBars = collect($candidates)
            ->filter(fn (object $trade): bool => $positionIds->has((int) $trade->instrument_id) && (float) ($trade->entry_price ?? 0) > 0)
            ->map(fn (object $trade): object => (object) [
                'instrument_id' => (int) $trade->instrument_id,
                'bar_time' => (string) $trade->entry_date.' 12:00:00+00',
                'close' => (float) $trade->entry_price,
            ]);
        $bars = $databaseBars
            ->concat($persistedTradeBars)
            ->sortBy('bar_time')
            ->groupBy(fn (object $bar): string => substr((string) $bar->bar_time, 0, 10))
            ->map(fn ($dailyBars) => $dailyBars->reverse()->unique('instrument_id')->reverse()->values());

        $cash = $initialCapital;
        $opened = [];
        $lastPrices = [];
        $series = [['x' => strtotime($periodStart) * 1000, 'y' => round($initialCapital, 2)]];
        $timeline = collect($bars->keys())
            ->merge($positions->pluck('entry_date'))
            ->filter(fn ($date): bool => $date >= $periodStart && $date <= $periodEnd)
            ->unique()
            ->sort()
            ->values();
        foreach ($timeline as $date) {
            foreach ($positions as $index => $position) {
                if (isset($opened[$index]) || $position['entry_date'] > $date) continue;
                $opened[$index] = true;
                $cash -= $position['allocation'];
                $lastPrices[$position['instrument_id']] = $position['entry_price'];
            }
            foreach ($bars->get($date, collect()) as $bar) {
                $lastPrices[(int) $bar->instrument_id] = (float) $bar->close;
            }
            $marketValue = $positions->sum(function (array $position, int $index) use ($opened, $lastPrices): float {
                if (! isset($opened[$index])) return 0.0;
                return $position['units'] * ($lastPrices[$position['instrument_id']] ?? $position['entry_price']);
            });
            $series[] = ['x' => strtotime($date) * 1000, 'y' => round($cash + $marketValue, 2)];
        }
        $periodEndTimestamp = strtotime($periodEnd) * 1000;
        if ($series[array_key_last($series)]['x'] < $periodEndTimestamp) {
            $series[] = ['x' => $periodEndTimestamp, 'y' => (float) $series[array_key_last($series)]['y']];
        }
        $final = (float) $series[array_key_last($series)]['y'];

        return [
            'series' => $series,
            'performance' => round((($final / $initialCapital) - 1) * 100, 2),
            'final' => round($final, 2),
            'executed' => $positions->count(),
            'max_drawdown' => $this->maximumSeriesDrawdown($series),
            'entry_at' => strtotime((string) $positions->min('entry_date')) * 1000,
        ];
    }
}

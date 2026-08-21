<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Jobs\RunFilteredBacktest;
use App\Services\PythonEngineJobDispatcher;
use App\Services\PlanAccessService;
use App\Services\PersonalizedSignalService;
use App\Services\FreeRegionalStockUniverseService;
use App\Services\SavedFilterLimitService;
use App\Services\UserQualityGateService;
use App\Services\YahooIndexService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class PredictionController extends Controller
{
    private const TABLE_FILTER_KEYS = [
        'q', 'symbols', 'country', 'exchange', 'index', 'sector', 'smart_label', 'quality_tier', 'signal',
        'score_min', 'confidence_min', 'drawdown_max', 'profit_per_trade_min', 'minimum_trades',
        'hit_rate_min', 'volatility_max', 'sort', 'direction',
    ];

    /**
     * Return the canonical index universe used by all German filter/setup pages.
     * Indexes are stored as instruments (type=index); the legacy market_indices
     * table is intentionally only a fallback for older installations.
     */
    private function activeIndexOptions()
    {
        $options = DB::table('instruments')
            ->where('type', 'index')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('symbol')
            ->get(['symbol', 'name']);

        if ($options->isEmpty() && DB::getSchemaBuilder()->hasTable('market_indices')) {
            $options = DB::table('market_indices')
                ->where('is_active', true)
                ->orderBy('symbol')
                ->get(['symbol', 'name']);
        }

        return $options
            ->map(fn (object $index): object => (object) [
                'symbol' => (string) $index->symbol,
                'name' => trim((string) ($index->name ?: $index->symbol)),
            ])
            ->values();
    }

    public function storeTableFilter(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);
        $filters = collect(self::TABLE_FILTER_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $request->input($key)])
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
        $user = $request->user();
        $preferences = (array) ($user->preferences ?? []);
        $presets = collect((array) data_get($preferences, 'prediction_table_filters', []));
        $name = trim($validated['name']);
        $existing = $presets->first(fn ($preset) => mb_strtolower((string) data_get($preset, 'name')) === mb_strtolower($name));
        $id = (string) (data_get($existing, 'id') ?: Str::uuid());
        $presets = $presets
            ->reject(fn ($preset) => (string) data_get($preset, 'id') === $id)
            ->push(['id' => $id, 'name' => $name, 'filters' => $filters])
            ->take(-20)
            ->values();
        data_set($preferences, 'prediction_table_filters', $presets->all());
        $user->forceFill(['preferences' => $preferences])->save();

        return redirect()->route('predictions.index', array_merge($filters, ['table_filter' => $id]))
            ->with('status', __('Tabellenfilter gespeichert.'));
    }

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

        $data = array_intersect_key($predictionData, array_flip([
            'heatmap',
            'qualifiedHeatmapCells',
            'heatmapSummary',
            'heatmapUniverseInstruments',
            'individualStats',
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
            'indices',
            'canUseSmartLabels',
            'smartLabels',
        ]));
        $data['indices'] = $this->activeIndexOptions();
        return view('predictions.heatmap', $data);
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
            'qualifiedHeatmapCells',
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
            'indices',
        ]));
        $data['indices'] = $this->activeIndexOptions();
        $data['setupMode'] = true;
        // A previous personal result must never become the data source for a
        // newly adjusted filter. Only keep a run active while its public ID is
        // explicitly present in the URL (directly after starting/opening it).
        $data['activeBacktestRun'] = $this->requestedUserBacktestRun($request);
        $data['savedFilters'] = $request->user()->savedPredictionFilters()->orderBy('name')->get();
        $data['canImportStrategy'] = app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro);
        $data['canSaveStrategy'] = app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Pro);
        $data['automationPortfolios'] = $request->user()->portfolios()
            ->where('type', 'paper')->where('active', true)->orderBy('name')->get();
        $data['savedFilterLimit'] = app(SavedFilterLimitService::class)->limitFor($request->user());
        $data['editingSavedFilter'] = $request->integer('saved_filter') > 0
            ? $request->user()->savedPredictionFilters()->whereKey($request->integer('saved_filter'))->first()
            : null;
        $data['hasPersonalQualityGate'] = $personalGate !== null;

        // Smart Selection statistics must describe the selected backtest universe,
        // never the latest live prediction snapshot.
        $run = $data['activeBacktestRun'];
        if ($run === null && $request->boolean('quality_setup')) {
            $run = DB::table('backtest_runs')
                ->whereIn('status', ['completed', 'completed_with_errors'])
                ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
                ->orderByDesc('finished_at')
                ->first();
            $data['activeBacktestRun'] = $run;
        }
        if ($run !== null && in_array((string) $run->status, ['completed', 'completed_with_errors'], true)) {
            // Keep Smart Selection inside the user's tariff universe as well.
            // Free users may evaluate only their regional 100-stock universe;
            // Plus and Pro users keep the complete available universe.
            $tariffInstrumentIds = app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Plus)
                ? null
                : app(FreeRegionalStockUniverseService::class)->instrumentIds($request->user());
            $latestQualityRankings = DB::table('model_quality_rankings')
                ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
                ->groupBy('trained_model_id');
            $latestFundamentalIds = DB::table('instrument_fundamentals')
                ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
                ->groupBy('instrument_id');
            $fundamentalValue = static fn (string $column, string $jsonKey): string =>
                "COALESCE(fundamental.{$column}, CASE WHEN NULLIF(fundamental.data::jsonb->>'{$jsonKey}', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'{$jsonKey}')::numeric END)";
            $minimumQualityTiers = [
                'top' => ['strong'],
                'strong' => ['strong'],
                'solid' => ['strong', 'solid'],
                'test' => ['strong', 'solid', 'test'],
            ];
            $backtestQuery = DB::table('backtest_trades as trade')
                ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
                ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
                ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'trade.trained_model_id')
                ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) => $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
                ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
                ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
                ->leftJoinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) => $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
                ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
                ->where('trade.backtest_run_id', $run->id)
                ->when($tariffInstrumentIds !== null, fn ($query) => $query->whereIn('instrument.id', $tariffInstrumentIds))
                ->when($request->filled('q'), fn ($query) => $query->where(function ($nested) use ($request): void {
                    $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                    $nested->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]);
                }))
                ->when($request->filled('country'), fn ($query) => $query->where('instrument.country', $request->query('country')))
                ->when($request->filled('exchange'), fn ($query) => $query->where('exchange.code', strtoupper((string) $request->query('exchange'))))
                ->when($request->filled('sector'), fn ($query) => $query->where('instrument.sector', $request->query('sector')))
                ->when(in_array((string) $request->query('quality_tier'), ['top', 'strong', 'solid', 'test'], true), fn ($query) => $query->whereIn('quality_tier.code', $minimumQualityTiers[(string) $request->query('quality_tier')]))
                ->when($request->query('quality_tier') === 'unqualified', fn ($query) => $query->whereNull('quality_tier.code'))
                ->when($request->filled('index'), function ($query) use ($request): void {
                    // Indexes are benchmark context, while backtest rows are
                    // stocks. Use the index's country as the universe scope
                    // instead of incorrectly requiring a traded index row.
                    $indexCountry = DB::table('instruments')
                        ->where('type', 'index')
                        ->where('symbol', (string) $request->query('index'))
                        ->value('country');
                    if (filled($indexCountry)) {
                        $query->where('instrument.country', strtoupper((string) $indexCountry));
                    }
                })
                ->when(in_array($request->query('ai_type'), ['horizon', 'pulse'], true), fn ($query) => $query->where('trade.ai_type', $request->query('ai_type')))
                ->when($this->requestedModelIds($request) !== [], fn ($query) => $query->whereIn('trade.model_definition_id', $this->requestedModelIds($request)))
                ->when(in_array(strtoupper((string) $request->query('signal')), ['BUY', 'SELL'], true), fn ($query) => $query->where('trade.signal', strtoupper((string) $request->query('signal'))))
                ->when(is_numeric($request->query('score_min')), fn ($query) => $query->where('trade.ki_score', '>=', (float) $request->query('score_min')))
                ->when(is_numeric($request->query('confidence_min')), fn ($query) => $query->where('trade.confidence', '>=', (float) $request->query('confidence_min')))
                ->when(is_numeric($request->query('drawdown_max')) && (float) $request->query('drawdown_max') < 50, fn ($query) => $query->whereRaw('ABS(trade.max_drawdown) * 100 <= ?', [(float) $request->query('drawdown_max')]))
                ->when(is_numeric($request->query('pe_max')) && (float) $request->query('pe_max') < 100, fn ($query) => $query->whereRaw($fundamentalValue('trailing_pe', 'trailingPE').' <= ?', [(float) $request->query('pe_max')]))
                ->when(is_numeric($request->query('dividend_yield_min')) && (float) $request->query('dividend_yield_min') > 0, fn ($query) => $query->whereRaw($fundamentalValue('dividend_yield', 'dividendYield').' >= ?', [(float) $request->query('dividend_yield_min') / 100]))
                ->when(is_numeric($request->query('market_cap_min')) && (float) $request->query('market_cap_min') > 0, fn ($query) => $query->whereRaw($fundamentalValue('market_cap', 'marketCap').' >= ?', [(float) $request->query('market_cap_min') * 1_000_000_000]))
                ->when(is_numeric($request->query('revenue_growth_min')) && (float) $request->query('revenue_growth_min') > -50, fn ($query) => $query->whereRaw($fundamentalValue('revenue_growth', 'revenueGrowth').' >= ?', [(float) $request->query('revenue_growth_min') / 100]))
                ->get([
                    'trade.net_return', 'trade.max_drawdown', 'trade.entry_date',
                    'trade.ki_score', 'trade.confidence', 'trade.predicted_return', 'trade.signal',
                    'instrument.id as instrument_id',
                    'instrument.symbol', 'instrument.name',
                    'instrument.type', 'instrument.country', 'instrument.sector',
                    'exchange.code as exchange_code',
                ]);
            $backtestRows = $backtestQuery;
            // Candidates reflect the tariff and all selected dropdown/range
            // filters. Qualification may reduce this number further through
            // the per-stock performance requirements below.
            $candidateInstrumentCount = $backtestRows->pluck('instrument_id')->filter()->unique()->count();
            $profitMinimum = is_numeric($request->query('profit_per_trade_min')) ? (float) $request->query('profit_per_trade_min') : 0.0;
            $hitMinimum = is_numeric($request->query('hit_rate_min')) ? (float) $request->query('hit_rate_min') : 0.0;
            $minimumTrades = is_numeric($request->query('minimum_trades')) ? max(0, (int) $request->query('minimum_trades')) : 0;
            if ($profitMinimum > 0 || $hitMinimum > 0 || $minimumTrades > 0) {
                $eligibleInstruments = $backtestRows->groupBy('instrument_id')->filter(function ($instrumentRows) use ($profitMinimum, $hitMinimum, $minimumTrades): bool {
                    $winsForInstrument = $instrumentRows->filter(fn (object $row): bool => (float) $row->net_return > 0);
                    $lossesForInstrument = $instrumentRows->filter(fn (object $row): bool => (float) $row->net_return < 0);
                    $profitFactor = (float) $lossesForInstrument->sum('net_return') !== 0.0
                        ? (float) $winsForInstrument->sum('net_return') / abs((float) $lossesForInstrument->sum('net_return'))
                        : 0.0;
                    $hitRate = $instrumentRows->count() > 0 ? ($winsForInstrument->count() / $instrumentRows->count()) * 100 : 0.0;
                    return $instrumentRows->count() >= $minimumTrades
                        && $profitFactor >= $profitMinimum
                        && $hitRate >= $hitMinimum;
                })->keys();
                $backtestRows = $backtestRows->whereIn('instrument_id', $eligibleInstruments->all())->values();
            }
            $data['individualStats'] = $backtestRows->groupBy('instrument_id')->map(function ($rows): array {
                $wins = $rows->filter(fn (object $row): bool => (float) $row->net_return > 0);
                $losses = $rows->filter(fn (object $row): bool => (float) $row->net_return < 0);
                $grossProfit = (float) $wins->sum('net_return');
                $grossLoss = abs((float) $losses->sum('net_return'));
                $first = $rows->first();
                return [
                    'symbol' => (string) ($first->symbol ?? '—'),
                    'name' => (string) ($first->name ?? ''),
                    'signal' => (string) ($first->signal ?? '—'),
                    'trades' => $rows->count(),
                    'hit_rate' => $rows->count() > 0 ? ($wins->count() / $rows->count()) * 100 : 0,
                    'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : null,
                    'drawdown' => (float) $rows->max(fn (object $row): float => abs((float) $row->max_drawdown)) * 100,
                    'average_return' => (float) $rows->avg('net_return') * 100,
                    'score' => (float) ($rows->avg('ki_score') ?? 0),
                    'confidence' => (float) ($rows->avg('confidence') ?? 0),
                ];
            })->sortByDesc('trades')->take(200)->values();
            $wins = $backtestRows->filter(fn (object $row): bool => (float) $row->net_return > 0)->count();
            $grossProfit = (float) $backtestRows->filter(fn (object $row): bool => (float) $row->net_return > 0)->sum('net_return');
            $grossLoss = abs((float) $backtestRows->filter(fn (object $row): bool => (float) $row->net_return < 0)->sum('net_return'));
            $returns = $backtestRows->pluck('net_return')->map(fn ($value): float => (float) $value)->values();
            $meanReturn = $returns->avg() ?? 0.0;
            $variance = $returns->count() > 1 ? $returns->map(fn (float $value): float => ($value - $meanReturn) ** 2)->sum() / ($returns->count() - 1) : 0.0;
            $dates = $backtestRows->pluck('entry_date')->filter()->map(fn ($date): int => strtotime((string) $date))->filter()->values();
            $months = $dates->count() > 1 ? max(1.0, ($dates->max() - $dates->min()) / (86400 * 30.4375)) : 1.0;
            $data['marketContextCounts'] = [
                // The backtest trades are equities; their market benchmark is
                // represented as one index context (DAX/S&P depending on the
                // configured market), not as a traded equity row.
                // Backtest trades are stock trades; the index dimension is the
                // available benchmark universe, not a traded row.  Count the
                // selected index (or all active indexes when no selection is
                // made) so the German "Indizes" card never appears empty.
                'indices' => $backtestRows->isEmpty()
                    ? 0
                    : ($request->filled('index') ? 1 : $this->activeIndexOptions()->count()),
                'exchanges' => $backtestRows->pluck('exchange_code')->filter()->unique()->count(),
                'sectors' => $backtestRows->pluck('sector')->filter()->unique()->count(),
                'countries' => $backtestRows->pluck('country')->filter()->unique()->count(),
            ];
            $data['heatmapSummary'] = (object) [
                'instruments' => $backtestRows->pluck('instrument_id')->filter()->unique()->count(),
                'candidates' => $candidateInstrumentCount,
                'qualified' => $backtestRows->pluck('instrument_id')->filter()->unique()->count(),
                'trades' => $backtestRows->count(),
                'winning_trades' => $wins,
                'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : 0,
                'hit_rate' => $backtestRows->count() > 0 ? ($wins / $backtestRows->count()) * 100 : 0,
                'drawdown' => (float) $backtestRows->max(fn (object $row): float => abs((float) $row->max_drawdown)) * 100,
                'volatility' => sqrt($variance * 252) * 100,
                'trades_per_month' => $backtestRows->count() / $months,
            ];
        }

        // Complete automatic trainings are persisted in the walk-forward
        // tables.  The strategy setup previously only looked for a user
        // backtest_run, so a finished US batch appeared as an empty setup.
        if ($run === null && DB::getSchemaBuilder()->hasTable('walk_forward_backtest_trades')) {
            $walkForwardRunId = DB::table('walk_forward_backtest_runs')
                ->where('status', 'completed')
                ->where('horizon_days', 20)
                ->orderByRaw('(SELECT COUNT(DISTINCT candidate_trade.instrument_id) FROM walk_forward_backtest_trades AS candidate_trade WHERE candidate_trade.run_id = walk_forward_backtest_runs.id) DESC')
                ->orderByDesc('finished_at')
                ->value('id');

            if ($walkForwardRunId) {
                $latestFundamentalIds = DB::table('instrument_fundamentals')
                    ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
                    ->groupBy('instrument_id');
                $fundamentalValue = static fn (string $column, string $jsonKey): string =>
                    "COALESCE(fundamental.{$column}, CASE WHEN NULLIF(fundamental.data::jsonb->>'{$jsonKey}', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'{$jsonKey}')::numeric END)";
                $walkForwardRows = DB::table('walk_forward_backtest_trades as trade')
                    ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
                    ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
                    ->leftJoinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) => $join->on('latest_fundamental.instrument_id', '=', 'instrument.id'))
                    ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
                    ->where('trade.run_id', $walkForwardRunId)
                    ->when($request->filled('q'), function ($query) use ($request): void {
                        $term = '%'.strtolower(trim((string) $request->query('q'))).'%';
                        $query->where(fn ($nested) => $nested
                            ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
                    })
                    ->when($request->filled('country'), fn ($query) => $query->where('instrument.country', strtoupper((string) $request->query('country'))))
                    ->when($request->filled('exchange'), fn ($query) => $query->where('exchange.code', strtoupper((string) $request->query('exchange'))))
                    ->when($request->filled('sector'), fn ($query) => $query->where('instrument.sector', (string) $request->query('sector')))
                    ->when(is_numeric($request->query('pe_max')) && (float) $request->query('pe_max') < 100, fn ($query) => $query->whereRaw($fundamentalValue('trailing_pe', 'trailingPE').' <= ?', [(float) $request->query('pe_max')]))
                    ->when(is_numeric($request->query('dividend_yield_min')) && (float) $request->query('dividend_yield_min') > 0, fn ($query) => $query->whereRaw($fundamentalValue('dividend_yield', 'dividendYield').' >= ?', [(float) $request->query('dividend_yield_min') / 100]))
                    ->when(is_numeric($request->query('market_cap_min')) && (float) $request->query('market_cap_min') > 0, fn ($query) => $query->whereRaw($fundamentalValue('market_cap', 'marketCap').' >= ?', [(float) $request->query('market_cap_min') * 1_000_000_000]))
                    ->when(is_numeric($request->query('revenue_growth_min')) && (float) $request->query('revenue_growth_min') > -50, fn ($query) => $query->whereRaw($fundamentalValue('revenue_growth', 'revenueGrowth').' >= ?', [(float) $request->query('revenue_growth_min') / 100]))
                    ->get([
                        'trade.instrument_id', 'trade.signal_date', 'trade.exit_date', 'trade.net_return',
                        'instrument.symbol', 'instrument.name', 'instrument.country', 'instrument.sector',
                        'exchange.code as exchange_code',
                    ]);

                $walkForwardInstrumentIds = $walkForwardRows->pluck('instrument_id')->unique()->values();
                $latestPredictions = $walkForwardInstrumentIds->isEmpty() ? collect() : DB::table('predictions as prediction')
                    ->whereIn('prediction.instrument_id', $walkForwardInstrumentIds)
                    ->whereRaw('prediction.id = (
                        SELECT latest_prediction.id FROM predictions AS latest_prediction
                        WHERE latest_prediction.instrument_id = prediction.instrument_id
                        ORDER BY latest_prediction.prediction_time DESC NULLS LAST, latest_prediction.id DESC
                        LIMIT 1
                    )')
                    ->get(['prediction.instrument_id', 'prediction.ai_score', 'prediction.prediction_score', 'prediction.confidence', 'prediction.confidence_score'])
                    ->keyBy('instrument_id');
                $latestTechnicalIds = DB::table('technical_indicators')
                    ->where('interval', '1d')
                    ->whereIn('instrument_id', $walkForwardInstrumentIds)
                    ->groupBy('instrument_id')
                    ->selectRaw('instrument_id, MAX(id) AS technical_id');
                $latestVolatilities = $walkForwardInstrumentIds->isEmpty() ? collect() : DB::table('technical_indicators as technical')
                    ->joinSub($latestTechnicalIds, 'latest_technical', fn ($join) => $join->on('latest_technical.technical_id', '=', 'technical.id'))
                    ->pluck('technical.volatility_20', 'technical.instrument_id');

                $instrumentStats = $walkForwardRows->groupBy('instrument_id')->map(function ($rows, $instrumentId) use ($latestPredictions, $latestVolatilities): array {
                    $trades = $rows->count();
                    $wins = $rows->filter(fn (object $row): bool => (float) $row->net_return > 0)->count();
                    $grossProfit = (float) $rows->filter(fn (object $row): bool => (float) $row->net_return > 0)->sum('net_return');
                    $grossLoss = abs((float) $rows->filter(fn (object $row): bool => (float) $row->net_return < 0)->sum('net_return'));
                    $equity = 1.0;
                    $peak = 1.0;
                    $maximumDrawdown = 0.0;
                    $positionOpenUntil = null;
                    foreach ($rows->sortBy('signal_date') as $row) {
                        // The walk-forward run emits a signal on every trading
                        // day. For a fixed 20-day strategy, only the next signal
                        // after the previous exit represents a new position.
                        // Compounding overlapping signals as separate full
                        // positions incorrectly drove drawdown to nearly 100%.
                        if ($positionOpenUntil !== null && (string) $row->signal_date <= $positionOpenUntil) {
                            continue;
                        }
                        $equity *= max(0.0, 1.0 + (float) $row->net_return);
                        $peak = max($peak, $equity);
                        $maximumDrawdown = max($maximumDrawdown, $peak > 0 ? ($peak - $equity) / $peak : 0.0);
                        $positionOpenUntil = (string) ($row->exit_date ?? $row->signal_date);
                    }
                    $first = $rows->first();
                    $prediction = $latestPredictions->get((int) $instrumentId);
                    $rawScore = is_numeric($prediction?->ai_score)
                        ? (float) $prediction->ai_score
                        : (is_numeric($prediction?->prediction_score) ? (float) $prediction->prediction_score : 0.0);
                    $score = $rawScore <= 1 ? $rawScore * 10 : ($rawScore <= 10 ? $rawScore : $rawScore / 10);
                    $rawConfidence = is_numeric($prediction?->confidence_score)
                        ? (float) $prediction->confidence_score
                        : (is_numeric($prediction?->confidence) ? (float) $prediction->confidence : 0.0);
                    $confidence = $rawConfidence <= 1 ? $rawConfidence * 100 : $rawConfidence;
                    $rawVolatility = $latestVolatilities->get((int) $instrumentId);
                    $volatility = is_numeric($rawVolatility) ? (float) $rawVolatility * 100 : 0.0;

                    return [
                        'symbol' => (string) $first->symbol,
                        'name' => (string) $first->name,
                        'signal' => '—',
                        'trades' => $trades,
                        'hit_rate' => $trades > 0 ? ($wins / $trades) * 100 : 0.0,
                        'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : 0.0,
                        'drawdown' => $maximumDrawdown * 100,
                        'average_return' => (float) $rows->avg('net_return') * 100,
                        'volatility' => max(0.0, $volatility),
                        'score' => max(0.0, min(10.0, $score)),
                        'confidence' => max(0.0, min(100.0, $confidence)),
                        'country' => (string) $first->country,
                        'sector' => (string) ($first->sector ?? ''),
                        'exchange_code' => (string) ($first->exchange_code ?? ''),
                    ];
                })->values();

                $minimumTrades = is_numeric($request->query('minimum_trades')) ? max(0, (int) $request->query('minimum_trades')) : 0;
                $profitMinimum = is_numeric($request->query('profit_per_trade_min')) ? (float) $request->query('profit_per_trade_min') : 0.0;
                $hitMinimum = is_numeric($request->query('hit_rate_min')) ? (float) $request->query('hit_rate_min') : 0.0;
                $scoreMinimum = is_numeric($request->query('score_min')) ? max(0.0, min(10.0, (float) $request->query('score_min'))) : 0.0;
                $confidenceMinimum = is_numeric($request->query('confidence_min')) ? max(0.0, min(100.0, (float) $request->query('confidence_min'))) : 0.0;
                $drawdownMaximum = is_numeric($request->query('drawdown_max')) ? max(0.0, (float) $request->query('drawdown_max')) : null;
                $volatilityMaximum = is_numeric($request->query('volatility_max')) ? max(0.0, (float) $request->query('volatility_max')) : null;
                $drawdownRangeMaximum = (float) data_get($data, 'rangeMaxima.drawdown', 50);
                $volatilityRangeMaximum = (float) data_get($data, 'rangeMaxima.volatility', 100);
                $allInstrumentStats = $instrumentStats;
                $instrumentStats = $instrumentStats->filter(function (array $stat) use (
                    $minimumTrades, $profitMinimum, $hitMinimum, $scoreMinimum, $confidenceMinimum,
                    $drawdownMaximum, $volatilityMaximum, $drawdownRangeMaximum, $volatilityRangeMaximum
                ): bool {
                    if ($stat['trades'] < $minimumTrades
                        || $stat['profit_factor'] < $profitMinimum
                        || $stat['hit_rate'] < $hitMinimum
                        || $stat['score'] < $scoreMinimum
                        || $stat['confidence'] < $confidenceMinimum) {
                        return false;
                    }
                    if ($drawdownMaximum !== null && $drawdownMaximum < $drawdownRangeMaximum && $stat['drawdown'] > $drawdownMaximum) {
                        return false;
                    }

                    return ! ($volatilityMaximum !== null
                        && $volatilityMaximum < $volatilityRangeMaximum
                        && $stat['volatility'] > $volatilityMaximum);
                })->values();

                $totalTrades = (int) $instrumentStats->sum('trades');
                $totalWins = (int) $instrumentStats->sum(fn (array $stat): int => (int) round($stat['trades'] * $stat['hit_rate'] / 100));
                $weightedAverage = fn (string $field): float => $totalTrades > 0
                    ? (float) $instrumentStats->sum(fn (array $stat): float => $stat[$field] * $stat['trades']) / $totalTrades
                    : 0.0;

                $data['individualStats'] = $instrumentStats->sortByDesc('trades')->take(200)->values();
                $data['qualifiedHeatmapCells'] = $instrumentStats
                    ->mapWithKeys(fn (array $stat): array => [
                        min(9, max(0, (int) floor($stat['score']))).'-'.min(9, max(0, (int) floor($stat['confidence'] / 10))) => true,
                    ]);
                $data['heatmap'] = $allInstrumentStats
                    ->groupBy(fn (array $stat): string => min(9, max(0, (int) floor($stat['score']))).'-'.min(9, max(0, (int) floor($stat['confidence'] / 10))))
                    ->map(function ($stats): object {
                        $trades = (int) $stats->sum('trades');
                        $weighted = fn (string $field): float => $trades > 0
                            ? (float) $stats->sum(fn (array $stat): float => $stat[$field] * $stat['trades']) / $trades
                            : 0.0;

                        return (object) [
                            'samples' => $trades,
                            'trades_per_month' => $trades / 36,
                            'hit_rate' => $weighted('hit_rate'),
                            'average_return' => $weighted('average_return'),
                            'profit_factor' => $weighted('profit_factor'),
                            'drawdown' => (float) ($stats->max('drawdown') ?? 0),
                            'volatility' => $weighted('volatility'),
                        ];
                    });
                $data['marketContextCounts'] = [
                    'indices' => $instrumentStats->isEmpty() ? 0 : ($request->filled('index') ? 1 : $this->activeIndexOptions()->count()),
                    'exchanges' => $instrumentStats->pluck('exchange_code')->filter()->unique()->count(),
                    'sectors' => $instrumentStats->pluck('sector')->filter()->unique()->count(),
                    'countries' => $instrumentStats->pluck('country')->filter()->unique()->count(),
                ];
                $data['heatmapSummary'] = (object) [
                    'instruments' => $instrumentStats->count(),
                    'candidates' => $instrumentStats->count(),
                    'qualified' => $instrumentStats->count(),
                    'trades' => $totalTrades,
                    'winning_trades' => $totalWins,
                    'profit_factor' => $weightedAverage('profit_factor'),
                    'hit_rate' => $totalTrades > 0 ? ($totalWins / $totalTrades) * 100 : 0.0,
                    'drawdown' => (float) ($instrumentStats->max('drawdown') ?? 0),
                    'volatility' => $weightedAverage('volatility'),
                    'trades_per_month' => $totalTrades / 36,
                ];
            }
        }

        return view('predictions.heatmap', $data);
    }

    public function qualitySetup(Request $request): View
    {
        if ($request->boolean('reset')) {
            $request->session()->forget('setup_filter_state');
            $request->replace(['reset' => '1']);
        }
        $request->merge(['quality_setup' => '1']);
        $view = $this->filterSetup($request);
        $data = $view->getData();
        $data['qualitySetupMode'] = true;
        $data['canSaveSmartLabel'] = app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Plus);
        $data['marketContextCounts'] ??= [
            'indices' => 0,
            'exchanges' => 0,
            'sectors' => 0,
            'countries' => 0,
        ];
        // Always provide the canonical index list.  Previously this variable
        // was missing on the setup view, leaving the German "Indizes" select
        // and universe card empty even though index instruments existed.
        $data['indices'] = $this->activeIndexOptions();
        if ($data['activeBacktestRun'] === null) {
            $data['heatmapSummary'] = (object) [
                'instruments' => 0, 'candidates' => 0, 'qualified' => 0, 'trades' => 0, 'winning_trades' => 0,
                'profit_factor' => 0, 'hit_rate' => 0, 'drawdown' => 0, 'volatility' => 0,
            ];
        }

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
            'signal' => ['nullable', 'in:BUY,WAIT,WATCH,HOLD,SELL'],
            'score_min' => ['nullable', 'numeric', 'between:0,10'],
            'confidence_min' => ['nullable', 'numeric', 'between:0,100'],
            'drawdown_max' => ['nullable', 'numeric', 'between:0,100'],
            'profit_per_trade_min' => ['nullable', 'numeric', 'between:0,10'],
            'volatility_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'pe_max' => ['nullable', 'numeric', 'between:0,1000000'],
            'dividend_yield_min' => ['nullable', 'numeric', 'between:0,1000000'],
            'market_cap_min' => ['nullable', 'numeric', 'between:0,1000000000'],
            'revenue_growth_min' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'hit_rate_min' => ['nullable', 'numeric', 'between:0,100'],
            'risk_max' => ['nullable', 'numeric', 'between:0,100'],
            'predicted_return_min' => ['nullable', 'numeric', 'between:-50,100'],
            'minimum_trades' => ['nullable', 'integer', 'between:0,10000'],
            'positive_prediction_required' => ['nullable', 'boolean'],
            'ensemble_veto_required' => ['nullable', 'boolean'],
            'gate_mode' => ['nullable', 'in:system,personal'],
            'quality_setup' => ['nullable', 'boolean'],
            'sector_score_rotation' => ['nullable', 'boolean'],
            'index_score_rotation' => ['nullable', 'boolean'],
            'forecast_score_rotation_5d_enabled' => ['nullable', 'boolean'],
            'entry_strategy' => ['nullable', 'in:direct_buy,wait_5d,forecast_score_rotation_5d'],
            'entry_risk_style' => ['nullable', 'in:conservative,balanced,chance'],
            'automatic_strategy_comparison' => ['nullable', 'boolean'],
            'strategy_priority' => ['nullable', 'required_if:forecast_score_rotation_5d_enabled,1', 'in:rotation_first,exit_first'],
            'automatic_optimization' => ['nullable', 'boolean'],
            'optimization_goal' => ['nullable', 'in:reduce_drawdown,fewer_trades,maximize_performance'],
            'exit_strategy' => ['nullable', 'in:fixed_20d,signal_change,buy_and_hold'],
            'fixed_20d_exit_enabled' => ['nullable', 'boolean'],
            'dynamic_horizon_exit_enabled' => ['nullable', 'boolean'],
            'support_stop_enabled' => ['nullable', 'boolean'],
            'resistance_trailing_stop_enabled' => ['nullable', 'boolean'],
            'entry_wait_5d_enabled' => ['nullable', 'boolean'],
            'signal_change_exit_enabled' => ['nullable', 'boolean'],
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
        $filters['minimum_trades'] = max(1, (int) ($filters['minimum_trades'] ?? 1));
        $filters['entry_strategy'] = in_array($filters['entry_strategy'] ?? null, ['direct_buy', 'wait_5d', 'forecast_score_rotation_5d'], true)
            ? $filters['entry_strategy']
            : 'direct_buy';
        $filters['entry_risk_style'] = in_array($filters['entry_risk_style'] ?? null, ['conservative', 'balanced', 'chance'], true)
            ? $filters['entry_risk_style']
            : 'balanced';
        $filters['automatic_strategy_comparison'] = $request->boolean('automatic_strategy_comparison') ? 1 : 0;
        $filters['forecast_score_rotation_5d_enabled'] = $filters['entry_strategy'] === 'forecast_score_rotation_5d' ? 1 : 0;
        $filters['entry_wait_5d_enabled'] = $filters['entry_strategy'] === 'wait_5d' ? 1 : 0;
        $filters['signal_change_exit_enabled'] = ($filters['exit_strategy'] ?? 'fixed_20d') === 'signal_change' ? 1 : 0;
        $maxPositions = (int) $filters['max_positions'];
        $positionFactor = ($filters['exit_strategy'] ?? null) === 'buy_and_hold' ? 1 : (int) $filters['position_factor'];
        $filters['position_factor'] = $positionFactor;
        foreach (['fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled', 'support_stop_enabled', 'resistance_trailing_stop_enabled'] as $disabledExitRule) {
            $filters[$disabledExitRule] = 0;
        }
        $filters['automatic_optimization'] = 0;
        unset($filters['optimization_goal']);
        $tradeCost = (float) $filters['trade_cost'];
        DB::table('backtest_runs')
            ->whereIn('status', ['queued', 'running'])
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->where('updated_at', '<', now()->subMinutes(25))
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => __('Der Backtest-Worker wurde unerwartet beendet. Bitte starte den Strategietest erneut.'),
                'updated_at' => now(),
            ]);
        $activeRun = DB::table('backtest_runs')
            ->whereIn('status', ['queued', 'running'])
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->orderByDesc('id')
            ->first();
        if ($activeRun !== null) {
            return redirect()->route($returnRoute, array_merge($filters, ['backtest_run' => $activeRun->public_id, 'show_result' => 1]))
                ->with('status', __('Ein Backtest läuft bereits.'));
        }
        // Always refresh the legacy-shaped source from the best completed
        // 20-day walk-forward run first. Otherwise an older materialized run
        // remains the newest system backtest forever and silently caps the
        // strategy universe at its historical instrument count.
        $sourceRun = $this->materializeWalkForwardSourceRun();
        if ($sourceRun === null) {
            $sourceRun = DB::table('backtest_runs')
                ->whereIn('status', ['completed', 'completed_with_errors'])
                ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
                ->where('trades_count', '>', 0)
                ->orderByDesc('id')
                ->first();
        }
        abort_if($sourceRun === null, 422, __('Es ist kein abgeschlossener Drei-Jahres-Ausgangslauf vorhanden.'));

        $publicId = (string) Str::uuid();
        $settings = [
            'run_type' => 'user_filter',
            'initiated_by_user_id' => $request->user()->id,
            'source_run_id' => $sourceRun->id,
            'lookback_years' => 3,
            'entry' => match ($filters['entry_strategy']) {
                'wait_5d' => 'WAIT up to 5 trading days, then BUY at current price',
                'forecast_score_rotation_5d' => 'forecast score entry selection every 5 trading days',
                default => 'BUY signal with positive predicted return',
            },
            'exit' => match ($filters['exit_strategy'] ?? 'fixed_20d') {
                'signal_change' => 'close on signal change',
                'buy_and_hold' => 'buy and hold',
                default => 'close after 20 trading days',
            },
            'selection_filters' => $filters,
            'selection_metrics' => [
                'drawdown' => 'maximum per instrument over source period',
                'profile' => $filters['entry_risk_style'],
                'conservative' => 'drawdown asc, hit rate desc, profit factor desc',
                'balanced' => 'hit rate desc, profit factor desc, drawdown asc',
                'chance' => 'profit factor desc, hit rate desc, drawdown asc',
                'profit_per_trade' => 'average net return per trade over source period',
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
        if (! empty($filters['automatic_optimization'])) {
            $riskLevel = app(PersonalizedSignalService::class)->riskLevel($request->user());
            $settings['optimization'] = [
                'mode' => 'automatic_multi_horizon',
                'goal' => $filters['optimization_goal'] ?? 'maximize_performance',
                'risk_profile' => $riskLevel,
                'horizon_weights' => match ($riskLevel) {
                    'cautious' => [5 => 10, 10 => 20, 15 => 30, 20 => 40],
                    'opportunity_oriented' => [5 => 35, 10 => 30, 15 => 20, 20 => 15],
                    default => [5 => 20, 10 => 25, 15 => 25, 20 => 30],
                },
                'variants_checked' => 1_778_112,
            ];
        }
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

        return redirect()->route($returnRoute, array_merge($filters, ['backtest_run' => $publicId, 'show_result' => 1]))
            ->with('status', __('Der Drei-Jahres-Backtest wurde gestartet.'));
    }

    public function optimizeFilter(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'optimization_goal' => ['required', 'in:reduce_drawdown,fewer_trades,maximize_performance'],
            'trade_capacity' => ['required', 'integer', 'in:5,10,20'],
            'initial_capital' => ['required', 'numeric', 'min:1000', 'max:1000000'],
            'trade_cost' => ['required', 'numeric', 'min:0', 'max:1000'],
            'country' => ['nullable', 'string', 'max:8'],
            'exchange' => ['nullable', 'string', 'max:32'],
            'sector' => ['nullable', 'string', 'max:120'],
        ]);
        $signalService = app(PersonalizedSignalService::class);
        $riskProfile = $signalService->riskLevel($request->user());
        $horizonWeights = match ($riskProfile) {
            'cautious' => [5 => 0.10, 10 => 0.20, 15 => 0.30, 20 => 0.40],
            'opportunity_oriented' => [5 => 0.35, 10 => 0.30, 15 => 0.20, 20 => 0.15],
            default => [5 => 0.20, 10 => 0.25, 15 => 0.25, 20 => 0.30],
        };
        $tradeCapacity = (int) $validated['trade_capacity'];
        $initialCapital = (float) $validated['initial_capital'];
        $tradeCost = (float) $validated['trade_cost'];
        $capitalPerPosition = $initialCapital / max(1, $tradeCapacity);
        $roundTripCostRate = $capitalPerPosition > 0 ? ($tradeCost * 2) / $capitalPerPosition : 0.0;
        $runIds = DB::table('walk_forward_backtest_runs')
            ->where('status', 'completed')->whereIn('horizon_days', array_keys($horizonWeights))
            ->orderByDesc('finished_at')->get(['id', 'horizon_days'])
            ->unique('horizon_days')->pluck('id', 'horizon_days');
        $missingHorizons = collect(array_keys($horizonWeights))->diff($runIds->keys()->map(fn ($horizon) => (int) $horizon));
        abort_if($missingHorizons->isNotEmpty(), 422, __(
            'Für die automatische Optimierung fehlen abgeschlossene Walk-Forward-Tests für folgende Horizonte: :horizons Tage.',
            ['horizons' => $missingHorizons->implode(', ')],
        ));

        $rows = DB::table('walk_forward_backtest_trades as trade')
            ->join('walk_forward_backtest_runs as run', 'run.id', '=', 'trade.run_id')
            ->join('instruments as instrument', 'instrument.id', '=', 'trade.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->whereIn('trade.run_id', $runIds->values())
            ->when(filled($validated['country'] ?? null), fn ($query) => $query->where('instrument.country', strtoupper($validated['country'])))
            ->when(filled($validated['exchange'] ?? null), fn ($query) => $query->where('exchange.code', strtoupper($validated['exchange'])))
            ->when(filled($validated['sector'] ?? null), fn ($query) => $query->where('instrument.sector', $validated['sector']))
            ->get(['trade.instrument_id', 'trade.signal_date', 'trade.exit_date', 'trade.net_return', 'run.horizon_days']);
        abort_if($rows->isEmpty(), 422, __('Für dieses Universum sind keine Walk-Forward-Trades vorhanden.'));

        $instrumentIds = $rows->pluck('instrument_id')->unique()->values();
        $latestPredictions = DB::table('predictions as prediction')
            ->whereIn('prediction.instrument_id', $instrumentIds)
            ->whereRaw('prediction.id = (SELECT p.id FROM predictions p WHERE p.instrument_id=prediction.instrument_id ORDER BY p.prediction_time DESC NULLS LAST,p.id DESC LIMIT 1)')
            ->get(['prediction.instrument_id', 'prediction.ai_score', 'prediction.prediction_score', 'prediction.confidence', 'prediction.confidence_score'])
            ->keyBy('instrument_id');
        $latestTechnicalIds = DB::table('technical_indicators')->where('interval', '1d')
            ->whereIn('instrument_id', $instrumentIds)->groupBy('instrument_id')
            ->selectRaw('instrument_id, MAX(id) AS technical_id');
        $volatilities = DB::table('technical_indicators as technical')
            ->joinSub($latestTechnicalIds, 'latest', fn ($join) => $join->on('latest.technical_id', '=', 'technical.id'))
            ->pluck('technical.volatility_20', 'technical.instrument_id');

        $stats = $rows->groupBy('instrument_id')->map(function ($trades, $instrumentId) use ($latestPredictions, $volatilities, $horizonWeights, $roundTripCostRate): array {
            $horizonStats = $trades->groupBy('horizon_days')->map(function ($horizonTrades) use ($roundTripCostRate): array {
                $returns = $horizonTrades->map(fn ($trade): float => (float) $trade->net_return - $roundTripCostRate);
                $wins = $returns->filter(fn (float $return): bool => $return > 0);
                $losses = $returns->filter(fn (float $return): bool => $return < 0);
                $grossLoss = abs((float) $losses->sum());
                $equity = $peak = 1.0;
                $drawdown = 0.0;
                $openUntil = null;
                foreach ($horizonTrades->sortBy('signal_date') as $trade) {
                    if ($openUntil !== null && (string) $trade->signal_date <= $openUntil) continue;
                    $equity *= max(0.0, 1.0 + (float) $trade->net_return - $roundTripCostRate);
                    $peak = max($peak, $equity);
                    $drawdown = max($drawdown, $peak > 0 ? ($peak - $equity) / $peak : 0.0);
                    $openUntil = (string) ($trade->exit_date ?? $trade->signal_date);
                }

                return [
                    'trades' => $horizonTrades->count(),
                    'hit_rate' => $returns->isNotEmpty() ? $wins->count() / $returns->count() * 100 : 0,
                    'profit_factor' => $grossLoss > 0 ? (float) $wins->sum() / $grossLoss : 0,
                    'average_return' => (float) $returns->avg() * 100,
                    'drawdown' => $drawdown * 100,
                ];
            });
            $availableWeight = collect($horizonWeights)->sum(fn ($weight, $horizon) => $horizonStats->has($horizon) ? $weight : 0);
            $weighted = fn (string $key): float => $availableWeight > 0
                ? (float) collect($horizonWeights)->sum(fn ($weight, $horizon) => ($horizonStats->get($horizon)[$key] ?? 0) * $weight) / $availableWeight
                : 0.0;
            $positiveWeight = (float) collect($horizonWeights)->sum(fn ($weight, $horizon) => ($horizonStats->get($horizon)['average_return'] ?? -INF) > 0 ? $weight : 0);
            $returnValues = collect($horizonWeights)->map(fn ($weight, $horizon) => $horizonStats->get($horizon)['average_return'] ?? null)->filter(fn ($value) => $value !== null);
            $meanReturn = $returnValues->avg() ?? 0;
            $returnDispersion = sqrt((float) $returnValues->avg(fn ($value) => ($value - $meanReturn) ** 2));
            $prediction = $latestPredictions->get((int) $instrumentId);
            $rawScore = is_numeric($prediction?->ai_score) ? (float) $prediction->ai_score : (float) ($prediction?->prediction_score ?? 0);
            $rawConfidence = is_numeric($prediction?->confidence_score) ? (float) $prediction->confidence_score : (float) ($prediction?->confidence ?? 0);

            return [
                'instrument_id' => (int) $instrumentId,
                'trades' => (int) round($weighted('trades')),
                'hit_rate' => $weighted('hit_rate'),
                'profit_factor' => $weighted('profit_factor'),
                'average_return' => $weighted('average_return'),
                'drawdown' => $weighted('drawdown'),
                'consistency' => $availableWeight > 0 ? $positiveWeight / $availableWeight * 100 : 0,
                'return_dispersion' => $returnDispersion,
                'volatility' => max(0, (float) ($volatilities->get((int) $instrumentId) ?? 0) * 100),
                'score' => max(0, min(10, $rawScore <= 1 ? $rawScore * 10 : ($rawScore <= 10 ? $rawScore : $rawScore / 10))),
                'confidence' => max(0, min(100, $rawConfidence <= 1 ? $rawConfidence * 100 : $rawConfidence)),
            ];
        })->filter(fn (array $stat): bool => $stat['average_return'] > 0)->values();
        abort_if($stats->count() < 5, 422, __('Nach Ausschluss negativer Modelle bleiben zu wenige robuste Aktien für eine Optimierung übrig.'));

        $profileWeights = match ($riskProfile) {
            'cautious' => ['performance' => 0.75, 'drawdown' => 1.65, 'quality' => 1.15],
            'opportunity_oriented' => ['performance' => 1.35, 'drawdown' => 0.65, 'quality' => 0.90],
            default => ['performance' => 1.00, 'drawdown' => 1.00, 'quality' => 1.00],
        };
        $profileLimits = match ($riskProfile) {
            'cautious' => ['drawdown' => 25, 'volatility' => 45, 'consistency' => 75, 'confidence' => 65, 'trades' => 20],
            'opportunity_oriented' => ['drawdown' => 50, 'volatility' => 100, 'consistency' => 25, 'confidence' => 45, 'trades' => 10],
            default => ['drawdown' => 40, 'volatility' => 65, 'consistency' => 50, 'confidence' => 55, 'trades' => 15],
        };
        $stats = $stats->filter(fn (array $stat): bool =>
            $stat['drawdown'] <= $profileLimits['drawdown']
            && $stat['volatility'] <= $profileLimits['volatility']
            && $stat['consistency'] >= $profileLimits['consistency']
            && $stat['confidence'] >= $profileLimits['confidence']
            && $stat['trades'] >= $profileLimits['trades']
        )->values();
        abort_if($stats->count() < 5, 422, __('Nach Anwendung deines Risikoprofils bleiben zu wenige Aktien für eine belastbare Optimierung übrig.'));

        $options = [
            'score_min' => collect([0, 3.5, 4, 4.5, 5, 5.5, 6, 6.5, 7]),
            'confidence_min' => collect([0, 40, 50, 60, 70, 80, 90]),
            'drawdown_max' => collect([10, 15, 20, 25, 30, 35, 40, 50]),
            'profit_factor_min' => collect([0, 1, 1.1, 1.2, 1.3, 1.5, 1.8, 2]),
            'volatility_max' => collect([20, 25, 30, 35, 40, 50, 60, 75, 100]),
            'hit_rate_min' => collect([0, 50, 52.5, 55, 57.5, 60, 65]),
            'minimum_trades' => collect([0, 25, 50, 100, 150, 200, 250]),
        ];
        $state = ['score_min' => 0, 'confidence_min' => 0, 'drawdown_max' => 50, 'profit_factor_min' => 0, 'volatility_max' => 100, 'hit_rate_min' => 0, 'minimum_trades' => 0];
        $evaluate = function ($selected) use ($validated, $profileWeights, $tradeCapacity): array {
            $trades = (int) $selected->sum('trades');
            if ($selected->count() < 5 || $trades < 100) return ['value' => -INF, 'stocks' => 0, 'trades' => 0];
            $weighted = fn (string $key): float => (float) $selected->sum(fn ($stat) => $stat[$key] * $stat['trades']) / $trades;
            $hit = $weighted('hit_rate');
            $profitFactor = $weighted('profit_factor');
            $performance = $weighted('average_return');
            $drawdown = $weighted('drawdown');
            $consistency = $weighted('consistency');
            $returnDispersion = $weighted('return_dispersion');
            $consistencyAdjustment = ($consistency / 25) - ($returnDispersion / 5);
            $capacityOverflow = max(0, $selected->count() - $tradeCapacity);
            $value = match ($validated['optimization_goal']) {
                'reduce_drawdown' => -($drawdown * 3 * $profileWeights['drawdown'])
                    + (($profitFactor * 3) + ($hit / 20) + $consistencyAdjustment) * $profileWeights['quality']
                    - $capacityOverflow,
                'fewer_trades' => ($profitFactor < 1 || $hit < 50)
                    ? -INF
                    : -($selected->count() * 100) - ($trades / 100)
                        + ($performance * 10 * $profileWeights['performance'])
                        + (($profitFactor * 2) + $consistencyAdjustment) * $profileWeights['quality'],
                default => ($performance * 100 * $profileWeights['performance'])
                    + (($profitFactor * 10) + ($hit / 5) + ($consistency / 5)) * $profileWeights['quality']
                    - ($drawdown * 0.15 * $profileWeights['drawdown']) - ($capacityOverflow * 0.10),
            };
            return compact('value', 'trades', 'hit', 'profitFactor', 'performance', 'drawdown', 'consistency') + ['stocks' => $selected->count()];
        };
        $bestMetrics = ['value' => -INF, 'stocks' => 0, 'trades' => 0];
        $keys = array_keys($options);
        $variantsChecked = 0;
        $metricsCache = [];
        $search = function (int $depth, $selected, array $candidate) use (&$search, &$state, &$bestMetrics, &$variantsChecked, &$metricsCache, $keys, $options, $evaluate): void {
            if ($depth === count($keys)) {
                $variantsChecked++;
                $selectionKey = $selected->pluck('instrument_id')->sort()->implode(',');
                $metrics = $metricsCache[$selectionKey] ??= $evaluate($selected);
                if ($metrics['value'] > $bestMetrics['value']) {
                    $state = $candidate;
                    $bestMetrics = $metrics;
                }
                return;
            }
            $key = $keys[$depth];
            foreach ($options[$key] as $value) {
                $filtered = $selected->filter(fn (array $stat): bool => match ($key) {
                    'score_min' => $stat['score'] >= $value,
                    'confidence_min' => $stat['confidence'] >= $value,
                    'drawdown_max' => $stat['drawdown'] <= $value,
                    'profit_factor_min' => $stat['profit_factor'] >= $value,
                    'volatility_max' => $stat['volatility'] <= $value,
                    'hit_rate_min' => $stat['hit_rate'] >= $value,
                    'minimum_trades' => $stat['trades'] >= $value,
                });
                $search($depth + 1, $filtered, [...$candidate, $key => $value]);
            }
        };
        $search(0, $stats, []);
        abort_if(! is_finite($bestMetrics['value']), 422, __('Keine der :count geprüften Filtervarianten erfüllt die Mindestanforderungen.', ['count' => number_format($variantsChecked, 0, ',', '.')]));

        $query = array_merge($request->except(['_token', 'optimization_goal', 'trade_capacity', 'backtest_run']), $state, [
            'max_positions' => $tradeCapacity,
            'initial_capital' => $initialCapital,
            'trade_cost' => $tradeCost,
            'position_factor' => 1,
            'sector_score_rotation' => 1,
            'index_score_rotation' => 1,
            'automatic_optimization' => 1,
            'optimization_goal' => $validated['optimization_goal'],
        ]);
        // The regular backtest validator uses 1 as the neutral lower bound;
        // the optimizer represents the same "no minimum" state internally as 0.
        $query['minimum_trades'] = max(1, (int) $query['minimum_trades']);
        $request->replace($query);

        return $this->startFilteredBacktest($request);
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
            'profit_factor_min' => min(3, (float) ($rules['profit_factor_min'] ?? 0)),
            'hit_rate_min' => $rules['hit_rate_min'] ?? 0,
            'minimum_trades' => $rules['minimum_trades'] ?? 1,
            'positive_prediction_required' => ($rules['positive_prediction_required'] ?? false) ? 1 : 0,
            'ensemble_veto_required' => ($rules['ensemble_veto_required'] ?? false) ? 1 : 0,
        ]);
    }

    public function filteredBacktestResult(Request $request, string $publicId, ?YahooIndexService $indices = null): JsonResponse
    {
        $indices ??= app(YahooIndexService::class);
        $resultCacheKey = 'filtered-backtest-result:v9:'.$request->user()->id.':'.$publicId;
        $cachedResult = Cache::store('file')->get($resultCacheKey);
        if (is_array($cachedResult)) {
            return response()->json($cachedResult)->header('Cache-Control', 'private, max-age=60');
        }

        $run = DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->first();
        abort_if($run === null, 404);

        // Completed backtests are immutable. Building all portfolio variants,
        // rotations, benchmarks and chart series is expensive, so reuse the
        // finished payload for the result modal and PDF report.
        $pythonJob = DB::table('python_engine_jobs')
            ->where('backtest_run_id', $run->id)
            ->where('status', 'completed')
            ->latest('id')
            ->first(['result']);
        $pythonResult = is_string($pythonJob?->result)
            ? json_decode($pythonJob->result, true)
            : (array) ($pythonJob?->result ?? []);
        $earlyRunSettings = is_string($run->settings) ? (json_decode($run->settings, true) ?: []) : (array) $run->settings;
        $scoreRotationRequested = (bool) data_get($earlyRunSettings, 'selection_filters.forecast_score_rotation_5d_enabled', false);
        if ($scoreRotationRequested && ! DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', \App\Services\HistoricalForecastScoreRotationService::STRATEGY)
            ->exists()) {
            $rotationSummary = app(\App\Services\HistoricalForecastScoreRotationService::class)->apply(
                (int) $run->id,
                max(1, (int) data_get($earlyRunSettings, 'selection_filters.max_positions', 5)),
                true,
                (bool) data_get($earlyRunSettings, 'selection_filters.sector_score_rotation', false),
                (bool) data_get($earlyRunSettings, 'selection_filters.index_score_rotation', false),
                (string) data_get($earlyRunSettings, 'selection_filters.strategy_priority', 'rotation_first'),
            );
            $updatedSummary = is_string($run->summary) ? (json_decode($run->summary, true) ?: []) : (array) ($run->summary ?? []);
            $updatedSummary['forecast_score_rotation_summary'] = $rotationSummary;
            DB::table('backtest_runs')->where('id', $run->id)->update([
                'summary' => json_encode($updatedSummary, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $run->summary = json_encode($updatedSummary, JSON_THROW_ON_ERROR);
        }
        $adaptiveChart = collect(data_get($pythonResult, 'adaptive_rotation_chart', []));
        $previousAdaptiveValue = null;
        $adaptiveResultIsImplausible = $adaptiveChart->contains(function (array $point) use (&$previousAdaptiveValue): bool {
            $value = (float) ($point['y'] ?? 0);
            $jump = $previousAdaptiveValue === null ? 0.0 : abs($value - $previousAdaptiveValue);
            $previousAdaptiveValue = $value;

            // A diversified portfolio cannot gain ten times its starting
            // capital in one exit event. Such jumps come from overlapping
            // duplicate positions or unadjusted corporate-action prices.
            return ! is_finite($value) || abs($value) > 1000 || $jump > 500;
        });
        // Version 2 used fractional capital blocks, charged only one fee per
        // round trip and therefore produced materially more optimistic values
        // than the strategy-depot simulator. Only reuse engine payloads that
        // explicitly implement the depot-compatible transaction accounting.
        if (data_get($pythonResult, 'calculation_version') === 'filtered-portfolio-v3-depot' && ! $adaptiveResultIsImplausible && ! $scoreRotationRequested) {
            // Rebuild the DAX comparison from current price bars on every
            // request. Older worker results may contain isolated provider
            // values in the wrong unit, producing artificial -100% spikes.
            $this->ensureIndexComparisonHistory(
                '^GDAXI',
                (int) data_get($pythonResult, 'period_start', 0),
                (int) data_get($pythonResult, 'period_end', 0),
                $indices,
            );
            $pythonResult = array_merge($pythonResult, $this->indexComparison(
                '^GDAXI',
                (int) data_get($pythonResult, 'period_start', 0),
                (int) data_get($pythonResult, 'period_end', 0),
                (float) data_get($pythonResult, 'initial_capital', 10000),
                'dax',
            ));
            Cache::store('file')->put($resultCacheKey, $pythonResult, now()->addHours(12));

            return response()->json($pythonResult)->header('Cache-Control', 'private, max-age=60');
        }

        $tradePeriod = DB::table('backtest_trades')->where('backtest_run_id', $run->id)
            ->selectRaw('MIN(entry_date) AS starts_at, MAX(exit_date) AS ends_at')
            ->first();
        if ($tradePeriod?->starts_at === null || $tradePeriod?->ends_at === null) {
            $emptyResult = ['strategy' => [], 'benchmark' => [], 'strategy_performance' => 0];
            Cache::store('file')->put($resultCacheKey, $emptyResult, now()->addHours(12));

            return response()->json($emptyResult)->header('Cache-Control', 'private, max-age=60');
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
        $entryAtrForCandidate = $this->entryAtrSubquery('backtest_trades');
        $candidateQuery = DB::table('backtest_trades')
            ->leftJoinLateral($entryAtrForCandidate, 'entry_atr')
            ->where('backtest_run_id', $run->id)
            ->whereBetween('gross_return', [-1.0, 3.0])
            ->whereDate('entry_date', '>=', $period->starts_at)
            ->whereDate('exit_date', '<=', $period->ends_at)
            ->orderBy('entry_date')
            ->orderByDesc('ki_score')
            ->orderByDesc('confidence');
        $candidates = $candidateQuery->orderBy('id')->get([
            'id', 'instrument_id', 'model_definition_id', 'trained_model_id',
            'entry_date', 'exit_date', 'entry_price', 'exit_price', 'gross_return', 'max_drawdown', 'metadata', 'entry_atr.atr_14 as entry_atr_14',
        ]);
        $portfolioSimulation = $this->simulatePortfolio(
            $candidates,
            $initialCapital,
            $maxPositions,
            $positionFactor,
            $tradeCost,
            (string) $period->starts_at,
            (string) $period->ends_at,
        );
        $executed = $portfolioSimulation['executed_collection'];
        $waitEntryCount = $executed->filter(function (object $trade): bool {
            $metadata = is_string($trade->metadata ?? null)
                ? (json_decode($trade->metadata, true) ?: [])
                : (array) ($trade->metadata ?? []);

            return (int) data_get($metadata, 'dynamic_exit.entry_wait_days', 0) > 0;
        })->count();
        $signalChangeExitCount = $executed->filter(function (object $trade): bool {
            $metadata = is_string($trade->metadata ?? null)
                ? (json_decode($trade->metadata, true) ?: [])
                : (array) ($trade->metadata ?? []);

            return data_get($metadata, 'dynamic_exit.reason') === 'signal_change';
        })->count();

        $capitalBinding = [
            'average' => $portfolioSimulation['average_capital_binding'],
            'maximum' => $portfolioSimulation['maximum_capital_binding'],
        ];
        $strategy = $portfolioSimulation['series'];
        $strategyValue = $portfolioSimulation['final'];
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
        $normalizationStartsAt = $runStartedAt->copy()->subYears(3)->toDateString();
        $normalizedTrades = $executed->filter(fn (object $trade): bool =>
            (string) ($trade->entry_date ?? '') >= $normalizationStartsAt
            && (float) ($trade->entry_price ?? 0) > 0
            && (float) ($trade->entry_atr_14 ?? 0) > 0
        );
        $averageTradeReturn = $normalizedTrades->isNotEmpty()
            ? (float) $normalizedTrades->avg(fn (object $trade): float =>
                $netReturn($trade) / ((float) $trade->entry_atr_14 / (float) $trade->entry_price)
            )
            : 0.0;
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

        $adaptiveCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', 'adaptive_rotation_20d')
            ->whereBetween('gross_return', [-1.0, 3.0])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['instrument_id', 'entry_date', 'exit_date', 'gross_return', 'max_drawdown', 'metadata']);
        $adaptive = $this->simulatePortfolio($adaptiveCandidates, $initialCapital, $maxPositions, $positionFactor, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $adaptiveGross = $this->simulatePortfolio($adaptiveCandidates->map(fn (object $trade): object => clone $trade), $initialCapital, $maxPositions, $positionFactor, 0, (string) $period->starts_at, (string) $period->ends_at);
        $scoreRotationCandidates = DB::table('backtest_strategy_trades')
            ->where('backtest_run_id', $run->id)
            ->where('strategy', \App\Services\HistoricalForecastScoreRotationService::STRATEGY)
            ->whereBetween('gross_return', [-1.0, 3.0])
            ->orderBy('entry_date')->orderBy('id')
            ->get(['instrument_id', 'entry_date', 'exit_date', 'gross_return', 'max_drawdown', 'metadata']);
        $scoreRotation = $this->simulatePortfolio($scoreRotationCandidates, $initialCapital, $maxPositions, $positionFactor, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        $scoreRotationGross = $this->simulatePortfolio($scoreRotationCandidates->map(fn (object $trade): object => clone $trade), $initialCapital, $maxPositions, $positionFactor, 0, (string) $period->starts_at, (string) $period->ends_at);
        $simulateAreaEntry = function (string $strategy) use ($run, $initialCapital, $maxPositions, $positionFactor, $tradeCost, $period): array {
            $rows = DB::table('backtest_strategy_trades')->where('backtest_run_id', $run->id)
                ->where('strategy', $strategy)->whereBetween('gross_return', [-1.0, 3.0])
                ->orderBy('entry_date')->orderBy('id')
                ->get(['instrument_id', 'entry_date', 'exit_date', 'gross_return', 'max_drawdown', 'metadata']);
            return $this->simulatePortfolio($rows, $initialCapital, $maxPositions, $positionFactor, $tradeCost, (string) $period->starts_at, (string) $period->ends_at);
        };
        $sectorEntryRotation = $simulateAreaEntry(\App\Services\HistoricalAreaEntryRotationService::SECTOR_STRATEGY);
        $indexEntryRotation = $simulateAreaEntry(\App\Services\HistoricalAreaEntryRotationService::INDEX_STRATEGY);
        $automaticExitVariants = collect(\App\Services\HistoricalDynamicExitService::AUTOMATIC_VARIANTS)
            ->mapWithKeys(function (array $rules, string $strategy) use ($simulateAreaEntry, $initialCapital): array {
                $simulation = $simulateAreaEntry($strategy);
                return [$strategy => [
                    'chart' => $this->performanceSeries($simulation['series'], $initialCapital),
                    'performance' => $simulation['performance'],
                    'final_capital' => $simulation['final'],
                    'executed_trades' => $simulation['executed'],
                    'max_drawdown' => $simulation['max_drawdown'],
                ]];
            })->all();
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

        $resultPayload = [
            'period_start' => strtotime((string) $period->starts_at) * 1000,
            'period_end' => strtotime((string) $period->ends_at) * 1000,
            'strategy' => $strategy,
            'adaptive_rotation' => $adaptive['series'],
            'buy_and_hold' => $buyAndHold['series'],
            'benchmark' => $benchmark,
            'strategy_chart' => $this->performanceSeries($strategy, $initialCapital),
            'adaptive_rotation_chart' => $this->performanceSeries($adaptive['series'], $initialCapital),
            'forecast_score_rotation' => $scoreRotation['series'],
            'forecast_score_rotation_chart' => $this->performanceSeries($scoreRotation['series'], $initialCapital),
            'sector_entry_rotation_chart' => $this->performanceSeries($sectorEntryRotation['series'], $initialCapital),
            'index_entry_rotation_chart' => $this->performanceSeries($indexEntryRotation['series'], $initialCapital),
            'selected_backtest_options' => [
                'entry_strategy' => (string) data_get($runSettings, 'selection_filters.entry_strategy', 'direct_buy'),
                'exit_strategy' => (string) data_get($runSettings, 'selection_filters.exit_strategy', 'fixed_20d'),
                'sector_rotation' => (bool) data_get($runSettings, 'selection_filters.sector_score_rotation', false),
                'index_rotation' => (bool) data_get($runSettings, 'selection_filters.index_score_rotation', false),
                'automatic' => (bool) data_get($runSettings, 'selection_filters.automatic_strategy_comparison', false),
            ],
            'automatic_exit_variants' => $automaticExitVariants,
            'buy_and_hold_chart' => $this->performanceSeries($buyAndHold['series'], $initialCapital),
            'benchmark_chart' => $this->performanceSeries($benchmark, $initialCapital),
            'strategy_performance' => round((($strategyValue / $initialCapital) - 1) * 100, 2),
            'strategy_gross_performance' => $fixedGross['performance'],
            'benchmark_start_capital' => $benchmarkStartCapital,
            'benchmark_final_capital' => $benchmarkFinalCapital,
            'benchmark_profit' => $benchmarkProfit !== null ? round($benchmarkProfit, 2) : null,
            'benchmark_performance' => $benchmarkPerformance,
            'adaptive_rotation_performance' => $adaptive['performance'],
            'adaptive_rotation_gross_performance' => $adaptiveGross['performance'],
            'adaptive_rotation_final_capital' => $adaptive['final'],
            'adaptive_rotation_executed_trades' => $adaptive['executed'],
            'adaptive_rotation_skipped_trades' => max(0, $adaptiveCandidates->count() - $adaptive['executed']),
            'forecast_score_rotation_performance' => $scoreRotation['performance'],
            'forecast_score_rotation_gross_performance' => $scoreRotationGross['performance'],
            'forecast_score_rotation_final_capital' => $scoreRotation['final'],
            'forecast_score_rotation_executed_trades' => $scoreRotation['executed'],
            'sector_entry_rotation_performance' => $sectorEntryRotation['performance'],
            'sector_entry_rotation_final_capital' => $sectorEntryRotation['final'],
            'sector_entry_rotation_executed_trades' => $sectorEntryRotation['executed'],
            'sector_entry_rotation_max_drawdown' => $sectorEntryRotation['max_drawdown'],
            'index_entry_rotation_performance' => $indexEntryRotation['performance'],
            'index_entry_rotation_final_capital' => $indexEntryRotation['final'],
            'index_entry_rotation_executed_trades' => $indexEntryRotation['executed'],
            'index_entry_rotation_max_drawdown' => $indexEntryRotation['max_drawdown'],
            'forecast_score_rotation_replacements' => (int) data_get(
                is_string($run->summary) ? (json_decode($run->summary, true) ?: []) : (array) ($run->summary ?? []),
                'forecast_score_rotation_summary.replacements',
                0,
            ),
            'buy_and_hold_performance' => $buyAndHold['performance'],
            'buy_and_hold_final_capital' => $buyAndHold['final'],
            'buy_and_hold_executed_trades' => $buyAndHold['executed'],
            'buy_and_hold_entry_at' => $buyAndHold['entry_at'],
            'buy_and_hold_trades_per_month' => round($buyAndHold['executed'] / $backtestMonths, 2),
            'buy_and_hold_max_drawdown' => $buyAndHold['max_drawdown'],
            'backtest_months' => round($backtestMonths, 2),
            'trades_per_month' => round($executed->count() / $backtestMonths, 2),
            'adaptive_rotation_trades_per_month' => round($adaptive['executed'] / $backtestMonths, 2),
            'initial_capital' => $initialCapital,
            'position_factor' => $positionFactor,
            'position_factor_usage' => $this->positionFactorUsage($executed, $basePositionCapital),
            'adaptive_rotation_position_factor_usage' => $adaptive['position_factor_usage'],
            'model_statistics' => $this->executedModelStatistics($executed),
            'final_capital' => $strategyValue,
            'executed_trades' => $executed->count(),
            'wait_entry_count' => $waitEntryCount,
            'signal_change_exit_count' => $signalChangeExitCount,
            'winner_trades' => $winnerTrades,
            'loser_trades' => $loserTrades,
            'average_gain_factor' => $averageGainFactor !== null ? round($averageGainFactor, 3) : null,
            'win_loss_ratio' => $winLossRatio !== null ? round($winLossRatio, 3) : null,
            'total_investment_days' => $totalInvestmentDays,
            'minimum_trade_drawdown' => round($minimumTradeDrawdown, 2),
            'average_trade_return' => round($averageTradeReturn, 2),
            'skipped_trades' => $candidates->count() - $executed->count(),
            'trade_cost' => $tradeCost,
            'total_costs' => round($executed->count() * $tradeCost * 2, 2),
            'hit_rate' => $executed->isNotEmpty()
                ? round(($executed->filter(fn (object $trade): bool => $netReturn($trade) > 0)->count() / $executed->count()) * 100, 2)
                : 0,
            'profit_factor' => $losingReturn > 0 ? round($winningReturn / $losingReturn, 3) : null,
            'max_drawdown' => round($executed->max(fn (object $trade): float => abs((float) $trade->max_drawdown)) * 100, 2),
            'portfolio_max_drawdown' => $this->maximumSeriesDrawdown($strategy),
            'benchmark_max_drawdown' => $benchmarkDrawdown,
            'adaptive_rotation_max_drawdown' => $adaptive['max_drawdown'],
            'forecast_score_rotation_max_drawdown' => $scoreRotation['max_drawdown'],
            'average_capital_binding' => $capitalBinding['average'],
            'maximum_capital_binding' => $capitalBinding['maximum'],
            'adaptive_rotation_average_capital_binding' => $adaptive['average_capital_binding'],
            'adaptive_rotation_maximum_capital_binding' => $adaptive['maximum_capital_binding'],
        ];
        Cache::store('file')->put($resultCacheKey, $resultPayload, now()->addHours(12));

        return response()->json($resultPayload)->header('Cache-Control', 'private, max-age=60');
    }

    public function filteredBacktestStatus(Request $request, string $publicId): JsonResponse
    {
        DB::table('backtest_runs')
            ->where('public_id', $publicId)
            ->whereRaw("settings->>'run_type' = 'user_filter'")
            ->whereRaw("(settings->>'initiated_by_user_id')::bigint = ?", [$request->user()->id])
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<', now()->subMinutes(25))
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => __('Der Backtest-Worker wurde unerwartet beendet. Bitte starte den Strategietest erneut.'),
                'updated_at' => now(),
            ]);
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
        if (data_get($settings, 'selection_filters.automatic_optimization', false)
            && data_get($settings, 'optimization.horizon_weights') === null) {
            $riskLevel = app(PersonalizedSignalService::class)->riskLevel($request->user());
            data_set($settings, 'optimization', [
                'mode' => 'automatic_multi_horizon',
                'goal' => data_get($settings, 'selection_filters.optimization_goal', 'maximize_performance'),
                'risk_profile' => $riskLevel,
                'horizon_weights' => match ($riskLevel) {
                    'cautious' => [5 => 10, 10 => 20, 15 => 30, 20 => 40],
                    'opportunity_oriented' => [5 => 35, 10 => 30, 15 => 20, 20 => 15],
                    default => [5 => 20, 10 => 25, 15 => 25, 20 => 30],
                },
                'variants_checked' => 1_778_112,
            ]);
        }
        $modelStatistics = $this->aggregateModelStatistics($result['model_statistics'] ?? []);
        $modelExitMatrix = $this->backtestModelExitMatrix((int) $run->id);
        $backtestStocks = $this->backtestStockStatistics((int) $run->id);
        $adaptiveStatistics = $this->backtestAdaptiveStatistics((int) $run->id);
        $horizonStatistics = $this->backtestHorizonStatistics((int) $run->id, (bool) data_get($settings, 'selection_filters.automatic_optimization', false));
        $reportSeries = ['Strategie' => ['color' => '#06b6d4', 'points' => $result['strategy_chart']]];
        $automaticComparison = (bool) data_get($settings, 'selection_filters.automatic_strategy_comparison', false);
        if ($automaticComparison || data_get($settings, 'selection_filters.entry_strategy') === 'forecast_score_rotation_5d') {
            $reportSeries['Forecast-Score-Einstieg (5T)'] = ['color' => '#fbbf24', 'points' => $result['forecast_score_rotation_chart'] ?? []];
        }
        if ($automaticComparison || data_get($settings, 'selection_filters.sector_score_rotation', false)) {
            $reportSeries['Sektorrotation'] = ['color' => '#a78bfa', 'points' => $result['sector_entry_rotation_chart'] ?? []];
        }
        if ($automaticComparison || data_get($settings, 'selection_filters.index_score_rotation', false)) {
            $reportSeries['Indexrotation'] = ['color' => '#f472b6', 'points' => $result['index_entry_rotation_chart'] ?? []];
        }
        if (data_get($settings, 'selection_filters.adaptive_rotation_enabled', false)) {
            $reportSeries['Adaptive Rotation'] = ['color' => '#22c55e', 'points' => $result['adaptive_rotation_chart']];
        }
        if ($automaticComparison || data_get($settings, 'selection_filters.exit_strategy') === 'buy_and_hold') {
            $reportSeries['Buy and Hold'] = ['color' => '#38bdf8', 'points' => $result['buy_and_hold_chart']];
        }
        if ($automaticComparison) {
            $automaticLabels = [
                'auto_exit_fixed_20d' => 'Exit 20T', 'auto_exit_dynamic_horizon' => 'Dynamischer Horizont',
                'auto_exit_support_stop' => 'Support-Stop', 'auto_exit_resistance_trailing' => 'Resistance-Trailing',
                'auto_exit_signal_change' => 'Signalwechsel', 'auto_entry_wait_5d' => 'WAIT-Einstieg 5T',
            ];
            $automaticColors = ['#2dd4bf', '#60a5fa', '#fb923c', '#e879f9', '#f87171', '#84cc16'];
            foreach (($result['automatic_exit_variants'] ?? []) as $strategy => $variant) {
                if ((int) ($variant['executed_trades'] ?? 0) < 1) continue;
                $reportSeries[$automaticLabels[$strategy] ?? $strategy] = [
                    'color' => $automaticColors[count($reportSeries) % count($automaticColors)],
                    'points' => $variant['chart'] ?? [],
                ];
            }
        }
        $reportSeries['S&P 500 (+'.number_format((float) $result['benchmark_performance'], 2, ',', '.').' %)'] = ['color' => '#d97706', 'points' => $result['benchmark_chart']];
        $chart = $this->reportChart($reportSeries);
        $html = view('predictions.backtest-report', compact('run', 'result', 'settings', 'chart', 'modelStatistics', 'modelExitMatrix', 'backtestStocks', 'adaptiveStatistics', 'horizonStatistics'))->render();
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

    private function backtestHorizonStatistics(int $runId, bool $multiHorizon): array
    {
        $horizons = $multiHorizon ? [5, 10, 15, 20] : [20];
        $instrumentIds = DB::table('backtest_trades')->where('backtest_run_id', $runId)
            ->distinct()->pluck('instrument_id');
        if ($instrumentIds->isEmpty()) return [];

        $runIds = DB::table('walk_forward_backtest_runs')
            ->where('status', 'completed')->whereIn('horizon_days', $horizons)
            ->orderByDesc('finished_at')->get(['id', 'horizon_days'])
            ->unique('horizon_days')->pluck('id', 'horizon_days');
        if ($runIds->isEmpty()) return [];

        $rows = DB::table('walk_forward_backtest_trades as trade')
            ->join('walk_forward_backtest_runs as horizon_run', 'horizon_run.id', '=', 'trade.run_id')
            ->whereIn('trade.run_id', $runIds->values())
            ->whereIn('trade.instrument_id', $instrumentIds)
            ->get(['horizon_run.horizon_days', 'trade.instrument_id', 'trade.net_return']);

        $statistics = $rows->groupBy('horizon_days')->map(function ($trades, $horizon): array {
            $wins = $trades->filter(fn (object $trade): bool => (float) $trade->net_return > 0);
            $losses = $trades->filter(fn (object $trade): bool => (float) $trade->net_return < 0);
            $grossLoss = abs((float) $losses->sum('net_return'));

            return [
                'horizon_days' => (int) $horizon,
                'instruments' => $trades->pluck('instrument_id')->unique()->count(),
                'trades' => $trades->count(),
                'hit_rate' => $trades->isNotEmpty() ? $wins->count() / $trades->count() * 100 : 0,
                'average_return' => (float) $trades->avg('net_return') * 100,
                'profit_factor' => $grossLoss > 0 ? (float) $wins->sum('net_return') / $grossLoss : null,
            ];
        })->sortBy('horizon_days')->values();

        $all = $rows;
        $wins = $all->filter(fn (object $trade): bool => (float) $trade->net_return > 0);
        $losses = $all->filter(fn (object $trade): bool => (float) $trade->net_return < 0);
        $grossLoss = abs((float) $losses->sum('net_return'));
        $statistics->push([
            'horizon_days' => null,
            'instruments' => $all->pluck('instrument_id')->unique()->count(),
            'trades' => $all->count(),
            'hit_rate' => $all->isNotEmpty() ? $wins->count() / $all->count() * 100 : 0,
            'average_return' => (float) $all->avg('net_return') * 100,
            'profit_factor' => $grossLoss > 0 ? (float) $wins->sum('net_return') / $grossLoss : null,
        ]);

        return $statistics->all();
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
            // A trained model exists once per horizon. The report, however, is
            // model-centric and therefore combines all horizon variants of the
            // same model definition into one performance row.
            ->groupBy(fn (object $trade): string => (string) ((int) ($trade->model_definition_id ?? 0)))
            ->map(function ($trades) use ($aliases, $qualityTiers): array {
                $first = $trades->first();
                $tierOrder = collect(['Quality Gate', 'Top', 'Stark', 'Solide', 'Basis', 'Start', 'Nicht qualifiziert']);
                $qualityTier = $trades
                    ->pluck('trained_model_id')
                    ->filter()
                    ->map(fn ($trainedModelId): string => (string) ($qualityTiers[(int) $trainedModelId] ?? 'Nicht qualifiziert'))
                    ->unique()
                    ->sortBy(fn (string $tier): int => (($position = $tierOrder->search($tier, true)) === false ? 99 : $position))
                    ->first() ?? 'Nicht qualifiziert';
                $deployedCapital = (float) $trades->sum(fn (object $trade): float => (float) ($trade->allocated_capital ?? 0));
                $profits = $trades->map(fn (object $trade): float =>
                    (float) ($trade->allocated_capital ?? 0) * (float) ($trade->net_return_after_cost ?? 0));
                $positiveProfit = (float) $profits->filter(fn (float $profit): bool => $profit > 0)->sum();
                $negativeProfit = abs((float) $profits->filter(fn (float $profit): bool => $profit < 0)->sum());

                return [
                    'model_name' => $aliases[(int) ($first->model_definition_id ?? 0)] ?? 'Unbekannt',
                    'quality_tier' => $qualityTier,
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

    /**
     * Combine legacy report rows that were stored separately per horizon.
     * New runs are already grouped by model definition, but applying the same
     * normalization here also fixes reports for completed historical runs.
     */
    private function aggregateModelStatistics(iterable $statistics)
    {
        $tierOrder = collect(['Quality Gate', 'Top', 'Stark', 'Solide', 'Basis', 'Start', 'Nicht qualifiziert']);

        return collect($statistics)
            ->map(fn ($model): object => (object) $model)
            ->groupBy(fn (object $model): string => trim((string) ($model->model_name ?? 'Unbekannt')) ?: 'Unbekannt')
            ->map(function ($models, string $modelName) use ($tierOrder): object {
                $trades = (int) $models->sum(fn (object $model): int => (int) ($model->trades ?? 0));
                $deployedCapital = (float) $models->sum(fn (object $model): float => (float) ($model->deployed_capital ?? 0));
                $performanceWeight = $deployedCapital > 0 ? 'deployed_capital' : 'trades';
                $weight = (float) $models->sum(fn (object $model): float => (float) ($model->{$performanceWeight} ?? 0));
                $weighted = static function ($models, string $field, string $weightField, float $totalWeight): float {
                    if ($totalWeight <= 0) return 0.0;

                    return (float) $models->sum(fn (object $model): float =>
                        (float) ($model->{$field} ?? 0) * (float) ($model->{$weightField} ?? 0)
                    ) / $totalWeight;
                };
                $qualityTier = $models
                    ->pluck('quality_tier')
                    ->filter()
                    ->unique()
                    ->sortBy(fn (string $tier): int => (($position = $tierOrder->search($tier, true)) === false ? 99 : $position))
                    ->first() ?? 'Nicht qualifiziert';
                $profitFactorModels = $models->filter(fn (object $model): bool => ($model->profit_factor ?? null) !== null);
                $profitFactorWeight = (float) $profitFactorModels->sum(fn (object $model): float => (float) ($model->{$performanceWeight} ?? 0));

                return (object) [
                    'model_name' => $modelName,
                    'quality_tier' => $qualityTier,
                    'trades' => $trades,
                    'deployed_capital' => $deployedCapital,
                    'hit_rate' => $weighted($models, 'hit_rate', 'trades', (float) $trades),
                    'average_return' => $weighted($models, 'average_return', $performanceWeight, $weight),
                    'profit_factor' => $profitFactorModels->isEmpty()
                        ? null
                        : $weighted($profitFactorModels, 'profit_factor', $performanceWeight, $profitFactorWeight),
                    'max_drawdown' => (float) $models->max(fn (object $model): float => (float) ($model->max_drawdown ?? 0)),
                    'first_trade' => $models->min('first_trade'),
                    'last_trade' => $models->max('last_trade'),
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
            ->where(function ($query): void {
                $query->whereIn('strategy_trade.strategy', ['fixed_20d', 'adaptive_rotation_20d'])
                    ->orWhere(function ($automaticExits): void {
                        $automaticExits->where('strategy_trade.strategy', 'like', 'auto_exit_%')
                            // fixed_20d already represents this identical exit
                            // and must not appear twice in the report matrix.
                            ->where('strategy_trade.strategy', '<>', 'auto_exit_fixed_20d');
                    });
            })
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
        // The visible stock universe follows the selected tariff even for
        // administrators. Admin privileges must not bypass a Free-plan UI test.
        $isFreeRegional = ! app(PlanAccessService::class)->allowsTariff($request->user(), PlanLevel::Plus);
        $freeRegionalInstrumentIds = $isFreeRegional
            ? app(FreeRegionalStockUniverseService::class)->instrumentIds($request->user())
            : null;

        $modelIds = $this->requestedModelIds($request);
        $requestedSymbols = collect((array) $request->query('symbols', []))
            ->flatMap(fn ($value) => is_string($value) ? preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) : [$value])
            ->map(fn ($symbol) => strtoupper(trim((string) $symbol)))
            ->filter(fn ($symbol) => preg_match('/^[A-Z0-9.\-]{1,20}$/', $symbol))
            ->unique()->take(20)->values()->all();
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
        // Labels are available from the Plus tier onward.  Keeping this gate
        // at Premium made the selector disappear for Pro/Plus users even
        // when they already had active labels configured.
        $smartLabels = ! $heatmapOnly
            ? DB::table('smart_selection_labels')
                ->where('user_id', $request->user()->id)
                ->where('tariff_plan_id', $request->user()->tariff_plan_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'icon', 'criteria'])
            : collect();
        $canUseSmartLabels = ! $heatmapOnly
            && (app(PlanAccessService::class)->allows($request->user(), PlanLevel::Plus) || $smartLabels->isNotEmpty());
        $smartLabels = $canUseSmartLabels
            ? $smartLabels
            : collect();
        $selectedSmartLabelId = $smartLabels->contains('id', $request->integer('smart_label'))
            ? $request->integer('smart_label')
            : null;
        $labelBacktestRunId = (int) DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            ->orderByDesc('id')
            ->value('id');
        // Production quality data comes from the completed Triple Timeline
        // walk-forward run. Keep the legacy id for label compatibility, but
        // route prediction filters/statistics through this run.
        $hasWalkForwardTables = DB::getSchemaBuilder()->hasTable('walk_forward_backtest_runs')
            && DB::getSchemaBuilder()->hasTable('walk_forward_backtest_year_stats');
        $walkForwardRunId = $hasWalkForwardTables
            ? (int) (DB::table('walk_forward_backtest_runs')
                ->where('id', 8)
                ->where('status', 'completed')
                ->exists()
                ? 8
                : (DB::table('walk_forward_backtest_runs')
                    ->where('status', 'completed')
                    ->orderByDesc('id')
                    ->value('id') ?? 0))
            : 0;
        $scoreWalkForwardRunIds = $hasWalkForwardTables
            ? DB::table('walk_forward_backtest_runs')
                ->where('status', 'completed')
                ->whereIn('horizon_days', [5, 10, 15, 20])
                ->select(['id', 'horizon_days'])
                ->selectSub(function ($query): void {
                    $query->from('walk_forward_backtest_trades as score_run_trade')
                        ->whereColumn('score_run_trade.run_id', 'walk_forward_backtest_runs.id')
                        ->selectRaw('COUNT(DISTINCT score_run_trade.instrument_id)');
                }, 'instrument_count')
                ->orderByDesc('instrument_count')
                ->orderByDesc('id')
                ->get()
                ->unique('horizon_days')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
            : collect();
        $sortColumns = [
            'time' => 'prediction.prediction_time',
            'stock' => 'instrument.name',
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
        $instrumentBacktestStats = ($hasWalkForwardTables
            ? DB::table('walk_forward_backtest_year_stats')->where('run_id', $walkForwardRunId)
            : DB::table('backtest_trades')->where('backtest_run_id', $labelBacktestRunId))
            ->groupBy('instrument_id')
            ->select('instrument_id')
            ->when($hasWalkForwardTables, fn ($query) => $query
                ->selectRaw('MAX(ABS(maximum_drawdown)) * 100 AS drawdown_percent')
                ->selectRaw('AVG(NULLIF(profit_factor, 0)) AS profit_factor')
                ->selectRaw('(SELECT AVG(profit_trade.net_return) * 100 FROM walk_forward_backtest_trades AS profit_trade WHERE profit_trade.run_id = '.(int) $walkForwardRunId.' AND profit_trade.instrument_id = walk_forward_backtest_year_stats.instrument_id) AS profit_per_trade')
                ->selectRaw('SUM(winning_trades)::numeric / NULLIF(SUM(trade_count), 0) * 100 AS hit_rate')
                ->selectRaw('SUM(trade_count) AS trade_count'))
            ->when(! $hasWalkForwardTables, fn ($query) => $query
                ->selectRaw('MAX(ABS(max_drawdown)) * 100 AS drawdown_percent')
                ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
                ->selectRaw('AVG(net_return) * 100 AS profit_per_trade')
                ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
                ->selectRaw('COUNT(*) AS trade_count'));
        $latestTechnicalIndicators = DB::table('technical_indicators')
            ->where('interval', '1d')
            ->groupBy('instrument_id')
            ->selectRaw('instrument_id, MAX(id) AS technical_id');
        $yearPriceRange = DB::table('price_bars')
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subWeeks(52))
            ->groupBy('instrument_id')
            ->select('instrument_id')
            ->selectRaw('MIN(low) AS week_52_low')
            ->selectRaw('MAX(high) AS week_52_high');

        $historicalBaseQuery = fn (): Builder => DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('public_prediction_models as public_model', 'public_model.id', '=', 'prediction.id')
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
            ->leftJoinSub($yearPriceRange, 'year_range', fn ($join) =>
                $join->on('year_range.instrument_id', '=', 'instrument.id'))
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            ->when($freeRegionalInstrumentIds !== null, fn (Builder $query) =>
                $query->whereIn('instrument.id', $freeRegionalInstrumentIds))
            // Die Produktionstabelle zeigt ausschließlich Aktien, deren
            // aktuellste Prognose mit dem neuen Daily-Makro-Standard erzeugt
            // wurde. Ältere Modelle bleiben in Historie und Detailansichten.
            ->where('trained_model.feature_set_version', 'triple_daily_macro_v1');

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
            'top' => ['strong'],
            'strong' => ['strong'],
            'solid' => ['strong', 'solid'],
            'test' => ['strong', 'solid', 'test'],
        ];

        $applyFilters = function (Builder $query, ?string $excluded = null, bool $includeNumericFilters = true) use ($request, $signalSql, $scoreSql, $confidenceSql, $predictedReturnSql, $minimumQualityTiers, $modelIds, $requestedSymbols): Builder {
            $qualityTier = (string) $request->query('quality_tier');

            return $query
            ->when($requestedSymbols !== [], fn (Builder $query) => $query->whereIn('instrument.symbol', $requestedSymbols))
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
            ->when($excluded !== 'signal' && in_array(strtoupper((string) $request->query('signal')), ['SELL', 'HOLD', 'WATCH', 'WAIT', 'BUY'], true), fn (Builder $query) =>
                $query->whereRaw("({$signalSql}) = ?", [strtoupper((string) $request->query('signal'))]))
            ->when($excluded !== 'validation' && $request->query('validation') === 'validated', fn (Builder $query) =>
                $query->whereNotNull('prediction.validated_at'))
            ->when($excluded !== 'validation' && $request->query('validation') === 'pending', fn (Builder $query) =>
                $query->whereNull('prediction.validated_at'))
            ->when($includeNumericFilters && $excluded !== 'confidence' && $request->filled('confidence_min') && is_numeric($request->query('confidence_min')), fn (Builder $query) =>
                $query->whereRaw("{$confidenceSql} >= ?", [max(0, min(100, (float) $request->query('confidence_min')))]))
            ->when($includeNumericFilters && $request->filled('score_min') && is_numeric($request->query('score_min')), fn (Builder $query) =>
                $query->whereRaw("{$scoreSql} >= ?", [max(0, min(10, (float) $request->query('score_min')))]))
            ->when($includeNumericFilters && $request->filled('drawdown_max') && is_numeric($request->query('drawdown_max')) && (float) $request->query('drawdown_max') < 50, fn (Builder $query) =>
                $query->where('backtest_stat.drawdown_percent', '<=', max(0, min(50, (float) $request->query('drawdown_max')))))
            ->when($includeNumericFilters && $request->filled('profit_per_trade_min') && is_numeric($request->query('profit_per_trade_min')) && (float) $request->query('profit_per_trade_min') > 0, fn (Builder $query) =>
                $query->where('backtest_stat.profit_factor', '>=', max(0, min(3, (float) $request->query('profit_per_trade_min')))))
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
                'prediction.signal',
                'prediction.prediction_time',
                'prediction.interval',
                'prediction.ai_type',
                'prediction.current_price',
                'prediction.predicted_price_5d',
                'prediction.predicted_price_10d',
                'prediction.predicted_price_15d',
                'prediction.predicted_price_20d',
                'prediction.confidence',
                'prediction.risk_score',
                'prediction.drawdown_risk_factor',
                'prediction.prediction_score',
                'prediction.action_score_version',
                'prediction.quality_band',
                'prediction.quality_gate_passed',
                'prediction.horizon_fusion_noise_passed',
                'prediction.horizon_fusion_stability_passed',
                'prediction.horizon_fusion_stability_score',
                'prediction.validated_at',
                'prediction.direction_correct',
                'prediction.actual_return',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.sector',
                'instrument.currency',
                'exchange.code as exchange_code',
                DB::raw('COALESCE(public_model.public_alias, model_definition.public_alias) as model_alias'),
                'public_model.ai_score as public_ai_score',
                'model_quality.quality_score as model_quality_score',
                'model_quality.eligible as model_quality_eligible',
                'quality_tier.code as model_quality_tier_code',
                'quality_tier.name as model_quality_tier_name',
                'year_range.week_52_low',
                'year_range.week_52_high',
                'technical.rsi_14',
                'technical.macd',
                'technical.macd_signal',
                'technical.sma_50',
                'technical.sma_200',
                'technical.adx_14',
            ])
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->selectRaw("(SELECT UPPER(previous_prediction.signal)
                FROM predictions AS previous_prediction
                WHERE previous_prediction.instrument_id = prediction.instrument_id
                  AND previous_prediction.prediction_time < prediction.prediction_time
                ORDER BY previous_prediction.prediction_time DESC, previous_prediction.id DESC
                LIMIT 1) AS previous_signal")
            ->selectRaw("(SELECT previous_prediction.prediction_time
                FROM predictions AS previous_prediction
                WHERE previous_prediction.instrument_id = prediction.instrument_id
                  AND previous_prediction.prediction_time < prediction.prediction_time
                ORDER BY previous_prediction.prediction_time DESC, previous_prediction.id DESC
                LIMIT 1) AS previous_signal_time")
            ->selectRaw('((prediction.predicted_price_5d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_5d')
            ->selectRaw('((prediction.predicted_price_20d - prediction.current_price) / NULLIF(prediction.current_price, 0)) * 100 AS expected_return_20d')
            ->selectRaw('(SELECT horizon_prediction.predicted_price_5d FROM predictions horizon_prediction WHERE horizon_prediction.instrument_id = prediction.instrument_id AND horizon_prediction.prediction_horizon_minutes = 7200 AND horizon_prediction.predicted_price_5d IS NOT NULL ORDER BY horizon_prediction.prediction_time DESC NULLS LAST, horizon_prediction.id DESC LIMIT 1) AS horizon_target_5d')
            ->selectRaw('(SELECT horizon_prediction.predicted_price_10d FROM predictions horizon_prediction WHERE horizon_prediction.instrument_id = prediction.instrument_id AND horizon_prediction.prediction_horizon_minutes = 14400 AND horizon_prediction.predicted_price_10d IS NOT NULL ORDER BY horizon_prediction.prediction_time DESC NULLS LAST, horizon_prediction.id DESC LIMIT 1) AS horizon_target_10d')
            ->selectRaw('(SELECT horizon_prediction.predicted_price_15d FROM predictions horizon_prediction WHERE horizon_prediction.instrument_id = prediction.instrument_id AND horizon_prediction.prediction_horizon_minutes = 21600 AND horizon_prediction.predicted_price_15d IS NOT NULL ORDER BY horizon_prediction.prediction_time DESC NULLS LAST, horizon_prediction.id DESC LIMIT 1) AS horizon_target_15d')
            ->selectRaw('(SELECT horizon_prediction.predicted_price_20d FROM predictions horizon_prediction WHERE horizon_prediction.instrument_id = prediction.instrument_id AND horizon_prediction.prediction_horizon_minutes = 28800 AND horizon_prediction.predicted_price_20d IS NOT NULL ORDER BY horizon_prediction.prediction_time DESC NULLS LAST, horizon_prediction.id DESC LIMIT 1) AS horizon_target_20d')
            ->selectRaw("{$scoreSql} AS score_10")
            ->selectRaw("{$confidenceSql} AS confidence_percent")
            ->selectRaw('GREATEST(
                20,
                COALESCE(
                    CASE
                        WHEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) <= 1
                            THEN COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) * 100
                        ELSE COALESCE(prediction.risk_score, prediction.drawdown_risk_factor)
                    END,
                    50
                ),
                COALESCE(backtest_stat.drawdown_percent, 0)
            ) AS risk_percent');

        $query
            ->orderBy($sortColumns[$sort], $direction)
            ->orderByDesc('prediction.id');

        if ($selectedSmartLabelId !== null) {
            $selectedLabel = $smartLabels->firstWhere('id', $selectedSmartLabelId);
            $criteria = is_string($selectedLabel?->criteria)
                ? (json_decode($selectedLabel->criteria, true) ?: [])
                : (array) ($selectedLabel?->criteria ?? []);
            $query
                ->when(filled($criteria['country'] ?? null), fn (Builder $query) => $query->where('instrument.country', strtoupper((string) $criteria['country'])))
                ->when(filled($criteria['exchange'] ?? null), fn (Builder $query) => $query->where('exchange.code', strtoupper((string) $criteria['exchange'])))
                ->when(filled($criteria['sector'] ?? null), fn (Builder $query) => $query->where('instrument.sector', (string) $criteria['sector']))
                ->when((float) ($criteria['confidence_min'] ?? 0) > 0, fn (Builder $query) => $query->whereRaw("{$confidenceSql} >= ?", [(float) $criteria['confidence_min']]))
                ->when((float) ($criteria['predicted_return_min'] ?? -50) > -50, fn (Builder $query) => $query->whereRaw("{$predictedReturnSql} >= ?", [(float) $criteria['predicted_return_min']]))
                ->when((float) ($criteria['drawdown_max'] ?? 50) < 50, fn (Builder $query) => $query->where('backtest_stat.drawdown_percent', '<=', (float) $criteria['drawdown_max']))
                ->when((float) ($criteria['profit_per_trade_min'] ?? $criteria['profit_factor_min'] ?? 0) > 0, fn (Builder $query) => $query->where('backtest_stat.profit_factor', '>=', (float) ($criteria['profit_per_trade_min'] ?? $criteria['profit_factor_min'])))
                ->when((float) ($criteria['hit_rate_min'] ?? 0) > 0, fn (Builder $query) => $query->where('backtest_stat.hit_rate', '>=', (float) $criteria['hit_rate_min']))
                ->when((int) ($criteria['minimum_trades'] ?? 0) > 0, fn (Builder $query) => $query->where('backtest_stat.trade_count', '>=', (int) $criteria['minimum_trades']))
                ->when((float) ($criteria['volatility_max'] ?? 100) < 100, fn (Builder $query) => $query->whereRaw('technical.volatility_20 * 100 <= ?', [(float) $criteria['volatility_max']]));
        }

        // Smart labels depend on values calculated below and therefore cannot
        // be completed in SQL. Load the SQL-prefiltered candidates first,
        // apply the label globally, then paginate. Filtering
        // only the current database page can otherwise show zero rows even
        // though matching stocks exist on a later page.
        $predictionsPaginator = $selectedSmartLabelId === null
            ? $query->paginate(50)->withQueryString()
            : null;
        $predictions = $predictionsPaginator
            ? collect($predictionsPaginator->items())
            : $query->get();
        $predictionInstrumentIds = $predictions->pluck('instrument_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $recentPatternBars = collect();
        if ($predictionInstrumentIds->isNotEmpty()) {
            $rankedPatternBars = DB::table('price_bars')
                ->whereIn('instrument_id', $predictionInstrumentIds)
                ->where('interval', '1d')
                ->where('bar_time', '>=', now()->subDays(60))
                ->select(['instrument_id', 'bar_time', 'open', 'high', 'low', 'close'])
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY instrument_id ORDER BY bar_time DESC) AS pattern_row');
            $recentPatternBars = DB::query()
                ->fromSub($rankedPatternBars, 'recent_pattern_bar')
                ->where('pattern_row', '<=', 25)
                ->orderBy('instrument_id')
                ->orderBy('bar_time')
                ->get(['instrument_id', 'bar_time', 'open', 'high', 'low', 'close'])
                ->groupBy('instrument_id');
        }
        $recentChartPatterns = $recentPatternBars->map(function ($bars): array {
            $bars = $bars->values();
            $found = [];
            $start = max(1, $bars->count() - 5);
            for ($index = $start; $index < $bars->count(); $index++) {
                $bar = $bars[$index];
                $previous = $bars[$index - 1];
                $open = (float) $bar->open;
                $close = (float) $bar->close;
                $high = (float) $bar->high;
                $low = (float) $bar->low;
                $previousOpen = (float) $previous->open;
                $previousClose = (float) $previous->close;
                if ($close > $open && $previousClose < $previousOpen && $open <= $previousClose && $close >= $previousOpen) {
                    $found['Bullish Engulfing'] = true;
                } elseif ($close < $open && $previousClose > $previousOpen && $open >= $previousClose && $close <= $previousOpen) {
                    $found['Bearish Engulfing'] = true;
                }
                $body = abs($close - $open);
                $range = $high - $low;
                if ($range > 0) {
                    $lowerWick = min($open, $close) - $low;
                    $upperWick = $high - max($open, $close);
                    if ($lowerWick >= 2 * max($body, $range * .05) && $upperWick <= $body) $found['Bullish Pin Bar'] = true;
                    if ($upperWick >= 2 * max($body, $range * .05) && $lowerWick <= $body) $found['Bearish Pin Bar'] = true;
                }
                if ($index >= 20) {
                    $prior = $bars->slice($index - 20, 20);
                    if ($close > (float) $prior->max('high')) $found['Ausbruch nach oben'] = true;
                    if ($close < (float) $prior->min('low')) $found['Ausbruch nach unten'] = true;
                }
            }

            return array_keys($found);
        });
        $newScoreTradeStats = $predictionInstrumentIds->isEmpty() || $scoreWalkForwardRunIds->isEmpty()
            ? collect()
            : DB::table('walk_forward_backtest_trades as score_trade')
                ->join('walk_forward_backtest_runs as score_run', 'score_run.id', '=', 'score_trade.run_id')
                ->whereIn('score_trade.run_id', $scoreWalkForwardRunIds)
                ->whereIn('score_trade.instrument_id', $predictionInstrumentIds)
                ->groupBy('score_trade.instrument_id', 'score_trade.run_id', 'score_run.horizon_days')
                ->select('score_trade.instrument_id', 'score_trade.run_id', 'score_run.horizon_days')
                ->selectRaw('COUNT(*) AS trade_count')
                ->selectRaw('SUM(CASE WHEN net_return > 0 THEN net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN net_return < 0 THEN net_return ELSE 0 END)), 0) AS profit_factor')
                ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
                ->selectRaw('AVG(net_return) * 100 AS average_profit_per_trade_percent')
                ->get()->groupBy('instrument_id');
        $newScoreDrawdowns = $predictionInstrumentIds->isEmpty() || $scoreWalkForwardRunIds->isEmpty()
            ? collect()
            : DB::table('walk_forward_backtest_year_stats')
                ->whereIn('run_id', $scoreWalkForwardRunIds)
                ->whereIn('instrument_id', $predictionInstrumentIds)
                ->whereNotNull('maximum_drawdown')
                ->groupBy('instrument_id', 'run_id')
                ->select('instrument_id', 'run_id')
                ->selectRaw('PERCENTILE_CONT(0.90) WITHIN GROUP (ORDER BY ABS(maximum_drawdown)) * 100 AS drawdown_p90')
                ->get()->groupBy('instrument_id');
        $smartLabelStats = $predictionInstrumentIds->isEmpty() || $smartLabels->isEmpty()
            ? collect()
            : DB::table('walk_forward_backtest_year_stats as label_trade')
                ->where('label_trade.run_id', $walkForwardRunId)
                ->whereIn('label_trade.instrument_id', $predictionInstrumentIds)
                ->groupBy('label_trade.instrument_id')
                ->select('label_trade.instrument_id')
                ->selectRaw('MAX(ABS(label_trade.maximum_drawdown)) * 100 AS drawdown')
                ->selectRaw('AVG(NULLIF(label_trade.profit_factor, 0)) AS profit_factor')
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

        $predictions = $predictions->map(function (object $prediction) use ($smartLabels, $selectedSmartLabelId, $smartLabelStats, $smartLabelVolatility, $newScoreTradeStats, $newScoreDrawdowns, $recentChartPatterns): object {
            $confidence = is_numeric($prediction->confidence_percent) ? max(0, min(100, (float) $prediction->confidence_percent)) : 0.0;
            $expectedReturn = is_numeric($prediction->expected_return_20d)
                ? (float) $prediction->expected_return_20d - max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', 0.5))
                : 0.0;
            // Nullrendite ist neutral. Erst -10 % bzw. +10 % bilden die
            // Endpunkte der Renditekomponente, statt jede negative Prognose
            // sofort mit null Punkten zu bewerten.
            $returnScore = max(0, min(100, 50 + ($expectedReturn * 5)));
            $horizonTradeStats = collect($newScoreTradeStats->get((int) $prediction->instrument_id, collect()));
            $reliableHorizonStats = $horizonTradeStats->filter(fn (object $stat): bool =>
                (int) ($stat->trade_count ?? 0) >= 10 && is_numeric($stat->profit_factor ?? null));
            $tradeCount = (int) $horizonTradeStats->sum(fn (object $stat): int => (int) ($stat->trade_count ?? 0));
            $profitFactors = $reliableHorizonStats->map(function (object $stat): float {
                $reliability = (int) $stat->trade_count / ((int) $stat->trade_count + 20);
                return 1 + ((max(0.0, min(2.5, (float) $stat->profit_factor)) - 1) * $reliability);
            });
            $hitRates = $reliableHorizonStats->filter(fn (object $stat): bool => is_numeric($stat->hit_rate ?? null))
                ->map(function (object $stat): float {
                    $reliability = (int) $stat->trade_count / ((int) $stat->trade_count + 20);
                    return 50 + (((float) $stat->hit_rate - 50) * $reliability);
                });
            $profitsPerTrade = $reliableHorizonStats->filter(fn (object $stat): bool => is_numeric($stat->average_profit_per_trade_percent ?? null))
                ->pluck('average_profit_per_trade_percent')->map(fn ($value): float => (float) $value);
            $profitFactor = $profitFactors->isNotEmpty() ? (float) $profitFactors->avg() : null;
            $hitRate = $hitRates->isNotEmpty() ? (float) $hitRates->avg() : null;
            $profitPerTrade = $profitsPerTrade->isNotEmpty() ? (float) $profitsPerTrade->avg() : null;
            $drawdowns = collect($newScoreDrawdowns->get((int) $prediction->instrument_id, collect()))
                ->filter(fn (object $stat): bool => is_numeric($stat->drawdown_p90 ?? null))
                ->pluck('drawdown_p90')->map(fn ($value): float => (float) $value);
            $drawdown = $drawdowns->isNotEmpty() ? (float) $drawdowns->avg() : null;
            $modelQuality = is_numeric($prediction->model_quality_score) ? max(0, min(100, (float) $prediction->model_quality_score * 100)) : null;
            $noiseAvailable = $prediction->horizon_fusion_noise_passed !== null;
            $noisePassed = $prediction->horizon_fusion_noise_passed === true;
            $stabilityAvailable = $prediction->horizon_fusion_stability_passed !== null;
            $stabilityPassed = $prediction->horizon_fusion_stability_passed === true;
            $stabilityScore = $stabilityPassed && is_numeric($prediction->horizon_fusion_stability_score)
                ? max(0, min(100, (float) $prediction->horizon_fusion_stability_score * 100)) : 0.0;

            $components = collect([
                ['value' => $profitFactor !== null ? max(0, min(100, (($profitFactor - 0.5) / 2.0) * 100)) : null, 'weight' => 20],
                ['value' => $profitPerTrade !== null ? max(0, min(100, 50 + ($profitPerTrade * 12.5))) : null, 'weight' => 10],
                ['value' => $confidence, 'weight' => 20],
                ['value' => $returnScore, 'weight' => 15],
                ['value' => $drawdown !== null ? max(0, min(100, 100 - (($drawdown / 50) * 100))) : null, 'weight' => 15],
                ['value' => $hitRate, 'weight' => 10],
                ['value' => $modelQuality, 'weight' => 5],
                ['value' => $noiseAvailable ? ($noisePassed ? 100 : 0) : null, 'weight' => 2.5],
                ['value' => $stabilityAvailable ? $stabilityScore : null, 'weight' => 2.5],
            ])->filter(fn (array $component): bool => $component['value'] !== null);
            $availableWeight = (float) $components->sum('weight');
            $legacyRankingScore = $availableWeight > 0
                ? (float) $components->sum(fn (array $component): float => $component['value'] * $component['weight']) / $availableWeight
                : max(0, min(100, (float) $prediction->score_10 * 10));
            $rankingScore = filled($prediction->action_score_version ?? null)
                ? (float) (\App\Support\AiScore::toPercent($prediction->prediction_score) ?? 0)
                : $legacyRankingScore;
            $prediction->ranking_score = round($rankingScore, 2);
            $prediction->score_10 = round($rankingScore / 10, 2);
            $prediction->ranking_profit_factor = $profitFactor;
            $prediction->ranking_trade_count = $tradeCount;
            $prediction->ranking_hit_rate = $hitRate;
            $prediction->ranking_profit_per_trade = $profitPerTrade;
            $prediction->ranking_drawdown = $drawdown;
            $rawVolatility = $smartLabelVolatility->get((int) $prediction->instrument_id);
            $volatility = is_numeric($rawVolatility)
                ? max(0.0, min(100.0, (float) $rawVolatility * 100))
                : null;
            $riskConfidence = is_numeric($prediction->confidence_percent)
                ? max(0.0, min(100.0, (float) $prediction->confidence_percent))
                : null;
            // Composite model risk (0=low, 100=high). Missing evidence is
            // conservatively neutral rather than silently treated as safe.
            $riskComponents = [
                'profit_factor' => [
                    'weight' => 30.0,
                    'value' => $profitFactor !== null
                        ? max(0.0, min(100.0, 100 - ((($profitFactor - 0.5) / 1.5) * 100)))
                        : 60.0,
                ],
                'drawdown' => [
                    'weight' => 25.0,
                    'value' => $drawdown !== null ? max(0.0, min(100.0, ($drawdown / 40) * 100)) : 60.0,
                ],
                'volatility' => [
                    'weight' => 20.0,
                    'value' => $volatility !== null ? max(0.0, min(100.0, (($volatility - 10) / 50) * 100)) : 60.0,
                ],
                'hit_rate' => [
                    'weight' => 15.0,
                    'value' => $hitRate !== null ? max(0.0, min(100.0, ((70 - $hitRate) / 40) * 100)) : 60.0,
                ],
                'confidence' => [
                    'weight' => 10.0,
                    'value' => $riskConfidence !== null ? 100 - $riskConfidence : 60.0,
                ],
            ];
            $prediction->risk_percent = round((float) collect($riskComponents)->sum(
                fn (array $component): float => $component['value'] * ($component['weight'] / 100)
            ), 1);
            $prediction->risk_components = $riskComponents;
            $prediction->ranking_volatility = $volatility;
            $prediction->ranking_model_quality = $modelQuality;
            $prediction->ranking_noise_passed = $noisePassed;
            $prediction->ranking_stability_passed = $stabilityPassed;
            $prediction->ranking_stability_percent = $stabilityScore;
            $prediction->recent_chart_patterns = $recentChartPatterns->get((int) $prediction->instrument_id, []);
            $score = is_numeric($prediction->score_10) ? (float) $prediction->score_10 : null;
            $confidence = is_numeric($prediction->confidence_percent) ? (float) $prediction->confidence_percent : null;
            $predictedReturn = is_numeric($prediction->expected_return_20d) ? (float) $prediction->expected_return_20d : null;
            // Smart-label criteria are configured and counted from the
            // dedicated label walk-forward run. Use that same source while
            // keeping the multi-horizon values for table ranking/display.
            $labelStats = $smartLabelStats->get((int) $prediction->instrument_id);
            $drawdown = is_numeric($labelStats?->drawdown) ? (float) $labelStats->drawdown : null;
            $profitFactor = is_numeric($labelStats?->profit_factor) ? (float) $labelStats->profit_factor : null;
            $hitRate = is_numeric($prediction->ranking_hit_rate) ? (float) $prediction->ranking_hit_rate : null;
            $tradeCount = (int) ($prediction->ranking_trade_count ?? 0);
            $rawVolatility = $smartLabelVolatility->get((int) $prediction->instrument_id);
            $volatility = is_numeric($rawVolatility) ? (float) $rawVolatility * 100 : null;
            $matching = $smartLabels->filter(function (object $label) use ($score, $confidence, $predictedReturn, $drawdown, $profitFactor, $volatility, $hitRate, $tradeCount): bool {
                $criteria = is_string($label->criteria) ? (json_decode($label->criteria, true) ?: []) : (array) $label->criteria;
                if ($score === null || $score < (float) ($criteria['score_min'] ?? 0)) return false;
                if ($confidence === null || $confidence < (float) ($criteria['confidence_min'] ?? 0)) return false;
                if ($predictedReturn === null || $predictedReturn < (float) ($criteria['predicted_return_min'] ?? -20)) return false;
                if ((float) ($criteria['drawdown_max'] ?? 50) < 50 && ($drawdown === null || $drawdown > (float) $criteria['drawdown_max'])) return false;
                $profitFactorMinimum = (float) ($criteria['profit_per_trade_min'] ?? $criteria['profit_factor_min'] ?? 0);
                if ($profitFactorMinimum > 0 && ($profitFactor === null || $profitFactor < $profitFactorMinimum)) return false;
                if ((float) ($criteria['hit_rate_min'] ?? 0) > 0 && ($hitRate === null || $hitRate < (float) $criteria['hit_rate_min'])) return false;
                if ((int) ($criteria['minimum_trades'] ?? 0) > 0 && $tradeCount < (int) $criteria['minimum_trades']) return false;
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
        if ($request->filled('score_min') && is_numeric($request->query('score_min'))) {
            $predictions = $predictions->filter(fn (object $prediction): bool =>
                (float) $prediction->score_10 >= max(0, min(10, (float) $request->query('score_min')))
            )->values();
        }
        if ($sort === 'score') {
            $predictions = $predictions->sortBy(
                fn (object $prediction): float => (float) $prediction->score_10,
                SORT_REGULAR,
                $direction === 'desc'
            )->values();
        }
        if ($sort === 'risk') {
            $predictions = $predictions->sortBy(
                fn (object $prediction): float => (float) $prediction->risk_percent,
                SORT_REGULAR,
                $direction === 'desc'
            )->values();
        }
        if ($selectedSmartLabelId !== null) {
            $predictions = $predictions->filter(fn (object $prediction): bool => collect($prediction->smart_labels)->contains('id', $selectedSmartLabelId))->values();
        }
        if ($selectedSmartLabelId !== null) {
            $currentPage = max(1, LengthAwarePaginator::resolveCurrentPage());
            $predictions = new LengthAwarePaginator(
                $predictions->forPage($currentPage, 50)->values(),
                $predictions->count(),
                50,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $predictionsPaginator->setCollection($predictions);
            $predictions = $predictionsPaginator;
        }
        } else {
            $predictions = collect();
        }

        // Keep the summary cards in sync with exactly the same filters as the table.
        $summary = $applyFilters($baseQuery())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT prediction.instrument_id) AS instruments')
            ->selectRaw('COUNT(prediction.validated_at) AS validated')
            ->selectRaw('MIN(trained_model.trained_at) AS oldest_training')
            ->selectRaw('MAX(prediction.prediction_time) AS latest_prediction')
            ->first();
        if (! $heatmapOnly && ($selectedSmartLabelId !== null || ($request->filled('score_min') && is_numeric($request->query('score_min'))))) {
            $summary->total = $predictions instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $predictions->total() : $predictions->count();
            $summary->instruments = $summary->total;
            $pagePredictions = collect($predictions->items());
            $summary->validated = $pagePredictions->whereNotNull('validated_at')->count();
            $summary->latest_prediction = $pagePredictions->max('prediction_time');
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
            ->sortBy(fn (object $tier): int => array_search($tier->code, ['test', 'solid', 'strong', 'unqualified'], true))
            ->values();

        $signals = $applyFilters($baseQuery(), 'signal', false)
            ->selectRaw("({$signalSql}) AS available_signal")
            ->distinct()
            ->orderBy('available_signal')
            ->pluck('available_signal')
            ->map(fn ($signal) => strtoupper((string) $signal))
            ->filter(fn (string $signal) => in_array($signal, ['SELL', 'HOLD', 'WATCH', 'WAIT', 'BUY'], true));

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
        $fundamentalNumber = static fn (string $key): string => match ($key) {
            'trailingPE' => "COALESCE(fundamental.trailing_pe, CASE WHEN NULLIF(fundamental.data::jsonb->>'trailingPE', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'trailingPE')::numeric END)",
            'dividendYield' => "COALESCE(fundamental.dividend_yield, CASE WHEN NULLIF(fundamental.data::jsonb->>'dividendYield', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'dividendYield')::numeric END)",
            'marketCap' => "COALESCE(fundamental.market_cap, CASE WHEN NULLIF(fundamental.data::jsonb->>'marketCap', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'marketCap')::numeric END)",
            'revenueGrowth' => "COALESCE(fundamental.revenue_growth, CASE WHEN NULLIF(fundamental.data::jsonb->>'revenueGrowth', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (fundamental.data::jsonb->>'revenueGrowth')::numeric END)",
        };
        $selectedUserBacktest = $this->requestedUserBacktestRun($request);
        $isUserBacktestResult = $selectedUserBacktest !== null
            && in_array($selectedUserBacktest->status, ['completed', 'completed_with_errors'], true);
        $backtestRunId = $this->selectedBacktestRunId($request);
        $selectedRunSettings = $isUserBacktestResult
            ? (is_string($selectedUserBacktest->settings)
                ? (json_decode($selectedUserBacktest->settings, true) ?: [])
                : (array) $selectedUserBacktest->settings)
            : [];
        // A personal run only contains the stocks that matched at start time.
        // Keep the complete source run as the heatmap universe so relaxing a
        // filter can restore cells and values without starting another test.
        $heatmapSourceRunId = $isUserBacktestResult
            ? (int) data_get($selectedRunSettings, 'source_run_id', $backtestRunId)
            : $backtestRunId;
        $rangeSource = DB::table('backtest_trades as range_trade')
            ->join('instruments as range_instrument', 'range_instrument.id', '=', 'range_trade.instrument_id')
            ->leftJoinSub($latestFundamentalIds, 'range_latest_fundamental', fn ($join) =>
                $join->on('range_latest_fundamental.instrument_id', '=', 'range_instrument.id'))
            ->leftJoin('instrument_fundamentals as range_fundamental', 'range_fundamental.id', '=', 'range_latest_fundamental.fundamental_id')
            ->leftJoinSub($latestTechnicalIds, 'range_latest_technical', fn ($join) =>
                $join->on('range_latest_technical.instrument_id', '=', 'range_instrument.id'))
            ->leftJoin('technical_indicators as range_technical', 'range_technical.id', '=', 'range_latest_technical.technical_id')
            ->where('range_trade.backtest_run_id', $heatmapSourceRunId)
            ->where('range_instrument.is_active', true)
            ->whereNull('range_instrument.deleted_at')
            ->when($freeRegionalInstrumentIds !== null, fn (Builder $query) =>
                $query->whereIn('range_instrument.id', $freeRegionalInstrumentIds));
        $rangeFundamentalNumber = static fn (string $key): string => match ($key) {
            'trailingPE' => "COALESCE(range_fundamental.trailing_pe, CASE WHEN NULLIF(range_fundamental.data::jsonb->>'trailingPE', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (range_fundamental.data::jsonb->>'trailingPE')::numeric END)",
            'dividendYield' => "COALESCE(range_fundamental.dividend_yield, CASE WHEN NULLIF(range_fundamental.data::jsonb->>'dividendYield', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (range_fundamental.data::jsonb->>'dividendYield')::numeric END)",
            'marketCap' => "COALESCE(range_fundamental.market_cap, CASE WHEN NULLIF(range_fundamental.data::jsonb->>'marketCap', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (range_fundamental.data::jsonb->>'marketCap')::numeric END)",
            'revenueGrowth' => "COALESCE(range_fundamental.revenue_growth, CASE WHEN NULLIF(range_fundamental.data::jsonb->>'revenueGrowth', '') ~ '^-?[0-9]+([.][0-9]+)?$' THEN (range_fundamental.data::jsonb->>'revenueGrowth')::numeric END)",
        };
        $rangeValues = (clone $rangeSource)
            ->selectRaw('MAX(range_trade.ki_score) AS score')
            ->selectRaw('MAX(range_trade.confidence) AS confidence')
            ->selectRaw('MAX(ABS(range_trade.max_drawdown)) * 100 AS drawdown')
            ->selectRaw('MAX(range_trade.predicted_return) * 100 AS predicted_return')
            ->selectRaw('MAX(range_technical.volatility_20) * 100 AS volatility')
            ->selectRaw('MAX('.$rangeFundamentalNumber('trailingPE').') AS pe')
            ->selectRaw('MAX('.$rangeFundamentalNumber('dividendYield').') * 100 AS dividend_yield')
            ->selectRaw('MAX('.$rangeFundamentalNumber('marketCap').') / 1000000000 AS market_cap')
            ->selectRaw('MAX('.$rangeFundamentalNumber('revenueGrowth').') * 100 AS revenue_growth')
            ->first();
        $instrumentRangeStats = DB::table('backtest_trades as range_stat_trade')
            ->join('instruments as range_stat_instrument', 'range_stat_instrument.id', '=', 'range_stat_trade.instrument_id')
            ->where('range_stat_trade.backtest_run_id', $heatmapSourceRunId)
            ->where('range_stat_instrument.is_active', true)
            ->whereNull('range_stat_instrument.deleted_at')
            ->when($freeRegionalInstrumentIds !== null, fn (Builder $query) =>
                $query->whereIn('range_stat_instrument.id', $freeRegionalInstrumentIds))
            ->groupBy('range_stat_trade.instrument_id')
            ->selectRaw('COUNT(*) AS trade_count')
            ->selectRaw('AVG(CASE WHEN range_stat_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(range_stat_trade.net_return) * 100 AS profit_per_trade')
            ->selectRaw('SUM(CASE WHEN range_stat_trade.net_return > 0 THEN range_stat_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN range_stat_trade.net_return < 0 THEN range_stat_trade.net_return ELSE 0 END)), 0) AS profit_factor');
        $instrumentRangeValues = DB::query()->fromSub($instrumentRangeStats, 'range_stats')
            ->selectRaw('MAX(trade_count) AS trade_count, MAX(hit_rate) AS hit_rate, MAX(profit_factor) AS profit_factor, MAX(profit_per_trade) AS profit_per_trade')
            ->first();
        $ceilTo = static fn (mixed $value, float $step, float $fallback): float => is_numeric($value)
            ? max($step, ceil((float) $value / $step) * $step)
            : $fallback;
        $rangeMaxima = [
            'score' => $ceilTo($rangeValues?->score, .5, 10),
            'confidence' => $ceilTo($rangeValues?->confidence, 5, 100),
            'drawdown' => $ceilTo($rangeValues?->drawdown, 5, 50),
            'profit_factor' => 3.0,
            'profit_per_trade' => $ceilTo($instrumentRangeValues?->profit_per_trade, .1, 5),
            // Volatility is displayed as a percentage and is intentionally
            // capped at 100 for every German filter page.
            'volatility' => min(100.0, $ceilTo($rangeValues?->volatility, 5, 100)),
            'predicted_return' => $ceilTo($rangeValues?->predicted_return, .5, 20),
            'pe' => $ceilTo($rangeValues?->pe, 1, 100),
            'dividend_yield' => $ceilTo($rangeValues?->dividend_yield, .1, 10),
            'market_cap' => $ceilTo($rangeValues?->market_cap, 25, 3000),
            'revenue_growth' => $ceilTo($rangeValues?->revenue_growth, 1, 100),
            'hit_rate' => $ceilTo($instrumentRangeValues?->hit_rate, 5, 100),
            'trades' => $ceilTo($instrumentRangeValues?->trade_count, 10, 100),
        ];
        $eligibleInstruments = $this->eligibleBacktestInstruments($heatmapSourceRunId, $request, $rangeMaxima);
        $entryAtrForHeatmap = $this->entryAtrSubquery('backtest_trade');
        $heatmapBaseQuery = DB::table('backtest_trades as backtest_trade')
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
            ->leftJoinLateral($entryAtrForHeatmap, 'entry_atr')
            ->where('backtest_trade.backtest_run_id', $heatmapSourceRunId)
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->when($freeRegionalInstrumentIds !== null, fn (Builder $query) =>
                $query->whereIn('instrument.id', $freeRegionalInstrumentIds));

        $heatmapQuery = (clone $heatmapBaseQuery)
            ->when($eligibleInstruments !== null, fn (Builder $query) =>
                $query->whereIn('backtest_trade.instrument_id', $eligibleInstruments))
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
            ->when(in_array(strtoupper((string) $request->query('signal')), ['SELL', 'HOLD', 'WATCH', 'WAIT', 'BUY'], true), fn (Builder $query) =>
                $query->where('backtest_trade.signal', strtoupper((string) $request->query('signal'))))
            ->when($request->filled('volatility_max') && is_numeric($request->query('volatility_max')) && (float) $request->query('volatility_max') < $rangeMaxima['volatility'], fn (Builder $query) =>
                $query->where('technical.volatility_20', '<=', max(0, (float) $request->query('volatility_max')) / 100))
            ->when($request->filled('pe_max') && is_numeric($request->query('pe_max')) && (float) $request->query('pe_max') < $rangeMaxima['pe'], fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('trailingPE').' <= ?', [(float) $request->query('pe_max')]))
            ->when($request->filled('dividend_yield_min') && is_numeric($request->query('dividend_yield_min')) && (float) $request->query('dividend_yield_min') > 0, fn (Builder $query) =>
                $query->whereRaw($fundamentalNumber('dividendYield').' >= ?', [(float) $request->query('dividend_yield_min') / 100]))
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

        $heatmapUniverseInstruments = (clone $heatmapBaseQuery)
            ->distinct('backtest_trade.instrument_id')
            ->count('backtest_trade.instrument_id');

        // The historical heatmap is identical when only table presentation
        // parameters (label, page or sorting) change. Cache these expensive
        // aggregates so table filtering does not recalculate the complete
        // backtest history on every request.
        $heatmapCacheQuery = collect($request->query())
            ->except(['smart_label', 'page', 'sort', 'direction'])
            ->sortKeys()
            ->all();
        $heatmapCacheKey = 'predictions:heatmap:v2:'.$heatmapSourceRunId.':'.($request->user()?->tariff_plan_id ?? 0).':'.sha1(json_encode($heatmapCacheQuery));

        $heatmapSummary = Cache::remember($heatmapCacheKey.':summary', now()->addMinutes(15), fn () => (clone $heatmapSummaryQuery)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('COUNT(DISTINCT backtest_trade.instrument_id) AS instruments')
            ->selectRaw("MAX(COALESCE(NULLIF(backtest_run.settings->>'lookback_years', '')::numeric * 12, 36)) AS backtest_months")
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(ABS(backtest_trade.max_drawdown)) * 100 AS drawdown')
            ->selectRaw('AVG(technical.volatility_20) * 100 AS volatility')
            ->selectRaw("AVG(CASE WHEN backtest_trade.entry_date >= (COALESCE(backtest_run.started_at, NOW())::date - interval '3 years') AND entry_atr.atr_14 > 0 AND backtest_trade.entry_price > 0 THEN backtest_trade.net_return / (entry_atr.atr_14 / backtest_trade.entry_price) END) AS normalized_profit_per_trade")
            ->selectRaw("COUNT(CASE WHEN backtest_trade.entry_date >= (COALESCE(backtest_run.started_at, NOW())::date - interval '3 years') AND entry_atr.atr_14 > 0 AND backtest_trade.entry_price > 0 THEN 1 END) AS normalized_trade_count")
            ->first());

        $qualifiedHeatmapCells = Cache::remember($heatmapCacheKey.':qualified-cells', now()->addMinutes(15), fn () => (clone $heatmapSummaryQuery)
            ->selectRaw("{$tradeScoreBucketSql} AS score_bucket")
            ->selectRaw("{$tradeConfidenceBucketSql} AS confidence_bucket")
            ->groupByRaw("{$tradeScoreBucketSql}, {$tradeConfidenceBucketSql}")
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->score_bucket.'-'.$row->confidence_bucket => true]));

        // Metrics stay based on the complete historical universe. Filters
        // only decide whether a populated cell is active or greyed out.
        $heatmap = Cache::remember($heatmapCacheKey.':cells', now()->addMinutes(15), fn () => (clone $heatmapBaseQuery)
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
            ->keyBy(fn ($row) => $row->score_bucket.'-'.$row->confidence_bucket));

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
        $savedPredictionFilters = collect((array) data_get($request->user()->preferences, 'prediction_table_filters', []))
            ->map(fn ($preset): object => (object) [
                'id' => (string) data_get($preset, 'id'),
                'name' => (string) data_get($preset, 'name'),
                'filters' => (array) data_get($preset, 'filters', []),
            ])
            ->filter(fn (object $preset): bool => $preset->id !== '' && $preset->name !== '')
            ->sortBy('name')
            ->values();
        $visiblePredictionIds = $predictions instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($predictions->items())->pluck('id')
            : $predictions->pluck('id');
        $reportRecords = $visiblePredictionIds->isEmpty()
            ? collect()
            : DB::table('analysis_reports')
                ->whereIn('prediction_id', $visiblePredictionIds)
                ->orderByDesc('id')
                ->get(['id', 'prediction_id', 'symbol', 'signal_from', 'signal_to', 'pdf_path'])
                ->unique('prediction_id')
                ->keyBy('prediction_id');

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
            'qualifiedHeatmapCells',
            'heatmapSummary',
            'heatmapUniverseInstruments',
            'rangeMaxima',
            'userWatchlists',
            'watchlistMemberships',
            'sort',
            'direction',
            'canUseSmartLabels',
            'smartLabels',
            'savedPredictionFilters',
            'reportRecords',
            'isFreeRegional',
        ));
    }

    private function selectedBacktestRunId(Request $request): int
    {
        $requested = $this->requestedUserBacktestRun($request);
        if ($requested !== null && in_array($requested->status, ['completed', 'completed_with_errors'], true)) {
            return (int) $requested->id;
        }

        // Keep the setup and its counters on the same, most complete current
        // walk-forward source that will also be used for a new strategy test.
        $walkForwardSource = $this->materializeWalkForwardSourceRun();
        if ($walkForwardSource !== null) {
            return (int) $walkForwardSource->id;
        }

        return (int) DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
            // Do not select an empty administrative run for the heatmap. The
            // Triple Timeline run stores its aggregate scores/year statistics
            // separately, so the legacy trade heatmap needs the newest
            // completed run that actually contains trades.
            ->where('trades_count', '>', 0)
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * Build the legacy-shaped source snapshot required by the personal
     * strategy simulator from the current 20-day walk-forward result. The
     * snapshot only contains current-model data and is reused for subsequent
     * user simulations.
     */
    private function materializeWalkForwardSourceRun(): ?object
    {
        if (! DB::getSchemaBuilder()->hasTable('walk_forward_backtest_trades')) {
            return null;
        }

        $walkForwardRun = DB::table('walk_forward_backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->where('horizon_days', 20)
            ->orderByRaw('(SELECT COUNT(DISTINCT source_trade.instrument_id) FROM walk_forward_backtest_trades AS source_trade WHERE source_trade.run_id = walk_forward_backtest_runs.id) DESC')
            ->orderByDesc('finished_at')
            ->first();
        if ($walkForwardRun === null) {
            return null;
        }

        $existing = DB::table('backtest_runs')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereRaw("settings->>'source_walk_forward_run_id' = ?", [(string) $walkForwardRun->id])
            ->where('trades_count', '>', 0)
            ->orderByDesc('id')
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($walkForwardRun): ?object {
            $publicId = (string) Str::uuid();
            $now = now();
            $runId = DB::table('backtest_runs')->insertGetId([
                'public_id' => $publicId,
                'status' => 'running',
                'strategy' => 'walk_forward_source_20d',
                'timeframe' => '1d',
                'horizon_days' => 20,
                'started_at' => $now,
                'settings' => json_encode([
                    'run_type' => 'system_walk_forward_source',
                    'source_walk_forward_run_id' => (int) $walkForwardRun->id,
                    'lookback_years' => 3,
                    'feature_set_version' => 'triple_daily_macro_v1',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $latestPredictionIds = DB::table('predictions')
                ->groupBy('instrument_id')
                ->selectRaw('instrument_id, MAX(id) AS prediction_id');
            $sourceTrades = DB::table('walk_forward_backtest_trades as trade')
                ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) => $join->on('latest_prediction.instrument_id', '=', 'trade.instrument_id'))
                ->join('predictions as prediction', 'prediction.id', '=', 'latest_prediction.prediction_id')
                ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
                ->where('trade.run_id', $walkForwardRun->id)
                ->select([
                    'trade.id as trade_id',
                    'trade.instrument_id', 'trade.signal_date', 'trade.exit_date', 'trade.horizon_days',
                    'trade.signal', 'trade.entry_price', 'trade.exit_price', 'trade.predicted_return',
                    'trade.gross_return', 'trade.net_return', 'trade.metadata',
                    'prediction.trained_model_id', 'prediction.ai_type', 'prediction.ai_score',
                    'prediction.prediction_score', 'prediction.confidence', 'prediction.confidence_score',
                    'prediction.live_maximum_drawdown', 'prediction.drawdown_risk_factor',
                    'prediction.quality_gate_score', 'prediction.signal_quality_score',
                    'trained_model.model_definition_id',
                ]);

            $sourceTrades->chunkById(500, function ($chunk) use ($runId, $now): void {
                DB::table('backtest_trades')->insertOrIgnore($chunk->map(function (object $trade) use ($runId, $now): array {
                    $rawScore = is_numeric($trade->ai_score) ? (float) $trade->ai_score : (float) ($trade->prediction_score ?? 0);
                    $score = $rawScore <= 1 ? $rawScore * 10 : ($rawScore <= 10 ? $rawScore : $rawScore / 10);
                    $rawConfidence = is_numeric($trade->confidence_score) ? (float) $trade->confidence_score : (float) ($trade->confidence ?? 0);
                    $confidence = $rawConfidence <= 1 ? $rawConfidence * 100 : $rawConfidence;

                    return [
                        'backtest_run_id' => $runId,
                        'instrument_id' => $trade->instrument_id,
                        'trained_model_id' => $trade->trained_model_id,
                        'model_definition_id' => $trade->model_definition_id,
                        'ai_type' => $trade->ai_type ?: 'horizon',
                        'timeframe' => '1d',
                        'horizon_days' => $trade->horizon_days,
                        'signal' => $trade->signal,
                        'entry_date' => $trade->signal_date,
                        'exit_date' => $trade->exit_date,
                        'entry_price' => $trade->entry_price,
                        'exit_price' => $trade->exit_price,
                        'predicted_return' => $trade->predicted_return,
                        'gross_return' => $trade->gross_return,
                        'net_return' => $trade->net_return,
                        'transaction_cost' => (float) $trade->gross_return - (float) $trade->net_return,
                        'max_drawdown' => abs((float) ($trade->live_maximum_drawdown ?? $trade->drawdown_risk_factor ?? 0)),
                        'ki_score' => max(0, min(10, $score)),
                        'confidence' => max(0, min(100, $confidence)),
                        'quality_gate_score' => $trade->quality_gate_score,
                        'signal_quality_score' => $trade->signal_quality_score,
                        'metadata' => $trade->metadata,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all());
            }, 'trade.id', 'trade_id');

            $summary = DB::table('backtest_trades')->where('backtest_run_id', $runId)
                ->selectRaw('COUNT(*) AS trades, COUNT(DISTINCT instrument_id) AS instruments')
                ->first();
            if ((int) ($summary->trades ?? 0) === 0) {
                DB::table('backtest_runs')->where('id', $runId)->delete();
                return null;
            }

            DB::table('backtest_runs')->where('id', $runId)->update([
                'status' => 'completed',
                'finished_at' => now(),
                'instruments_total' => (int) $summary->instruments,
                'instruments_completed' => (int) $summary->instruments,
                'trades_count' => (int) $summary->trades,
                'updated_at' => now(),
            ]);

            return DB::table('backtest_runs')->where('id', $runId)->first();
        });
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
        $profitFactorMinimum = $request->filled('profit_per_trade_min') && is_numeric($request->query('profit_per_trade_min'))
            ? (float) $request->query('profit_per_trade_min')
            : 0.0;
        $hitRateMinimum = $request->filled('hit_rate_min') && is_numeric($request->query('hit_rate_min'))
            ? (float) $request->query('hit_rate_min')
            : 0.0;
        $minimumTrades = $request->filled('minimum_trades') && is_numeric($request->query('minimum_trades'))
            ? max(0, (int) $request->query('minimum_trades'))
            : 0;
        if ($drawdownMaximum >= (float) ($rangeMaxima['drawdown'] ?? 50.0) && $profitFactorMinimum <= 0 && $hitRateMinimum <= 0 && $minimumTrades <= 0) {
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
                    'SUM(CASE WHEN eligibility_trade.net_return > 0 THEN eligibility_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN eligibility_trade.net_return < 0 THEN eligibility_trade.net_return ELSE 0 END)), 0) >= ?',
                    [$profitFactorMinimum],
                ))
            ->when($hitRateMinimum > 0, fn (Builder $query) =>
                $query->havingRaw(
                    'AVG(CASE WHEN eligibility_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 >= ?',
                    [min(100, $hitRateMinimum)],
                ))
            ->when($minimumTrades > 0, fn (Builder $query) =>
                $query->havingRaw('COUNT(*) >= ?', [$minimumTrades]));
    }

    /**
     * Calculate ATR(14) from the OHLC bars that were known at the trade entry.
     * The lateral query prevents current volatility from leaking into a
     * historical strategy result.
     */
    private function entryAtrSubquery(string $tradeAlias): Builder
    {
        $recentBars = DB::table('price_bars as atr_source')
            ->whereColumn('atr_source.instrument_id', "{$tradeAlias}.instrument_id")
            ->where('atr_source.interval', '1d')
            ->whereRaw("atr_source.bar_time < {$tradeAlias}.entry_date::timestamp + interval '1 day'")
            ->orderByDesc('atr_source.bar_time')
            ->limit(15)
            ->select(['atr_source.bar_time', 'atr_source.high', 'atr_source.low', 'atr_source.close']);

        $barsWithPreviousClose = DB::query()
            ->fromSub($recentBars, 'recent_atr_bar')
            ->select(['recent_atr_bar.bar_time', 'recent_atr_bar.high', 'recent_atr_bar.low'])
            ->selectRaw('LAG(recent_atr_bar.close) OVER (ORDER BY recent_atr_bar.bar_time) AS previous_close');

        return DB::query()
            ->fromSub($barsWithPreviousClose, 'atr_bar')
            ->whereNotNull('atr_bar.previous_close')
            ->selectRaw('AVG(GREATEST(atr_bar.high - atr_bar.low, ABS(atr_bar.high - atr_bar.previous_close), ABS(atr_bar.low - atr_bar.previous_close))) AS atr_14');
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
        $cash = $initialCapital;
        $open = [];
        $executed = collect();
        foreach ($candidates as $trade) {
            if ($periodStart !== null && (string) $trade->entry_date < $periodStart) continue;
            if ($periodEnd !== null && (string) $trade->exit_date > $periodEnd) continue;
            $metadata = is_string($trade->metadata ?? null)
                ? (json_decode($trade->metadata, true) ?: [])
                : (array) ($trade->metadata ?? []);
            foreach ($open as $key => $position) {
                if ($position['exit_date'] > $trade->entry_date) continue;
                $cash += $position['exit_credit'];
                unset($open[$key]);
            }
            $instrumentId = (int) ($trade->instrument_id ?? 0);
            if ($instrumentId > 0 && collect($open)->contains(
                fn (array $position): bool => (int) ($position['instrument_id'] ?? 0) === $instrumentId
            )) continue;
            if (count($open) >= $maxPositions || $cash + 0.00001 < $basePositionCapital) continue;
            $allocationWeight = max(1.0, min(1.5, (float) ($metadata['allocation_weight'] ?? 1.0)));
            $requestedFactor = max(1.0, min((float) $maxPositions, $positionFactor * $allocationWeight));
            $factorStep = $allocationWeight > 1.0 ? 0.5 : 1.0;
            $availableFactor = floor((($cash + 0.00001) / $basePositionCapital) / $factorStep) * $factorStep;
            $appliedFactor = min($requestedFactor, $availableFactor);
            if ($appliedFactor < 1.0) continue;
            $entryPrice = (float) ($trade->entry_price ?? 0);
            $exitPrice = (float) ($trade->exit_price ?? 0);
            if ($entryPrice > 0) {
                if ($exitPrice <= 0) {
                    $exitPrice = $entryPrice * (1 + (float) $trade->gross_return);
                }
                $requestedCapital = min(max(0.0, $cash - $tradeCost), $basePositionCapital * $appliedFactor);
                $quantity = (int) floor($requestedCapital / $entryPrice);
                if ($quantity < 1) continue;
                $allocatedCapital = $quantity * $entryPrice;
                $entryDebit = $allocatedCapital + $tradeCost;
                $exitCredit = max(0.0, ($quantity * $exitPrice) - $tradeCost);
                $profit = $exitCredit - $entryDebit;
                $return = $entryDebit > 0 ? $profit / $entryDebit : 0.0;
                $trade->quantity = $quantity;
                $trade->entry_debit = $entryDebit;
                $trade->exit_credit = $exitCredit;
            } else {
                // Supplemental strategy rows currently persist returns but no
                // prices. Keep them usable while applying both transaction
                // fees, just like the depot simulator.
                $allocatedCapital = $basePositionCapital * $appliedFactor;
                $entryDebit = $allocatedCapital + $tradeCost;
                if ($cash + 0.00001 < $entryDebit) continue;
                $profit = ($allocatedCapital * (float) $trade->gross_return) - (2 * $tradeCost);
                $return = $profit / $entryDebit;
                $trade->quantity = null;
                $trade->entry_debit = $entryDebit;
                $trade->exit_credit = $entryDebit + $profit;
            }
            $trade->allocated_capital = $allocatedCapital;
            $trade->net_return_after_cost = $return;
            $cash -= $trade->entry_debit;
            $open[] = [
                'instrument_id' => $instrumentId,
                'exit_date' => (string) $trade->exit_date,
                'capital' => $allocatedCapital,
                'entry_debit' => (float) $trade->entry_debit,
                'exit_credit' => (float) $trade->exit_credit,
                'return' => $return,
            ];
            $executed->push($trade);
        }
        $events = [];
        foreach ($executed as $trade) {
            $events[(string) $trade->entry_date]['entries'][] = [
                'capital' => (float) $trade->allocated_capital,
                'cash_debit' => (float) $trade->entry_debit,
            ];
            $events[(string) $trade->exit_date]['exits'][] = [
                'capital' => (float) $trade->allocated_capital,
                'cash_credit' => (float) $trade->exit_credit,
                'return' => (float) $trade->net_return_after_cost,
            ];
        }
        $bindingEvents = collect($events)->map(fn (array $event): array => [
            'entries' => collect($event['entries'] ?? [])->pluck('capital')->all(),
            'exits' => collect($event['exits'] ?? [])->map(fn (array $exit): array => [
                'capital' => $exit['capital'],
                'return' => $exit['return'],
            ])->all(),
        ])->all();
        $capitalBinding = $periodStart !== null && $periodEnd !== null
            ? $this->capitalBinding($bindingEvents, $periodStart, $periodEnd, $initialCapital)
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
                $cash += $exit['cash_credit'];
            }
            foreach ($event['entries'] ?? [] as $entry) {
                $cash -= $entry['cash_debit'];
                $invested += $entry['capital'];
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
            'executed_collection' => $executed,
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

    private function indexComparison(string $symbol, int $periodStart, int $periodEnd, float $initialCapital, string $prefix): array
    {
        $bars = DB::table('price_bars as bar')
            ->join('instruments as instrument', 'instrument.id', '=', 'bar.instrument_id')
            ->where('instrument.symbol', $symbol)
            ->where('bar.interval', '1d')
            ->whereBetween('bar.bar_time', [
                Carbon::createFromTimestampMsUTC($periodStart),
                Carbon::createFromTimestampMsUTC($periodEnd)->endOfDay(),
            ])
            ->selectRaw('DISTINCT ON (DATE(bar.bar_time)) bar.bar_time, COALESCE(bar.adjusted_close, bar.close) AS close')
            ->orderByRaw('DATE(bar.bar_time), bar.bar_time DESC')
            ->get();
        $validCloses = $bars->pluck('close')
            ->filter(fn ($close): bool => is_numeric($close) && (float) $close > 0)
            ->map(fn ($close): float => (float) $close)
            ->sort()
            ->values();
        $median = (float) ($validCloses->get((int) floor($validCloses->count() / 2)) ?? 0);
        if ($median > 0) {
            $bars = $bars->filter(fn (object $bar): bool =>
                (float) $bar->close >= $median * .5 && (float) $bar->close <= $median * 1.5
            )->values();
        }
        $start = (float) ($bars->first()->close ?? 0);
        $series = $start > 0 ? $bars->map(fn (object $bar): array => [
            'x' => strtotime((string) $bar->bar_time) * 1000,
            'y' => round(((float) $bar->close / $start) * $initialCapital, 2),
        ])->values()->all() : [];
        $final = $series !== [] ? (float) $series[array_key_last($series)]['y'] : null;
        $performance = $final !== null && $initialCapital > 0 ? round((($final / $initialCapital) - 1) * 100, 2) : null;

        return [
            $prefix => $series,
            $prefix.'_chart' => $this->performanceSeries($series, $initialCapital),
            $prefix.'_final_capital' => $final,
            $prefix.'_performance' => $performance,
            $prefix.'_max_drawdown' => $this->maximumSeriesDrawdown($series),
        ];
    }

    private function ensureIndexComparisonHistory(
        string $symbol,
        int $periodStart,
        int $periodEnd,
        YahooIndexService $indices,
    ): void {
        if ($periodStart <= 0 || $periodEnd <= $periodStart) return;

        $instrumentId = DB::table('instruments')->where('symbol', $symbol)->value('id');
        if (! $instrumentId) return;

        $requiredStart = Carbon::createFromTimestampMsUTC($periodStart)->startOfDay();
        $firstValidBar = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d')
            ->whereRaw('COALESCE(adjusted_close, close) > 1000')
            ->min('bar_time');
        if ($firstValidBar !== null && Carbon::parse($firstValidBar)->lte($requiredStart->copy()->addDays(7))) return;

        $history = $indices->dailyHistory($symbol, '5y');
        $rows = collect($history)
            ->filter(fn (array $bar): bool =>
                (int) ($bar['timestamp'] ?? 0) * 1000 >= $periodStart
                && (int) ($bar['timestamp'] ?? 0) * 1000 <= $periodEnd
                && (float) ($bar['close'] ?? 0) > 1000
            )
            ->map(fn (array $bar): array => [
                'instrument_id' => $instrumentId,
                'interval' => '1d',
                'bar_time' => Carbon::createFromTimestampUTC((int) $bar['timestamp']),
                'open' => $bar['open'],
                'high' => $bar['high'],
                'low' => $bar['low'],
                'close' => $bar['close'],
                'adjusted_close' => $bar['adjusted_close'],
                'volume' => $bar['volume'],
                'source' => 'yahoo_index_rest',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('price_bars')->upsert($chunk->all(), ['instrument_id', 'interval', 'bar_time'], [
                'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
            ]);
        }
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

<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Models\Portfolio;
use App\Services\PlanAccessService;
use App\Support\AiScore;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

final class DepotController extends Controller
{
    public function index(Request $request): View
    {
        $portfolios = $this->portfolios((int) $request->user()->id);
        $paperMode = false;
        $stockInstrumentIds = $this->stockInstrumentIds();
        $availableStrategies = $request->user()->savedPredictionFilters()->orderBy('name')->get(['id', 'name']);
        // Musterdepots are rebuilt later from the revised strategy logic.
        // Keep the page empty for now and avoid the expensive template queries.
        $strategyTemplates = [];

        return view('depots.index', compact('portfolios', 'paperMode', 'stockInstrumentIds', 'strategyTemplates', 'availableStrategies'));
    }

    public function paperIndex(Request $request): View
    {
        $portfolios = $this->portfolios((int) $request->user()->id, 'paper')
            ->sortByDesc(fn (Portfolio $portfolio): int => data_get($portfolio->meta, 'automation.live_enabled', false) ? 1 : 0)
            ->values();
        $paperMode = true;
        $stockInstrumentIds = collect();
        $strategyTemplates = [];
        $availableStrategies = $request->user()->savedPredictionFilters()->orderBy('name')->get(['id', 'name']);

        return view('depots.index', compact('portfolios', 'paperMode', 'stockInstrumentIds', 'strategyTemplates', 'availableStrategies'));
    }

    private function realStrategyTemplates(): array
    {
        $latestPredictions = DB::table('predictions as latest_prediction')
            ->selectRaw('DISTINCT ON (latest_prediction.instrument_id) latest_prediction.id, latest_prediction.instrument_id')
            ->orderBy('latest_prediction.instrument_id')
            ->orderByRaw('latest_prediction.prediction_time DESC NULLS LAST')
            ->orderByDesc('latest_prediction.id');
        $latestIndicators = DB::table('technical_indicators as latest_indicator')
            ->selectRaw('DISTINCT ON (latest_indicator.instrument_id) latest_indicator.id, latest_indicator.instrument_id')
            ->where('latest_indicator.interval', '1d')
            ->orderBy('latest_indicator.instrument_id')
            ->orderByDesc('latest_indicator.bar_time')
            ->orderByDesc('latest_indicator.id');
        $latestFundamentals = DB::table('instrument_fundamentals as latest_fundamental')
            ->selectRaw('DISTINCT ON (latest_fundamental.instrument_id) latest_fundamental.id, latest_fundamental.instrument_id')
            ->orderBy('latest_fundamental.instrument_id')
            ->orderByDesc('latest_fundamental.snapshot_date')
            ->orderByDesc('latest_fundamental.id');
        $latestModelQuality = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');

        $baseQuery = function () use ($latestPredictions, $latestIndicators, $latestFundamentals, $latestModelQuality): Builder {
            return DB::table('instruments as instrument')
                ->joinSub(clone $latestPredictions, 'latest_prediction_id', fn ($join) => $join->on('latest_prediction_id.instrument_id', '=', 'instrument.id'))
                ->join('predictions as prediction', 'prediction.id', '=', 'latest_prediction_id.id')
                ->joinSub(clone $latestIndicators, 'latest_indicator_id', fn ($join) => $join->on('latest_indicator_id.instrument_id', '=', 'instrument.id'))
                ->join('technical_indicators as technical', 'technical.id', '=', 'latest_indicator_id.id')
                ->leftJoinSub(clone $latestFundamentals, 'latest_fundamental_id', fn ($join) => $join->on('latest_fundamental_id.instrument_id', '=', 'instrument.id'))
                ->leftJoin('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental_id.id')
                ->leftJoinSub(clone $latestModelQuality, 'latest_model_quality', fn ($join) =>
                    $join->on('latest_model_quality.trained_model_id', '=', 'prediction.trained_model_id'))
                ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_model_quality.ranking_id')
                ->leftJoin('model_quality_tiers as model_tier', 'model_tier.id', '=', 'model_quality.tier_id')
                ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->where('instrument.currency', 'USD')
                ->whereNull('instrument.deleted_at')
                ->whereNotNull('prediction.prediction_score')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('price_bars as history_bar')
                        ->whereColumn('history_bar.instrument_id', 'instrument.id')
                        ->where('history_bar.interval', '1d')
                        ->where('history_bar.close', '>', 0)
                        ->orderByDesc('history_bar.bar_time')
                        ->offset(20)
                        ->limit(1);
                })
                ->select([
                    'instrument.id', 'instrument.symbol', 'instrument.name', 'instrument.country',
                    'exchange.code as exchange_code', 'exchange.name as exchange_name',
                    'prediction.prediction_score', 'prediction.confidence',
                    'prediction.quality_gate_passed',
                    'model_quality.quality_score as model_quality_score',
                    'model_tier.code as model_tier_code',
                    'model_tier.name as model_tier_name',
                    'technical.volatility_20', 'fundamental.data as fundamental_data',
                ]);
        };

        $quality = $baseQuery()
            ->where('prediction.quality_gate_passed', true)
            ->where('model_quality.eligible', true)
            ->where('model_tier.code', 'top')
            ->orderByDesc('prediction.prediction_score')
            ->orderByDesc('prediction.confidence')
            ->limit(5)
            ->get();

        $value = $baseQuery()
            ->whereRaw("COALESCE(
                NULLIF(fundamental.data->>'trailingPE', '')::numeric,
                NULLIF(fundamental.data->>'forwardPE', '')::numeric,
                NULLIF(fundamental.data->>'priceToBook', '')::numeric * 8
            ) BETWEEN 1 AND 50")
            ->orderByRaw("COALESCE(
                NULLIF(fundamental.data->>'trailingPE', '')::numeric,
                NULLIF(fundamental.data->>'forwardPE', '')::numeric,
                NULLIF(fundamental.data->>'priceToBook', '')::numeric * 8
            ) ASC")
            ->orderByDesc('prediction.prediction_score')
            ->limit(5)
            ->get();

        $lowVolatility = $baseQuery()
            ->whereNotNull('technical.volatility_20')
            ->where('technical.volatility_20', '>', 0)
            ->orderBy('technical.volatility_20')
            ->orderByDesc('prediction.prediction_score')
            ->limit(5)
            ->get();

        return [
            ['strategy', 'Quality-Gate', __('Aktienauswahl mit Fokus auf Modellqualität, robuste Signale und bestandene Qualitätsprüfungen.'), 'shield', 'USD', ...$this->realPortfolioSnapshot($quality)],
            ['strategy', 'Value Strategie', __('Unterbewertete Qualitätsunternehmen anhand fundamentaler Kennzahlen strukturiert beobachten.'), 'scale', 'USD', ...$this->realPortfolioSnapshot($value)],
            ['strategy', 'Low Volatility', __('Ein defensiver Ansatz mit Fokus auf stabilere Kursverläufe und geringere Schwankungen.'), 'wave', 'USD', ...$this->realPortfolioSnapshot($lowVolatility)],
        ];
    }

    private function realPortfolioSnapshot($selection): array
    {
        $positions = collect();
        $priceSeries = collect();

        foreach ($selection as $instrument) {
            $bars = DB::table('price_bars')
                ->where('instrument_id', $instrument->id)
                ->where('interval', '1d')
                ->where('close', '>', 0)
                ->orderByDesc('bar_time')
                ->limit(21)
                ->get(['bar_time', 'close'])
                ->reverse()
                ->values();
            if ($bars->count() < 2) {
                continue;
            }

            $buyPrice = (float) $bars->first()->close;
            $currentPrice = (float) $bars->last()->close;
            if ($buyPrice <= 0 || $currentPrice <= 0) {
                continue;
            }

            $quantity = max(1, (int) floor(2000 / $buyPrice));
            $positions->push([
                'symbol' => $instrument->symbol,
                'name' => $instrument->name,
                'country' => $instrument->country,
                'exchange_code' => $instrument->exchange_code,
                'exchange_name' => $instrument->exchange_name,
                'model_quality_score' => $instrument->model_quality_score,
                'model_tier_code' => $instrument->model_tier_code ?: 'unqualified',
                'model_tier_name' => $instrument->model_tier_name ?: 'Nicht qualifiziert',
                'purchase_date' => \Illuminate\Support\Carbon::parse($bars->first()->bar_time)->format('d.m.Y'),
                'quantity' => $quantity,
                'buy_price' => $buyPrice,
                'current_price' => $currentPrice,
                'value' => $quantity * $currentPrice,
                'change' => (($currentPrice - $buyPrice) / $buyPrice) * 100,
            ]);
            $priceSeries->push([
                'quantity' => $quantity,
                'buy_price' => $buyPrice,
                'bars' => $bars,
            ]);
        }

        if ($priceSeries->isEmpty()) {
            return [$positions->all(), []];
        }

        $invested = $priceSeries->sum(fn (array $position) => $position['quantity'] * $position['buy_price']);
        $currentScore = $selection
            ->map(fn ($instrument) => AiScore::toPercent($instrument->prediction_score))
            ->filter(fn ($score) => $score !== null)
            ->avg();
        $history = collect(range(0, max(0, $priceSeries->min(fn (array $position) => $position['bars']->count()) - 1)))
            ->map(function (int $index) use ($priceSeries, $invested, $currentScore): array {
                $value = $priceSeries->sum(fn (array $position) =>
                    $position['quantity'] * (float) $position['bars']->get($index)->close
                );

                return [
                    'x' => \Illuminate\Support\Carbon::parse($priceSeries->first()['bars']->get($index)->bar_time)->format('Y-m-d'),
                    'y' => $invested > 0 ? round((($value - $invested) / $invested) * 100, 2) : 0,
                    'score' => round((float) ($currentScore ?? 0), 1),
                ];
            })
            ->values()
            ->all();

        return [$positions->all(), $history];
    }

    private function portfolios(int $userId, ?string $type = null)
    {
        return Portfolio::query()
            ->where('user_id', $userId)
            ->where('active', true)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->with(['strategies', 'cashAccount', 'positions' => fn ($query) => $query
                ->whereHas('instrument', fn ($instrument) => $instrument
                    ->where('is_active', true)
                    ->whereNull('deleted_at'))
                ->with('instrument')])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function (Portfolio $portfolio): Portfolio {
                $invested = $portfolio->positions->sum(
                    fn ($position) => (float) $position->quantity * (float) $position->average_buy_price
                );
                $positionsValue = $portfolio->positions->sum(
                    fn ($position) => (float) $position->quantity
                        * (float) ($position->current_price ?? $position->average_buy_price)
                );
                $cashBalance = (float) ($portfolio->cashAccount?->balance ?? 0);
                $currentValue = $positionsValue + $cashBalance;
                $initialCapital = max(0.0, (float) data_get($portfolio->meta, 'automation.initial_capital', 0));

                $portfolio->setAttribute('invested_value', $invested);
                $portfolio->setAttribute('positions_value', $positionsValue);
                $portfolio->setAttribute('cash_balance', $cashBalance);
                $portfolio->setAttribute('current_value', $currentValue);
                $portfolio->setAttribute('initial_capital', $initialCapital);
                $portfolio->setAttribute(
                    'performance_percent',
                    $initialCapital > 0 ? (($currentValue - $initialCapital) / $initialCapital) * 100 : 0.0
                );

                return $portfolio;
            });
    }

    private function stockInstrumentIds()
    {
        return DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id', 'symbol');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:strategy,paper'],
            'currency' => ['required', 'in:EUR,USD,CHF,GBP'],
            'description' => ['nullable', 'string', 'max:500'],
            'initial_capital' => ['required_if:type,paper', 'nullable', 'numeric', 'between:1000,1000000'],
            'trade_cost' => ['required_if:type,paper', 'nullable', 'numeric', 'between:0,1000'],
        ]);

        $exists = Portfolio::query()
            ->where('user_id', $request->user()->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => __('Ein Depot mit diesem Namen existiert bereits.')]);
        }

        DB::transaction(function () use ($request, $validated): void {
            $isFirst = ! Portfolio::query()
                ->where('user_id', $request->user()->id)
                ->where('active', true)
                ->exists();

            $portfolio = Portfolio::query()->create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'is_default' => $isFirst,
                'active' => true,
                'meta' => $validated['type'] === 'paper' ? [
                    'automation' => [
                        'initial_capital' => round((float) $validated['initial_capital'], 2),
                        'trade_cost' => round((float) $validated['trade_cost'], 2),
                    ],
                ] : null,
            ]);
            $initialCapital = $validated['type'] === 'paper'
                ? round((float) $validated['initial_capital'], 2)
                : 0.0;
            $accountId = DB::table('portfolio_cash_accounts')->insertGetId([
                'portfolio_id' => $portfolio->id, 'currency' => $portfolio->currency,
                'balance' => $initialCapital, 'reserved_balance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('portfolio_cash_ledger')->insert([
                'portfolio_cash_account_id' => $accountId, 'type' => 'initial_deposit',
                'amount' => $initialCapital, 'balance_after' => $initialCapital,
                'currency' => $portfolio->currency, 'occurred_at' => now(),
                'meta' => json_encode(['source' => 'portfolio_creation'], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'portfolio-created');
    }

    public function show(Request $request, Portfolio $portfolio): View
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active, 404);

        $portfolio->load(['strategies', 'positions' => fn ($query) => $query
            ->whereHas('instrument', fn ($instrument) => $instrument
                ->where('is_active', true)
                ->whereNull('deleted_at'))
            ->with('instrument'), 'transactions' => fn ($query) => $query
                ->with('instrument')->orderByDesc('transaction_date')->orderByDesc('id')]);
        $invested = $portfolio->positions->sum(
            fn ($position) => (float) $position->quantity * (float) $position->average_buy_price
        );
        $positionInstrumentIds = $portfolio->positions->pluck('instrument_id')->filter()->unique()->values();
        $latestPositionQuoteIds = DB::table('current_stock_quotes')
            ->whereIn('instrument_id', $positionInstrumentIds)
            ->where('status', 'current')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');
        $positionQuotes = $positionInstrumentIds->isEmpty()
            ? collect()
            : DB::table('current_stock_quotes as quote')
                ->joinSub($latestPositionQuoteIds, 'latest_quote', fn ($join) =>
                    $join->on('latest_quote.quote_id', '=', 'quote.id'))
                ->get(['quote.instrument_id', 'quote.price', 'quote.quote_time'])
                ->keyBy('instrument_id');
        $currentValue = $portfolio->positions->sum(
            fn ($position) => (float) $position->quantity * (float) (
                $positionQuotes->get($position->instrument_id)?->price
                ?? $position->current_price
                ?? $position->average_buy_price
            )
        );
        $cashBalance = (float) DB::table('portfolio_cash_accounts')
            ->where('portfolio_id', $portfolio->id)->where('currency', $portfolio->currency)
            ->value('balance');
        $totalValue = $currentValue + $cashBalance;
        $capitalUtilization = $totalValue > 0
            ? min(100.0, max(0.0, ($currentValue / $totalValue) * 100))
            : 0.0;
        $initialCapital = max(0.0, (float) data_get($portfolio->meta, 'automation.initial_capital', 0));
        $performance = $initialCapital > 0 ? (($totalValue - $initialCapital) / $initialCapital) * 100 : 0.0;
        $returnToPaper = $portfolio->type === 'paper' && $request->query('return_to') === 'paper';
        $backUrl = $returnToPaper ? route('paper-depots.index') : route('depots.index');
        $backLabel = $returnToPaper ? __('Zurück zu Musterdepots') : __('Zurück zu Depots');
        $availableStrategies = $request->user()->savedPredictionFilters()
            ->orderBy('name')
            ->get(['id', 'name', 'filters']);
        $strategyNames = $availableStrategies->pluck('name', 'id');
        $openBuys = [];
        $chartTrades = $portfolio->transactions
            ->sortBy(fn ($transaction) => ($transaction->transaction_date?->format('Y-m-d') ?? '').'-'.str_pad((string) $transaction->id, 12, '0', STR_PAD_LEFT))
            ->map(function ($transaction) use (&$openBuys, $strategyNames): ?array {
                $instrumentId = (int) $transaction->instrument_id;
                if (strtolower((string) $transaction->type) === 'buy') {
                    $openBuys[$instrumentId] = $transaction;

                    return null;
                }
                if (strtolower((string) $transaction->type) !== 'sell') return null;
                $buy = $openBuys[$instrumentId] ?? null;
                $performance = data_get($transaction->meta, 'performance_percent');
                $buyPrice = $buy ? (float) $buy->price : null;
                if ((! is_numeric($buyPrice) || $buyPrice <= 0) && is_numeric($performance) && (100 + (float) $performance) > 0) {
                    $buyPrice = (float) $transaction->price / (1 + ((float) $performance / 100));
                }
                $strategyIds = collect(data_get($transaction->meta, 'strategy_ids', [data_get($transaction->meta, 'strategy_id')]))
                    ->filter()->map(fn ($id) => (int) $id)->unique();

                return [
                    'symbol' => $transaction->instrument?->symbol ?? '—',
                    'name' => $transaction->instrument?->name ?? '',
                    'buy_date' => $buy?->transaction_date?->format('Y-m-d'),
                    'sell_date' => $transaction->transaction_date?->format('Y-m-d'),
                    'buy_price' => is_numeric($buyPrice) ? round((float) $buyPrice, 4) : null,
                    'sell_price' => round((float) $transaction->price, 4),
                    'performance' => is_numeric($performance) ? round((float) $performance, 2) : ($buyPrice > 0 ? round((((float) $transaction->price - $buyPrice) / $buyPrice) * 100, 2) : null),
                    'realized_profit' => is_numeric(data_get($transaction->meta, 'realized_profit')) ? round((float) data_get($transaction->meta, 'realized_profit'), 2) : null,
                    'strategies' => $strategyIds->map(fn ($id) => $strategyNames->get($id, '#'.$id))->values()->all(),
                ];
            })
            ->filter()
            ->values();
        $strategyPerformance = $portfolio->strategies->mapWithKeys(function ($strategy) use ($portfolio): array {
            $transactions = DB::table('portfolio_automation_executions as execution')
                ->join('portfolio_transactions as transaction', 'transaction.id', '=', 'execution.portfolio_transaction_id')
                ->where('execution.portfolio_id', $portfolio->id)
                ->where('execution.saved_prediction_filter_id', $strategy->id)
                ->select('transaction.id', 'transaction.type', 'transaction.meta')
                ->distinct()
                ->get();
            $realized = $transactions->sum(function (object $transaction): float {
                $meta = is_string($transaction->meta) ? (json_decode($transaction->meta, true) ?: []) : (array) $transaction->meta;
                return (float) ($meta['realized_profit'] ?? 0);
            });

            return [$strategy->id => [
                'transactions' => $transactions->count(),
                'buys' => $transactions->where('type', 'buy')->count(),
                'sells' => $transactions->where('type', 'sell')->count(),
                'realized' => $realized,
            ]];
        });
        $simulationRun = DB::table('portfolio_simulation_runs')->where('portfolio_id', $portfolio->id)->latest('id')->first();
        $simulationSummary = $simulationRun
            ? (is_string($simulationRun->summary) ? (json_decode($simulationRun->summary, true) ?: []) : (array) $simulationRun->summary)
            : [];
        $averageCapitalUtilization = is_numeric($simulationSummary['average_capital_utilization_percent'] ?? null)
            ? min(100.0, max(0.0, (float) $simulationSummary['average_capital_utilization_percent']))
            : $capitalUtilization;
        $distinctStocksCount = $portfolio->transactions
            ->pluck('instrument_id')->filter()->unique()->count();
        $realizedResults = $portfolio->transactions
            ->map(fn ($transaction) => data_get($transaction->meta, 'realized_profit'))
            ->filter(fn ($result) => is_numeric($result))
            ->map(fn ($result) => (float) $result);
        $highestProfit = $realizedResults->filter(fn (float $result) => $result > 0)->max();
        $highestLoss = $realizedResults->filter(fn (float $result) => $result < 0)->min();
        $liveSimulationEnabled = (bool) data_get($portfolio->meta, 'automation.live_enabled', false);
        $transactionEmailsEnabled = (bool) data_get($portfolio->meta, 'automation.transaction_email_enabled', false);
        $canActivateStrategyAccount = app(PlanAccessService::class)->allows($request->user(), PlanLevel::Pro);
        $livePortfolioPositions = $portfolio->positions->map(fn ($position): array => [
            'symbol' => (string) $position->instrument->symbol,
            'quantity' => (float) $position->quantity,
            'price' => (float) ($positionQuotes->get($position->instrument_id)?->price
                ?? $position->current_price
                ?? $position->average_buy_price),
        ])->values();

        return view('depots.show', compact(
            'portfolio', 'invested', 'currentValue', 'cashBalance', 'totalValue', 'capitalUtilization',
            'averageCapitalUtilization', 'distinctStocksCount', 'highestProfit', 'highestLoss',
            'liveSimulationEnabled', 'transactionEmailsEnabled',
            'canActivateStrategyAccount',
            'livePortfolioPositions',
            'performance', 'backUrl', 'backLabel', 'availableStrategies', 'strategyNames', 'strategyPerformance',
            'simulationRun', 'simulationSummary', 'chartTrades'
        ));
    }

    public function updateStrategies(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active, 404);
        $validated = $request->validate([
            'strategies' => ['nullable', 'array', 'max:20'],
            'strategies.*' => ['integer', 'distinct'],
        ]);
        $strategyIds = collect($validated['strategies'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $ownedIds = $request->user()->savedPredictionFilters()->whereIn('id', $strategyIds)->pluck('id');
        abort_if($ownedIds->count() !== $strategyIds->count(), 404);

        DB::transaction(function () use ($portfolio, $ownedIds): void {
            $previousIds = DB::table('portfolio_strategy_assignments')
                ->where('portfolio_id', $portfolio->id)
                ->pluck('saved_prediction_filter_id');
            DB::table('portfolio_strategy_assignments')->where('portfolio_id', $portfolio->id)->delete();
            foreach ($ownedIds->values() as $index => $strategyId) {
                // A strategy controls one portfolio; a portfolio may use many strategies.
                DB::table('portfolio_strategy_assignments')
                    ->where('saved_prediction_filter_id', $strategyId)
                    ->delete();
                DB::table('portfolio_strategy_assignments')->insert([
                    'portfolio_id' => $portfolio->id,
                    'saved_prediction_filter_id' => $strategyId,
                    'enabled' => true,
                    'priority' => ($index + 1) * 10,
                    'capital_weight' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('saved_prediction_filters')->where('id', $strategyId)->update([
                    'portfolio_id' => null,
                    'automatic_portfolio_enabled' => true,
                    'updated_at' => now(),
                ]);
            }
            $removedIds = $previousIds->diff($ownedIds);
            foreach ($removedIds as $strategyId) {
                $stillAssigned = DB::table('portfolio_strategy_assignments')
                    ->where('saved_prediction_filter_id', $strategyId)
                    ->where('enabled', true)
                    ->exists();
                if (! $stillAssigned) {
                    DB::table('saved_prediction_filters')->where('id', $strategyId)->update([
                        'automatic_portfolio_enabled' => false,
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('status', __('Depotstrategien gespeichert.'));
    }

    public function updateAutomation(Request $request, Portfolio $portfolio, PlanAccessService $plans): RedirectResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active && $portfolio->type === 'paper', 404);
        abort_unless($plans->allows($request->user(), PlanLevel::Pro), 403);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'transaction_email_enabled' => ['nullable', 'boolean'],
        ]);
        $enabled = (bool) $validated['enabled'];

        if ($enabled && $portfolio->strategies()->count() === 0) {
            return back()->withErrors(['automation' => __('Ordne dem Depot zuerst mindestens eine Strategie zu.')]);
        }

        $isPremium = $plans->allows($request->user(), PlanLevel::Premium);
        if ($enabled && ! $isPremium) {
            $hasOtherActiveAccount = Portfolio::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'paper')
                ->where('active', true)
                ->whereKeyNot($portfolio->id)
                ->get(['meta'])
                ->contains(fn (Portfolio $candidate): bool => (bool) data_get($candidate->meta, 'automation.live_enabled', false));
            if ($hasOtherActiveAccount) {
                return back()->withErrors(['automation' => __('Im Pro-Tarif kann nur ein Strategiekonto gleichzeitig aktiv sein.')]);
            }
        }

        $meta = (array) $portfolio->meta;
        data_set($meta, 'automation.live_enabled', $enabled);
        data_set($meta, 'automation.transaction_email_enabled', $enabled && (bool) ($validated['transaction_email_enabled'] ?? false));
        data_set($meta, 'automation.label', $enabled ? 'Strategie' : null);
        data_set($meta, 'automation.activated_at', $enabled ? now()->toIso8601String() : null);
        $portfolio->forceFill(['meta' => $meta])->save();

        return back()->with('status', $enabled
            ? __('Strategiekonto aktiviert.')
            : __('Strategiekonto deaktiviert.'));
    }

    public function startSimulation(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active && $portfolio->type === 'paper', 404);
        if ((bool) data_get($portfolio->meta, 'automation.live_enabled', false)) {
            return back()->withErrors([
                'simulation' => __('Deaktiviere zuerst das mitlaufende Strategiedepot, bevor du eine historische Simulation startest.'),
            ]);
        }

        $assignments = DB::table('portfolio_strategy_assignments')
            ->where('portfolio_id', $portfolio->id)->where('enabled', true)
            ->orderBy('priority')->get();
        if ($assignments->isEmpty()) return back()->withErrors(['simulation' => __('Ordne dem Depot zuerst mindestens eine Strategie zu.')]);

        $publicId = (string) Str::uuid();
        DB::transaction(function () use ($request, $portfolio, $assignments, $publicId): void {
            DB::table('python_engine_jobs')
                ->whereIn('status', ['queued', 'running'])
                ->whereRaw("payload->>'portfolio_id' = ?", [(string) $portfolio->id])
                ->update([
                    'status' => 'cancelled',
                    'error_message' => __('Durch einen neuen Simulationslauf ersetzt.'),
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            $account = DB::table('portfolio_cash_accounts')->where('portfolio_id', $portfolio->id)->lockForUpdate()->first();
            abort_if($account === null, 422, __('Das Verrechnungskonto fehlt.'));
            $initial = max(1000.0, (float) data_get($portfolio->meta, 'automation.initial_capital', 10000));
            $transactionIds = DB::table('portfolio_transactions')->where('portfolio_id', $portfolio->id)->pluck('id');
            DB::table('portfolio_cash_ledger')->where('portfolio_cash_account_id', $account->id)->delete();
            DB::table('portfolio_automation_executions')->where('portfolio_id', $portfolio->id)->delete();
            DB::table('portfolio_transactions')->whereIn('id', $transactionIds)->delete();
            DB::table('portfolio_positions')->where('portfolio_id', $portfolio->id)->delete();
            DB::table('portfolio_simulation_runs')->where('portfolio_id', $portfolio->id)->delete();
            DB::table('portfolio_cash_accounts')->where('id', $account->id)->update(['balance' => $initial, 'reserved_balance' => 0, 'updated_at' => now()]);
            DB::table('portfolio_cash_ledger')->insert([
                'portfolio_cash_account_id' => $account->id, 'type' => 'initial_deposit',
                'amount' => $initial, 'balance_after' => $initial, 'currency' => $account->currency,
                'occurred_at' => now(), 'meta' => json_encode(['source' => 'portfolio_simulation_reset'], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $sourceRunId = (int) DB::table('backtest_runs')->whereIn('status', ['completed', 'completed_with_errors'])
                ->whereRaw("COALESCE(settings->>'run_type','system') <> 'user_filter'")->latest('id')->value('id');
            abort_if($sourceRunId < 1, 422, __('Kein Ausgangs-Backtest vorhanden.'));
            $runId = DB::table('portfolio_simulation_runs')->insertGetId([
                'public_id' => $publicId, 'user_id' => $request->user()->id,
                'saved_prediction_filter_id' => $assignments->first()->saved_prediction_filter_id,
                'portfolio_id' => $portfolio->id, 'backtest_run_id' => $sourceRunId,
                'status' => 'queued', 'started_at' => now(), 'initial_capital' => $initial,
                'settings' => json_encode(['strategy_ids' => $assignments->pluck('saved_prediction_filter_id')->map(fn ($id) => (int) $id)->all(), 'parallel' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('python_engine_jobs')->insert([
                'public_id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
                'type' => 'portfolio_simulation', 'calculation_version' => 'portfolio-v2',
                'status' => 'queued', 'progress' => 0,
                'payload' => json_encode(['portfolio_id' => $portfolio->id, 'simulation_run_id' => $runId, 'source_run_id' => $sourceRunId, 'strategy_ids' => $assignments->pluck('saved_prediction_filter_id')->map(fn ($id) => (int) $id)->all()], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('depots.show', [$portfolio, 'simulation' => $publicId])->with('status', __('Depotsimulation gestartet.'));
    }

    public function simulationStatus(Request $request, Portfolio $portfolio, string $publicId): \Illuminate\Http\JsonResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id, 404);
        $run = DB::table('portfolio_simulation_runs')->where('portfolio_id', $portfolio->id)->where('public_id', $publicId)->first();
        abort_if($run === null, 404);
        $job = DB::table('python_engine_jobs')->whereRaw("payload->>'simulation_run_id' = ?", [(string) $run->id])->latest('id')->first();
        return response()->json(['status' => $run->status, 'finished' => in_array($run->status, ['completed', 'failed'], true), 'progress' => (int) ($job?->progress ?? 0), 'error' => $run->error_message ?: $job?->error_message]);
    }

    public function simulationReport(Request $request, Portfolio $portfolio, string $publicId): Response
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id, 404);
        $run = DB::table('portfolio_simulation_runs')->where('portfolio_id', $portfolio->id)->where('public_id', $publicId)->where('status', 'completed')->first();
        abort_if($run === null, 404);
        $portfolio->loadMissing('strategies');
        $summary = is_string($run->summary) ? (json_decode($run->summary, true) ?: []) : (array) $run->summary;
        $transactions = $portfolio->transactions()->with('instrument')->orderBy('transaction_date')->orderBy('id')->get();
        $options = new Options(); $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options); $pdf->loadHtml(view('depots.simulation-report', compact('portfolio', 'run', 'summary', 'transactions'))->render());
        $pdf->setPaper('a4', 'portrait'); $pdf->render();
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Depot-Simulation-'.$portfolio->id.'.pdf"']);
    }

    public function reset(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active && $portfolio->type === 'paper', 404);
        $request->validate(['confirm_reset' => ['accepted']]);
        DB::transaction(fn () => $this->clearPortfolioHistory($portfolio, 'manual_portfolio_reset'));

        return back()->with('status', __('Das Musterdepot wurde auf das Startkapital zurückgesetzt.'));
    }

    public function updateCapital(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active && $portfolio->type === 'paper', 404);
        $validated = $request->validate([
            'initial_capital' => ['required', 'numeric', 'between:1000,1000000'],
        ]);

        DB::transaction(function () use ($portfolio, $validated): void {
            $account = DB::table('portfolio_cash_accounts')
                ->where('portfolio_id', $portfolio->id)
                ->where('currency', $portfolio->currency)
                ->lockForUpdate()
                ->first();
            abort_if($account === null, 422, __('Das Verrechnungskonto fehlt.'));

            $oldCapital = max(0.0, (float) data_get($portfolio->meta, 'automation.initial_capital', 10000));
            $newCapital = round((float) $validated['initial_capital'], 2);
            $difference = round($newCapital - $oldCapital, 2);
            $newBalance = round((float) $account->balance + $difference, 2);
            abort_if($newBalance < (float) $account->reserved_balance, 422, __('Das Kapital kann nicht unter den bereits gebundenen Betrag reduziert werden.'));

            $meta = (array) ($portfolio->meta ?? []);
            data_set($meta, 'automation.initial_capital', $newCapital);
            $portfolio->update(['meta' => $meta]);
            DB::table('portfolio_cash_accounts')->where('id', $account->id)->update([
                'balance' => $newBalance,
                'updated_at' => now(),
            ]);
            if (abs($difference) > 0.001) {
                DB::table('portfolio_cash_ledger')->insert([
                    'portfolio_cash_account_id' => $account->id,
                    'type' => 'capital_adjustment',
                    'amount' => $difference,
                    'balance_after' => $newBalance,
                    'currency' => $account->currency,
                    'occurred_at' => now(),
                    'meta' => json_encode(['source' => 'manual_initial_capital_update', 'previous_capital' => $oldCapital], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', __('Das Startkapital wurde aktualisiert.'));
    }

    public function destroy(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active && $portfolio->type === 'paper', 404);
        $request->validate(['confirm_delete' => ['accepted']]);
        DB::transaction(function () use ($portfolio): void {
            DB::table('python_engine_jobs')
                ->whereIn('status', ['queued', 'running'])
                ->whereRaw("payload->>'portfolio_id' = ?", [(string) $portfolio->id])
                ->update(['status' => 'cancelled', 'finished_at' => now(), 'updated_at' => now()]);
            $wasDefault = (bool) $portfolio->is_default;
            $userId = (int) $portfolio->user_id;
            $portfolio->delete();
            if ($wasDefault) {
                $nextPortfolioId = Portfolio::query()->where('user_id', $userId)->where('active', true)->orderBy('id')->value('id');
                if ($nextPortfolioId) Portfolio::query()->whereKey($nextPortfolioId)->update(['is_default' => true]);
            }
        });

        return redirect()->route('paper-depots.index')->with('status', __('Das Musterdepot wurde gelöscht.'));
    }

    private function clearPortfolioHistory(Portfolio $portfolio, string $source): float
    {
        $account = DB::table('portfolio_cash_accounts')->where('portfolio_id', $portfolio->id)->lockForUpdate()->first();
        abort_if($account === null, 422, __('Das Verrechnungskonto fehlt.'));
        $initial = max(1000.0, (float) data_get($portfolio->meta, 'automation.initial_capital', 10000));
        DB::table('python_engine_jobs')->whereIn('status', ['queued', 'running'])
            ->whereRaw("payload->>'portfolio_id' = ?", [(string) $portfolio->id])
            ->update(['status' => 'cancelled', 'finished_at' => now(), 'updated_at' => now()]);
        DB::table('portfolio_cash_ledger')->where('portfolio_cash_account_id', $account->id)->delete();
        DB::table('portfolio_automation_executions')->where('portfolio_id', $portfolio->id)->delete();
        DB::table('portfolio_transactions')->where('portfolio_id', $portfolio->id)->delete();
        DB::table('portfolio_positions')->where('portfolio_id', $portfolio->id)->delete();
        DB::table('portfolio_simulation_runs')->where('portfolio_id', $portfolio->id)->delete();
        DB::table('portfolio_cash_accounts')->where('id', $account->id)->update(['balance' => $initial, 'reserved_balance' => 0, 'updated_at' => now()]);
        DB::table('portfolio_cash_ledger')->insert([
            'portfolio_cash_account_id' => $account->id, 'type' => 'initial_deposit',
            'amount' => $initial, 'balance_after' => $initial, 'currency' => $account->currency,
            'occurred_at' => now(), 'meta' => json_encode(['source' => $source], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $initial;
    }
}

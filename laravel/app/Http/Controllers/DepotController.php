<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Support\AiScore;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DepotController extends Controller
{
    public function index(Request $request): View
    {
        $portfolios = $this->portfolios((int) $request->user()->id);
        $paperMode = false;
        $stockInstrumentIds = $this->stockInstrumentIds();
        $strategyTemplates = $this->realStrategyTemplates();

        return view('depots.index', compact('portfolios', 'paperMode', 'stockInstrumentIds', 'strategyTemplates'));
    }

    public function paperIndex(Request $request): View
    {
        $portfolios = $this->portfolios((int) $request->user()->id, 'paper');
        $paperMode = true;
        $stockInstrumentIds = collect();
        $strategyTemplates = [];

        return view('depots.index', compact('portfolios', 'paperMode', 'stockInstrumentIds', 'strategyTemplates'));
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
            ->with(['positions' => fn ($query) => $query
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
                $currentValue = $portfolio->positions->sum(
                    fn ($position) => (float) $position->quantity
                        * (float) ($position->current_price ?? $position->average_buy_price)
                );

                $portfolio->setAttribute('invested_value', $invested);
                $portfolio->setAttribute('current_value', $currentValue);
                $portfolio->setAttribute(
                    'performance_percent',
                    $invested > 0 ? (($currentValue - $invested) / $invested) * 100 : 0.0
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

            Portfolio::query()->create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? null,
                'is_default' => $isFirst,
                'active' => true,
            ]);
        });

        return back()->with('status', 'portfolio-created');
    }

    public function show(Request $request, Portfolio $portfolio): View
    {
        abort_unless((int) $portfolio->user_id === (int) $request->user()->id && $portfolio->active, 404);

        $portfolio->load(['positions' => fn ($query) => $query
            ->whereHas('instrument', fn ($instrument) => $instrument
                ->where('is_active', true)
                ->whereNull('deleted_at'))
            ->with('instrument')]);
        $invested = $portfolio->positions->sum(
            fn ($position) => (float) $position->quantity * (float) $position->average_buy_price
        );
        $currentValue = $portfolio->positions->sum(
            fn ($position) => (float) $position->quantity
                * (float) ($position->current_price ?? $position->average_buy_price)
        );
        $performance = $invested > 0 ? (($currentValue - $invested) / $invested) * 100 : 0.0;
        $returnToPaper = $portfolio->type === 'paper' && $request->query('return_to') === 'paper';
        $backUrl = $returnToPaper ? route('paper-depots.index') : route('depots.index');
        $backLabel = $returnToPaper ? __('Zurück zu Musterdepots') : __('Zurück zu Depots');

        return view('depots.show', compact('portfolio', 'invested', 'currentValue', 'performance', 'backUrl', 'backLabel'));
    }
}

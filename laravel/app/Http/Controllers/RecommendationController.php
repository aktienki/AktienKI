<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use App\Support\AiScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class RecommendationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', $request->user());
        $country = strtoupper(substr(trim((string) $request->query('country')), 0, 2));
        $sector = trim((string) $request->query('sector'));
        $exchangeId = max(0, $request->integer('exchange'));
        $selectionDate = DB::table('daily_top_stock_selections')
            ->max('selection_date');
        $latestQualityRankings = DB::table('model_quality_rankings')
            ->selectRaw('trained_model_id, MAX(id) AS ranking_id')
            ->groupBy('trained_model_id');
        $recommendations = DB::table('predictions as prediction')
            ->join('daily_top_stock_selections as daily_selection', function ($join) use ($selectionDate): void {
                $join->on('daily_selection.prediction_id', '=', 'prediction.id')
                    ->where('daily_selection.selection_date', '=', $selectionDate);
            })
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('exchanges as instrument_exchange', 'instrument_exchange.id', '=', 'instrument.exchange_id')
            ->leftJoin('trained_models as trained_model', 'trained_model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
            ->leftJoinSub($latestQualityRankings, 'latest_quality', fn ($join) =>
                $join->on('latest_quality.trained_model_id', '=', 'trained_model.id'))
            ->leftJoin('model_quality_rankings as model_quality', 'model_quality.id', '=', 'latest_quality.ranking_id')
            ->leftJoin('model_quality_tiers as model_tier', 'model_tier.id', '=', 'model_quality.tier_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('prediction.prediction_score')
            ->whereNotNull('prediction.confidence')
            ->whereNotNull('prediction.current_price')
            ->where('prediction.current_price', '>', 0)
            ->where('prediction.quality_gate_passed', true)
            ->when($country !== '', fn ($query) => $query->where('instrument.country', $country))
            ->when($sector !== '', fn ($query) => $query->where('instrument.sector', $sector))
            ->when($exchangeId > 0, fn ($query) => $query->where('instrument.exchange_id', $exchangeId))
            ->select([
                'prediction.id as prediction_id',
                'daily_selection.rank as selection_rank',
                'daily_selection.selection_date',
                'daily_selection.recommendation_score as stored_recommendation_score',
                'daily_selection.risk_percent as stored_risk_percent',
                'prediction.instrument_id',
                'prediction.prediction_time',
                'prediction.current_price',
                'prediction.predicted_price_5d',
                'prediction.predicted_price_20d',
                'prediction.economic_edge_return',
                'prediction.prediction_score',
                'prediction.confidence',
                'prediction.risk_score',
                'prediction.drawdown_risk_factor',
                'instrument.symbol',
                'instrument.name',
                'instrument.country',
                'instrument.sector',
                'instrument.currency',
                'instrument_exchange.code as exchange_code',
                'instrument_exchange.name as exchange_name',
                'model_definition.public_alias as model_alias',
                'model_quality.quality_score as model_quality_score',
                'model_quality.eligible as model_quality_eligible',
                'model_tier.code as model_tier_code',
                'model_tier.name as model_tier_name',
            ])
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->get()
            ->map(fn (object $row): object => $this->score($row))
            ->sortBy('selection_rank')
            ->take(3)
            ->values();

        $recommendations = $recommendations
            ->map(function (object $recommendation): object {
                $recommendation->candles = DB::table('price_bars')
                    ->where('instrument_id', $recommendation->instrument_id)
                    ->where('interval', '1d')
                    ->orderByDesc('bar_time')
                    ->limit(100)
                    ->get(['bar_time', 'open', 'high', 'low', 'close'])
                    ->unique(fn (object $bar): string => \Illuminate\Support\Carbon::parse($bar->bar_time)->format('Y-m-d'))
                    ->take(32)
                    ->reverse()
                    ->values()
                    ->map(fn (object $bar): array => [
                        'x' => \Illuminate\Support\Carbon::parse($bar->bar_time)->getTimestampMs(),
                        'y' => [
                            (float) $bar->open,
                            (float) $bar->high,
                            (float) $bar->low,
                            (float) $bar->close,
                        ],
                    ]);

                return $recommendation;
            });

        $countries = DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('country')
            ->where('country', '<>', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $sectors = DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('sector')
            ->where('sector', '<>', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector');

        $exchanges = DB::table('exchanges as exchange')
            ->join('instruments as instrument', 'instrument.exchange_id', '=', 'exchange.id')
            ->where('exchange.is_active', true)
            ->where('exchange.code', '<>', 'UNKNOWN')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->select([
                'exchange.id',
                'exchange.code',
                'exchange.name',
                'exchange.country',
                DB::raw('COUNT(instrument.id) AS stocks_count'),
            ])
            ->groupBy('exchange.id', 'exchange.code', 'exchange.name', 'exchange.country')
            ->orderBy('exchange.name')
            ->get();

        $userWatchlists = DB::table('watchlists')
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $watchlistMemberships = $userWatchlists->isEmpty() || $recommendations->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->whereIn('instrument_id', $recommendations->pluck('instrument_id'))
                ->get(['instrument_id', 'watchlist_id'])
                ->groupBy('instrument_id')
                ->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id));

        return view('recommendations.index', [
            'recommendations' => $recommendations,
            'countries' => $countries,
            'sectors' => $sectors,
            'exchanges' => $exchanges,
            'country' => $country,
            'sector' => $sector,
            'exchangeId' => $exchangeId,
            'userWatchlists' => $userWatchlists,
            'watchlistMemberships' => $watchlistMemberships,
        ]);
    }

    private function score(object $row): object
    {
        $scorePercent = AiScore::toPercent($row->prediction_score) ?? 0.0;
        $confidencePercent = $this->percentage($row->confidence) ?? 0.0;
        $riskPercent = is_numeric($row->stored_risk_percent ?? null)
            ? max(0.0, min(100.0, (float) $row->stored_risk_percent))
            : ($this->percentage($row->risk_score ?? $row->drawdown_risk_factor) ?? 50.0);
        $expectedReturn = match (true) {
            (float) $row->current_price !== 0.0 && is_numeric($row->predicted_price_20d) =>
                (((float) $row->predicted_price_20d - (float) $row->current_price) / (float) $row->current_price) * 100,
            (float) $row->current_price !== 0.0 && is_numeric($row->predicted_price_5d) =>
                (((float) $row->predicted_price_5d - (float) $row->current_price) / (float) $row->current_price) * 100,
            default => $this->returnPercent($row->economic_edge_return),
        };

        // Renditen zwischen -10 % und +20 % werden auf eine robuste 0–100-Skala begrenzt.
        $returnScore = max(0.0, min(100.0, (($expectedReturn + 10.0) / 30.0) * 100.0));

        $row->score_percent = $scorePercent;
        $row->score_10 = $scorePercent / 10;
        $row->confidence_percent = $confidencePercent;
        $row->risk_percent = $riskPercent;
        $row->expected_return_20d = $expectedReturn;
        $calculatedScore = round(
            ($scorePercent * 0.40)
            + ($confidencePercent * 0.25)
            + ((100.0 - $riskPercent) * 0.20)
            + ($returnScore * 0.15),
            1
        );
        $row->recommendation_score = is_numeric($row->stored_recommendation_score ?? null)
            ? round((float) $row->stored_recommendation_score, 1)
            : $calculatedScore;

        return $row;
    }

    /**
     * Combine the latest technical snapshot with its historical signal bucket.
     */
    private function combineIndicatorStatistics(
        ?object $technical,
        \Illuminate\Support\Collection $statistics,
        float $currentPrice,
    ): array
    {
        if (! $technical) {
            return [];
        }

        $trend = (float) $technical->sma_20 >= (float) $technical->sma_50 ? 'bull' : 'bear';
        $volatility = (float) $technical->volatility_20;
        $regime = $trend.'_'.($volatility >= 0.40 ? 'high_vol' : 'normal');
        $side = 'long';
        $momentumBase = $currentPrice - (float) $technical->momentum_10;
        $values = [
            'rsi_14' => $technical->rsi_14,
            'adx_14' => $technical->adx_14,
            'stochastic_k' => $technical->stochastic_k,
            'volatility_20' => $technical->volatility_20,
            'atr_14_pct' => $currentPrice > 0 ? (float) $technical->atr_14 / $currentPrice : null,
            'bollinger_width' => $technical->bollinger_width,
            'macd_histogram_pct' => $currentPrice > 0 ? (float) $technical->macd_histogram / $currentPrice : null,
            'momentum_10_pct' => abs($momentumBase) > 0.000001 ? (float) $technical->momentum_10 / $momentumBase : null,
        ];

        return collect($values)->mapWithKeys(function (mixed $rawValue, string $indicator) use ($statistics, $regime, $side): array {
            if (! is_numeric($rawValue)) {
                return [$indicator => null];
            }

            $value = (float) $rawValue;
            $bucket = $statistics->get($indicator.'|'.$regime.'|'.$side, collect())
                ->first(fn (object $row): bool =>
                    ($row->value_lower === null || $value >= (float) $row->value_lower)
                    && ($row->value_upper === null || $value < (float) $row->value_upper)
                );

            return [$indicator => [
                'value' => $value,
                'signal_score' => $bucket ? (float) $bucket->signal_score : null,
                'hit_rate' => $bucket ? (float) $bucket->hit_rate * 100 : null,
                'mean_return' => $bucket ? (float) $bucket->mean_return * 100 : null,
                'sample_size' => $bucket ? (int) $bucket->sample_size : null,
                'eligible' => $bucket ? (bool) $bucket->eligible : false,
            ]];
        })->all();
    }

    private function percentage(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return max(0.0, min(100.0, $number <= 1.0 ? $number * 100.0 : $number));
    }

    private function returnPercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $number = (float) $value;

        return abs($number) <= 1.0 ? $number * 100.0 : $number;
    }
}

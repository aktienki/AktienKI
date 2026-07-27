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
        $latestPredictions = DB::table('predictions as latest_prediction')
            ->selectRaw('DISTINCT ON (latest_prediction.instrument_id) latest_prediction.id')
            ->orderBy('latest_prediction.instrument_id')
            ->orderByRaw('latest_prediction.prediction_time DESC NULLS LAST')
            ->orderByDesc('latest_prediction.id');

        $recommendations = DB::table('predictions as prediction')
            ->joinSub($latestPredictions, 'latest', fn ($join) => $join->on('latest.id', '=', 'prediction.id'))
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->where('instrument.type', 'stock')
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('prediction.prediction_score')
            ->whereNotNull('prediction.confidence')
            ->whereNotNull('prediction.current_price')
            ->where('prediction.current_price', '>', 0)
            ->when($country !== '', fn ($query) => $query->where('instrument.country', $country))
            ->when($sector !== '', fn ($query) => $query->where('instrument.sector', $sector))
            ->when($exchangeId > 0, fn ($query) => $query->where('instrument.exchange_id', $exchangeId))
            ->select([
                'prediction.id as prediction_id',
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
            ])
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->get()
            ->map(fn (object $row): object => $this->score($row))
            ->sortByDesc('recommendation_score')
            ->take(3)
            ->values()
            ->map(function (object $recommendation): object {
                $recommendation->candles = DB::table('price_bars')
                    ->where('instrument_id', $recommendation->instrument_id)
                    ->where('interval', '1d')
                    ->orderByDesc('bar_time')
                    ->limit(32)
                    ->get(['bar_time', 'open', 'high', 'low', 'close'])
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
            ->whereNull('deleted_at')
            ->whereNotNull('country')
            ->where('country', '<>', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $sectors = DB::table('instruments')
            ->where('type', 'stock')
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
        $riskPercent = $this->percentage($row->risk_score ?? $row->drawdown_risk_factor) ?? 50.0;
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
        $row->recommendation_score = round(
            ($scorePercent * 0.40)
            + ($confidencePercent * 0.25)
            + ((100.0 - $riskPercent) * 0.20)
            + ($returnScore * 0.15),
            1
        );

        return $row;
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

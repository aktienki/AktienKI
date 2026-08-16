<?php

namespace App\Console\Commands;

use App\Http\Controllers\RecommendationController;
use App\Models\User;
use App\Notifications\RecommendationDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\AiScore;

final class SendRecommendationDigest extends Command
{
    protected $signature = 'recommendations:send-digest
        {--user= : User ID; defaults to the most recently logged-in account}
        {--demo-fill=0 : Fill the preview with additional current candidates up to this total}';
    protected $description = 'Send a single email containing the current qualified Top recommendations';

    public function handle(RecommendationController $controller): int
    {
        $user = User::query()
            ->whereNotNull('email')
            ->when($this->option('user'), fn ($query) => $query->whereKey((int) $this->option('user')))
            ->orderByDesc('last_login_at')->orderByDesc('id')->firstOrFail();
        $request = Request::create('/recommendations', 'GET');
        $request->setUserResolver(fn () => $user);
        $recommendations = collect($controller($request)->getData()['recommendations'] ?? [])
            ->take(3)->map(fn (object $item): array => $this->payload($item));
        $fillTo = min(5, max(0, (int) $this->option('demo-fill')));
        if ($fillTo > $recommendations->count()) {
            $recommendations = $recommendations->concat(
                $this->additionalCandidates($recommendations->pluck('prediction_id')->all(), $fillTo - $recommendations->count())
            )->take($fillTo)->values();
        }
        $recommendations = $recommendations->all();

        if ($recommendations === []) {
            $this->error('No qualified recommendations are currently available.');
            return self::FAILURE;
        }

        $user->notifyNow(new RecommendationDigestNotification($recommendations));
        $this->info('Recommendation digest sent with '.count($recommendations).' signal(s).');
        return self::SUCCESS;
    }

    private function payload(object $item): array
    {
        return [
            'instrument_id' => (int) $item->instrument_id,
            'prediction_id' => (int) $item->prediction_id,
            'name' => (string) $item->name,
            'symbol' => (string) $item->symbol,
            'currency' => (string) ($item->currency ?: 'EUR'),
            'signal' => (string) ($item->personalized_signal ?: 'BUY'),
            'current_price' => (float) $item->current_price,
            'target_price' => is_numeric($item->predicted_price_20d) ? (float) $item->predicted_price_20d : null,
            'expected_return' => is_numeric($item->expected_return_20d) ? (float) $item->expected_return_20d : null,
            'score' => (float) $item->score_10,
            'confidence' => (float) $item->confidence_percent,
            'risk' => (float) $item->risk_percent,
            'quality_tier' => (string) ($item->model_tier_name ?: 'Quality Gate'),
            'model_alias' => (string) ($item->model_alias ?: 'aKI Ensemble'),
            'prediction_date' => Carbon::parse($item->prediction_time)->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'analysis_url' => route('stocks.show', ['symbol' => $item->symbol, 'prediction' => $item->prediction_id]),
            'candles' => $item->candles->all(),
        ];
    }

    private function additionalCandidates(array $excludedPredictionIds, int $limit): array
    {
        if ($limit <= 0) return [];

        $selectionDate = DB::table('daily_top_stock_selections')->max('selection_date');
        $rows = DB::table('daily_top_stock_selections as selection')
            ->join('predictions as prediction', 'prediction.id', '=', 'selection.prediction_id')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->leftJoin('trained_models as model', 'model.id', '=', 'prediction.trained_model_id')
            ->leftJoin('model_definitions as definition', 'definition.id', '=', 'model.model_definition_id')
            ->where('selection.selection_date', $selectionDate)
            ->where('instrument.is_active', true)->where('instrument.is_german_tradeable', true)->whereNull('instrument.deleted_at')
            ->whereNotIn('prediction.id', $excludedPredictionIds ?: [0])
            ->orderBy('selection.rank')->limit($limit)
            ->get([
                'prediction.id as prediction_id', 'prediction.instrument_id', 'prediction.prediction_time',
                'prediction.current_price', 'prediction.predicted_price_20d', 'prediction.prediction_score',
                'prediction.confidence', 'prediction.risk_score', 'prediction.drawdown_risk_factor',
                'prediction.signal', 'prediction.quality_gate_passed', 'instrument.name', 'instrument.symbol',
                'instrument.currency', 'definition.public_alias as model_alias',
            ]);

        if ($rows->count() < $limit) {
            $latestPredictionIds = DB::table('predictions')
                ->selectRaw('MAX(id) AS prediction_id')->groupBy('instrument_id');
            $fallback = DB::table('predictions as prediction')
                ->joinSub($latestPredictionIds, 'latest', fn ($join) => $join->on('latest.prediction_id', '=', 'prediction.id'))
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->leftJoin('trained_models as model', 'model.id', '=', 'prediction.trained_model_id')
                ->leftJoin('model_definitions as definition', 'definition.id', '=', 'model.model_definition_id')
                ->where('instrument.is_active', true)->where('instrument.is_german_tradeable', true)->whereNull('instrument.deleted_at')
                ->whereNotIn('prediction.id', array_merge($excludedPredictionIds, $rows->pluck('prediction_id')->all(), [0]))
                ->whereRaw('prediction.predicted_price_20d > prediction.current_price')
                ->whereIn('prediction.signal', ['BUY', 'WATCH'])
                ->orderByDesc('prediction.prediction_score')
                ->limit($limit - $rows->count())
                ->get([
                    'prediction.id as prediction_id', 'prediction.instrument_id', 'prediction.prediction_time',
                    'prediction.current_price', 'prediction.predicted_price_20d', 'prediction.prediction_score',
                    'prediction.confidence', 'prediction.risk_score', 'prediction.drawdown_risk_factor',
                    'prediction.signal', 'prediction.quality_gate_passed', 'instrument.name', 'instrument.symbol',
                    'instrument.currency', 'definition.public_alias as model_alias',
                ]);
            $rows = $rows->concat($fallback)->take($limit)->values();
        }

        return $rows->map(function (object $item): array {
                $current = (float) $item->current_price;
                $target = is_numeric($item->predicted_price_20d) ? (float) $item->predicted_price_20d : null;
                $confidence = (float) $item->confidence;
                if ($confidence <= 1) $confidence *= 100;
                $risk = (float) ($item->risk_score ?? $item->drawdown_risk_factor ?? 0);
                if ($risk <= 1) $risk *= 100;
                $candles = DB::table('price_bars')->where('instrument_id', $item->instrument_id)
                    ->where('interval', '1d')->orderByDesc('bar_time')->limit(32)
                    ->get(['bar_time', 'open', 'high', 'low', 'close'])->reverse()->values()
                    ->map(fn (object $bar): array => [
                        'x' => Carbon::parse($bar->bar_time)->getTimestampMs(),
                        'y' => [(float) $bar->open, (float) $bar->high, (float) $bar->low, (float) $bar->close],
                    ])->all();

                return [
                    'instrument_id' => (int) $item->instrument_id,
                    'prediction_id' => (int) $item->prediction_id,
                    'name' => (string) $item->name,
                    'symbol' => (string) $item->symbol,
                    'currency' => (string) ($item->currency ?: 'EUR'),
                    'signal' => strtoupper((string) ($item->signal ?: 'HOLD')),
                    'current_price' => $current,
                    'target_price' => $target,
                    'expected_return' => $target !== null && $current > 0 ? (($target / $current) - 1) * 100 : null,
                    'score' => (AiScore::toPercent($item->prediction_score) ?? 0) / 10,
                    'confidence' => max(0, min(100, $confidence)),
                    'risk' => max(0, min(100, $risk)),
                    'quality_tier' => $item->quality_gate_passed ? 'Quality Gate' : __('Kandidat'),
                    'model_alias' => (string) ($item->model_alias ?: 'aKI Ensemble'),
                    'prediction_date' => Carbon::parse($item->prediction_time)->timezone('Europe/Berlin')->format('d.m.Y H:i'),
                    'analysis_url' => route('stocks.show', ['symbol' => $item->symbol, 'prediction' => $item->prediction_id]),
                    'candles' => $candles,
                ];
            })->all();
    }
}

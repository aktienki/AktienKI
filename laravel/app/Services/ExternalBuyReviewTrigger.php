<?php

namespace App\Services;

use App\Jobs\GenerateExternalBuyReview;
use App\Models\ExternalBuyReview;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ExternalBuyReviewTrigger
{
    public function __construct(private readonly ServingReadService $serving) {}

    /**
     * Attach the independent review to the same transition already detected
     * by SignalEmailService. The latest-prediction guard prevents stale rows
     * from a multi-horizon batch from starting duplicate web research.
     */
    public function queueForTransition(Prediction $prediction, Prediction $previous): bool
    {
        if (! config('aktienki.external_buy_review.enabled', false)
            || ! Schema::hasTable('external_buy_reviews')
            || strtoupper((string) $prediction->signal) !== 'BUY'
            || strtoupper((string) $previous->signal) === 'BUY') {
            return false;
        }

        $instrument = $prediction->instrument;
        if (! $instrument
            || $instrument->type !== 'stock'
            || ! $instrument->is_active
            || $instrument->deleted_at !== null) {
            return false;
        }

        $latestPredictionId = Prediction::query()
            ->where('instrument_id', $prediction->instrument_id)
            ->orderByDesc('prediction_time')
            ->orderByDesc('id')
            ->value('id');
        if ((int) $latestPredictionId !== (int) $prediction->id) {
            return false;
        }

        $exchange = $instrument->exchange_id
            ? DB::table('exchanges')->where('id', $instrument->exchange_id)->first(['code', 'mic', 'name', 'country'])
            : null;
        $identity = array_filter([
            'company_name' => $instrument->name,
            'ticker' => $instrument->symbol,
            'isin' => $instrument->isin,
            'exchange_code' => $exchange?->code,
            'exchange_mic' => $exchange?->mic,
            'exchange_name' => $exchange?->name,
            'country' => $instrument->country ?: $exchange?->country,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $confidence = is_numeric($prediction->confidence) ? (float) $prediction->confidence : null;
        if ($confidence !== null && $confidence <= 1) $confidence *= 100;
        $expectedReturn = is_numeric($prediction->predicted_return)
            ? (float) $prediction->predicted_return * (abs((float) $prediction->predicted_return) <= 1 ? 100 : 1)
            : ($prediction->current_price && is_numeric($prediction->predicted_price)
                ? (((float) $prediction->predicted_price / (float) $prediction->current_price) - 1) * 100
                : null);
        $features = (array) ($prediction->features ?? []);
        $mlAnalysis = array_filter([
            'ticker' => $instrument->symbol,
            'signal' => 'BUY',
            'prediction_horizon_days' => is_numeric($prediction->prediction_horizon_minutes)
                ? round((float) $prediction->prediction_horizon_minutes / 1440, 1) : null,
            'expected_return_percent' => $expectedReturn,
            'confidence_percent' => $confidence,
            'ai_score' => $prediction->ai_score ?? $prediction->prediction_score,
            'risk_score' => $prediction->risk_score,
            'profit_factor' => $instrument->risk_profit_factor,
            'historical_hit_rate_percent' => data_get($prediction->meta, 'hit_rate')
                ?? data_get($features, 'hit_rate'),
            'max_drawdown_percent' => $instrument->risk_max_drawdown,
            'trend' => data_get($prediction->meta, 'trend') ?? data_get($features, 'trend'),
            'momentum' => data_get($prediction->meta, 'momentum') ?? data_get($features, 'momentum'),
            'rsi' => $prediction->rsi ?? data_get($features, 'rsi'),
            'macd' => $prediction->macd ?? data_get($features, 'macd'),
            'quality_gate_passed' => $prediction->quality_gate_passed,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $mlAnalysis = [...$mlAnalysis, ...$this->servingAnalysis((string) $instrument->symbol)];

        $review = ExternalBuyReview::query()->firstOrCreate(
            ['prediction_id' => $prediction->id],
            [
                'instrument_id' => $prediction->instrument_id,
                'previous_prediction_id' => $previous->id,
                'triggered_at' => $prediction->prediction_time,
                'status' => 'pending',
                'provider' => 'openai',
                'model' => (string) config('aktienki.external_buy_review.model', 'gpt-5.6-luna'),
                'prompt_version' => (string) config('aktienki.external_buy_review.prompt_version', 'buy-twelve-data-luna-v3'),
                'request_identity' => $identity,
                'signal_scope' => [
                    'ai_type' => $prediction->ai_type,
                    'position_side' => $prediction->position_side,
                    'timeframe' => $prediction->timeframe,
                    'model_scope' => $prediction->model_scope,
                    'prediction_horizon_minutes' => $prediction->prediction_horizon_minutes,
                    'aktienki_ml_analysis' => $mlAnalysis,
                ],
            ],
        );

        if (! $review->wasRecentlyCreated) {
            return false;
        }

        GenerateExternalBuyReview::dispatch($review->id);

        return true;
    }

    /** @return array<string, mixed> */
    private function servingAnalysis(string $symbol): array
    {
        try {
            $stock = $this->serving->stock($symbol);
            $prediction = $stock?->latest_prediction;
            if (! $stock || ! $prediction) return [];

            $horizon = collect($stock->horizons ?? [])->first(
                fn (array $item): bool => (int) ($item['horizon'] ?? 0) === (int) $prediction->horizon
            );
            $metrics = (array) data_get($horizon, 'active_model.metrics', []);
            $expectedReturn = is_numeric($prediction->expected_return_percent ?? null)
                ? (float) $prediction->expected_return_percent : null;

            return array_filter([
                'data_source' => 'remote_serving_database',
                'prediction_horizon_days' => is_numeric($prediction->horizon) ? (int) $prediction->horizon : null,
                'expected_return_percent' => $expectedReturn,
                'confidence_percent' => is_numeric($prediction->confidence_percent ?? null) ? (float) $prediction->confidence_percent : null,
                'current_price' => is_numeric($prediction->current_price ?? null) ? (float) $prediction->current_price : null,
                'target_price' => is_numeric($prediction->target_price ?? null) ? (float) $prediction->target_price : null,
                'profit_factor' => is_numeric($metrics['profit_factor'] ?? null) ? (float) $metrics['profit_factor'] : null,
                'historical_hit_rate_percent' => is_numeric($metrics['hit_rate'] ?? null) ? (float) $metrics['hit_rate'] * 100 : null,
                'max_drawdown_percent' => is_numeric($metrics['max_drawdown'] ?? null) ? abs((float) $metrics['max_drawdown']) * 100 : null,
            ], static fn ($value): bool => $value !== null && $value !== '');
        } catch (Throwable) {
            return [];
        }
    }
}

<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\SavedPredictionFilter;
use App\Models\SmartSelectionLabel;
use App\Models\SignalEmailDelivery;
use App\Models\EasyAccessSubscriber;
use App\Notifications\SignalChangedNotification;
use App\Notifications\SmartSelectionSignalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class SignalEmailService
{
    public function __construct(private readonly UserQualityGateService $qualityGates) {}

    public function scan(int $minutes = 1440): array
    {
        $stats = ['checked' => 0, 'changes' => 0, 'queued' => 0, 'skipped' => 0];

        Prediction::query()
            ->with('instrument')
            ->where('prediction_time', '>=', now()->subMinutes(max(1, $minutes)))
            ->whereHas('instrument', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('id')
            ->chunkById(250, function ($predictions) use (&$stats): void {
                foreach ($predictions as $prediction) {
                    $stats['checked']++;
                    $previous = Prediction::query()
                        ->where('instrument_id', $prediction->instrument_id)
                        ->where('prediction_time', '<', $prediction->prediction_time)
                        ->orderByDesc('prediction_time')->orderByDesc('id')
                        ->first(['id', 'signal']);

                    if (! $previous || strtoupper((string) $previous->signal) === strtoupper((string) $prediction->signal)) {
                        $stats['skipped']++;
                        continue;
                    }
                    $stats['changes']++;

                    SavedPredictionFilter::query()
                        ->with('user')
                        ->where(function (Builder $query): void {
                            $query->where('email_notification_enabled', true)
                                ->orWhereExists(function ($subscriberQuery): void {
                                    $subscriberQuery->select(DB::raw('1'))
                                        ->from('easy_access_subscribers')
                                        ->whereColumn('easy_access_subscribers.saved_prediction_filter_id', 'saved_prediction_filters.id')
                                        ->where('easy_access_subscribers.is_active', true);
                                });
                        })
                        ->whereHas('user')
                        ->chunkById(100, function ($strategies) use ($prediction, $previous, &$stats): void {
                            foreach ($strategies as $strategy) {
                                if (! data_get($strategy->user->preferences, 'email_service', true)
                                    || ! $this->matches($strategy, $prediction)) {
                                    continue;
                                }

                                try {
                                    $delivery = SignalEmailDelivery::query()->create([
                                        'user_id' => $strategy->user_id,
                                        'saved_prediction_filter_id' => $strategy->id,
                                        'prediction_id' => $prediction->id,
                                        'instrument_id' => $prediction->instrument_id,
                                        'previous_signal' => strtoupper((string) $previous->signal),
                                        'new_signal' => strtoupper((string) $prediction->signal),
                                        'status' => 'queued',
                                        'queued_at' => now(),
                                    ]);
                                } catch (QueryException $exception) {
                                    if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) continue;
                                    throw $exception;
                                }

                                $strategy->user->notify(new SignalChangedNotification(
                                    $prediction, $strategy, (string) $previous->signal, $delivery->id
                                ));
                                $stats['queued']++;

                                // Easy Access subscriptions are intentionally limited to
                                // the official Top-3 selection, never to every strategy hit.
                                if ($this->isTopThreeSignal($prediction)) {
                                    EasyAccessSubscriber::query()
                                        ->where('saved_prediction_filter_id', $strategy->id)
                                        ->where('is_active', true)
                                        ->each(function (EasyAccessSubscriber $subscriber) use ($prediction, $previous, $strategy, &$stats): void {
                                            $subscriber->notify(new SignalChangedNotification(
                                                $prediction, $strategy, (string) $previous->signal, 0
                                            ));
                                            $stats['queued']++;
                                        });
                                }
                            }
                        });

                    if (strtoupper((string) $prediction->signal) === 'BUY') {
                        SmartSelectionLabel::query()
                            ->with('user')
                            ->where('is_active', true)
                            ->whereRaw("COALESCE((criteria->>'email_notification_enabled')::boolean, false) = true")
                            ->whereHas('user')
                            ->chunkById(100, function ($labels) use ($prediction, $previous, &$stats): void {
                                foreach ($labels as $label) {
                                    if (! data_get($label->user->preferences, 'email_service', true)
                                        || ! $this->matchesSmartLabel($label, $prediction)) {
                                        continue;
                                    }
                                    $label->user->notify(new SmartSelectionSignalNotification(
                                        $prediction, $label, (string) $previous->signal
                                    ));
                                    $stats['queued']++;
                                }
                            });
                    }
                }
            });

        return $stats;
    }

    private function matchesSmartLabel(SmartSelectionLabel $label, Prediction $prediction): bool
    {
        $criteria = (array) $label->criteria;
        $score = (float) ($prediction->ai_score ?? $prediction->prediction_score ?? 0);
        if ($score > 10) $score /= 10;
        $confidence = (float) ($prediction->confidence ?? 0);
        if ($confidence <= 1) $confidence *= 100;
        $current = (float) ($prediction->current_price ?? 0);
        $target = (float) ($prediction->predicted_price_20d ?? 0);
        $return = $current > 0 && $target > 0 ? (($target / $current) - 1) * 100 : null;

        return $score >= (float) ($criteria['score_min'] ?? 0)
            && $confidence >= (float) ($criteria['confidence_min'] ?? 0)
            && ($return === null || $return >= (float) ($criteria['predicted_return_min'] ?? -20));
    }

    private function isTopThreeSignal(Prediction $prediction): bool
    {
        return DB::table('daily_top_stock_selections')
            ->where('prediction_id', $prediction->id)
            ->where('rank', '<=', 3)
            ->exists();
    }

    private function matches(SavedPredictionFilter $strategy, Prediction $prediction): bool
    {
        $filters = (array) $strategy->filters;
        $instrument = $prediction->instrument;

        if ($strategy->watchlist_id && ! DB::table('watchlist_items')->where('watchlist_id', $strategy->watchlist_id)->where('instrument_id', $prediction->instrument_id)->exists()) return false;
        if ($strategy->portfolio_id && ! DB::table('portfolio_positions')->where('portfolio_id', $strategy->portfolio_id)->where('instrument_id', $prediction->instrument_id)->exists()) return false;
        if (($filters['country'] ?? '') !== '' && strtoupper((string) $instrument->country) !== strtoupper((string) $filters['country'])) return false;
        if (($filters['sector'] ?? '') !== '' && (string) $instrument->sector !== (string) $filters['sector']) return false;
        if (($filters['exchange'] ?? '') !== '' && (string) $instrument->exchange_id !== (string) $filters['exchange']) return false;
        if (($filters['signal'] ?? '') !== '' && strtoupper((string) $prediction->signal) !== strtoupper((string) $filters['signal'])) return false;
        if (! empty($filters['model']) && ! in_array((int) $prediction->trained_model_id, array_map('intval', (array) $filters['model']), true)) return false;

        $score = (float) ($prediction->ai_score ?? 0);
        if ($score > 10) $score /= 10;
        $confidence = (float) ($prediction->confidence ?? 0);
        if ($confidence <= 1) $confidence *= 100;

        if ($score < (float) ($filters['score_min'] ?? 0) || $confidence < (float) ($filters['confidence_min'] ?? 0)) return false;

        $gate = $this->qualityGates->rules($strategy->user);
        if ($gate === null) return true;
        $risk = (float) ($prediction->risk_score ?? 0);
        if ($risk <= 1) $risk *= 100;
        $current = (float) ($prediction->current_price ?? 0);
        $target = (float) ($prediction->predicted_price_20d ?? 0);
        $return = $current > 0 && $target > 0 ? (($target / $current) - 1) * 100 : null;

        return $score >= (float) ($gate['score_min'] ?? 0)
            && $confidence >= (float) ($gate['confidence_min'] ?? 0)
            && $risk <= (float) ($gate['risk_max'] ?? 100)
            && ($return === null || $return >= (float) ($gate['predicted_return_min'] ?? -50))
            && (! ($gate['positive_prediction_required'] ?? false) || ($return !== null && $return > 0));
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only production boundary for FINAL entry signals.
 *
 * The database view owns the safety contract (ACCEPTED decision, raw BUY,
 * passed filters, ACTIVE lifecycle, verified non-stale session feed). This
 * service deliberately never reconstructs any of those rules in PHP.
 */
class FinalEntrySignalReadService
{
    public function enforced(): bool
    {
        return (bool) config('aktienki.final_entry_reads.enabled', false);
    }

    /**
     * Return active FINAL user-profile BUY decisions keyed by the Serving
     * instrument id used by screener/dashboard rows.
     *
     * @param  iterable<int|string>  $sourceInstrumentIds
     * @return Collection<int, object>
     */
    public function userProfileBySourceInstrument(int $userId, iterable $sourceInstrumentIds): Collection
    {
        return $this->forContext(
            $userId,
            'user',
            'source_instrument_id',
            $sourceInstrumentIds,
        );
    }

    /**
     * Return active FINAL user-profile BUY decisions keyed by the exact
     * Serving prediction id used by the model/prediction table.
     *
     * @param  iterable<int|string>  $sourcePredictionIds
     * @return Collection<int, object>
     */
    public function userProfileBySourcePrediction(int $userId, iterable $sourcePredictionIds): Collection
    {
        return $this->forContext(
            $userId,
            'user',
            'source_prediction_id',
            $sourcePredictionIds,
        );
    }

    /**
     * Return active FINAL user-profile BUY decisions keyed by the local
     * instrument id used by legacy notification records.
     *
     * @param  iterable<int|string>  $instrumentIds
     * @return Collection<int, object>
     */
    public function userProfileByInstrument(int $userId, iterable $instrumentIds): Collection
    {
        return $this->forContext(
            $userId,
            'user',
            'instrument_id',
            $instrumentIds,
        );
    }

    /**
     * Return active FINAL strategy BUY decisions keyed by the local instrument
     * id used by e-mail and portfolio-automation records.
     *
     * @param  iterable<int|string>  $instrumentIds
     * @return Collection<int, object>
     */
    public function savedFilterByInstrument(
        int $userId,
        int $savedPredictionFilterId,
        iterable $instrumentIds,
    ): Collection {
        if ($savedPredictionFilterId <= 0) {
            return collect();
        }

        return $this->forContext(
            $userId,
            'strategy:'.$savedPredictionFilterId,
            'instrument_id',
            $instrumentIds,
        );
    }

    public function allowsUserProfileBuy(int $userId, int $sourceInstrumentId): bool
    {
        if (! $this->enforced()) {
            return true;
        }

        return $this->userProfileBySourceInstrument($userId, [$sourceInstrumentId])->isNotEmpty();
    }

    public function allowsUserProfileBuyForLocalInstrument(int $userId, int $instrumentId): bool
    {
        if (! $this->enforced()) {
            return true;
        }

        return $this->userProfileByInstrument($userId, [$instrumentId])->isNotEmpty();
    }

    public function allowsSavedFilterBuy(
        int $userId,
        int $savedPredictionFilterId,
        int $instrumentId,
    ): bool {
        if (! $this->enforced()) {
            return true;
        }

        return $this->savedFilterByInstrument(
            $userId,
            $savedPredictionFilterId,
            [$instrumentId],
        )->isNotEmpty();
    }

    /**
     * Only BUY is an entry decision and may be demoted. Existing WATCH/HOLD/
     * SELL lifecycle semantics are passed through byte-for-byte (apart from
     * canonical upper-casing) and therefore cannot be promoted accidentally.
     */
    public function gateSignal(string $rawSignal, bool $hasAcceptedDecision): string
    {
        $signal = strtoupper(trim($rawSignal));

        if (! $this->enforced() || $signal !== 'BUY') {
            return $signal;
        }

        return $hasAcceptedDecision ? 'BUY' : 'WATCH';
    }

    /**
     * Project a stock-level current signal from the authoritative lifecycle.
     * An active accepted lifecycle remains BUY until the view removes it after
     * exit/expiry; without one, only a raw BUY is demoted to WATCH.
     */
    public function currentSignal(string $rawSignal, bool $hasActiveAcceptedLifecycle): string
    {
        $signal = strtoupper(trim($rawSignal));

        if (! $this->enforced()) {
            return $signal;
        }
        if ($hasActiveAcceptedLifecycle) {
            return 'BUY';
        }

        return $signal === 'BUY' ? 'WATCH' : $signal;
    }

    /**
     * @param  iterable<int|string>  $ids
     * @return Collection<int, object>
     */
    private function forContext(
        int $userId,
        string $contextKey,
        string $keyColumn,
        iterable $ids,
    ): Collection {
        if (! $this->enforced() || $userId <= 0) {
            return collect();
        }

        $ids = collect($ids)
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        // Do not catch database/view failures here. During enforced cutover a
        // missing or unreadable FINAL ledger must fail closed, never fall back
        // to an unfiltered raw BUY.
        return DB::table('current_final_entry_signals')
            ->where('user_id', $userId)
            ->where('context_key', $contextKey)
            ->whereIn($keyColumn, $ids->all())
            ->get([
                'decision_id', 'lifecycle_id', 'user_id', 'context_type',
                'context_key', 'saved_prediction_filter_id_snapshot',
                'instrument_id', 'source_prediction_id', 'source_instrument_id',
                'source_as_of', 'forecast_horizon_sessions', 'entry_consumed_at',
            ])
            ->keyBy(fn (object $row): int => (int) $row->{$keyColumn});
    }
}

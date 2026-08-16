<?php

namespace App\Services;

use App\Models\InstrumentExitProfile;

final class VariableExitStrategyService
{
    public const DEFAULT_HOLDING_DAYS = 20;

    public function resolve(int $instrumentId, string $strategyKey = 'combined_5_10_20'): array
    {
        $profile = InstrumentExitProfile::query()
            ->where('instrument_id', $instrumentId)
            ->where('strategy_key', $strategyKey)
            ->where('validation_status', 'validated')
            ->where('is_active', true)
            ->latest('validated_at')
            ->first();

        return [
            'holding_days' => $profile?->holding_days ?? self::DEFAULT_HOLDING_DAYS,
            'source' => $profile ? 'instrument_exit_profile' : 'default_20d',
            'profile_id' => $profile?->id,
            'model_signature' => $profile?->model_signature,
            'validated' => $profile !== null,
        ];
    }

    /**
     * Prefer the horizon with the highest current forecast target. Historical
     * exit profiles remain the fallback when the prediction has no usable
     * multi-horizon targets.
     */
    public function resolveForPrediction(int $instrumentId, object $prediction): array
    {
        $targets = collect([5, 10, 15, 20])
            ->mapWithKeys(function (int $days) use ($prediction): array {
                $value = $prediction->{'predicted_price_'.$days.'d'} ?? null;

                return [$days => is_numeric($value) && (float) $value > 0 ? (float) $value : null];
            })
            ->filter(fn (?float $value): bool => $value !== null);

        if ($targets->isNotEmpty()) {
            $holdingDays = (int) $targets->sortDesc()->keys()->first();

            return [
                'holding_days' => $holdingDays,
                'target_price' => (float) $targets->get($holdingDays),
                'source' => 'current_prediction_peak',
                'profile_id' => null,
                'model_signature' => null,
                'validated' => false,
            ];
        }

        return $this->resolve($instrumentId);
    }
}

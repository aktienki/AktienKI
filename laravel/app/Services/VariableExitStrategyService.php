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
}

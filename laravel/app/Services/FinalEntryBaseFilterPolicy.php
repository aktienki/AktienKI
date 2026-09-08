<?php

namespace App\Services;

use App\Models\User;

/**
 * Mandatory post-model entry policy for every user-facing BUY decision.
 *
 * The serving model first has to emit an immutable raw BUY. These rules are
 * then evaluated against that exact stock/model snapshot. A saved strategy
 * may only add stricter rules; it can never replace this baseline.
 */
final class FinalEntryBaseFilterPolicy
{
    public const VERSION = 'user-profile-entry-v2';

    private const MINIMUM_PROFIT_FACTOR = 1.05;

    private const MINIMUM_HISTORICAL_TRADES = 5;

    public function __construct(
        private readonly PersonalizedSignalService $personalizedSignals,
    ) {}

    /** @return array<string, mixed> */
    public function settings(User $user): array
    {
        $thresholds = $this->personalizedSignals->profileThresholds($user);
        $roundTripCost = max(
            0.0,
            (float) config('aktienki.signals.round_trip_cost_percent', 0.5),
        );
        $minimumNetReturn = max(
            1.0,
            (float) config('aktienki.signals.minimum_net_return_percent', 1.0),
        );
        $profileNetReturn = max(
            $minimumNetReturn,
            (float) ($thresholds['buy_return'] ?? $minimumNetReturn),
        );

        return [
            // This is deliberately recorded as an evaluated rule even though
            // the decision service also prevents promotion of non-BUY input.
            'signal' => 'BUY',
            'score_min' => round((float) ($thresholds['buy_score'] ?? 100.0) / 10.0, 8),
            'confidence_min' => round((float) ($thresholds['buy_confidence'] ?? 1.0) * 100.0, 8),
            'risk_max' => round((float) ($thresholds['buy_risk'] ?? 0.0) * 100.0, 8),
            // Serving expected_return is gross. Require the configured net
            // edge after estimated round-trip costs.
            'predicted_return_min' => round($profileNetReturn + $roundTripCost, 8),
            'profit_factor_min' => self::MINIMUM_PROFIT_FACTOR,
            'minimum_trades' => self::MINIMUM_HISTORICAL_TRADES,
            'positive_prediction_required' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function metadata(User $user): array
    {
        return [
            'policy_version' => self::VERSION,
            'risk_profile' => $this->personalizedSignals->riskLevel($user),
            'round_trip_cost_percent' => max(
                0.0,
                (float) config('aktienki.signals.round_trip_cost_percent', 0.5),
            ),
            'minimum_net_return_percent' => max(
                1.0,
                (float) config('aktienki.signals.minimum_net_return_percent', 1.0),
            ),
        ];
    }
}

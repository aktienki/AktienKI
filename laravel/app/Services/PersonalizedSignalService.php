<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\User;
use InvalidArgumentException;

class PersonalizedSignalService
{
    /**
     * Build the SQL expression used everywhere a user-facing signal is needed.
     * The model's original prediction.signal remains untouched for auditing.
     */
    public function sql(string $predictionAlias = 'prediction', ?User $user = null): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $predictionAlias)) {
            throw new InvalidArgumentException('Invalid prediction table alias.');
        }

        $thresholds = $this->thresholds($this->riskLevel($user));
        $rawScore = "COALESCE({$predictionAlias}.ai_score, {$predictionAlias}.prediction_score, 0)";
        $score = "(CASE WHEN {$rawScore} <= 1 THEN {$rawScore} * 100 WHEN {$rawScore} <= 10 THEN {$rawScore} * 10 ELSE {$rawScore} END)";
        $confidence = "(CASE WHEN {$predictionAlias}.confidence > 1 THEN {$predictionAlias}.confidence / 100 ELSE {$predictionAlias}.confidence END)";
        $riskSource = "COALESCE({$predictionAlias}.risk_score, {$predictionAlias}.drawdown_risk_factor)";
        $risk = "(CASE WHEN {$riskSource} > 1 THEN {$riskSource} / 100 ELSE {$riskSource} END)";
        $costPercent = max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', 0.5));
        $minimumNetReturn = max(1.0, (float) config('aktienki.signals.minimum_net_return_percent', 1.0));
        $return5d = "COALESCE(
            ({$predictionAlias}.predicted_price_5d - {$predictionAlias}.current_price) / NULLIF({$predictionAlias}.current_price, 0) * 100 - {$costPercent},
            NULLIF({$predictionAlias}.horizon_fusion_details->'points_return'->>'5', '')::numeric * 100 - {$costPercent}
        )";
        $return10d = "COALESCE(
            ({$predictionAlias}.predicted_price_10d - {$predictionAlias}.current_price) / NULLIF({$predictionAlias}.current_price, 0) * 100 - {$costPercent},
            NULLIF({$predictionAlias}.horizon_fusion_details->'points_return'->>'10', '')::numeric * 100 - {$costPercent}
        )";
        $return20d = "COALESCE(
            ({$predictionAlias}.predicted_price_20d - {$predictionAlias}.current_price) / NULLIF({$predictionAlias}.current_price, 0) * 100 - {$costPercent},
            NULLIF({$predictionAlias}.horizon_fusion_details->'points_return'->>'20', '')::numeric * 100 - {$costPercent}
        )";
        $thresholds['buy_return'] = max($minimumNetReturn, $thresholds['buy_return']);
        $thresholds['buy_return_20d'] = max($minimumNetReturn, $thresholds['buy_return_20d']);
        $annualizedVolatility = $this->annualizedVolatilitySql($predictionAlias);
        $sectorVolatilityPercentile = $this->sectorVolatilityPercentileSql($predictionAlias);
        $hardQualityAccepted = "NOT EXISTS (
            SELECT 1 FROM jsonb_array_elements_text(COALESCE({$predictionAlias}.quality_gate_blockers, '[]'::jsonb)) AS blocker(value)
            WHERE blocker.value <> 'volatility'
        )";
        $volatilityAccepted = "(COALESCE({$annualizedVolatility}, 0) <= {$thresholds['maximum_annualized_volatility']}
            AND COALESCE({$sectorVolatilityPercentile}, 0.5) <= {$thresholds['maximum_sector_volatility_percentile']})";
        $waitBranch = $user && app(PlanAccessService::class)->allowsTariff($user, PlanLevel::Pro)
            ? "WHEN {$score} >= {$thresholds['watch_score']}
                    AND COALESCE({$confidence}, 0) >= {$thresholds['watch_confidence']}
                    AND ({$risk} IS NULL OR {$risk} <= {$thresholds['watch_risk']})
                    AND {$return20d} IS NOT NULL
                    AND {$return20d} >= {$thresholds['buy_return_20d']}
                    AND (({$return5d} IS NOT NULL AND {$return5d} < {$thresholds['watch_return']})
                        OR ({$return10d} IS NOT NULL AND {$return10d} < {$thresholds['watch_return']}))
                    THEN 'WAIT'"
            : '';

        return <<<SQL
            CASE
                WHEN {$predictionAlias}.id IS NULL THEN 'HOLD'
                {$waitBranch}
                WHEN {$score} < {$thresholds['sell_score']}
                    OR ({$return5d} IS NOT NULL AND {$return5d} <= {$thresholds['sell_return']})
                    THEN 'SELL'
                WHEN {$score} >= {$thresholds['buy_score']}
                    AND {$hardQualityAccepted}
                    AND {$volatilityAccepted}
                    AND COALESCE({$confidence}, 0) >= {$thresholds['buy_confidence']}
                    AND ({$risk} IS NULL OR {$risk} <= {$thresholds['buy_risk']})
                    AND ({$return5d} IS NULL OR {$return5d} >= 0)
                    AND ({$return20d} IS NULL OR {$return20d} >= 0)
                    AND (({$return5d} IS NOT NULL AND {$return5d} >= {$thresholds['buy_return']})
                        OR ({$return20d} IS NOT NULL AND {$return20d} >= {$thresholds['buy_return_20d']}))
                    THEN 'BUY'
                WHEN {$score} >= {$thresholds['watch_score']}
                    AND {$hardQualityAccepted}
                    AND {$volatilityAccepted}
                    AND COALESCE({$confidence}, 0) >= {$thresholds['watch_confidence']}
                    AND ({$risk} IS NULL OR {$risk} <= {$thresholds['watch_risk']})
                    AND ({$return5d} IS NULL OR {$return5d} >= {$thresholds['watch_return']})
                    THEN 'WATCH'
                ELSE 'HOLD'
            END
        SQL;
    }

    public function riskLevel(?User $user = null): string
    {
        $level = (string) data_get($user?->meta, 'risk_profile.level', 'normal');

        return match ($level) {
            'cautious', 'conservative' => 'cautious',
            'opportunity_oriented', 'opportunity', 'aggressive' => 'opportunity_oriented',
            'risk' => 'risk',
            default => 'normal',
        };
    }

    public function profileLabel(?User $user = null): string
    {
        return match ($this->riskLevel($user)) {
            'cautious' => 'Konservativ',
            'opportunity_oriented' => 'Chance',
            'risk' => 'Risk',
            default => 'Ausgewogen',
        };
    }

    /** @return array<string, float|int> */
    public function profileThresholds(?User $user = null): array
    {
        return $this->thresholds($this->riskLevel($user));
    }

    public function explanation(object $prediction, ?User $user = null): string
    {
        $profile = $this->profileLabel($user);
        $thresholds = $this->thresholds($this->riskLevel($user));
        $score = (float) ($prediction->ranking_score ?? $prediction->prediction_score ?? 0);
        $score = $score <= 1 ? $score * 100 : ($score <= 10 ? $score * 10 : $score);
        $confidence = (float) ($prediction->confidence ?? 0);
        $confidence = $confidence > 1 ? $confidence / 100 : $confidence;
        $volatility = is_numeric($prediction->annualized_volatility ?? null)
            ? (float) $prediction->annualized_volatility : null;
        $sectorPercentile = is_numeric($prediction->sector_volatility_percentile ?? null)
            ? (float) $prediction->sector_volatility_percentile : null;
        $signal = strtoupper((string) ($prediction->personalized_signal ?? 'HOLD'));
        $blockers = is_array($prediction->quality_gate_blockers ?? null)
            ? $prediction->quality_gate_blockers
            : (json_decode((string) ($prediction->quality_gate_blockers ?? '[]'), true) ?: []);
        $hardBlockers = array_values(array_filter($blockers, fn (mixed $blocker): bool => (string) $blocker !== 'volatility'));

        if ($signal === 'SELL') {
            return __('SELL: Score oder erwartete Nettorendite liegen unter der Verkaufsschwelle. Profil: :profile.', ['profile' => $profile]);
        }
        if ($signal === 'WAIT') {
            return __('ABWARTEN: Der längerfristige Ausblick ist positiv, für die nächsten 5 oder 10 Tage wird jedoch zunächst ein Rücksetzer erwartet. Ein günstigerer Einstieg kann sinnvoll sein. Profil: :profile.', ['profile' => $profile]);
        }
        if ($hardBlockers !== []) {
            return __('HOLD: Das Quality Gate enthält harte Blocker: :blockers. Diese können vom Nutzerprofil nicht überstimmt werden.', [
                'blockers' => implode(', ', $hardBlockers),
            ]);
        }

        $volatilityText = $volatility !== null
            ? number_format($volatility * 100, 1, ',', '.').' %'
            : __('keine Daten');
        $sectorText = $sectorPercentile !== null
            ? number_format($sectorPercentile, 0, ',', '.').'. Perzentil'
            : __('keine Daten');
        $profileLimits = __('Profil :profile: maximal :volatility % annualisiert und :percentile. Sektorperzentil.', [
            'profile' => $profile,
            'volatility' => number_format($thresholds['maximum_annualized_volatility'] * 100, 0, ',', '.'),
            'percentile' => number_format($thresholds['maximum_sector_volatility_percentile'] * 100, 0, ',', '.'),
        ]);

        if ($signal === 'BUY') {
            return __('BUY: Score :score, Konfidenz :confidence % und Nettorendite erfüllen die Anforderungen. Volatilität :volatility, Sektor :sector. :limits', [
                'score' => number_format($score, 1, ',', '.'),
                'confidence' => number_format($confidence * 100, 0, ',', '.'),
                'volatility' => $volatilityText,
                'sector' => $sectorText,
                'limits' => $profileLimits,
            ]);
        }
        if ($signal === 'WATCH') {
            $reason = $score < $thresholds['buy_score']
                ? __('Der Score :score liegt unter der BUY-Schwelle :threshold.', [
                    'score' => number_format($score, 1, ',', '.'),
                    'threshold' => number_format($thresholds['buy_score'], 0, ',', '.'),
                ])
                : __('Mindestens eine BUY-Anforderung ist noch nicht vollständig erfüllt.');
            return __('WATCH statt BUY: :reason Volatilität :volatility, Sektor :sector. :limits', [
                'reason' => $reason,
                'volatility' => $volatilityText,
                'sector' => $sectorText,
                'limits' => $profileLimits,
            ]);
        }

        return __('HOLD: Score, Konfidenz oder die profilabhängige Volatilitätsgrenze reichen noch nicht für WATCH oder BUY. Volatilität :volatility, Sektor :sector. :limits', [
            'volatility' => $volatilityText,
            'sector' => $sectorText,
            'limits' => $profileLimits,
        ]);
    }

    /**
     * Return a compact, user-facing explanation for the signal modal.
     * The signal itself is still calculated by sql(); this only explains it.
     *
     * @return array{summary: string, pros: list<string>, cons: list<string>}
     */
    public function breakdown(object $prediction, ?User $user = null): array
    {
        $profile = $this->profileLabel($user);
        $thresholds = $this->thresholds($this->riskLevel($user));
        $score = (float) ($prediction->ranking_score ?? $prediction->prediction_score ?? 0);
        $score = $score <= 1 ? $score * 100 : ($score <= 10 ? $score * 10 : $score);
        $confidence = (float) ($prediction->confidence ?? 0);
        $confidence = $confidence > 1 ? $confidence / 100 : $confidence;
        $riskSource = $prediction->risk_score ?? $prediction->drawdown_risk_factor ?? null;
        $risk = is_numeric($riskSource) ? (float) $riskSource : null;
        $risk = $risk !== null && $risk > 1 ? $risk / 100 : $risk;
        $volatility = is_numeric($prediction->annualized_volatility ?? null) ? (float) $prediction->annualized_volatility : null;
        $sectorPercentile = is_numeric($prediction->sector_volatility_percentile ?? null) ? (float) $prediction->sector_volatility_percentile : null;
        $signal = strtoupper((string) ($prediction->personalized_signal ?? 'HOLD'));
        $blockers = is_array($prediction->quality_gate_blockers ?? null)
            ? $prediction->quality_gate_blockers
            : (json_decode((string) ($prediction->quality_gate_blockers ?? '[]'), true) ?: []);
        $hardBlockers = array_values(array_filter($blockers, fn (mixed $blocker): bool => (string) $blocker !== 'volatility'));
        $costPercent = max(0.0, (float) config('aktienki.signals.round_trip_cost_percent', 0.5));
        $currentPrice = is_numeric($prediction->current_price ?? null) ? (float) $prediction->current_price : null;
        $predictedPrice = is_numeric($prediction->predicted_price_20d ?? null) ? (float) $prediction->predicted_price_20d : null;
        $netReturn = $currentPrice && $predictedPrice
            ? (($predictedPrice - $currentPrice) / $currentPrice * 100) - $costPercent
            : null;

        $pros = [];
        $cons = [];
        $addCheck = static function (bool $passed, string $positive, string $negative) use (&$pros, &$cons): void {
            if ($passed) {
                $pros[] = $positive;
            } else {
                $cons[] = $negative;
            }
        };

        $addCheck(
            $score >= $thresholds['buy_score'],
            __('KI-Score :value erfüllt die BUY-Schwelle von :limit.', ['value' => number_format($score, 1, ',', '.'), 'limit' => number_format($thresholds['buy_score'], 0, ',', '.')]),
            __('KI-Score :value liegt unter der BUY-Schwelle von :limit.', ['value' => number_format($score, 1, ',', '.'), 'limit' => number_format($thresholds['buy_score'], 0, ',', '.')]),
        );
        $addCheck(
            $confidence >= $thresholds['buy_confidence'],
            __('Konfidenz :value % ist ausreichend.', ['value' => number_format($confidence * 100, 0, ',', '.')]),
            __('Konfidenz :value % ist niedriger als die erforderlichen :limit %.', ['value' => number_format($confidence * 100, 0, ',', '.'), 'limit' => number_format($thresholds['buy_confidence'] * 100, 0, ',', '.')]),
        );
        if ($netReturn !== null) {
            $addCheck(
                $netReturn >= $thresholds['buy_return_20d'],
                __('Erwartete Nettorendite über 20 Tage: :value % (Kosten berücksichtigt).', ['value' => number_format($netReturn, 1, ',', '.')]),
                __('Erwartete Nettorendite :value % liegt unter den erforderlichen :limit %.', ['value' => number_format($netReturn, 1, ',', '.'), 'limit' => number_format($thresholds['buy_return_20d'], 1, ',', '.')]),
            );
        }
        if ($risk !== null) {
            $addCheck(
                $risk <= $thresholds['buy_risk'],
                __('Risiko :value % liegt innerhalb des Profil-Limits.', ['value' => number_format($risk * 100, 0, ',', '.')]),
                __('Risiko :value % überschreitet das Profil-Limit von :limit %.', ['value' => number_format($risk * 100, 0, ',', '.'), 'limit' => number_format($thresholds['buy_risk'] * 100, 0, ',', '.')]),
            );
        }
        if ($volatility !== null) {
            $addCheck(
                $volatility <= $thresholds['maximum_annualized_volatility'],
                __('Volatilität :value % passt zum Profil :profile.', ['value' => number_format($volatility * 100, 1, ',', '.'), 'profile' => $profile]),
                __('Volatilität :value % ist für das Profil :profile zu hoch.', ['value' => number_format($volatility * 100, 1, ',', '.'), 'profile' => $profile]),
            );
        }
        if ($sectorPercentile !== null) {
            $addCheck(
                $sectorPercentile <= $thresholds['maximum_sector_volatility_percentile'],
                __('Sektorvergleich: Volatilität im akzeptierten Bereich (:value. Perzentil).', ['value' => number_format($sectorPercentile * 100, 0, ',', '.')]),
                __('Sektorvergleich: Volatilität liegt zu hoch (:value. Perzentil).', ['value' => number_format($sectorPercentile * 100, 0, ',', '.')]),
            );
        }
        if ($hardBlockers !== []) {
            $cons[] = __('Quality Gate nicht bestanden: :blockers.', ['blockers' => implode(', ', $hardBlockers)]);
        }

        $summary = match ($signal) {
            'BUY' => __('Die Voraussetzungen für ein Kaufsignal sind im Profil :profile erfüllt.', ['profile' => $profile]),
            'WAIT' => __('Der längerfristige Ausblick ist positiv, kurzfristig wird jedoch zunächst ein deutlicher Rücksetzer unter die Profilschwelle erwartet. Daher noch abwarten.'),
            'WATCH' => __('Die Aktie ist interessant, erfüllt aber noch nicht alle Voraussetzungen für BUY.'),
            'SELL' => __('Score oder erwartete Rendite sprechen derzeit für ein Verkaufssignal.'),
            default => __('Die positiven und negativen Faktoren reichen derzeit weder für BUY noch für SELL.'),
        };

        return compact('summary', 'pros', 'cons');
    }

    public function annualizedVolatilitySql(string $predictionAlias = 'prediction'): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $predictionAlias)) {
            throw new InvalidArgumentException('Invalid prediction table alias.');
        }

        return "(CASE WHEN NULLIF({$predictionAlias}.metadata->'recommendation_verification'->>'volatility', '') ~ '^[0-9]+([.][0-9]+|)$'
            THEN ({$predictionAlias}.metadata->'recommendation_verification'->>'volatility')::numeric END)";
    }

    public function sectorVolatilityPercentileSql(string $predictionAlias = 'prediction'): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $predictionAlias)) {
            throw new InvalidArgumentException('Invalid prediction table alias.');
        }

        $currentVolatility = "(SELECT current_technical.volatility_20 FROM technical_indicators AS current_technical
            WHERE current_technical.instrument_id = {$predictionAlias}.instrument_id AND current_technical.interval = '1d'
              AND current_technical.volatility_20 IS NOT NULL
            ORDER BY current_technical.bar_time DESC, current_technical.id DESC LIMIT 1)";

        return "(SELECT AVG(CASE WHEN peer_volatility.volatility_20 <= {$currentVolatility} THEN 1.0 ELSE 0.0 END)
            FROM instruments AS peer_instrument
            JOIN LATERAL (SELECT peer_technical.volatility_20 FROM technical_indicators AS peer_technical
                WHERE peer_technical.instrument_id = peer_instrument.id AND peer_technical.interval = '1d'
                  AND peer_technical.volatility_20 IS NOT NULL
                ORDER BY peer_technical.bar_time DESC, peer_technical.id DESC LIMIT 1) AS peer_volatility ON TRUE
            WHERE peer_instrument.type = 'stock' AND peer_instrument.is_active = TRUE AND peer_instrument.deleted_at IS NULL
              AND (peer_instrument.risk_status IS NULL OR peer_instrument.risk_status <> 'sleep')
              AND peer_instrument.sector IS NOT DISTINCT FROM (SELECT current_instrument.sector FROM instruments AS current_instrument
                  WHERE current_instrument.id = {$predictionAlias}.instrument_id))";
    }

    private function thresholds(string $level): array
    {
        return match ($level) {
            'cautious' => [
                'sell_score' => 40,
                'sell_return' => -2.5,
                'buy_score' => 68,
                'buy_confidence' => 0.65,
                'buy_risk' => 0.35,
                'buy_return' => 1.5,
                'buy_return_20d' => 1.5,
                'watch_score' => 55,
                'watch_confidence' => 0.50,
                'watch_risk' => 0.55,
                'watch_return' => -1,
                'maximum_annualized_volatility' => 0.45,
                'maximum_sector_volatility_percentile' => 0.40,
            ],
            'opportunity_oriented', 'risk' => [
                'sell_score' => 32,
                'sell_return' => -4,
                'buy_score' => 57,
                'buy_confidence' => 0.45,
                'buy_risk' => 0.80,
                'buy_return' => 0.5,
                'buy_return_20d' => 0.5,
                'watch_score' => 46,
                'watch_confidence' => 0.30,
                'watch_risk' => 0.90,
                'watch_return' => -2.5,
                'maximum_annualized_volatility' => 0.85,
                'maximum_sector_volatility_percentile' => 0.90,
            ],
            default => [
                'sell_score' => 36,
                'sell_return' => -3,
                'buy_score' => 62,
                'buy_confidence' => 0.55,
                'buy_risk' => 0.60,
                'buy_return' => 1,
                'buy_return_20d' => 1,
                'watch_score' => 50,
                'watch_confidence' => 0.40,
                'watch_risk' => 0.75,
                'watch_return' => -1.5,
                'maximum_annualized_volatility' => 0.65,
                'maximum_sector_volatility_percentile' => 0.70,
            ],
        };
    }
}

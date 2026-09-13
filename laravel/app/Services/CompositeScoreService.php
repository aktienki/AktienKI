<?php

namespace App\Services;

use App\Support\ProfitFactor;

/**
 * Blends the AI score, whether the model's own three-year quality gate
 * passed, walk-forward quality (profit factor + confidence), risk, the
 * technical indicator probability and the cross-sectional panel model
 * percentile into a single 0-100 number - a companion to the categorical
 * signal badge, not a replacement for it.
 *
 * The AI score and the quality gate dominate; the rest only correct the
 * result. Any component that is not available for a given stock is left out
 * and its weight is redistributed across the remaining ones - never
 * defaulted to a neutral value, since that would silently dilute stocks for
 * which less data exists.
 */
final class CompositeScoreService
{
    public const VERSION = 'composite-score-v1';

    /** Relative weights, must sum to 100. */
    private const WEIGHTS = [
        'ai_score' => 30,
        'quality_gate' => 25,
        'profit_factor' => 13,
        'confidence' => 10,
        'risk' => 12,
        'indicator' => 5,
        'panel' => 5,
    ];

    /**
     * Backtested against 1.4M walk-forward trades (2016-2026): the AI
     * forecast's edge is not linear across its own range - deciles 1-7 of
     * predicted_return were roughly flat (profit factor ~0.9-1.3), decile 9
     * ~1.7, and only decile 10 jumped sharply (~2.4-3.9 across horizons).
     * A percentile^exponent curve reproduces that "mostly flat, steep at the
     * top" shape from one parameter instead of fitting a noisy per-decile
     * lookup table to sample noise.
     */
    private const AI_RANK_EXPONENT = 2.5;

    /**
     * @param float|null $aiScoreOutOf10 personalized/serving score_10 (0-10)
     * @param bool|null $qualityGatePassed whether this instrument/horizon/variant's
     *        own three-year out-of-sample quality gate passed
     *        (serving_model_horizon_status.quality_gate_passed /
     *        serving_releases.compact_metrics->...->quality_gate->passed).
     *        Backtested on serving_strategy_trades (standard-tcn-confirmed,
     *        3 years): gate passed -> profit factor ~4.9, hit rate ~76% vs.
     *        ~1.1 / ~47% for the 94% of trades whose gate never passed - the
     *        single strongest signal found so far, hence the near-ai_score weight.
     * @param float|null $profitFactor uncapped three-year walk-forward profit factor
     * @param float|null $confidencePercent 0-100
     * @param float|null $riskPercent 0-100, higher is riskier
     * @param float|null $indicatorProbabilityPercent 0-100 ChartView rise probability
     * @param float|null $panelPercentile 0-100 cross-sectional panel model percentile
     * @param float|null $aiScorePercentile 0-100 rank of $aiScoreOutOf10 within the
     *        current universe. When given, replaces the plain linear score*10 for the
     *        ai_score component with the backtested rank curve above; without it (e.g.
     *        no universe context available) the linear value is used as a fallback.
     */
    public function score(
        ?float $aiScoreOutOf10,
        ?bool $qualityGatePassed = null,
        ?float $profitFactor = null,
        ?float $confidencePercent = null,
        ?float $riskPercent = null,
        ?float $indicatorProbabilityPercent = null,
        ?float $panelPercentile = null,
        ?float $aiScorePercentile = null,
    ): ?int {
        if (! is_numeric($aiScoreOutOf10)) {
            return null;
        }

        $cappedProfitFactor = ProfitFactor::cap($profitFactor);

        /** @var array<string, float|null> $values */
        $values = [
            'ai_score' => is_numeric($aiScorePercentile)
                ? $this->rankCurve($this->clamp($aiScorePercentile))
                : $this->clamp($aiScoreOutOf10 * 10),
            'quality_gate' => $qualityGatePassed === null ? null : ($qualityGatePassed ? 100.0 : 0.0),
            'profit_factor' => $cappedProfitFactor !== null
                ? $this->clamp((($cappedProfitFactor - .5) / 2) * 100)
                : null,
            'confidence' => is_numeric($confidencePercent) ? $this->clamp($confidencePercent) : null,
            'risk' => is_numeric($riskPercent) ? 100 - $this->clamp($riskPercent) : null,
            'indicator' => is_numeric($indicatorProbabilityPercent) ? $this->clamp($indicatorProbabilityPercent) : null,
            'panel' => is_numeric($panelPercentile) ? $this->clamp($panelPercentile) : null,
        ];

        $weightSum = 0;
        $weighted = 0.0;
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }
            $weightSum += self::WEIGHTS[$key];
            $weighted += $value * self::WEIGHTS[$key];
        }

        if ($weightSum <= 0) {
            return null;
        }

        return (int) round($weighted / $weightSum);
    }

    private function rankCurve(float $percentile0To100): float
    {
        return 100 * (($percentile0To100 / 100) ** self::AI_RANK_EXPONENT);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}

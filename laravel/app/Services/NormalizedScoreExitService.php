<?php

namespace App\Services;

use App\Models\PredictionPurchaseReminder;
use App\Support\QualityGrade;
use Illuminate\Support\Facades\DB;

final class NormalizedScoreExitService
{
    public const VERSION = 'normalized-score-exit-v1';
    public const MIN_INSTRUMENT_HISTORY = 60;
    public const MIN_SECTOR_HISTORY = 200;
    public const MIN_MARKET_HISTORY = 500;

    public function __construct(private readonly DecisionProcessAuditService $audit) {}

    public function evaluate(PredictionPurchaseReminder $position): array
    {
        $latest = DB::table('predictions')->where('instrument_id', $position->instrument_id)
            ->whereNotNull('ai_score')->orderByDesc('prediction_time')->orderByDesc('id')
            ->first(['id', 'prediction_time', 'ai_score', 'signal', 'risk_score']);

        if (! $latest || ! is_numeric($latest->ai_score)) {
            return ['decision' => 'NO_DATA', 'rating' => null, 'source' => null, 'streak' => (int) $position->score_exit_streak];
        }

        if ((int) data_get($position->score_exit_details, 'prediction_id', 0) === (int) $latest->id) {
            return [...(array) $position->score_exit_details, 'decision' => data_get($position->score_exit_details, 'decision', 'HOLD'),
                'streak' => (int) $position->score_exit_streak, 'unchanged' => true];
        }

        $auditRun = $this->audit->start('exit', (int) $position->instrument_id, (int) $latest->id, [
            'position_id' => (int) $position->id, 'intent' => $position->intent,
            'previous_streak' => (int) $position->score_exit_streak,
        ]);
        $this->audit->step($auditRun, 10, 'INPUT', 'Aktuelle Prognose', 'PASSED', [
            'rule_version' => self::VERSION,
            'raw_values' => ['ai_score' => (float) $latest->ai_score, 'signal' => strtoupper((string) $latest->signal), 'risk_score' => $latest->risk_score],
            'sources' => ['prediction_id' => (int) $latest->id, 'prediction_time' => (string) $latest->prediction_time],
        ]);

        [$percentile, $source, $samples] = $this->percentile(
            (int) $position->instrument_id,
            (int) $latest->id,
            (float) $latest->ai_score
        );
        $rating = QualityGrade::fromPercent($percentile);
        $quality = self::ratingQuality($rating);
        $policy = $this->policy((int) $position->instrument_id);
        $regularExitPercentile = (float) ($policy['regular_exit_percentile'] ?? 50);
        $confirmationDays = max(1, (int) ($policy['confirmation_days'] ?? 2));
        $immediateExitPercentile = is_numeric($policy['immediate_exit_percentile'] ?? null)
            ? (float) $policy['immediate_exit_percentile'] : null;
        $this->audit->step($auditRun, 20, 'NORMALIZATION', 'Point-in-time Normalisierung', 'PASSED', [
            'rule_version' => self::VERSION,
            'raw_values' => ['ai_score' => (float) $latest->ai_score],
            'normalized_values' => ['percentile' => $percentile, 'rating' => $rating],
            'sources' => ['hierarchy_level' => $source, 'sample_count' => $samples],
        ]);
        $this->audit->step($auditRun, 30, 'EXIT_POLICY', 'Exit-Schwellen und Bestätigung', 'PASSED', [
            'rule_version' => self::VERSION,
            'thresholds' => ['regular_exit_percentile' => $regularExitPercentile,
                'immediate_exit_percentile' => $immediateExitPercentile, 'confirmation_days' => $confirmationDays],
            'sources' => ['policy_status' => $policy['status'] ?? 'default'],
        ]);
        $inExitZone = $percentile < $regularExitPercentile;
        $streak = $inExitZone ? (int) $position->score_exit_streak + 1 : 0;
        $risk = is_numeric($latest->risk_score) ? (float) $latest->risk_score : null;
        if ($risk !== null && $risk <= 1) $risk *= 100;
        $risk = $risk !== null ? max(0, min(100, $risk)) : null;
        $bearishConfirmation = strtoupper((string) $latest->signal) === 'SELL' || ($risk !== null && $risk >= 70);
        $immediate = $immediateExitPercentile !== null && $percentile < $immediateExitPercentile;
        $exit = $immediate || $streak >= $confirmationDays;
        $decision = $exit ? 'EXIT' : ($streak === 1 ? 'WARNING' : 'HOLD');
        $details = [
            'version' => self::VERSION, 'prediction_id' => (int) $latest->id,
            'raw_ai_score' => (float) $latest->ai_score, 'percentile' => $percentile,
            'rating' => $rating, 'rating_source' => $source, 'rating_samples' => $samples,
            'exit_zone' => $inExitZone, 'regular_exit_percentile' => $regularExitPercentile,
            'immediate_exit_percentile' => $immediateExitPercentile,
            'immediate_exit' => $immediate, 'confirmation_days' => $confirmationDays,
            'policy_status' => $policy['status'] ?? 'default', 'bearish_confirmation' => $bearishConfirmation,
            'risk_percent' => $risk, 'signal' => strtoupper((string) $latest->signal),
            'decision' => $decision,
        ];

        $position->update([
            'score_rating' => $rating, 'score_rating_source' => $source,
            'score_exit_streak' => $streak, 'score_exit_evaluated_at' => now(),
            'score_exit_triggered_at' => $exit ? ($position->score_exit_triggered_at ?? now()) : null,
            'score_exit_details' => $details,
            'exit_state' => $exit ? 'exit_recommended' : ($streak === 1 ? 'exit_warning' : 'monitoring'),
        ]);

        $this->audit->step($auditRun, 40, 'EXIT_DECISION', 'Exit-Entscheidung', 'PASSED', [
            'rule_version' => self::VERSION, 'decision' => $decision,
            'raw_values' => ['risk_percent' => $risk, 'signal' => strtoupper((string) $latest->signal)],
            'normalized_values' => ['percentile' => $percentile, 'rating' => $rating, 'streak' => $streak],
            'evidence' => ['immediate_exit' => $immediate, 'bearish_confirmation' => $bearishConfirmation],
            'reason' => $exit ? 'Exit-Schwelle erreicht.' : ($streak === 1 ? 'Erster schwacher Handelstag; Bestätigung ausstehend.' : 'Exit-Schwelle nicht erreicht.'),
        ]);
        $this->audit->finish($auditRun, $decision, $details);

        return [...$details, 'streak' => $streak];
    }

    public static function ratingQuality(?string $rating): ?int
    {
        $index = array_search($rating, ['5−', '5+', '4−', '4+', '3−', '3+', '2−', '2+', '1−', '1+'], true);
        return $index === false ? null : $index + 1;
    }

    private function percentile(int $instrumentId, int $beforePredictionId, float $score): array
    {
        $instrumentScores = DB::table('predictions')->where('instrument_id', $instrumentId)
            ->where('id', '<', $beforePredictionId)->whereNotNull('ai_score')
            ->orderByDesc('prediction_time')->limit(756)->pluck('ai_score')->map(fn ($v) => (float) $v);
        if ($instrumentScores->count() >= self::MIN_INSTRUMENT_HISTORY) {
            return [$this->rank($instrumentScores->all(), $score), 'instrument', $instrumentScores->count()];
        }

        $sector = DB::table('instruments')->where('id', $instrumentId)->value('sector');
        if ($sector) {
            $sectorScores = DB::table('predictions as p')->join('instruments as i', 'i.id', '=', 'p.instrument_id')
                ->where('i.sector', $sector)->where('p.id', '<', $beforePredictionId)->whereNotNull('p.ai_score')
                ->orderByDesc('p.prediction_time')->limit(3000)->pluck('p.ai_score')->map(fn ($v) => (float) $v);
            if ($sectorScores->count() >= self::MIN_SECTOR_HISTORY) {
                return [$this->rank($sectorScores->all(), $score), 'sector', $sectorScores->count()];
            }
        }

        $marketScores = DB::table('predictions')->where('id', '<', $beforePredictionId)->whereNotNull('ai_score')
            ->orderByDesc('prediction_time')->limit(5000)->pluck('ai_score')->map(fn ($v) => (float) $v);
        if ($marketScores->count() >= self::MIN_MARKET_HISTORY) {
            return [$this->rank($marketScores->all(), $score), 'market', $marketScores->count()];
        }

        return [max(0, min(100, $score)), 'safety_fallback', $marketScores->count()];
    }

    private function policy(int $instrumentId): array
    {
        $row = DB::table('instrument_exit_policies')->where('instrument_id', $instrumentId)
            ->where('policy_name', 'normalized_score_exit')->where('is_active', true)
            ->orderByDesc('evaluated_at')->first(['status', 'parameters']);
        $parameters = $row ? (is_string($row->parameters) ? json_decode($row->parameters, true) : (array) $row->parameters) : [];
        return [...$parameters, 'status' => $row?->status ?? 'default'];
    }

    private function rank(array $history, float $score): float
    {
        if ($history === []) return max(0, min(100, $score));
        $below = count(array_filter($history, fn (float $value): bool => $value < $score));
        $equal = count(array_filter($history, fn (float $value): bool => $value === $score));
        return round((($below + .5 * $equal) / count($history)) * 100, 4);
    }
}

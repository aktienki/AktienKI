<?php

namespace App\Support;

final class SignalQualityCalibration
{
    public const VERSION = 'signal-quality-performance-v2-stock-oos-ladder';

    /**
     * Calibrate a user-facing grade from realized signal-change performance.
     * Raw model/action scores are deliberately not part of this calculation.
     *
     * @param array{trades:int,hit_rate:float,profit_factor:float,average_return_percent:float} $overall
     * @param array{trades:int,hit_rate:float,profit_factor:float,average_return_percent:float} $validation
     * @return array<string,mixed>
     */
    public static function calculate(array $overall, array $validation, bool $allFourHorizonsConfirmed = false): array
    {
        $blend = static fn (string $key): float =>
            ((float) $overall[$key] * .70) + ((float) $validation[$key] * .30);

        $hitRate = $blend('hit_rate');
        $profitFactor = $blend('profit_factor');
        $averageReturn = $blend('average_return_percent');

        $validationTrades = max(0, (int) $validation['trades']);
        $grade = self::gradeFromEvidence(
            $hitRate,
            $profitFactor,
            $averageReturn,
            $validationTrades,
            $allFourHorizonsConfirmed,
        );
        $qualityPercent = self::percentForGrade($grade);

        return [
            'version' => self::VERSION,
            'grade' => $grade,
            'quality_percent' => $qualityPercent,
            'status' => $validationTrades >= 20 ? 'validated' : 'provisional',
            'all_four_horizons_confirmed' => $allFourHorizonsConfirmed,
            'one_plus_requires_all_four_horizons' => true,
            'evidence_factor' => round(min(1.0, $validationTrades / 20.0), 4),
            'components' => [
                'hit_rate' => round($hitRate, 2),
                'profit_factor' => round($profitFactor, 3),
                'average_return_percent' => round($averageReturn, 3),
            ],
            'weights' => ['overall' => .70, 'validation' => .30],
            'ladder' => self::ladder(),
        ];
    }

    /**
     * The grade is deliberately based on realized, stock-specific OOS trades.
     * Every threshold must be met; a high hit rate cannot hide negative returns.
     */
    private static function gradeFromEvidence(
        float $hitRate,
        float $profitFactor,
        float $averageReturn,
        int $validationTrades,
        bool $allFourHorizonsConfirmed,
    ): string {
        $sparseGrade = null;
        foreach (self::ladder() as $grade => $limits) {
            if ($hitRate < $limits['hit_rate']) continue;
            if ($profitFactor < $limits['profit_factor']) continue;
            if ($averageReturn < $limits['average_return_percent']) continue;
            if ($grade === '1+' && ! $allFourHorizonsConfirmed) continue;

            if ($validationTrades < $limits['trades']) {
                $sparseGrade ??= $grade;
                continue;
            }

            // Small validation samples remain visibly provisional.
            if ($validationTrades < 20 && in_array($grade, ['1+', '1−', '2+'], true)) {
                return '2−';
            }

            return $grade;
        }

        if ($sparseGrade !== null && $validationTrades > 0) return '2−';

        return '5−';
    }

    /** @return array<string,array{hit_rate:float,profit_factor:float,average_return_percent:float,trades:int}> */
    private static function ladder(): array
    {
        return [
            '1+' => ['hit_rate' => 70.0, 'profit_factor' => 1.50, 'average_return_percent' => 3.00, 'trades' => 20],
            '1−' => ['hit_rate' => 68.0, 'profit_factor' => 1.50, 'average_return_percent' => 2.50, 'trades' => 20],
            '2+' => ['hit_rate' => 65.0, 'profit_factor' => 1.40, 'average_return_percent' => 2.00, 'trades' => 20],
            '2−' => ['hit_rate' => 62.0, 'profit_factor' => 1.30, 'average_return_percent' => 1.50, 'trades' => 10],
            '3+' => ['hit_rate' => 60.0, 'profit_factor' => 1.20, 'average_return_percent' => 1.00, 'trades' => 10],
            '3−' => ['hit_rate' => 57.5, 'profit_factor' => 1.10, 'average_return_percent' => .50, 'trades' => 10],
            '4+' => ['hit_rate' => 55.0, 'profit_factor' => 1.00, 'average_return_percent' => .01, 'trades' => 10],
            '4−' => ['hit_rate' => 50.0, 'profit_factor' => .90, 'average_return_percent' => 0.00, 'trades' => 10],
            '5+' => ['hit_rate' => 45.0, 'profit_factor' => .75, 'average_return_percent' => -.50, 'trades' => 10],
        ];
    }

    private static function percentForGrade(string $grade): float
    {
        return [
            '5−' => 5.0, '5+' => 15.0, '4−' => 25.0, '4+' => 35.0, '3−' => 45.0,
            '3+' => 55.0, '2−' => 65.0, '2+' => 75.0, '1−' => 85.0, '1+' => 95.0,
        ][$grade] ?? 5.0;
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}

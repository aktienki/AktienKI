<?php

namespace Tests\Unit;

use App\Services\PersonalizedSignalService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PersonalizedSignalRawPrerequisiteTest extends TestCase
{
    public function test_non_buy_raw_signals_are_never_promoted(): void
    {
        foreach (['HOLD', 'WATCH', 'SELL'] as $rawSignal) {
            $this->assertSame($rawSignal, $this->evaluate(['signal' => $rawSignal]));
        }

        $this->assertSame('WATCH', $this->evaluate(['signal' => ' watch ']));
        $this->assertSame('HOLD', $this->evaluate(['signal' => 'unexpected']));
    }

    public function test_raw_buy_can_be_confirmed_when_existing_entry_conditions_pass(): void
    {
        $this->assertSame('BUY', $this->evaluate());
    }

    public function test_raw_buy_filter_failures_only_downgrade_to_watch_or_hold(): void
    {
        $lowScore = $this->evaluate(['ai_score' => 20]);
        $underperform = $this->evaluate([
            'quality_gate_blockers' => '["underperform"]',
        ]);
        $missingData = $this->evaluate([
            'ai_score' => null,
            'confidence' => null,
            'predicted_price_5d' => null,
            'predicted_price_20d' => null,
        ]);

        $this->assertSame('HOLD', $lowScore);
        $this->assertSame('HOLD', $underperform);
        $this->assertSame('HOLD', $missingData);
        $this->assertNotSame('SELL', $lowScore);
        $this->assertNotSame('SELL', $underperform);
    }

    public function test_quality_badge_and_basic_tier_are_not_universal_buy_vetoes(): void
    {
        $sql = app(PersonalizedSignalService::class)->sql('prediction');

        $this->assertStringNotContainsString('quality_gate_passed', $sql);
        $this->assertStringNotContainsString('quality_tier', $sql);
        $this->assertStringNotContainsString('model_quality_class', $sql);
        $this->assertSame('BUY', $this->evaluate());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function evaluate(array $overrides = []): string
    {
        $row = array_replace([
            'id' => 1,
            'signal' => 'BUY',
            'ai_score' => 80,
            'prediction_score' => null,
            'confidence' => 0.90,
            'risk_score' => 0.10,
            'drawdown_risk_factor' => null,
            'predicted_price_5d' => 103,
            'predicted_price_20d' => 110,
            'current_price' => 100,
            'horizon_fusion_details' => '{}',
            'quality_gate_blockers' => '[]',
            'metadata' => '{}',
            'instrument_id' => -1,
        ], $overrides);

        $signalSql = app(PersonalizedSignalService::class)->sql('prediction');
        $result = DB::selectOne(
            "SELECT {$signalSql} AS final_signal
             FROM (
                 SELECT
                     CAST(? AS bigint) AS id,
                     CAST(? AS text) AS signal,
                     CAST(? AS numeric) AS ai_score,
                     CAST(? AS numeric) AS prediction_score,
                     CAST(? AS numeric) AS confidence,
                     CAST(? AS numeric) AS risk_score,
                     CAST(? AS numeric) AS drawdown_risk_factor,
                     CAST(? AS numeric) AS predicted_price_5d,
                     CAST(? AS numeric) AS predicted_price_20d,
                     CAST(? AS numeric) AS current_price,
                     CAST(? AS jsonb) AS horizon_fusion_details,
                     CAST(? AS jsonb) AS quality_gate_blockers,
                     CAST(? AS jsonb) AS metadata,
                     CAST(? AS bigint) AS instrument_id,
                     FALSE AS quality_gate_passed,
                     'BASIC'::text AS quality_tier
             ) AS prediction",
            [
                $row['id'],
                $row['signal'],
                $row['ai_score'],
                $row['prediction_score'],
                $row['confidence'],
                $row['risk_score'],
                $row['drawdown_risk_factor'],
                $row['predicted_price_5d'],
                $row['predicted_price_20d'],
                $row['current_price'],
                $row['horizon_fusion_details'],
                $row['quality_gate_blockers'],
                $row['metadata'],
                $row['instrument_id'],
            ],
        );

        return (string) $result->final_signal;
    }
}

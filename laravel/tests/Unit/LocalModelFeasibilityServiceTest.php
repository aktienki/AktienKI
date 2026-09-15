<?php

namespace Tests\Unit;

use App\Services\LocalModelFeasibilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Seeds a small, self-contained local prediction inside a transaction (not
 * seeded data borrowed from whatever the test database happens to contain)
 * to confirm the checker's rules mirror
 * AutomatedPortfolioService::candidates()'s actual local matching.
 */
final class LocalModelFeasibilityServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_40_day_horizon_is_always_infeasible(): void
    {
        // ApplyHorizonFusion only ever produces 5/10/15/20 day predictions -
        // no symbol can ever have a local 40-day row, so this must be
        // rejected before even touching the database.
        $result = app(LocalModelFeasibilityService::class)->check('__ANY_SYMBOL__', 40, 'standard', 'GradientBoostingRegressor');

        $this->assertFalse($result['feasible']);
        $this->assertSame('unsupported_horizon', $result['reason']);
    }

    public function test_a_symbol_with_no_local_prediction_at_that_horizon_is_infeasible(): void
    {
        $result = app(LocalModelFeasibilityService::class)->check('__NO_SUCH_SYMBOL__', 10, 'standard', 'CatBoostRegressor');

        $this->assertFalse($result['feasible']);
        $this->assertSame('no_local_model', $result['reason']);
        $this->assertNull($result['local_model_name']);
    }

    public function test_a_matching_local_standard_model_is_feasible(): void
    {
        [$symbol] = $this->seedLocalPrediction(horizonDays: 20, modelName: 'gradient_boosting_regressor future_return_20', publicAlias: 'Horizon Vega');

        $result = app(LocalModelFeasibilityService::class)->check($symbol, 20, 'standard', 'gradient_boosting_regressor');

        $this->assertTrue($result['feasible']);
        $this->assertNull($result['reason']);
    }

    public function test_a_locally_changed_champion_model_is_infeasible(): void
    {
        [$symbol] = $this->seedLocalPrediction(horizonDays: 20, modelName: 'catboost_regressor future_return_20', publicAlias: 'Horizon Helios');

        // The configuration was saved for a different model than the one
        // that is actually the local champion today.
        $result = app(LocalModelFeasibilityService::class)->check($symbol, 20, 'standard', 'gradient_boosting_regressor');

        $this->assertFalse($result['feasible']);
        $this->assertSame('local_model_changed', $result['reason']);
        $this->assertSame('catboost_regressor future_return_20', $result['local_model_name']);
    }

    public function test_pure_tcn_requires_a_local_model_with_tcn_in_its_name(): void
    {
        [$symbol] = $this->seedLocalPrediction(horizonDays: 10, modelName: 'gradient_boosting_regressor future_return_10', publicAlias: 'Horizon Atlas');

        $result = app(LocalModelFeasibilityService::class)->check($symbol, 10, 'pure_tcn');

        $this->assertFalse($result['feasible']);
        $this->assertSame('no_local_tcn_variant', $result['reason']);
    }

    public function test_pure_tcn_matches_a_local_model_named_with_tcn(): void
    {
        [$symbol] = $this->seedLocalPrediction(horizonDays: 10, modelName: 'pure_tcn future_return_10', publicAlias: 'Horizon TCN');

        $result = app(LocalModelFeasibilityService::class)->check($symbol, 10, 'pure_tcn');

        $this->assertTrue($result['feasible']);
    }

    public function test_explain_fills_in_the_symbol_and_horizon_for_every_reason(): void
    {
        $service = app(LocalModelFeasibilityService::class);

        $this->assertStringContainsString('40', $service->explain('unsupported_horizon', 'SIE.DE', 40, null, null));
        $this->assertStringContainsString('SIE.DE', $service->explain('no_local_model', 'SIE.DE', 10, null, null));
        $this->assertStringContainsString('Pure-TCN', $service->explain('no_local_tcn_variant', 'SIE.DE', 20, null, 'Horizon Helios'));
        $this->assertStringContainsString('Horizon Helios', $service->explain('local_model_changed', 'SIE.DE', 20, 'GradientBoostingRegressor', 'Horizon Helios'));
    }

    /**
     * @return array{0: string} the symbol of the seeded instrument
     */
    private function seedLocalPrediction(int $horizonDays, string $modelName, string $publicAlias): array
    {
        $symbol = 'TST'.random_int(10000, 99999).'.DE';

        $instrumentId = DB::table('instruments')->insertGetId([
            'type' => 'stock', 'symbol' => $symbol, 'name' => $symbol,
            'country' => 'DE', 'currency' => 'EUR', 'is_active' => true, 'is_tradeable' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $modelDefinitionId = DB::table('model_definitions')->insertGetId([
            'code' => Str::random(12), 'name' => $modelName, 'algorithm' => 'test_algorithm',
            'task_type' => 'regression', 'target_name' => 'future_return_'.$horizonDays,
            'interval' => '1d', 'is_active' => true, 'public_alias' => $publicAlias,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $trainedModelId = DB::table('trained_models')->insertGetId([
            'model_definition_id' => $modelDefinitionId, 'instrument_id' => $instrumentId,
            'scope' => 'per_instrument', 'version' => 1, 'status' => 'active',
            'storage_disk' => 'local', 'artifact_path' => 'test/path',
            'prediction_horizon_minutes' => $horizonDays * 1440,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('predictions')->insert([
            'instrument_id' => $instrumentId, 'trained_model_id' => $trainedModelId,
            'prediction_time' => now(), 'interval' => '1d', 'current_price' => 100,
            'prediction_horizon_minutes' => $horizonDays * 1440,
            'signal' => 'HOLD', 'status' => 'complete', 'strategy' => 'test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$symbol];
    }
}

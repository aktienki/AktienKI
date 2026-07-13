<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModelChampionTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_champion_challenger_comparison_and_elo_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('model_champions'));
        $this->assertTrue(Schema::hasTable('model_challengers'));
        $this->assertTrue(Schema::hasTable('model_comparisons'));
        $this->assertTrue(Schema::hasTable('model_elo_history'));
    }

    public function test_model_champions_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('model_champions', [
                'strategy_profile_id',
                'instrument_id',
                'active_trained_model_id',
                'previous_trained_model_id',
                'algorithm',
                'status',
                'elo_rating',
                'validated_predictions_count',
                'direction_accuracy',
                'average_strategy_return',
                'rmse',
                'stability_score',
                'activated_at',
                'activation_reason',
                'activation_metrics',
                'metadata',
            ])
        );
    }

    public function test_model_challengers_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('model_challengers', [
                'strategy_profile_id',
                'instrument_id',
                'trained_model_id',
                'champion_model_id',
                'algorithm',
                'status',
                'elo_rating',
                'validated_predictions_count',
                'direction_accuracy',
                'average_strategy_return',
                'rmse',
                'stability_score',
                'evaluation_started_at',
                'evaluation_finished_at',
                'status_reason',
                'evaluation_metrics',
                'metadata',
            ])
        );
    }

    public function test_model_comparisons_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('model_comparisons', [
                'strategy_profile_id',
                'instrument_id',
                'champion_model_id',
                'challenger_model_id',
                'prediction_count',
                'champion_direction_accuracy',
                'challenger_direction_accuracy',
                'champion_strategy_return',
                'challenger_strategy_return',
                'champion_rmse',
                'challenger_rmse',
                'champion_stability_score',
                'challenger_stability_score',
                'champion_selection_score',
                'challenger_selection_score',
                'winner',
                'promotion_recommended',
                'compared_at',
                'comparison_rules',
                'metrics',
                'metadata',
            ])
        );
    }

    public function test_model_elo_history_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('model_elo_history', [
                'trained_model_id',
                'model_comparison_id',
                'rating_before',
                'rating_after',
                'rating_change',
                'result',
                'opponent_type',
                'opponent_model_id',
                'rated_at',
                'metadata',
            ])
        );
    }
}

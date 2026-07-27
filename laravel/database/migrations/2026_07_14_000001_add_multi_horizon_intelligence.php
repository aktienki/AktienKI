<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendTable('strategy_profiles', [
            'model_scope',
            'timeframe',
            'training_window_days',
            'prediction_horizon_minutes',
            'market_session',
            'multi_horizon_enabled',
        ]);

        $this->extendTable('predictions', [
            'model_scope',
            'timeframe',
            'prediction_horizon_minutes',
            'direction',
            'confidence_score',
        ]);

        $this->extendTable('trained_models', [
            'model_scope',
            'timeframe',
            'training_window_days',
            'prediction_horizon_minutes',
            'feature_set_version',
        ]);

        foreach (['model_training_runs', 'training_runs'] as $tableName) {
            $this->extendTable($tableName, [
                'model_scope',
                'timeframe',
                'training_window_days',
                'prediction_horizon_minutes',
            ]);
        }

        if (! Schema::hasTable('multi_horizon_consensuses')) {
            Schema::create('multi_horizon_consensuses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('instrument_id')->index();

                $table->unsignedBigInteger('short_term_prediction_id')->nullable()->index();
                $table->unsignedBigInteger('long_term_prediction_id')->nullable()->index();
                $table->unsignedBigInteger('short_term_trained_model_id')->nullable()->index();
                $table->unsignedBigInteger('long_term_trained_model_id')->nullable()->index();

                $table->string('short_term_timeframe', 16)->default('1h');
                $table->unsignedInteger('short_term_horizon_minutes')->default(1440);
                $table->string('short_term_direction', 16)->nullable();
                $table->decimal('short_term_score', 8, 4)->nullable();
                $table->decimal('short_term_confidence', 7, 4)->nullable();

                $table->string('long_term_timeframe', 16)->default('1d');
                $table->unsignedInteger('long_term_horizon_minutes')->default(28800);
                $table->string('long_term_direction', 16)->nullable();
                $table->decimal('long_term_score', 8, 4)->nullable();
                $table->decimal('long_term_confidence', 7, 4)->nullable();

                $table->decimal('consensus_score', 8, 4)->nullable()->index();
                $table->string('consensus_status', 40)->index();
                $table->string('consensus_direction', 16)->nullable()->index();
                $table->decimal('consensus_confidence', 7, 4)->nullable();
                $table->text('interpretation')->nullable();
                $table->json('details')->nullable();
                $table->timestamp('calculated_at')->index();
                $table->timestamps();

                $table->index(
                    ['instrument_id', 'calculated_at'],
                    'mh_consensus_instrument_calculated_idx'
                );
                $table->index(
                    ['instrument_id', 'consensus_status'],
                    'mh_consensus_instrument_status_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_horizon_consensuses');

        $this->dropColumnsIfPresent('strategy_profiles', [
            'model_scope',
            'timeframe',
            'training_window_days',
            'prediction_horizon_minutes',
            'market_session',
            'multi_horizon_enabled',
        ]);

        $this->dropColumnsIfPresent('predictions', [
            'model_scope',
            'timeframe',
            'prediction_horizon_minutes',
            'direction',
            'confidence_score',
        ]);

        $this->dropColumnsIfPresent('trained_models', [
            'model_scope',
            'timeframe',
            'training_window_days',
            'prediction_horizon_minutes',
            'feature_set_version',
        ]);

        foreach (['model_training_runs', 'training_runs'] as $tableName) {
            $this->dropColumnsIfPresent($tableName, [
                'model_scope',
                'timeframe',
                'training_window_days',
                'prediction_horizon_minutes',
            ]);
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function extendTable(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    continue;
                }

                match ($column) {
                    'model_scope' => $table->string($column, 32)->default('long_term')->index(),
                    'timeframe' => $table->string($column, 16)->default('1d')->index(),
                    'training_window_days' => $table->unsignedInteger($column)->default(3650),
                    'prediction_horizon_minutes' => $table->unsignedInteger($column)->default(28800)->index(),
                    'market_session' => $table->string($column, 32)->default('regular'),
                    'multi_horizon_enabled' => $table->boolean($column)->default(false)->index(),
                    'direction' => $table->string($column, 16)->nullable()->index(),
                    'confidence_score' => $table->decimal($column, 7, 4)->nullable(),
                    'feature_set_version' => $table->string($column, 64)->nullable()->index(),
                    default => null,
                };
            }
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
};

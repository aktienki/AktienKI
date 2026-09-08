<?php

namespace Tests\Feature;

use App\Console\Commands\ImportPumaExitRecommendation;
use App\Services\PumaExitRecommendationService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

final class PumaExitRecommendationBatchImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.serving', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('serving');
        $this->createServingContract();
    }

    protected function tearDown(): void
    {
        DB::purge('serving');

        parent::tearDown();
    }

    public function test_two_day_confirmation_can_be_imported_as_replay_prefix_plus_catch_up_suffix(): void
    {
        $service = app(PumaExitRecommendationService::class);
        $first = $this->payload('2026-09-01', 100, 'state_seed');
        $second = $this->payload('2026-09-02', 101, 'historical_backfill');

        $initial = $service->importMany([$first]);
        $catchUp = $service->importMany([$first, $second]);

        $this->assertSame(1, $initial['imported_count']);
        $this->assertSame(1, $catchUp['idempotent_replay_count']);
        $this->assertSame(1, $catchUp['imported_count']);
        $this->assertCount(2, $catchUp['observations']);
        $this->assertSame('EXIT_RECOMMENDED', $catchUp['observations'][1]['model_decision']);
        $this->assertSame('NO_DATA', $catchUp['observations'][1]['decision']);
        $this->assertSame('STATE_ONLY', $catchUp['observations'][1]['recommendation_status']);
        $this->assertFalse($catchUp['observations'][1]['recommendation_actionable']);

        $stored = DB::connection('serving')->table('serving_exit_signal_observations')
            ->orderBy('market_session_position')->get();
        $this->assertCount(2, $stored);
        $this->assertFalse((bool) $stored[0]->long_curve_confirmed);
        $this->assertTrue((bool) $stored[1]->long_curve_confirmed);
        $this->assertSame('EXIT_RECOMMENDED', $stored[1]->decision);
    }

    public function test_an_identical_full_ndjson_reimport_is_idempotent(): void
    {
        $service = app(PumaExitRecommendationService::class);
        $batch = [
            $this->payload('2026-09-01', 100, 'state_seed'),
            $this->payload('2026-09-02', 101, 'historical_backfill'),
        ];

        $service->importMany($batch);
        $replay = $service->importMany($batch);

        $this->assertSame(0, $replay['imported_count']);
        $this->assertSame(2, $replay['idempotent_replay_count']);
        $this->assertTrue($replay['observations'][0]['idempotent_replay']);
        $this->assertTrue($replay['observations'][1]['idempotent_replay']);
        $this->assertSame(
            2,
            DB::connection('serving')->table('serving_exit_signal_observations')->count(),
        );
    }

    public function test_a_database_error_on_the_second_line_rolls_the_first_insert_back(): void
    {
        DB::connection('serving')->unprepared(<<<'SQL'
CREATE TRIGGER reject_second_puma_observation
BEFORE INSERT ON serving_exit_signal_observations
WHEN NEW.market_session_date = '2026-09-02'
BEGIN
    SELECT RAISE(ABORT, 'synthetic second-line failure');
END
SQL);

        try {
            app(PumaExitRecommendationService::class)->importMany([
                $this->payload('2026-09-01', 100, 'state_seed'),
                $this->payload('2026-09-02', 101, 'historical_backfill'),
            ]);
            $this->fail('The synthetic database failure should have aborted the batch.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('synthetic second-line failure', $exception->getMessage());
        }

        $this->assertSame(
            0,
            DB::connection('serving')->table('serving_exit_signal_observations')->count(),
        );
    }

    public function test_a_changed_reimport_conflicts_without_overwriting_the_original(): void
    {
        $service = app(PumaExitRecommendationService::class);
        $original = $this->payload('2026-09-01', 100, 'state_seed');
        $service->importMany([$original]);
        $changed = $original;
        $changed['champion_40t'] = -0.08;

        try {
            $service->importMany([$changed]);
            $this->fail('A changed immutable observation should conflict.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('complete catch-up transaction was rolled back', $exception->getMessage());
        }

        $stored = DB::connection('serving')->table('serving_exit_signal_observations')->first();
        $this->assertSame(-0.06, (float) $stored->champion_40t);
        $this->assertSame(1, DB::connection('serving')->table('serving_exit_signal_observations')->count());
    }

    public function test_import_command_reads_a_complete_ndjson_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'puma-exit-');
        $this->assertIsString($path);

        try {
            file_put_contents($path, $this->ndjson([
                $this->payload('2026-09-01', 100, 'state_seed'),
                $this->payload('2026-09-02', 101, 'historical_backfill'),
            ]));

            $tester = $this->commandTester();
            $status = $tester->execute(['file' => $path]);

            $this->assertSame(0, $status, $tester->getDisplay());
            $this->assertStringContainsString('"imported_count": 2', $tester->getDisplay());
            $this->assertSame(
                2,
                DB::connection('serving')->table('serving_exit_signal_observations')->count(),
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_import_command_reads_chronological_ndjson_from_stdin(): void
    {
        $tester = $this->commandTester();
        $tester->setInputs([$this->ndjson([
            $this->payload('2026-09-01', 100, 'state_seed'),
            $this->payload('2026-09-02', 101, 'historical_backfill'),
        ])]);

        $status = $tester->execute(['--stdin' => true]);

        $this->assertSame(0, $status, $tester->getDisplay());
        $this->assertStringContainsString('"observation_count": 2', $tester->getDisplay());
        $this->assertSame(
            2,
            DB::connection('serving')->table('serving_exit_signal_observations')->count(),
        );
    }

    public function test_malformed_stdin_ndjson_is_rejected_without_partial_processing(): void
    {
        $tester = $this->commandTester();
        $tester->setInputs([
            json_encode($this->payload('2026-09-01', 100, 'state_seed'), JSON_THROW_ON_ERROR)
            ."\n{not-json}\n",
        ]);

        $status = $tester->execute(['--stdin' => true]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Invalid JSON on NDJSON line 2', $tester->getDisplay());
        $this->assertSame(
            0,
            DB::connection('serving')->table('serving_exit_signal_observations')->count(),
        );
    }

    private function createServingContract(): void
    {
        Schema::connection('serving')->create('serving_instruments', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('isin');
            $table->string('provider_symbol');
        });
        Schema::connection('serving')->create('serving_exit_policies', function (Blueprint $table): void {
            $table->string('policy_name')->primary();
            $table->string('policy_version');
            $table->string('selection_procedure_version');
            $table->string('core_policy_fingerprint');
            $table->unsignedBigInteger('instrument_id');
            $table->string('isin');
            $table->string('provider_symbol');
            $table->string('status');
            $table->boolean('is_active');
            $table->boolean('recommendation_only');
            $table->boolean('automatic_execution');
            $table->boolean('release_gate_passed');
            $table->boolean('user_override');
            $table->string('activation_basis');
            $table->string('comparison_baseline');
            $table->string('signal_timing');
            $table->string('execution_timing');
            $table->integer('maximum_holding_sessions')->nullable();
            $table->string('formula');
            $table->json('configuration');
            $table->json('evidence');
        });
        Schema::connection('serving')->create('serving_exit_signal_observations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('policy_name');
            $table->unsignedSmallInteger('input_schema_version');
            $table->unsignedBigInteger('instrument_id');
            $table->date('market_session_date');
            $table->unsignedBigInteger('market_session_position');
            $table->dateTimeTz('signal_as_of');
            $table->dateTimeTz('valid_until');
            $table->string('observation_role');
            $table->boolean('state_only');
            $table->boolean('short_shock_10t');
            $table->double('short_shock_10t_score');
            $table->boolean('short_lower_tail_20t');
            $table->double('short_lower_tail_20t_score');
            $table->boolean('short_bear_area_40t');
            $table->double('short_bear_area_40t_score');
            $table->unsignedSmallInteger('short_vote_count');
            $table->double('champion_10t');
            $table->double('champion_40t');
            $table->boolean('long_curve_raw');
            $table->boolean('long_curve_confirmed');
            $table->double('trend_momentum_probability');
            $table->boolean('indicator_raw');
            $table->boolean('indicator_confirmed');
            $table->boolean('exit_recommended');
            $table->string('decision');
            $table->string('execution_timing');
            $table->boolean('automatic_execution');
            $table->string('input_sha256');
            $table->json('source_lineage');
            $table->timestampTz('imported_at')->useCurrent();
            $table->unique(['policy_name', 'market_session_date']);
            $table->unique(['policy_name', 'market_session_position']);
        });

        DB::connection('serving')->table('serving_instruments')->insert([
            'id' => PumaExitRecommendationService::INSTRUMENT_ID,
            'isin' => PumaExitRecommendationService::ISIN,
            'provider_symbol' => PumaExitRecommendationService::PROVIDER_SYMBOL,
        ]);
        DB::connection('serving')->table('serving_exit_policies')->insert([
            'policy_name' => PumaExitRecommendationService::POLICY_NAME,
            'policy_version' => PumaExitRecommendationService::POLICY_VERSION,
            'selection_procedure_version' => PumaExitRecommendationService::SELECTION_PROCEDURE_VERSION,
            'core_policy_fingerprint' => PumaExitRecommendationService::CORE_POLICY_FINGERPRINT,
            'instrument_id' => PumaExitRecommendationService::INSTRUMENT_ID,
            'isin' => PumaExitRecommendationService::ISIN,
            'provider_symbol' => PumaExitRecommendationService::PROVIDER_SYMBOL,
            'status' => 'active_recommendation',
            'is_active' => true,
            'recommendation_only' => true,
            'automatic_execution' => false,
            'release_gate_passed' => false,
            'user_override' => true,
            'activation_basis' => 'explicit_user_override',
            'comparison_baseline' => 'dynamic_tcn_observable',
            'signal_timing' => 'after_xetra_close',
            'execution_timing' => PumaExitRecommendationService::EXECUTION_TIME,
            'maximum_holding_sessions' => null,
            'formula' => PumaExitRecommendationService::FORMULA,
            'configuration' => json_encode([
                'policy_contract' => PumaExitRecommendationService::policyContract(),
            ], JSON_THROW_ON_ERROR),
            'evidence' => json_encode([
                'active_runtime_bundle' => [
                    'source_path' => PumaExitRecommendationService::BUNDLE_SOURCE_PATH,
                    'manifest_sha256' => PumaExitRecommendationService::BUNDLE_MANIFEST_SHA256,
                    'policy_json_sha256' => PumaExitRecommendationService::BUNDLE_POLICY_JSON_SHA256,
                ],
                'source_model_release_gates_passed' => false,
                'policy_release_gate_passed' => false,
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function payload(string $date, int $position, string $role): array
    {
        $validUntil = match ($date) {
            '2026-09-01' => '2026-09-02T09:00:00+02:00',
            '2026-09-02' => '2026-09-03T09:00:00+02:00',
        };

        return [
            'schema_version' => PumaExitRecommendationService::INPUT_SCHEMA_VERSION,
            'policy_name' => PumaExitRecommendationService::POLICY_NAME,
            'policy_version' => PumaExitRecommendationService::POLICY_VERSION,
            'core_policy_fingerprint' => PumaExitRecommendationService::CORE_POLICY_FINGERPRINT,
            'instrument_id' => PumaExitRecommendationService::INSTRUMENT_ID,
            'symbol' => PumaExitRecommendationService::SYMBOL,
            'isin' => PumaExitRecommendationService::ISIN,
            'provider_symbol' => PumaExitRecommendationService::PROVIDER_SYMBOL,
            'market_calendar' => PumaExitRecommendationService::MARKET_CALENDAR,
            'market_session_date' => $date,
            'market_session_position' => $position,
            'signal_as_of' => '2026-09-05T20:00:00+02:00',
            'valid_until' => $validUntil,
            'observation_role' => $role,
            'state_only' => true,
            'short_shock_10t' => true,
            'short_shock_10t_score' => -0.12,
            'short_lower_tail_20t' => true,
            'short_lower_tail_20t_score' => -0.08,
            'short_bear_area_40t' => false,
            'short_bear_area_40t_score' => -0.03,
            'champion_10t' => -0.04,
            'champion_40t' => -0.06,
            'trend_momentum_probability' => 0.40,
            'source_lineage' => [
                'bundle_manifest_sha256' => PumaExitRecommendationService::BUNDLE_MANIFEST_SHA256,
                'source_data_sha256' => hash('sha256', $date),
                'frozen_replay_verified' => true,
                'source_release_gate_passed' => false,
                'activation' => 'explicit_user_override',
                'comparison_baseline' => 'dynamic_tcn_observable',
            ],
            'scorer_diagnostics' => [
                'short_vote_count' => 2,
                'long_curve_raw' => true,
                'long_curve_confirmed' => $position === 101,
                'indicator_raw' => false,
                'indicator_confirmed' => false,
                'exit_signal' => $position === 101,
                'reasons' => $position === 101 ? ['short_vote_2_and_confirmed_long_curve'] : ['hold'],
                'automatic_execution' => false,
                'maximum_holding_sessions' => null,
            ],
        ];
    }

    private function commandTester(): CommandTester
    {
        $command = app(ImportPumaExitRecommendation::class);
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }

    private function ndjson(array $payloads): string
    {
        return implode("\n", array_map(
            static fn (array $payload): string => json_encode($payload, JSON_THROW_ON_ERROR),
            $payloads,
        ))."\n";
    }
}

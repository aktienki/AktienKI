<?php

namespace Tests\Unit;

use App\Services\FinalEntryCanonicalizer;
use App\Services\FinalEntryRawPredictionResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class FinalEntryRawPredictionMappingTest extends TestCase
{
    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDefault = (string) config('database.default');
        config()->set('database.connections.entry_mapping_main', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.connections.serving', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('entry_mapping_main');
        DB::purge('serving');
        DB::setDefaultConnection('entry_mapping_main');

        Schema::create('exchanges', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('code');
            $table->string('mic');
            $table->string('timezone');
        });
        Schema::create('instruments', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('exchange_id');
            $table->string('provider_symbol')->nullable();
            $table->string('isin')->nullable();
        });
        Schema::connection('serving')->create('serving_instruments', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('symbol');
            $table->string('provider_symbol')->nullable();
            $table->string('isin')->nullable();
            $table->string('exchange');
            $table->string('country_code')->nullable();
            $table->string('sector_code')->nullable();
            $table->string('instrument_type');
        });

        DB::table('exchanges')->insert([
            'id' => 3, 'code' => 'XETR', 'mic' => 'XETR',
            'timezone' => 'Europe/Berlin',
        ]);
        DB::table('instruments')->insert([
            'id' => 11, 'exchange_id' => 3, 'provider_symbol' => 'PUM.DE',
            'isin' => null,
        ]);
        DB::connection('serving')->table('serving_instruments')->insert([
            'id' => 501, 'symbol' => 'PUM', 'provider_symbol' => 'PUM.DE',
            'isin' => null, 'exchange' => 'XETR', 'country_code' => 'DE',
            'sector_code' => 'CONS', 'instrument_type' => 'stock',
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('entry_mapping_main');
        DB::purge('serving');
        DB::setDefaultConnection($this->originalDefault);
        parent::tearDown();
    }

    public function test_serving_id_is_bound_to_the_exact_local_instrument(): void
    {
        $mapping = (new FinalEntryRawPredictionResolver(
            new FinalEntryCanonicalizer,
        ))->resolveServingInstrumentMapping(501);

        self::assertSame(11, $mapping['local_instrument_id']);
        self::assertSame(501, $mapping['serving_instrument_id']);
        self::assertSame('unique_provider_symbol_exchange', $mapping['method']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $mapping['sha256']);
    }

    public function test_an_ambiguous_local_mapping_fails_closed(): void
    {
        DB::table('instruments')->insert([
            'id' => 12, 'exchange_id' => 3, 'provider_symbol' => 'PUM.DE',
            'isin' => null,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('INSTRUMENT_MAPPING_NOT_UNIQUE');

        (new FinalEntryRawPredictionResolver(
            new FinalEntryCanonicalizer,
        ))->resolveServingInstrumentMapping(501);
    }

    public function test_frozen_outbox_identity_maps_without_current_serving_row(): void
    {
        DB::connection('serving')->table('serving_instruments')->delete();
        $resolver = new FinalEntryRawPredictionResolver(new FinalEntryCanonicalizer);
        $mapping = $resolver->resolveOutboxInstrumentMapping([
            'instrument_id' => 501,
            'symbol' => 'PUM',
            'provider_symbol' => 'PUM.DE',
            'isin' => null,
            'exchange' => 'XETR',
            'country_code' => 'DE',
            'sector_code' => 'CONS',
            'instrument_type' => 'stock',
        ]);

        self::assertSame(11, $mapping['local_instrument_id']);
        self::assertSame(501, $mapping['serving_instrument_id']);
        self::assertSame(
            'immutable_outbox_unique_provider_symbol_exchange',
            $mapping['method'],
        );
    }

    public function test_unselected_basic_buy_without_quality_gate_is_valid_rating_evidence(): void
    {
        $resolver = new FinalEntryRawPredictionResolver(new FinalEntryCanonicalizer);
        $method = new \ReflectionMethod($resolver, 'deriveImmutableBatchRating');
        $rating = $method->invoke($resolver, [[
            'release_id' => '20000000-0000-4000-8000-000000000001',
            'horizon' => 20,
            'variant' => 'standard',
            'signal' => 'BUY',
            'expected_return' => 0.03,
            'selected_for_prediction' => false,
            'model_quality_label' => 'Basic',
            'quality_gate_passed' => false,
        ]]);

        self::assertSame('4-', $rating['batch_buy_rating']);
        self::assertFalse($rating['batch_is_watch']);
    }
}

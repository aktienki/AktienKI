<?php

namespace Tests\Unit;

use App\Services\FinalEntrySignalReadService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class FinalEntrySignalReadServiceTest extends TestCase
{
    public function test_disabled_cutover_preserves_existing_signals_without_reading_final_ledger(): void
    {
        config()->set('aktienki.final_entry_reads.enabled', false);
        DB::shouldReceive('table')->never();

        $service = new FinalEntrySignalReadService;

        $this->assertSame('BUY', $service->gateSignal('buy', false));
        $this->assertSame('HOLD', $service->gateSignal('hold', false));
        $this->assertSame('SELL', $service->gateSignal('sell', true));
        $this->assertTrue($service->allowsUserProfileBuy(7, 101));
        $this->assertTrue($service->allowsSavedFilterBuy(7, 12, 55));
    }

    public function test_enforced_cutover_demotes_only_buy_without_an_accepted_decision(): void
    {
        config()->set('aktienki.final_entry_reads.enabled', true);
        $service = new FinalEntrySignalReadService;

        $this->assertSame('WATCH', $service->gateSignal('BUY', false));
        $this->assertSame('BUY', $service->gateSignal('BUY', true));
        $this->assertSame('WATCH', $service->gateSignal('WATCH', false));
        $this->assertSame('HOLD', $service->gateSignal('HOLD', false));
        $this->assertSame('SELL', $service->gateSignal('SELL', false));
        $this->assertSame('BUY', $service->currentSignal('HOLD', true));
        $this->assertSame('WATCH', $service->currentSignal('BUY', false));
        $this->assertSame('SELL', $service->currentSignal('SELL', false));
    }

    public function test_user_profile_lookup_uses_only_current_final_view_and_exact_context(): void
    {
        config()->set('aktienki.final_entry_reads.enabled', true);
        $builder = Mockery::mock(Builder::class);
        DB::shouldReceive('table')
            ->once()
            ->with('current_final_entry_signals')
            ->andReturn($builder);
        $builder->shouldReceive('where')->once()->with('user_id', 7)->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('context_key', 'user')->andReturnSelf();
        $builder->shouldReceive('whereIn')->once()->with('source_instrument_id', [101, 202])->andReturnSelf();
        $builder->shouldReceive('get')->once()->with(Mockery::type('array'))->andReturn(collect([
            (object) ['source_instrument_id' => 202, 'decision_id' => 91],
        ]));

        $rows = (new FinalEntrySignalReadService)->userProfileBySourceInstrument(7, [0, 101, '202', 202]);

        $this->assertSame([202], $rows->keys()->all());
        $this->assertSame(91, $rows->get(202)->decision_id);
    }

    public function test_saved_filter_lookup_uses_strategy_context_and_local_instrument_id(): void
    {
        config()->set('aktienki.final_entry_reads.enabled', true);
        $builder = Mockery::mock(Builder::class);
        DB::shouldReceive('table')
            ->once()
            ->with('current_final_entry_signals')
            ->andReturn($builder);
        $builder->shouldReceive('where')->once()->with('user_id', 7)->andReturnSelf();
        $builder->shouldReceive('where')->once()->with('context_key', 'strategy:12')->andReturnSelf();
        $builder->shouldReceive('whereIn')->once()->with('instrument_id', [55])->andReturnSelf();
        $builder->shouldReceive('get')->once()->with(Mockery::type('array'))->andReturn(collect([
            (object) ['instrument_id' => 55, 'decision_id' => 92],
        ]));

        $rows = (new FinalEntrySignalReadService)->savedFilterByInstrument(7, 12, [55]);

        $this->assertSame([55], $rows->keys()->all());
        $this->assertSame(92, $rows->get(55)->decision_id);
    }

    public function test_empty_or_invalid_lookup_never_queries_the_database(): void
    {
        config()->set('aktienki.final_entry_reads.enabled', true);
        DB::shouldReceive('table')->never();
        $service = new FinalEntrySignalReadService;

        $this->assertTrue($service->userProfileBySourceInstrument(7, [0, null, 'x'])->isEmpty());
        $this->assertTrue($service->savedFilterByInstrument(7, 0, [55])->isEmpty());
    }
}

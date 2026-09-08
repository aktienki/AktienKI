<?php

namespace Tests\Unit;

use App\Services\FinalEntryShadowWriter;
use Tests\TestCase;

final class FinalEntryShadowCommandTest extends TestCase
{
    public function test_command_is_a_no_op_while_shadow_flag_is_disabled(): void
    {
        config()->set('aktienki.final_entry_shadow.enabled', false);
        $this->mock(FinalEntryShadowWriter::class)
            ->shouldNotReceive('run');

        $this->artisan('signals:shadow-final-entry')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_enabled_command_passes_the_bounded_batch_limit_to_writer(): void
    {
        config()->set('aktienki.final_entry_shadow.enabled', true);
        $this->mock(FinalEntryShadowWriter::class)
            ->shouldReceive('run')
            ->once()
            ->with(2)
            ->andReturn([
                'worker' => FinalEntryShadowWriter::WORKER_KEY,
                'processed_batch_count' => 0,
                'idle' => true,
            ]);

        $this->artisan('signals:shadow-final-entry', ['--batches' => 2])
            ->expectsOutputToContain('"processed_batch_count":0')
            ->assertSuccessful();
    }

    public function test_invalid_manual_batch_limit_is_rejected_before_any_write(): void
    {
        config()->set('aktienki.final_entry_shadow.enabled', false);
        $this->mock(FinalEntryShadowWriter::class)
            ->shouldNotReceive('run');

        $this->artisan('signals:shadow-final-entry', [
            '--force' => true,
            '--batches' => 0,
        ])->assertExitCode(2);
    }
}

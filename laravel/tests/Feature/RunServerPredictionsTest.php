<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunServerPredictionsTest extends TestCase
{
    public function test_it_runs_horizon_predictions_locally_for_the_requested_region(): void
    {
        config()->set('aktienki.python_engine.server_predictions_enabled', true);
        config()->set('aktienki.python_engine.path', '/srv/aktienki/python-engine');
        config()->set('aktienki.python_engine.executable', '/srv/aktienki/python-engine/.venv/bin/aktienki-engine');
        Process::fake();

        $this->artisan('predictions:run-server', ['region' => 'other', '--limit' => 5000])
            ->assertSuccessful();

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->path === '/srv/aktienki/python-engine'
                && $process->timeout === 7200
                && $process->command === [
                    '/srv/aktienki/python-engine/.venv/bin/aktienki-engine',
                    'predict-active', '--ai-type', 'horizon',
                    '--market-region', 'other', '--limit', '20000',
                ];
        });
    }

    public function test_it_does_not_start_python_when_server_predictions_are_disabled(): void
    {
        config()->set('aktienki.python_engine.server_predictions_enabled', false);
        Process::fake();

        $this->artisan('predictions:run-server', ['region' => 'americas'])
            ->expectsOutput('Serverseitige Predictions sind deaktiviert.')
            ->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_it_runs_all_markets_without_a_region_filter(): void
    {
        config()->set('aktienki.python_engine.server_predictions_enabled', true);
        config()->set('aktienki.python_engine.path', '/srv/aktienki/python-engine');
        config()->set('aktienki.python_engine.executable', '/srv/aktienki/python-engine/.venv/bin/aktienki-engine');
        Process::fake();

        $this->artisan('predictions:run-server', [
            'region' => 'all',
            '--limit' => 5000,
        ])->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            '/srv/aktienki/python-engine/.venv/bin/aktienki-engine',
            'predict-active', '--ai-type', 'horizon', '--limit', '20000',
        ]);
    }
}

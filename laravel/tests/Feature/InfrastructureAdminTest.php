<?php

namespace Tests\Feature;

use App\Http\Controllers\InfrastructureAdminController;
use App\Models\User;
use App\Services\MiniPcOperationsService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class InfrastructureAdminTest extends TestCase
{
    public function test_admin_can_open_infrastructure_page(): void
    {
        $admin = User::factory()->make(['id' => 1, 'is_admin' => true]);
        $operations = Mockery::mock(MiniPcOperationsService::class);
        $operations->shouldReceive('status')->once()->andReturn($this->fixtureStatus());
        $request = Request::create('/admin/infrastruktur');
        $request->setUserResolver(fn (): User => $admin);

        $view = app(InfrastructureAdminController::class)->index($request, $operations);

        $this->assertSame('admin.infrastructure', $view->name());
        $this->assertTrue($view->getData()['status']['reachable']);
    }

    public function test_non_admin_cannot_open_infrastructure_page(): void
    {
        $user = User::factory()->make(['id' => 2, 'is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.infrastructure'))
            ->assertForbidden();
    }

    public function test_admin_can_restore_database_tunnel(): void
    {
        $admin = User::factory()->make(['id' => 1, 'is_admin' => true]);
        $this->mock(MiniPcOperationsService::class, function ($mock): void {
            $mock->shouldReceive('restoreDatabaseTunnel')->once()->andReturn([
                'successful' => true,
                'message' => 'Tunnel aktiv',
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.infrastructure.action'), ['action' => 'restore_database_tunnel'])
            ->assertRedirect(route('admin.infrastructure'))
            ->assertSessionHas('status', 'Tunnel aktiv');
    }

    public function test_admin_can_restart_engine_worker(): void
    {
        $admin = User::factory()->make(['id' => 1, 'is_admin' => true]);
        $this->mock(MiniPcOperationsService::class, function ($mock): void {
            $mock->shouldReceive('restartEngineWorker')->once()->andReturn([
                'successful' => true,
                'message' => 'Worker aktiv',
            ]);
        });

        $this->actingAs($admin)
            ->post(route('admin.infrastructure.action'), ['action' => 'restart_engine_worker'])
            ->assertRedirect(route('admin.infrastructure'))
            ->assertSessionHas('status', 'Worker aktiv');
    }

    public function test_unknown_action_is_rejected(): void
    {
        $admin = User::factory()->make(['id' => 1, 'is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.infrastructure.action'), ['action' => 'shell_command'])
            ->assertSessionHasErrors('action');
    }

    private function fixtureStatus(): array
    {
        return [
            'reachable' => true,
            'error' => null,
            'metrics' => [
                'hostname' => 'aki', 'uptime' => 'up 2 days', 'load' => '0.10 0.20 0.30',
                'database_tunnel' => 'active', 'database_port' => '1', 'vscode_tunnels' => '1',
                'engine_worker' => '1', 'walk_forward' => '0', 'disk' => '3%',
            ],
            'database' => ['connected' => true, 'name' => 'aktienki', 'server' => '127.0.0.1/32', 'port' => '5432'],
            'errors' => [],
            'log' => ['CATCHUP_COMPLETE'],
            'runs' => [],
        ];
    }
}

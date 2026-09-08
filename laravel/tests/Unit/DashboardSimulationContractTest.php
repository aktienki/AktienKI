<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardSimulationContractTest extends TestCase
{
    public function test_simulation_is_local_only_and_has_an_explicit_live_bypass(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/DashboardController.php');

        $this->assertStringContainsString("app()->environment('local') && ! \$request->boolean('dashboard_live')", $controller);
        $this->assertStringContainsString('DashboardSimulationService::class', $controller);
        $this->assertStringContainsString("'dashboardActivities'", $controller);
        $this->assertStringContainsString("'dashboardSimulation'", $controller);
    }

    public function test_view_marks_fake_data_and_keeps_fake_objects_away_from_mutating_routes(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString("__('Lokale Simulation · keine Orders oder E-Mails')", $view);
        $this->assertStringContainsString("__('Aktivitäten')", $view);
        $this->assertStringNotContainsString("__('Meine Handelschancen')", $view);
        $this->assertStringContainsString('@if($dashboardSimulation)', $view);
        $this->assertStringContainsString("__('Nur Vorschau · keine echten Orders')", $view);
        $this->assertStringContainsString("__('Vorschau · kein Versand')", $view);
        $this->assertStringContainsString("in_array((\$reminder['type'] ?? null), ['earnings', 'simulation_event'], true)", $view);
        $this->assertStringContainsString("in_array(\$reminder['type'] ?? null, ['signal', 'prediction'], true)", $view);
        $this->assertStringNotContainsString("['signal', 'prediction', 'simulation_email']", $view);
    }
}

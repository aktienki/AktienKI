<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DepotExitPolicyContractTest extends TestCase
{
    public function test_controller_validates_and_calculates_the_exit_policy_before_resetting_a_depot(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__, 2).'/app/Http/Controllers/DepotController.php',
        );

        $validation = strpos($controller, "'exit_policy' => [");
        $calculation = strpos($controller, '$exitSimulation->calculate(');
        $destructiveTransaction = strpos($controller, 'DB::transaction(function () use (', $calculation);

        $this->assertNotFalse($validation);
        $this->assertNotFalse($calculation);
        $this->assertNotFalse($destructiveTransaction);
        $this->assertLessThan($calculation, $validation);
        $this->assertLessThan($destructiveTransaction, $calculation);
        $this->assertStringContainsString("'exit_policy' => \$exitPolicy", $controller);
        $this->assertStringContainsString("'entry_policy' => \$servingResult['entry_policy'] ?? null", $controller);
    }

    public function test_view_offers_dynamic_fixed_comparison_and_unchanged_strategy_exit(): void
    {
        $view = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/depots/show.blade.php',
        );

        $this->assertStringContainsString('name="exit_policy" value="stock_specific_final_exit"', $view);
        $this->assertStringContainsString('name="exit_policy" value="fixed_horizon_20t"', $view);
        $this->assertStringContainsString('name="exit_policy" value="strategy_default"', $view);
        $this->assertStringContainsString('final gefilterten BUY-Übergänge', $view);
        $this->assertStringContainsString('exakt denselben gefilterten Einstiegen', $view);
        $this->assertStringContainsString('denselben eingefrorenen und geprüften Eintrittsdatensatz', $view);
    }
}

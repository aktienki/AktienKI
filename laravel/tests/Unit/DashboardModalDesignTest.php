<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardModalDesignTest extends TestCase
{
    public function test_all_dashboard_modals_use_the_shared_modal_shell(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');
        $css = (string) file_get_contents($root.'/resources/css/app.css');

        $this->assertSame(6, substr_count($view, 'dashboard-modal-panel'));
        $this->assertSame(6, substr_count($view, 'dashboard-modal-header'));
        $this->assertGreaterThanOrEqual(6, substr_count($view, 'ak-modal-overlay'));
        $this->assertStringContainsString('/* Unified high-contrast modal system. */', $css);
        $this->assertStringContainsString('background: rgba(15, 23, 42, .62) !important;', $css);
        $this->assertStringContainsString('border: 2px solid #0e7490 !important;', $css);
        $this->assertStringContainsString('background: #0e7490 !important;', $css);
        $this->assertStringContainsString('#message-settings-modal .message-reminder-actions button[class*="border-emerald"]', $css);
        $this->assertStringContainsString('#message-settings-modal .message-reminder-actions button[class*="border-rose"]', $css);
    }
}

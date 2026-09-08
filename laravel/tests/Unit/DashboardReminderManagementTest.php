<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardReminderManagementTest extends TestCase
{
    public function test_expired_email_reminders_remain_manageable_but_stay_off_the_dashboard_schedule(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/DashboardController.php');
        $reminderController = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/PredictionPurchaseReminderController.php');
        $profileController = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ProfileController.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringNotContainsString("->whereDate('reminder.remind_on', '>=', today())", $controller);
        $this->assertStringContainsString("->whereIn('reminder.status', ['active', 'disabled', 'sent'])", $controller);
        $this->assertStringContainsString("'expired' => \$remindOn->isBefore(today())", $controller);
        $this->assertStringContainsString("->where('expired', false)", $controller);
        $this->assertStringContainsString("__('ABGELAUFEN')", $view);
        $this->assertStringContainsString("__('VERSENDET')", $view);
        $this->assertStringContainsString("route('notifications.purchase-reminders.destroy', \$item['id'])", $view);
        $this->assertStringContainsString("Route::delete('/notifications/purchase-reminders/{reminder}'", $routes);
        $this->assertStringContainsString('$reminder->delete()', $reminderController);
        $this->assertStringContainsString('dashboard_dismissed_corporate_event_ids', $profileController);
        $this->assertStringContainsString("Route::delete('/profile/dashboard-schedule-events/{event}'", $routes);
        $this->assertStringContainsString("route('profile.dashboard-schedule-events.destroy', \$item['id'])", $view);
    }
}

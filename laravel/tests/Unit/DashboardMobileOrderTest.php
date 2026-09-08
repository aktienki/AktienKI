<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardMobileOrderTest extends TestCase
{
    public function test_mobile_dashboard_uses_the_fixed_priority_order(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');
        $mobileStart = strrpos($view, '@media (max-width: 767px)');
        $mobileEnd = strpos($view, '@media (min-width: 768px)', $mobileStart ?: 0);
        $mobileCss = substr($view, $mobileStart ?: 0, ($mobileEnd ?: strlen($view)) - ($mobileStart ?: 0));

        $this->assertStringNotContainsString("@if(in_array('market', \$dashboardMobileCards, true))", $mobileCss);
        $this->assertStringContainsString(
            "#personal-dashboard #dashboard-middle-column {\n                display: contents !important;",
            $mobileCss,
        );
        $this->assertStringContainsString(
            "#personal-dashboard #dashboard-newscenter-card {\n                order: -130 !important;",
            $mobileCss,
        );
        $this->assertStringContainsString(
            "#personal-dashboard #dashboard-middle-column > article:first-child {\n                order: -120 !important;",
            $mobileCss,
        );
        $this->assertStringContainsString(
            "#personal-dashboard .dashboard-bento [data-dashboard-card=\"signal-cockpit\"] {\n                order: -79 !important;",
            $mobileCss,
        );
        $this->assertStringContainsString(
            "#personal-dashboard .dashboard-daily-tips {\n                order: -78 !important;",
            $mobileCss,
        );
    }

    public function test_mobile_dashboard_contains_a_compact_community_card(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('data-dashboard-card="community"', $view);
        $this->assertStringContainsString('dashboard-mobile-community', $view);
        $this->assertStringContainsString("\$communityOverview['posts']", $view);
        $this->assertStringContainsString("\$communityOverview['members']", $view);
        $this->assertStringContainsString("\$communityOverview['recent']", $view);
    }

    public function test_mobile_card_selection_is_not_overridden_and_hides_the_layout_cog(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString(
            "array_unique([...\$dashboardMobileCards, 'mobile-view'])",
            $view,
        );
        $this->assertStringNotContainsString(
            "array_unique([...\$dashboardMobileCards, 'community', 'mobile-view'])",
            $view,
        );
        $this->assertStringContainsString(
            'data-dashboard-layout-open class="hidden h-9 w-9',
            $view,
        );
    }

    public function test_mobile_configuration_has_touch_feedback_and_an_immediate_save_action(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/profile/mobile-view.blade.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ProfileController.php');

        $this->assertStringContainsString('data-mobile-card-form', $view);
        $this->assertStringContainsString('peer-checked:bg-cyan-400', $view);
        $this->assertStringContainsString("form.addEventListener('change', refreshCount)", $view);
        $this->assertStringContainsString("form.addEventListener('submit'", $view);
        $this->assertStringContainsString("{{ __('Auswahl speichern') }}", $view);
        $this->assertStringContainsString(
            "return redirect()->route('dashboard')->with('status', __('Mobile Ansicht gespeichert.'));",
            $controller,
        );
        $this->assertStringContainsString(
            "'champion', 'market', 'market-summary', 'schedule', 'strategy', 'signal-cockpit',\n        'personal', 'community', 'mobile-view'",
            $controller,
        );
        $this->assertStringContainsString('data-mobile-dashboard-card="champion"', (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php'));
    }

    public function test_new_mobile_texts_have_english_translations(): void
    {
        $translations = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/lang/en.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ([
            'Aktie des Tages',
            'Stärkste aktuell bewertete Aktie mit KI-Score und Risiko.',
            'Aktives Portfolio',
            'Community entdecken',
            'Auswahl speichern',
            'Wähle mindestens eine Karte aus.',
        ] as $key) {
            $this->assertArrayHasKey($key, $translations);
            $this->assertNotSame($key, $translations[$key]);
        }
    }

    public function test_signal_cockpit_uses_the_new_quality_and_risk_ratings(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString(
            'QualityGrade::fromPercent(\\App\\Support\\AiScore::toPercent($cockpitAverageScore))',
            $view,
        );
        $this->assertStringContainsString('QualityGrade::riskLevel(is_numeric($cockpitAverageRisk)', $view);
        $this->assertStringContainsString('KI {{ $changeScoreGrade }}', $view);
        $this->assertStringContainsString("{{ __('Risiko') }} {{ \$changeRiskLevel }}", $view);
        $this->assertStringNotContainsString('Ø KI {{ is_numeric($cockpitAverageScore) ? number_format', $view);
    }

    public function test_mobile_signal_cockpit_keeps_its_full_title_readable(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('truncate text-sm font-black text-[var(--ak-text)] sm:text-base', $view);
        $this->assertStringContainsString('border-cyan-400/20 px-1 py-0.5 sm:hidden', $view);
        $this->assertStringContainsString('class="hidden text-[9px] font-black text-cyan-600 sm:inline"', $view);
    }

    public function test_activity_feed_replaces_the_trading_opportunity_card(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString("__('Aktivitäten')", $view);
        $this->assertStringContainsString('collect($dashboardActivities)->take(5)', $view);
        $this->assertStringContainsString("'positive' => ['border-emerald-400/25", $view);
        $this->assertStringContainsString('$activityType === \'email\'', $view);
        $this->assertStringNotContainsString("__('Meine Handelschancen')", $view);
    }

    public function test_champion_reason_is_expandable_and_risk_always_has_a_level(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/DashboardController.php');

        $this->assertStringContainsString('dashboard-champion-reason group', $view);
        $this->assertStringContainsString('[&::-webkit-details-marker]:hidden', $view);
        $this->assertStringContainsString('default => 50.0', $view);
        $this->assertStringContainsString(':score="$rankRisk" :display="$rankRiskLevel"', $view);
        $this->assertStringContainsString('$stock->risk_percent', $controller);
        $this->assertStringContainsString('ServingScreenerService::class', $controller);
    }
}

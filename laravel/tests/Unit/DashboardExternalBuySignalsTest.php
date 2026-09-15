<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardExternalBuySignalsTest extends TestCase
{
    public function test_signal_card_only_renders_exact_batch_external_buy_confirmations(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $view = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString("->where('verdict', 'NO_OBJECTION')", $controller);
        $this->assertStringContainsString("\$stock->instrument_id.'|'.\$stock->serving_batch_id", $controller);
        $this->assertStringContainsString('@forelse($externalConfirmedBuys as $stock)', $view);
        $this->assertStringContainsString("__('Bestätigte POSITIV-Signale')", $view);
        $this->assertStringNotContainsString("\$recentSignalOverview['wait_count']", $view);
        $this->assertStringNotContainsString("\$recentSignalOverview['sell_count']", $view);
        $this->assertStringNotContainsString("\$recentSignalOverview['hold_count']", $view);
    }

    public function test_champion_uses_the_screener_composite_score_and_positive_panel_half(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $view = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');

        // Champion and its two ranked alternatives are ranked in the SAME
        // pool (every externally GPT-confirmed BUY signal) by the same
        // composite score the screener shows for that instrument, and the
        // champion is simply whichever ranks first - no extra, champion-only
        // requirement (e.g. a minimum panel decile) sits on top of that,
        // since such a requirement can let an alternative that does not
        // clear it outscore the champion that does.
        $this->assertStringContainsString('$stock->three_factor_buy_score', $controller);
        $this->assertStringContainsString('$stock->three_factor_external_score', $controller);
        $this->assertStringContainsString('$stock->three_factor_panel_score', $controller);
        $this->assertStringContainsString('is_numeric($stock->composite_score ?? null)', $controller);
        $this->assertStringContainsString('(float) $stock->composite_score', $controller);
        $this->assertStringContainsString(') / 3, 1)', $controller);
        $this->assertStringContainsString("__('Drei-Faktoren-Champion')", $view);
        $this->assertStringNotContainsString('@if($champion)<a href=', $view);
    }

    public function test_champion_card_lists_only_ranked_three_factor_alternatives(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DashboardController.php');
        $view = (string) file_get_contents($root.'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('$externalConfirmedRanked', $controller);
        $this->assertStringContainsString('->take(2)', $controller);
        $this->assertStringContainsString("\$alternative->alternative_category = 'rank';", $controller);
        $this->assertStringContainsString('$alternative->alternative_rank = $index + 2;', $controller);
        $this->assertStringContainsString('@forelse($threeFactorAlternatives as $alternative)', $view);
        $this->assertStringContainsString("__('Beste Alternativen')", $view);
        $this->assertStringContainsString("__('Ranking · Platz :rank'", $view);
        // The "maybe interesting soon" pick moved out of this ranked pool
        // entirely (on request) - it never went through the same
        // external-confirmation + panel pipeline the ranked ones do, so it
        // no longer gets an alternative_category/alternative_rank at all
        // and isn't pushed into $threeFactorAlternatives anymore.
        $this->assertStringNotContainsString("\$threeFactorAlternatives->push(\$additionalAlternative);", $controller);
        $this->assertStringNotContainsString("__('Vielleicht in ein paar Tagen interessant')", $view);
        // Instead it shows up as its own entry in the "Bestätigte
        // POSITIV-Signale" card, alongside the other individual picks.
        $this->assertStringContainsString("__('Bestätigte POSITIV-Signale')", $view);
        $this->assertStringContainsString('@if($additionalAlternative)', $view);
        $this->assertStringContainsString("__('Evtl. bald interessant')", $view);
        $this->assertStringContainsString('dashboard-alternative-entry', $view);
        $this->assertStringContainsString('dashboard-opportunity-card', $view);
        $this->assertStringContainsString('min-height: 3.9rem;', $view);
        $this->assertStringContainsString('margin-top: 1rem;', $view);
        $this->assertStringContainsString('id="dashboard-ranking-stocks-row"', $view);
        $this->assertStringContainsString('#personal-dashboard #dashboard-best-stocks-row', $view);
        $this->assertStringContainsString('row-gap: .4rem !important;', $view);
        $this->assertStringContainsString('const alignStockRows = () =>', $view);
        $this->assertStringContainsString('const targetTop = Math.max(rankingTop, opportunityTop);', $view);
        $this->assertStringContainsString('x-heroicon-o-check-badge', $view);
    }

    public function test_all_help_buttons_share_the_personal_card_design(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('width: 2.25rem; height: 2.25rem;', $view);
        $this->assertStringContainsString('border-radius: .5rem;', $view);
        $this->assertStringContainsString(".dashboard-card-help-btn--inline {\n            position: static;", $view);
        $this->assertStringNotContainsString('width: 1.25rem; height: 1.25rem;', $view);
    }

    public function test_desktop_champion_and_signal_cockpit_share_one_card_shell(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');

        $this->assertStringContainsString('id="dashboard-center-combined-card"', $view);
        $this->assertStringContainsString('.dashboard-center-combined-card > #dashboard-newscenter-card,', $view);
        $this->assertStringContainsString('.dashboard-center-combined-card > [data-dashboard-card="signal-cockpit"]', $view);
        $this->assertStringContainsString('grid-template-rows: auto minmax(0, 1fr);', $view);
        $this->assertStringNotContainsString('id="dashboard-newscenter-card" data-dashboard-top-row-card', $view);
        $this->assertStringContainsString('border-bottom: 1px solid var(--ak-border) !important;', $view);
        $this->assertStringNotContainsString('middleColumn.appendChild(signalCockpit)', $view);
        $this->assertStringContainsString('dashboardGrid.appendChild(dailyTips);', $view);
        $this->assertStringContainsString('dashboardGrid.appendChild(marketOverview);', $view);
        $this->assertStringContainsString("marketShell.classList.add('dashboard-market-shell-empty')", $view);
    }

    public function test_strategy_card_only_shows_the_portfolio_value_metric(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard.blade.php');
        $strategyStart = strpos($view, '@if ($strategyPortfolio)');
        $strategyEnd = strpos($view, '@else', $strategyStart ?: 0);
        $strategyCard = substr($view, $strategyStart ?: 0, ($strategyEnd ?: strlen($view)) - ($strategyStart ?: 0));

        $this->assertStringContainsString("{{ __('Depotwert') }}", $strategyCard);
        $this->assertStringContainsString('dashboard_positions_value', $strategyCard);
        $this->assertStringNotContainsString("{{ __('Kapital') }}", $strategyCard);
        $this->assertStringNotContainsString("{{ __('Gesamtwert') }}", $strategyCard);
        $this->assertStringNotContainsString("{{ __('Offene Positionen') }}", $strategyCard);
    }
}

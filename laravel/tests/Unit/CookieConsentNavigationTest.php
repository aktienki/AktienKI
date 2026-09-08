<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CookieConsentNavigationTest extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/components/cookie-consent.blade.php',
        );
    }

    public function test_banner_and_settings_have_explicit_dismiss_actions(): void
    {
        $this->assertStringContainsString('data-cc-back', $this->view);
        $this->assertStringContainsString('data-cc-dismiss', $this->view);
        $this->assertStringContainsString('data-cc-close', $this->view);
        $this->assertStringContainsString("{{ __('Zurück') }}", $this->view);
        $this->assertStringContainsString(
            "root.querySelectorAll('[data-cc-close],[data-cc-dismiss]').forEach(button=>button.addEventListener('click',dismiss))",
            $this->view,
        );
    }

    public function test_banner_is_non_blocking_and_only_settings_show_the_backdrop(): void
    {
        $this->assertStringContainsString('data-cc-backdrop data-cc-hidden', $this->view);
        $this->assertStringContainsString('<section class="cc-banner" data-cc-banner role="dialog" aria-labelledby="cc-title">', $this->view);
        $this->assertStringContainsString("const openBanner=()=>{root.removeAttribute('data-cc-hidden');backdrop.setAttribute('data-cc-hidden','');", $this->view);
        $this->assertStringContainsString('const openSettings=event=>{', $this->view);
        $this->assertStringContainsString("backdrop.removeAttribute('data-cc-hidden')", $this->view);
    }

    public function test_dismiss_rejects_optional_cookies_when_no_choice_exists(): void
    {
        $this->assertStringContainsString(
            'const dismiss=()=>{read()?close():necessary();restoreFocus()};',
            $this->view,
        );
    }

    public function test_back_action_returns_to_banner_without_saving_a_choice(): void
    {
        $this->assertStringContainsString(
            'const returnFromSettings=()=>{read()?close():openBanner();',
            $this->view,
        );
        $this->assertStringContainsString(
            "root.querySelector('[data-cc-back]')?.addEventListener('click',returnFromSettings)",
            $this->view,
        );
    }

    public function test_storage_failures_cannot_prevent_the_dialog_from_closing(): void
    {
        $this->assertStringContainsString('try{localStorage.setItem(key,JSON.stringify(value))}catch(_){}', $this->view);
        $this->assertStringContainsString("try{document.cookie='aki_cookie_consent=1;", $this->view);
        $this->assertStringContainsString('apply(value);close()', $this->view);
    }

    public function test_server_acceptance_and_cookie_are_safe_fallbacks(): void
    {
        $this->assertStringContainsString('serverAccepted=@js((bool) (auth()->user()?->accepted_cookie_notice ?? false))', $this->view);
        $this->assertStringContainsString("cookie.trim()==='aki_cookie_consent=1'", $this->view);
        $this->assertStringContainsString('preferences:false,analytics:false,marketing:false', $this->view);
    }

    public function test_escape_dismisses_the_settings_dialog(): void
    {
        $this->assertStringContainsString("event.key==='Escape'", $this->view);
        $this->assertStringContainsString('event.preventDefault();dismiss()', $this->view);
    }
}

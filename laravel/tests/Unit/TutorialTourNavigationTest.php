<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TutorialTourNavigationTest extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/components/tutorial-tour.blade.php',
        );
    }

    public function test_first_step_and_skip_remain_usable_without_alpine(): void
    {
        $this->assertStringNotContainsString('x-data=', $this->view);
        $this->assertStringNotContainsString('x-text=', $this->view);
        $this->assertStringContainsString('data-tour-title', $this->view);
        $this->assertStringContainsString('{{ $steps[0][0] }}', $this->view);
        $this->assertStringContainsString('method="POST"', $this->view);
        $this->assertStringContainsString("route('tutorial.complete')", $this->view);
        $this->assertStringContainsString('@csrf', $this->view);
    }

    public function test_dismissal_is_immediate_and_does_not_wait_for_the_request(): void
    {
        $this->assertStringContainsString('root.hidden=true;try{fetch(', $this->view);
        $this->assertStringContainsString("sessionStorage.setItem(sessionKey,'1')", $this->view);
        $this->assertStringContainsString('.catch(()=>{})', $this->view);
    }

    public function test_every_exit_path_uses_the_same_finish_action(): void
    {
        $this->assertStringContainsString("finishForm.addEventListener('submit',finish)", $this->view);
        $this->assertStringContainsString("querySelector('[data-tour-backdrop]').addEventListener('click',finish)", $this->view);
        $this->assertStringContainsString("event.key==='Escape'", $this->view);
        $this->assertStringContainsString('event.preventDefault();finish()', $this->view);
    }

    public function test_explicit_restart_ignores_session_dismissal(): void
    {
        $this->assertStringContainsString('data-force-tutorial="{{ $forceTutorial ?', $this->view);
        $this->assertStringContainsString("if(!forced&&sessionStorage.getItem(sessionKey)==='1')", $this->view);
    }

    public function test_spotlight_ignores_missing_or_invisible_targets(): void
    {
        $this->assertStringContainsString('if(!element)return;', $this->view);
        $this->assertStringContainsString('if(rect.width<=0||rect.height<=0)return;', $this->view);
        $this->assertStringContainsString('spotlight.hidden=false', $this->view);
    }
}

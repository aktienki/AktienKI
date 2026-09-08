<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GlobalMailHeaderTest extends TestCase
{
    public function test_standard_header_is_injected_into_every_html_mail_once(): void
    {
        $provider = (string) file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');
        $header = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor/mail/html/header.blade.php');

        $this->assertStringContainsString('data-aktienki-mail-header="standard"', $header);
        $this->assertStringContainsString("view('vendor.mail.html.header'", $provider);
        $this->assertStringContainsString("if (! str_contains(\$html, 'data-aktienki-mail-header=\"standard\"'))", $provider);
        $this->assertStringContainsString("'cid:aktienki-logo.png'", $provider);
        $this->assertStringContainsString("'aktienki-logo.png'", $provider);
    }
}

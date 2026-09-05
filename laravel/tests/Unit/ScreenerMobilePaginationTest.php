<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScreenerMobilePaginationTest extends TestCase
{
    public function test_mobile_screener_uses_bounded_server_side_pages(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root.'/app/Services/ServingScreenerService.php');
        $view = file_get_contents($root.'/resources/views/screener/index.blade.php');

        self::assertStringContainsString("preg_match('/Mobile|iPhone|iPod|Android/i'", $service);
        self::assertStringContainsString('$mobilePerPage = 25', $service);
        self::assertStringContainsString("'mobilePagination' => [", $service);
        self::assertStringContainsString("'mobile_page' => data_get(\$mobilePagination, 'page') + 1", $view);
    }
}

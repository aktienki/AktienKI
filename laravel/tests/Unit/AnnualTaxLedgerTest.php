<?php

namespace Tests\Unit;

use App\Services\AnnualTaxLedger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnnualTaxLedgerTest extends TestCase
{
    #[Test]
    public function it_refunds_same_year_tax_after_a_later_loss(): void
    {
        $ledger = new AnnualTaxLedger(1000, 25);

        self::assertSame(250.0, $ledger->book('2026-03-01', 2000));
        self::assertSame(-125.0, $ledger->book('2026-07-01', -500));
        self::assertSame(125.0, $ledger->totalTax());
        self::assertSame(500.0, $ledger->years()[2026]['taxable_profit_eur']);
    }

    #[Test]
    public function it_resets_the_allowance_for_each_calendar_year(): void
    {
        $ledger = new AnnualTaxLedger(1000, 25);

        self::assertSame(0.0, $ledger->book('2025-12-20', 1000));
        self::assertSame(0.0, $ledger->book('2026-01-20', 1000));
        self::assertSame(0.0, $ledger->totalTax());
        self::assertSame(1000.0, $ledger->years()[2025]['allowance_used_eur']);
        self::assertSame(1000.0, $ledger->years()[2026]['allowance_used_eur']);
    }
}

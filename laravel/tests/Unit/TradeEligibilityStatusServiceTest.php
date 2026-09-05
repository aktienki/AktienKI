<?php

namespace Tests\Unit;

use App\Services\TradeEligibilityStatusService;
use PHPUnit\Framework\TestCase;

final class TradeEligibilityStatusServiceTest extends TestCase
{
    public function test_buy_is_paused_and_resumed_with_hysteresis_without_changing_model_signal(): void
    {
        $service = new TradeEligibilityStatusService;

        [$initial] = $service->resolveStatus('BUY', 2.1, null, 1.0, 2.0);
        [$paused] = $service->resolveStatus('BUY', 0.9, $initial, 1.0, 2.0);
        [$stillPaused] = $service->resolveStatus('BUY', 1.5, $paused, 1.0, 2.0);
        [$resumed] = $service->resolveStatus('BUY', 2.0, $stillPaused, 1.0, 2.0);

        self::assertSame(TradeEligibilityStatusService::ACTIONABLE, $initial);
        self::assertSame(TradeEligibilityStatusService::PAUSED_LOW_RETURN, $paused);
        self::assertSame(TradeEligibilityStatusService::PAUSED_LOW_RETURN, $stillPaused);
        self::assertSame(TradeEligibilityStatusService::ACTIONABLE, $resumed);
    }

    public function test_non_buy_signal_is_never_actionable(): void
    {
        $service = new TradeEligibilityStatusService;

        [$status, $reason] = $service->resolveStatus('WAIT', 20.0, TradeEligibilityStatusService::ACTIONABLE, 1.0, 2.0);

        self::assertSame(TradeEligibilityStatusService::NOT_BUY, $status);
        self::assertSame('model_signal_not_buy', $reason);
    }
}

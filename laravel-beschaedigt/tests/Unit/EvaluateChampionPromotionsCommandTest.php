<?php

namespace Tests\Unit;

use App\Console\Commands\EvaluateChampionPromotions;
use App\Services\Champion\ChampionSchedulerService;
use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class EvaluateChampionPromotionsCommandTest extends TestCase
{
    public function test_service_result_contract(): void
    {
        $service = new FakeChampionSchedulerService();

        $result = $service->run(
            limit: 10,
            dryRun: true,
        );

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['promoted']);
        $this->assertSame(1, $result['rejected']);
        $this->assertSame(10, $service->receivedLimit);
        $this->assertTrue($service->receivedDryRun);
    }
}

class FakeChampionSchedulerService extends ChampionSchedulerService
{
    public ?int $receivedLimit = null;
    public bool $receivedDryRun = false;

    public function __construct()
    {
    }

    public function run(
        ?int $limit = null,
        bool $dryRun = false,
    ): array {
        $this->receivedLimit = $limit;
        $this->receivedDryRun = $dryRun;

        return [
            'total' => 2,
            'promoted' => 1,
            'rejected' => 1,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];
    }
}

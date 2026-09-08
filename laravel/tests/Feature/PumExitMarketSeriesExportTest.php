<?php

namespace Tests\Feature;

use App\Services\FredDailySeriesService;
use App\Services\PumExitMarketSeriesExportService;
use App\Services\TwelveDataService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use Mockery;
use Tests\TestCase;

final class PumExitMarketSeriesExportTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @throws JsonException */
    public function test_it_exports_the_four_canonical_series_as_stable_json(): void
    {
        CarbonImmutable::setTestNow('2026-09-05 19:30:00 UTC');
        $marketData = Mockery::mock(TwelveDataService::class);
        $marketData->shouldReceive('dailyHistory')
            ->once()->with('PUM:XETR', 2)
            ->andReturn($this->exchangeHistory(25.0));
        $marketData->shouldReceive('dailyHistory')
            ->once()->with('EXS1:XETR', 2)
            ->andReturn($this->exchangeHistory(218.0));
        $fred = $this->completeFred();
        $this->app->instance(
            PumExitMarketSeriesExportService::class,
            new PumExitMarketSeriesExportService($marketData, $fred),
        );

        $exitCode = Artisan::call('pum:export-exit-market-series', ['--days' => 2]);

        $this->assertSame(0, $exitCode);
        $payload = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['schema_version', 'generated_at', 'series'],
            array_keys($payload),
        );
        $this->assertSame(1, $payload['schema_version']);
        $this->assertSame('2026-09-05T19:30:00Z', $payload['generated_at']);
        $this->assertSame(
            ['pum', 'home_index', 'interest_rate_2y', 'vix'],
            array_keys($payload['series']),
        );
        $this->assertSame('PUM:XETR', $payload['series']['pum']['provider_symbol']);
        $this->assertSame('DE0006969603', $payload['series']['pum']['isin']);
        $this->assertSame('all', $payload['series']['pum']['adjustment']);
        $this->assertSame('EXS1:XETR', $payload['series']['home_index']['provider_symbol']);
        $this->assertSame('DGS2:FRED', $payload['series']['interest_rate_2y']['provider_symbol']);
        $this->assertSame('VIXCLS:FRED', $payload['series']['vix']['provider_symbol']);

        foreach ($payload['series'] as $series) {
            $this->assertCount(2, $series['bars']);
            $this->assertSame(
                ['2026-09-03', '2026-09-04'],
                array_column($series['bars'], 'date'),
            );
            foreach ($series['bars'] as $bar) {
                $this->assertSame(
                    ['date', 'open', 'high', 'low', 'close', 'adjusted_close', 'volume'],
                    array_keys($bar),
                );
            }
        }
        $rate = $payload['series']['interest_rate_2y']['bars'][0];
        $this->assertSame(3.52, $rate['close']);
        $this->assertSame($rate['close'], $rate['open']);
        $this->assertSame($rate['close'], $rate['high']);
        $this->assertSame($rate['close'], $rate['low']);
        $this->assertSame($rate['close'], $rate['adjusted_close']);
        $this->assertSame(0.0, $rate['volume']);
    }

    public function test_it_fails_without_emitting_a_partial_envelope_when_fred_is_incomplete(): void
    {
        $fred = Mockery::mock(FredDailySeriesService::class);
        $fred->shouldReceive('csv')->once()->with(
            'DGS2', Mockery::type('string'), Mockery::type('string'),
        )->andReturn("observation_date,DGS2\n2026-09-03,3.52\n2026-09-04,.\n");
        $this->bindCompleteExchangeHistory($fred);

        $exitCode = Artisan::call('pum:export-exit-market-series', ['--days' => 2]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('interest_rate_2y has only 1 valid bars', Artisan::output());
        $this->assertStringNotContainsString('"series"', Artisan::output());
    }

    public function test_it_fails_without_querying_fred_when_an_exchange_series_is_incomplete(): void
    {
        $marketData = Mockery::mock(TwelveDataService::class);
        $marketData->shouldReceive('dailyHistory')
            ->once()->with('PUM:XETR', 2)
            ->andReturn([$this->exchangeHistory(25.0)[0]]);
        $fred = Mockery::mock(FredDailySeriesService::class);
        $fred->shouldNotReceive('csv');
        $this->app->instance(
            PumExitMarketSeriesExportService::class,
            new PumExitMarketSeriesExportService($marketData, $fred),
        );

        $exitCode = Artisan::call('pum:export-exit-market-series', ['--days' => 2]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('pum has only 1 valid bars', Artisan::output());
        $this->assertStringNotContainsString('"series"', Artisan::output());
    }

    public function test_it_rejects_invalid_day_counts_before_requesting_market_data(): void
    {
        $marketData = Mockery::mock(TwelveDataService::class);
        $fred = Mockery::mock(FredDailySeriesService::class);
        $fred->shouldNotReceive('csv');
        $this->app->instance(
            PumExitMarketSeriesExportService::class,
            new PumExitMarketSeriesExportService($marketData, $fred),
        );

        $exitCode = Artisan::call('pum:export-exit-market-series', ['--days' => '1']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('days must be between 2 and 5000', Artisan::output());
        $this->assertStringNotContainsString('"series"', Artisan::output());
    }

    private function bindCompleteExchangeHistory(
        ?FredDailySeriesService $fred = null,
    ): void {
        $marketData = Mockery::mock(TwelveDataService::class);
        $marketData->shouldReceive('dailyHistory')
            ->once()->with('PUM:XETR', 2)
            ->andReturn($this->exchangeHistory(25.0));
        $marketData->shouldReceive('dailyHistory')
            ->once()->with('EXS1:XETR', 2)
            ->andReturn($this->exchangeHistory(218.0));
        $this->app->instance(
            PumExitMarketSeriesExportService::class,
            new PumExitMarketSeriesExportService(
                $marketData, $fred ?? $this->completeFred(),
            ),
        );
    }

    private function completeFred(): FredDailySeriesService
    {
        $fred = Mockery::mock(FredDailySeriesService::class);
        $fred->shouldReceive('csv')->once()->with(
            'DGS2', Mockery::type('string'), Mockery::type('string'),
        )->andReturn(
            "observation_date,DGS2\n2026-09-04,3.55\n2026-09-02,.\n2026-09-01,\n2026-09-03,3.52\n",
        );
        $fred->shouldReceive('csv')->once()->with(
            'VIXCLS', Mockery::type('string'), Mockery::type('string'),
        )->andReturn(
            "DATE,VIXCLS\n2026-09-04,15.4\n2026-09-03,15.1\n",
        );

        return $fred;
    }

    private function exchangeHistory(float $base): array
    {
        return [
            $this->exchangeBar('2026-09-04', $base + 1.0),
            $this->exchangeBar('2026-09-03', $base),
        ];
    }

    private function exchangeBar(string $date, float $close): array
    {
        return [
            'timestamp' => CarbonImmutable::parse($date, 'Europe/Berlin')->utc()->timestamp,
            'open' => $close - 0.2,
            'high' => $close + 0.3,
            'low' => $close - 0.4,
            'close' => $close,
            'adjusted_close' => $close,
            'volume' => 1000.0,
        ];
    }
}

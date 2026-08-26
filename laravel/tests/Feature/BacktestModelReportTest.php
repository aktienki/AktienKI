<?php

namespace Tests\Feature;

use App\Http\Controllers\PredictionController;
use ReflectionMethod;
use Tests\TestCase;

class BacktestModelReportTest extends TestCase
{
    public function test_it_combines_performance_of_the_same_model_across_horizons(): void
    {
        $method = new ReflectionMethod(PredictionController::class, 'aggregateModelStatistics');
        $method->setAccessible(true);

        $statistics = $method->invoke(new PredictionController, [
            [
                'model_name' => 'Horizon Atlas', 'quality_tier' => 'Solide', 'trades' => 10,
                'deployed_capital' => 1000, 'hit_rate' => 60, 'average_return' => 5,
                'profit_factor' => 1.5, 'max_drawdown' => 10,
                'first_trade' => '2025-01-01', 'last_trade' => '2025-03-01',
            ],
            [
                'model_name' => 'Horizon Atlas', 'quality_tier' => 'Top', 'trades' => 30,
                'deployed_capital' => 3000, 'hit_rate' => 40, 'average_return' => 1,
                'profit_factor' => 1.1, 'max_drawdown' => 15,
                'first_trade' => '2024-12-01', 'last_trade' => '2025-04-01',
            ],
        ]);

        $this->assertCount(1, $statistics);
        $model = $statistics->first();
        $this->assertSame('Horizon Atlas', $model->model_name);
        $this->assertSame('Top', $model->quality_tier);
        $this->assertSame(40, $model->trades);
        $this->assertSame(4000.0, $model->deployed_capital);
        $this->assertEqualsWithDelta(45.0, $model->hit_rate, 0.001);
        $this->assertEqualsWithDelta(2.0, $model->average_return, 0.001);
        $this->assertEqualsWithDelta(1.2, $model->profit_factor, 0.001);
        $this->assertSame(15.0, $model->max_drawdown);
        $this->assertSame('2024-12-01', $model->first_trade);
        $this->assertSame('2025-04-01', $model->last_trade);
    }
}

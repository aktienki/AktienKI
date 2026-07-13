<?php

namespace Tests\Unit;

use App\Models\ModelChampion;
use App\Models\ModelComparison;
use App\Models\ModelEloHistory;
use App\Repositories\AiDashboardRepository;
use App\Services\AiDashboard\AiDashboardService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class AiDashboardServiceTest extends TestCase
{
    public function test_it_builds_the_dashboard_overview(): void
    {
        $repository = new InMemoryAiDashboardRepository();
        $service = new AiDashboardService($repository);

        $result = $service->overview();

        $this->assertSame(
            1,
            $result['summary']['champions_total']
        );
        $this->assertSame(
            1,
            $result['summary']['challengers_total']
        );
        $this->assertSame(
            1,
            $result['summary']['promotions_recommended']
        );

        $item = $result['items'][0];

        $this->assertSame(10, $item['active_model_id']);
        $this->assertSame('xgboost', $item['algorithm']);
        $this->assertSame('up', $item['elo_trend']);
        $this->assertCount(1, $item['challengers']);
        $this->assertCount(2, $item['elo_history']);
    }

    public function test_empty_dashboard_returns_empty_summary(): void
    {
        $repository = new InMemoryAiDashboardRepository();
        $repository->returnEmpty = true;

        $result = (
            new AiDashboardService($repository)
        )->overview();

        $this->assertSame(
            0,
            $result['summary']['champions_total']
        );
        $this->assertNull(
            $result['summary']['average_elo']
        );
        $this->assertSame([], $result['items']);
    }
}

class InMemoryAiDashboardRepository extends AiDashboardRepository
{
    public bool $returnEmpty = false;

    public function champions(
        ?int $strategyProfileId = null,
        ?int $instrumentId = null,
        int $limit = 50,
    ): EloquentCollection {
        if ($this->returnEmpty) {
            return new EloquentCollection();
        }

        $champion = new ModelChampion();
        $champion->setRawAttributes([
            'id' => 1,
            'strategy_profile_id' => 2,
            'instrument_id' => 3,
            'active_trained_model_id' => 10,
            'previous_trained_model_id' => 9,
            'algorithm' => 'xgboost',
            'elo_rating' => 1580,
            'validated_predictions_count' => 1200,
            'direction_accuracy' => 0.64,
            'average_strategy_return' => 0.08,
            'rmse' => 0.02,
            'stability_score' => 0.91,
            'activated_at' => '2026-07-01 00:00:00',
            'activation_reason' => 'Test promotion',
        ]);

        $champion->setRelation(
            'strategyProfile',
            (object) [
                'id' => 2,
                'code' => 'us-tech-momentum-v1',
                'name' => 'US Tech Momentum V1',
            ],
        );

        $champion->setRelation(
            'instrument',
            (object) [
                'id' => 3,
                'symbol' => 'AAPL',
                'name' => 'Apple Inc.',
            ],
        );

        $champion->setRelation(
            'activeModel',
            (object) [
                'id' => 10,
                'version' => 'v10',
            ],
        );

        $champion->setRelation(
            'previousModel',
            (object) [
                'id' => 9,
                'version' => 'v9',
            ],
        );

        return new EloquentCollection([$champion]);
    }

    public function challengersFor(
        int $strategyProfileId,
        ?int $instrumentId,
        int $limit = 10,
    ): EloquentCollection {
        $challenger = new \App\Models\ModelChallenger();
        $challenger->setRawAttributes([
            'id' => 20,
            'trained_model_id' => 11,
            'algorithm' => 'lightgbm',
            'status' => 'evaluating',
            'elo_rating' => 1565,
            'validated_predictions_count' => 1100,
            'direction_accuracy' => 0.65,
            'average_strategy_return' => 0.085,
            'rmse' => 0.019,
            'stability_score' => 0.92,
        ]);

        return new EloquentCollection([$challenger]);
    }

    public function latestComparisonFor(
        int $strategyProfileId,
        ?int $instrumentId,
    ): ?ModelComparison {
        $comparison = new ModelComparison();
        $comparison->setRawAttributes([
            'champion_selection_score' => 0.78,
            'promotion_recommended' => true,
        ]);

        return $comparison;
    }

    public function eloHistoryForModel(
        int $trainedModelId,
        int $limit = 30,
    ): EloquentCollection {
        $first = new ModelEloHistory();
        $first->setRawAttributes([
            'rating_before' => 1500,
            'rating_after' => 1510,
            'rating_change' => 10,
            'result' => 'win',
            'rated_at' => '2026-07-01 00:00:00',
        ]);

        $second = new ModelEloHistory();
        $second->setRawAttributes([
            'rating_before' => 1510,
            'rating_after' => 1580,
            'rating_change' => 70,
            'result' => 'win',
            'rated_at' => '2026-07-10 00:00:00',
        ]);

        return new EloquentCollection([$first, $second]);
    }
}

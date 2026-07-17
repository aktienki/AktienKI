<?php

namespace Tests\Unit;

use App\Models\ModelChallenger;
use App\Models\ModelChampion;
use App\Models\ModelComparison;
use App\Models\ModelEloHistory;
use App\Repositories\ModelComparisonRepository;
use App\Services\Elo\EloCalculator;
use App\Services\Elo\EloRatingService;
use App\Services\ModelComparison\ComparisonPersistenceContext;
use App\Services\ModelComparison\ModelComparisonPersistenceService;
use App\Services\ModelComparison\ModelComparisonService;
use App\Services\ModelComparison\ModelMetrics;
use App\Services\ModelComparison\SelectionScoreCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ModelComparisonPersistenceServiceTest extends TestCase
{
    public function test_it_persists_comparison_history_and_ratings(): void
    {
        $repository = new InMemoryModelComparisonRepository();

        $service = new ModelComparisonPersistenceService(
            $repository
        );

        $result = $this->comparisonResult();

        $saved = $service->persist(
            result: $result,
            context: $this->context(),
        );

        $this->assertSame(77, $saved->id);

        $this->assertCount(
            1,
            $repository->comparisons
        );

        $this->assertCount(
            2,
            $repository->eloHistory
        );

        $this->assertSame(
            100,
            $repository->updatedChampionId
        );

        $this->assertSame(
            101,
            $repository->updatedChallengerId
        );

        $this->assertEqualsWithDelta(
            $result->eloResult->championAfter,
            $repository->updatedChampionRating,
            0.000001,
        );

        $this->assertEqualsWithDelta(
            $result->eloResult->challengerAfter,
            $repository->updatedChallengerRating,
            0.000001,
        );

        $this->assertSame(
            'loss',
            $repository->eloHistory[0]['result']
        );

        $this->assertSame(
            'win',
            $repository->eloHistory[1]['result']
        );

        $this->assertSame(
            'challenger',
            $repository->comparisons[0]['winner']
        );

        $this->assertTrue(
            $repository->comparisons[0][
                'promotion_recommended'
            ]
        );
    }

    public function test_it_propagates_transaction_errors(): void
    {
        $repository = new InMemoryModelComparisonRepository();
        $repository->throwTransactionError = true;

        $service = new ModelComparisonPersistenceService(
            $repository
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database failed');

        $service->persist(
            result: $this->comparisonResult(),
            context: $this->context(),
        );
    }

    private function comparisonResult()
    {
        return (
            new ModelComparisonService(
                new SelectionScoreCalculator(),
                new EloRatingService(
                    new EloCalculator()
                ),
            )
        )->compare(
            champion: $this->championMetrics(),
            challenger: $this->challengerMetrics(),
            rules: [
                'minimum_validated_predictions' => 1000,
                'minimum_selection_score_difference' => 0.02,
                'minimum_direction_accuracy_difference' => 0.01,
                'minimum_strategy_return_difference' => 0.005,
                'elo_k_factor' => 32,
            ],
        );
    }

    private function context(): ComparisonPersistenceContext
    {
        return new ComparisonPersistenceContext(
            strategyProfileId: 1,
            instrumentId: 1,
            championRecordId: 100,
            challengerRecordId: 101,
            championModelId: 10,
            challengerModelId: 11,
            predictionCount: 1200,
            championMetrics: $this->championMetrics(),
            challengerMetrics: $this->challengerMetrics(),
            metadata: [
                'source' => 'phpunit',
            ],
        );
    }

    private function championMetrics(): ModelMetrics
    {
        return new ModelMetrics(
            directionAccuracy: 0.58,
            strategyReturn: 0.03,
            rmse: 0.03,
            stability: 0.75,
            predictionCount: 1200,
            eloRating: 1550,
        );
    }

    private function challengerMetrics(): ModelMetrics
    {
        return new ModelMetrics(
            directionAccuracy: 0.65,
            strategyReturn: 0.08,
            rmse: 0.02,
            stability: 0.92,
            predictionCount: 1200,
            eloRating: 1500,
        );
    }
}

class InMemoryModelComparisonRepository extends ModelComparisonRepository
{
    public array $comparisons = [];

    public array $eloHistory = [];

    public ?int $updatedChampionId = null;

    public ?float $updatedChampionRating = null;

    public ?int $updatedChallengerId = null;

    public ?float $updatedChallengerRating = null;

    public bool $throwTransactionError = false;

    public function transaction(callable $callback): mixed
    {
        if ($this->throwTransactionError) {
            throw new RuntimeException('Database failed');
        }

        return $callback();
    }

    public function createComparison(
        array $attributes
    ): ModelComparison {
        $this->comparisons[] = $attributes;

        $comparison = new ModelComparison();
        $comparison->id = 77;

        return $comparison;
    }

    public function createEloHistory(
        array $attributes
    ): ModelEloHistory {
        $this->eloHistory[] = $attributes;

        return new ModelEloHistory();
    }

    public function updateChampionRating(
        int $championId,
        float $eloRating,
    ): ModelChampion {
        $this->updatedChampionId = $championId;
        $this->updatedChampionRating = $eloRating;

        $champion = new ModelChampion();
        $champion->id = $championId;
        $champion->elo_rating = $eloRating;

        return $champion;
    }

    public function updateChallengerRating(
        int $challengerId,
        float $eloRating,
    ): ModelChallenger {
        $this->updatedChallengerId = $challengerId;
        $this->updatedChallengerRating = $eloRating;

        $challenger = new ModelChallenger();
        $challenger->id = $challengerId;
        $challenger->elo_rating = $eloRating;

        return $challenger;
    }
}
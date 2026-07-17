<?php

namespace Tests\Unit;

use App\Models\ModelChallenger;
use App\Models\ModelChampion;
use App\Repositories\ChampionPromotionRepository;
use App\Services\Champion\ChampionPromotionContext;
use App\Services\Champion\ChampionPromotionService;
use App\Services\Champion\PromotionDecision;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;
use RuntimeException;

class ChampionPromotionServiceTest extends TestCase
{
    public function test_it_promotes_an_approved_challenger(): void
    {
        $repository = new InMemoryChampionPromotionRepository();

        $service = new ChampionPromotionService($repository);

        $decision = PromotionDecision::approved(
            championModelId: 10,
            challengerModelId: 11,
            reason: 'Challenger ist objektiv besser.',
            decidedAt: new DateTimeImmutable(
                '2026-07-12T18:00:00+00:00'
            ),
            checks: [
                'minimum_predictions' => true,
                'cooldown_complete' => true,
            ],
        );

        $result = $service->execute(
            decision: $decision,
            context: new ChampionPromotionContext(
                championRecordId: 100,
                challengerRecordId: 101,
                comparisonId: 77,
                metrics: [
                    'selection_score' => 0.84,
                ],
                metadata: [
                    'source' => 'phpunit',
                ],
            ),
        );

        $this->assertSame(11, $result->active_trained_model_id);
        $this->assertSame(10, $result->previous_trained_model_id);
        $this->assertSame('lightgbm', $result->algorithm);
        $this->assertSame('promoted', $repository->challenger->status);
        $this->assertSame(
            77,
            $repository->lastMetrics['comparison_id']
        );
        $this->assertTrue($repository->transactionExecuted);
    }

    public function test_it_rejects_execution_of_a_denied_decision(): void
    {
        $repository = new InMemoryChampionPromotionRepository();
        $service = new ChampionPromotionService($repository);

        $decision = PromotionDecision::rejected(
            championModelId: 10,
            challengerModelId: 11,
            reason: 'Cooldown aktiv.',
            decidedAt: new DateTimeImmutable(),
        );

        $this->expectException(LogicException::class);

        $service->execute(
            decision: $decision,
            context: new ChampionPromotionContext(
                championRecordId: 100,
                challengerRecordId: 101,
                comparisonId: 77,
            ),
        );
    }

    public function test_it_rejects_mismatching_model_ids(): void
    {
        $repository = new InMemoryChampionPromotionRepository();
        $repository->challenger->trained_model_id = 99;

        $service = new ChampionPromotionService($repository);

        $decision = PromotionDecision::approved(
            championModelId: 10,
            challengerModelId: 11,
            reason: 'Promotion erlaubt.',
            decidedAt: new DateTimeImmutable(),
        );

        $this->expectException(LogicException::class);

        $service->execute(
            decision: $decision,
            context: new ChampionPromotionContext(
                championRecordId: 100,
                challengerRecordId: 101,
                comparisonId: 77,
            ),
        );
    }

    public function test_it_propagates_transaction_errors(): void
    {
        $repository = new InMemoryChampionPromotionRepository();
        $repository->throwTransactionError = true;

        $service = new ChampionPromotionService($repository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database failed');

        $service->execute(
            decision: PromotionDecision::approved(
                championModelId: 10,
                challengerModelId: 11,
                reason: 'Promotion erlaubt.',
                decidedAt: new DateTimeImmutable(),
            ),
            context: new ChampionPromotionContext(
                championRecordId: 100,
                challengerRecordId: 101,
                comparisonId: 77,
            ),
        );
    }
}

class InMemoryChampionPromotionRepository
    extends ChampionPromotionRepository
{
    public ModelChampion $champion;
    public ModelChallenger $challenger;
    public bool $transactionExecuted = false;
    public bool $throwTransactionError = false;
    public array $lastMetrics = [];

    public function __construct()
    {
        $this->champion = new ModelChampion();
        $this->champion->id = 100;
        $this->champion->strategy_profile_id = 1;
        $this->champion->instrument_id = 1;
        $this->champion->active_trained_model_id = 10;
        $this->champion->previous_trained_model_id = null;
        $this->champion->algorithm = 'xgboost';
        $this->champion->status = 'active';
        $this->champion->elo_rating = 1550;

        $this->challenger = new ModelChallenger();
        $this->challenger->id = 101;
        $this->challenger->strategy_profile_id = 1;
        $this->challenger->instrument_id = 1;
        $this->challenger->trained_model_id = 11;
        $this->challenger->algorithm = 'lightgbm';
        $this->challenger->status = 'evaluating';
        $this->challenger->elo_rating = 1580;
        $this->challenger->validated_predictions_count = 1200;
        $this->challenger->direction_accuracy = 0.65;
        $this->challenger->average_strategy_return = 0.08;
        $this->challenger->rmse = 0.02;
        $this->challenger->stability_score = 0.92;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->throwTransactionError) {
            throw new RuntimeException('Database failed');
        }

        $this->transactionExecuted = true;

        return $callback();
    }

    public function lockChampion(int $championRecordId): ModelChampion
    {
        return $this->champion;
    }

    public function lockChallenger(int $challengerRecordId): ModelChallenger
    {
        return $this->challenger;
    }

    public function promote(
        ModelChampion $champion,
        ModelChallenger $challenger,
        string $reason,
        array $metrics,
        array $metadata,
        Carbon $activatedAt,
    ): ModelChampion {
        $this->lastMetrics = $metrics;

        $champion->setRawAttributes([
            ...$champion->getAttributes(),
            'previous_trained_model_id' =>
                $champion->active_trained_model_id,
            'active_trained_model_id' =>
                $challenger->trained_model_id,
            'algorithm' => $challenger->algorithm,
            'elo_rating' => $challenger->elo_rating,
            'activated_at' => $activatedAt->toDateTimeString(),
            'activation_reason' => $reason,
        ]);

        $challenger->setRawAttributes([
            ...$challenger->getAttributes(),
            'status' => 'promoted',
            'evaluation_finished_at' =>
                $activatedAt->toDateTimeString(),
            'status_reason' => $reason,
        ]);

        return $champion;
    }
}

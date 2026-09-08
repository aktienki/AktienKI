<?php

namespace Tests\Feature;

use App\Jobs\GenerateExternalBuyReview;
use App\Jobs\SendSignalEmailAfterReview;
use App\Models\ExternalBuyReview;
use App\Models\Instrument;
use App\Models\Prediction;
use App\Models\SavedPredictionFilter;
use App\Models\SignalEmailDelivery;
use App\Models\User;
use App\Notifications\SignalChangedNotification;
use App\Services\ExternalBuyReviewService;
use App\Services\FinalEntrySignalReadService;
use App\Services\SignalEmailDonutChart;
use App\Services\SignalEmailMetrics;
use App\Services\SignalEmailService;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class ExternalBuyReviewTest extends TestCase
{
    // The project test database is provisioned from the production-compatible
    // schema because walk-forward tables are installed from database/sql.
    use DatabaseTransactions;

    public function test_existing_signal_email_scan_queues_one_external_review_for_a_new_buy(): void
    {
        config()->set('aktienki.external_buy_review.enabled', true);
        Queue::fake();
        $instrument = $this->instrument();
        $previous = $this->prediction($instrument, 'HOLD', now()->subMinutes(10));
        $current = $this->prediction($instrument, 'BUY', now()->subMinutes(5), [
            'ai_score' => 8.7,
            'confidence' => 91,
            'predicted_price_20d' => 123.45,
        ]);

        $first = app(SignalEmailService::class)->scan(30);
        $second = app(SignalEmailService::class)->scan(30);

        $this->assertSame(1, $first['external_reviews']);
        $this->assertSame(0, $second['external_reviews']);
        $this->assertDatabaseCount('external_buy_reviews', 1);
        $this->assertDatabaseHas('external_buy_reviews', [
            'prediction_id' => $current->id,
            'previous_prediction_id' => $previous->id,
            'status' => 'pending',
            'model' => 'gpt-5.6-terra',
        ]);
        Queue::assertPushed(GenerateExternalBuyReview::class, 1);

        $storedReview = ExternalBuyReview::query()->firstOrFail();
        $identity = $storedReview->request_identity;
        $this->assertSame('Test AG', $identity['company_name']);
        $this->assertSame('TEST', $identity['ticker']);
        $this->assertArrayNotHasKey('ai_score', $identity);
        $this->assertArrayNotHasKey('confidence', $identity);
        $this->assertArrayNotHasKey('predicted_price_20d', $identity);
        $this->assertSame('BUY', data_get($storedReview->signal_scope, 'aktienki_ml_analysis.signal'));
        $this->assertSame(91.0, data_get($storedReview->signal_scope, 'aktienki_ml_analysis.confidence_percent'));
        $this->assertSame(8.7, data_get($storedReview->signal_scope, 'aktienki_ml_analysis.ai_score'));
    }

    public function test_signal_email_scan_does_not_review_buy_to_buy_or_sell_transitions(): void
    {
        config()->set('aktienki.external_buy_review.enabled', true);
        Queue::fake();
        $buyInstrument = $this->instrument('BUY1');
        $this->prediction($buyInstrument, 'BUY', now()->subMinutes(10));
        $this->prediction($buyInstrument, 'BUY', now()->subMinutes(5));
        $sellInstrument = $this->instrument('SELL1');
        $this->prediction($sellInstrument, 'HOLD', now()->subMinutes(10));
        $this->prediction($sellInstrument, 'SELL', now()->subMinutes(5));

        $stats = app(SignalEmailService::class)->scan(30);

        $this->assertSame(0, $stats['external_reviews']);
        $this->assertDatabaseCount('external_buy_reviews', 0);
        Queue::assertNothingPushed();
    }

    public function test_review_combines_ml_context_with_public_research_and_persists_sources_and_cost(): void
    {
        config()->set('aktienki.external_buy_review.api_key', 'test-key');
        config()->set('aktienki.external_buy_review.model', 'gpt-5.6-terra');
        config()->set('aktienki.external_buy_review.minimum_source_domains', 2);
        $review = $this->review();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openAiResponse())]);

        $completed = app(ExternalBuyReviewService::class)->review($review);

        $this->assertSame('completed', $completed->status);
        $this->assertSame('NO_OBJECTION', $completed->model_verdict);
        $this->assertSame('NO_OBJECTION', $completed->verdict);
        $this->assertSame(78, $completed->confidence);
        $this->assertCount(2, $completed->sources);
        $this->assertSame(1, $completed->search_call_count);
        $this->assertSame(36_000, $completed->estimated_cost_microusd);
        $this->assertCount(1, $completed->key_findings);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $data['model'] === 'gpt-5.6-terra'
                && $data['tools'] === [['type' => 'web_search']]
                && $data['tool_choice'] === 'required'
                && $data['max_tool_calls'] === 3
                && $data['include'] === ['web_search_call.action.sources']
                && $data['text']['format']['type'] === 'json_schema'
                && str_contains($data['input'], 'Test AG')
                && str_contains($encoded, 'aktienki_ml_analysis')
                && str_contains($encoded, 'Webrecherche')
                && str_contains($encoded, 'Synthese');
        });
    }

    public function test_review_forces_insufficient_evidence_when_sources_are_not_independent(): void
    {
        config()->set('aktienki.external_buy_review.api_key', 'test-key');
        config()->set('aktienki.external_buy_review.minimum_source_domains', 2);
        $review = $this->review();
        $response = $this->openAiResponse();
        $response['output'][0]['action']['sources'] = [
            ['url' => 'https://example.com/ir/update', 'title' => 'Official update'],
            ['url' => 'https://example.com/news/update', 'title' => 'Copied update'],
        ];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($response)]);

        $completed = app(ExternalBuyReviewService::class)->review($review);

        $this->assertSame('NO_OBJECTION', $completed->model_verdict);
        $this->assertSame('INSUFFICIENT_EVIDENCE', $completed->verdict);
        $this->assertSame(25, $completed->confidence);
        $this->assertNotEmpty($completed->research_limitations);
    }

    public function test_completed_external_review_is_rendered_inside_buy_signal_email(): void
    {
        $review = $this->review();
        $signalPrediction = $review->prediction()->firstOrFail();
        $signalPrediction->update([
            'prediction_horizon_minutes' => 7200,
            'ai_score' => 50,
            'confidence' => 92,
            'risk_score' => .12,
            'predicted_price_5d' => 105,
        ]);
        foreach ([
            [14400, 'predicted_price_10d', 110],
            [21600, 'predicted_price_15d', 115],
            [28800, 'predicted_price_20d', 120],
        ] as [$minutes, $field, $target]) {
            $this->prediction($signalPrediction->instrument, 'BUY', $signalPrediction->prediction_time, [
                'prediction_horizon_minutes' => $minutes,
                $field => $target,
            ]);
        }
        $review->forceFill([
            'status' => 'completed',
            'model_verdict' => 'CAUTION',
            'verdict' => 'CAUTION',
            'confidence' => 72,
            'summary' => 'Die externe Recherche sieht ein erhöhtes Ereignisrisiko.',
            'positive_factors' => ['Die Liquidität ist laut Geschäftsbericht solide.'],
            'risk_factors' => ['Quartalszahlen werden in Kürze veröffentlicht.'],
            'key_findings' => [[
                'claim' => 'Der nächste Berichtstermin steht unmittelbar bevor.',
                'source_urls' => ['https://example.com/investors/calendar'],
            ]],
            'sources' => [[
                'url' => 'https://example.com/investors/calendar',
                'title' => 'Investor Relations Kalender',
                'domain' => 'example.com',
            ]],
            'researched_at' => now(),
        ])->save();
        $user = User::factory()->create(['preferences' => ['locale' => 'de', 'theme' => 'light']]);
        $strategy = SavedPredictionFilter::query()->create([
            'user_id' => $user->id,
            'name' => 'Teststrategie',
            'filters' => [],
        ]);

        $html = (new SignalChangedNotification(
            $review->prediction()->with('instrument')->firstOrFail(),
            $strategy,
            'HOLD',
            0,
        ))->toMail($user)->render();

        $this->assertStringContainsString('UNABHÄNGIGER KI-CHECK', $html);
        $this->assertStringContainsString('NEIN', $html);
        $this->assertStringContainsString('BUY extern abgestuft', $html);
        $this->assertStringContainsString('Vorsicht', $html);
        $this->assertStringContainsString('erhöhtes Ereignisrisiko', $html);
        $this->assertStringContainsString('Quartalszahlen werden in Kürze veröffentlicht', $html);
        $this->assertStringContainsString('Investor Relations Kalender', $html);
        $this->assertStringContainsString('🇩🇪', $html);
        $this->assertStringContainsString('cid:aki-signal-score-risk.png', $html);
        $this->assertStringContainsString('PROGNOSE-ZIELKURSE', $html);
        $this->assertStringContainsString('105,00 EUR', $html);
        $this->assertStringContainsString('110,00 EUR', $html);
        $this->assertStringContainsString('115,00 EUR', $html);
        $this->assertStringContainsString('120,00 EUR', $html);
        $this->assertStringNotContainsString('92,0%', $html);
        $this->assertStringContainsString('<ul style=', $html);
        $this->assertStringContainsString('<li style=', $html);
        $this->assertStringNotContainsString('&lt;div', $html);
        $this->assertStringNotContainsString('&lt;ul', $html);
        $this->assertStringNotContainsString('<pre><code>', $html);
    }

    public function test_signal_email_metrics_and_donut_use_score_risk_and_all_horizons(): void
    {
        $instrument = $this->instrument('DONUT');
        $time = now();
        $prediction = $this->prediction($instrument, 'BUY', $time, [
            'prediction_horizon_minutes' => 7200,
            'ai_score' => 87,
            'risk_score' => .18,
            'predicted_price_5d' => 104,
        ]);
        foreach ([
            [14400, 'predicted_price_10d', 108],
            [21600, 'predicted_price_15d', 112],
            [28800, 'predicted_price_20d', 116],
        ] as [$minutes, $field, $target]) {
            $this->prediction($instrument, 'BUY', $time, [
                'prediction_horizon_minutes' => $minutes,
                $field => $target,
            ]);
        }

        $metrics = app(SignalEmailMetrics::class)->forPrediction($prediction);
        $png = app(SignalEmailDonutChart::class)->render($metrics, true);
        $imageInfo = getimagesizefromstring($png);

        $this->assertSame(8.7, $metrics['score']);
        $this->assertSame('1−', $metrics['score_grade']);
        $this->assertSame(18.0, $metrics['risk_percent']);
        $this->assertSame(2, $metrics['risk_level']);
        $this->assertSame(104.0, $metrics['horizon_targets'][5]['target']);
        $this->assertSame(108.0, $metrics['horizon_targets'][10]['target']);
        $this->assertSame(112.0, $metrics['horizon_targets'][15]['target']);
        $this->assertSame(116.0, $metrics['horizon_targets'][20]['target']);
        $this->assertSame('image/png', $imageInfo['mime'] ?? null);
        $this->assertSame(400, $imageInfo[0] ?? null);
        $this->assertSame(165, $imageInfo[1] ?? null);
    }

    public function test_signal_mail_job_waits_for_review_and_sends_after_completion(): void
    {
        config()->set('aktienki.external_buy_review.enabled', true);
        Notification::fake();
        $review = $this->review();
        $user = User::factory()->create(['preferences' => ['locale' => 'de']]);
        $strategy = SavedPredictionFilter::query()->create([
            'user_id' => $user->id,
            'name' => 'Mail-Wartetest',
            'filters' => [],
        ]);
        $prediction = $review->prediction()->with('instrument')->firstOrFail();
        $pendingJob = new SendSignalEmailAfterReview($user, $prediction, $strategy, 'HOLD');
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->once()->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(20);
        $pendingJob->setJob($queueJob);

        $pendingJob->handle();
        Notification::assertNothingSent();

        $review->update([
            'status' => 'completed',
            'model_verdict' => 'NO_OBJECTION',
            'verdict' => 'NO_OBJECTION',
            'confidence' => 80,
            'summary' => 'Kein wesentlicher externer Einwand.',
            'researched_at' => now(),
        ]);
        (new SendSignalEmailAfterReview($user, $prediction, $strategy, 'HOLD'))->handle();

        Notification::assertSentTo($user, SignalChangedNotification::class);
    }

    public function test_signal_mail_job_suppresses_buy_when_final_strategy_decision_is_not_active(): void
    {
        config()->set('aktienki.final_entry_reads.enabled', true);
        Notification::fake();
        $instrument = $this->instrument('FINAL0');
        $prediction = $this->prediction($instrument, 'BUY', now());
        $user = User::factory()->create(['preferences' => ['locale' => 'de']]);
        $strategy = SavedPredictionFilter::query()->create([
            'user_id' => $user->id,
            'name' => 'FINAL-E-Mail-Gate',
            'filters' => [],
        ]);
        $delivery = SignalEmailDelivery::query()->create([
            'user_id' => $user->id,
            'saved_prediction_filter_id' => $strategy->id,
            'prediction_id' => $prediction->id,
            'instrument_id' => $instrument->id,
            'previous_signal' => 'HOLD',
            'new_signal' => 'BUY',
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        $reader = Mockery::mock(FinalEntrySignalReadService::class);
        $reader->shouldReceive('allowsSavedFilterBuy')
            ->once()
            ->with($user->id, $strategy->id, $instrument->id)
            ->andReturnFalse();
        app()->instance(FinalEntrySignalReadService::class, $reader);

        (new SendSignalEmailAfterReview(
            $user,
            $prediction,
            $strategy,
            'HOLD',
            $delivery->id,
        ))->handle();

        Notification::assertNothingSent();
        $this->assertDatabaseHas('signal_email_deliveries', [
            'id' => $delivery->id,
            'status' => 'suppressed',
        ]);
    }

    private function instrument(string $symbol = 'TEST'): Instrument
    {
        return Instrument::query()->create([
            'type' => 'stock',
            'symbol' => $symbol,
            'isin' => 'DE000'.str_pad((string) abs(crc32($symbol)), 7, '0', STR_PAD_LEFT),
            'name' => 'Test AG',
            'country' => 'DE',
            'currency' => 'EUR',
            'is_active' => true,
            'is_tradeable' => true,
        ]);
    }

    private function prediction(Instrument $instrument, string $signal, $time, array $extra = []): Prediction
    {
        return Prediction::query()->create($extra + [
            'instrument_id' => $instrument->id,
            'prediction_time' => $time,
            'interval' => '1d',
            'current_price' => 100,
            'strategy' => 'long',
            'signal' => $signal,
        ]);
    }

    private function review(): ExternalBuyReview
    {
        $instrument = $this->instrument();
        $previous = $this->prediction($instrument, 'HOLD', now()->subDay());
        $current = $this->prediction($instrument, 'BUY', now());

        return ExternalBuyReview::query()->create([
            'instrument_id' => $instrument->id,
            'prediction_id' => $current->id,
            'previous_prediction_id' => $previous->id,
            'triggered_at' => $current->prediction_time,
            'status' => 'pending',
            'provider' => 'openai',
            'model' => 'gpt-5.6-terra',
            'prompt_version' => 'independent-buy-review-v1',
            'request_identity' => [
                'company_name' => 'Test AG',
                'ticker' => 'TEST',
                'isin' => $instrument->isin,
                'country' => 'DE',
            ],
            'signal_scope' => [
                'ai_type' => $current->ai_type,
                'position_side' => $current->position_side,
                'timeframe' => $current->timeframe,
                'model_scope' => $current->model_scope,
                'prediction_horizon_minutes' => $current->prediction_horizon_minutes,
            ],
        ]);
    }

    private function openAiResponse(): array
    {
        $result = [
            'verdict' => 'NO_OBJECTION',
            'confidence' => 78,
            'summary' => 'Die externe Recherche ergab derzeit keinen wesentlichen Einwand.',
            'positive_factors' => ['Aktuelle Prognose wurde bestätigt.'],
            'risk_factors' => ['Das nächste Ergebnisdatum bleibt ein Ereignisrisiko.'],
            'key_findings' => [[
                'claim' => 'Das Unternehmen bestätigte seine Prognose.',
                'source_urls' => ['https://example.com/ir/update', 'https://reuters.com/markets/test-update'],
            ]],
            'research_limitations' => [],
        ];

        return [
            'id' => 'resp_test_123',
            'model' => 'gpt-5.6-terra',
            'output' => [
                [
                    'type' => 'web_search_call',
                    'action' => ['sources' => [
                        ['url' => 'https://example.com/ir/update', 'title' => 'Official update'],
                        ['url' => 'https://reuters.com/markets/test-update', 'title' => 'Independent report'],
                    ]],
                ],
                [
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'annotations' => [],
                    ]],
                ],
            ],
            'usage' => ['input_tokens' => 10_000, 'output_tokens' => 500, 'total_tokens' => 10_500],
        ];
    }
}

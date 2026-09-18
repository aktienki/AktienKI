<?php

namespace App\Console\Commands;

use App\Services\ServingScreenerService;
use App\Services\StockAiAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateStockAiAssessments extends Command
{
    protected $signature = 'reports:stock-ai-assessments {--force : Regenerate even if today\'s assessment already exists}';

    protected $description = 'Writes a short opportunities/risks/key-factors assessment (via The Grid) for every current POSITIV stock';

    public function handle(ServingScreenerService $screener, StockAiAssessmentService $assessments): int
    {
        if (! config('aktienki.stock_ai_assessment.enabled', false) || (bool) config('aktienki.ai_disabled')) {
            $this->warn('STOCK_AI_ASSESSMENT_ENABLED is off or ai_disabled is set - nothing done.');

            return self::SUCCESS;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Only genuinely new BUY signals get a (paid) AI assessment - a
        // stock that was already BUY yesterday and still is today is not
        // reassessed, even if today's assessment for it doesn't exist yet.
        $wasBuyYesterday = DB::table('today_highlights_mv')
            ->where('serving_signal', 'BUY')
            ->whereDate('prediction_date', $yesterday)
            ->pluck('instrument_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();
        $isBuyToday = DB::table('today_highlights_mv')
            ->where('serving_signal', 'BUY')
            ->whereDate('prediction_date', $today)
            ->pluck('instrument_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();
        $newBuyInstrumentIds = $isBuyToday->diffKeys($wasBuyYesterday)->keys();

        $stocks = $screener->currentStocks()
            ->filter(fn (object $stock): bool => strtoupper((string) ($stock->personalized_signal ?? '')) === 'BUY')
            ->filter(fn (object $stock): bool => $this->option('force') || $newBuyInstrumentIds->contains((int) $stock->instrument_id))
            ->values();

        if (! $this->option('force')) {
            $existingInstrumentIds = DB::table('stock_ai_assessments')
                ->where('assessment_date', $today)
                ->pluck('instrument_id')
                ->map(fn ($id): int => (int) $id)
                ->flip();
            $stocks = $stocks->reject(fn (object $stock): bool => $existingInstrumentIds->has((int) $stock->instrument_id))->values();
        }

        $this->info("New BUY signals to assess: {$stocks->count()}.");

        $generated = 0;
        $failed = 0;
        // Batched: the shared system prompt is only paid for once per
        // chunk instead of once per stock. Chunked (not one giant call) so
        // an unusually active day can't push a single request past a safe
        // token/time budget.
        foreach ($stocks->chunk(10) as $chunk) {
            try {
                $batch = $assessments->generateBatch($chunk->all());
            } catch (Throwable $exception) {
                $failed += $chunk->count();
                report($exception);
                $this->error('Batch von '.$chunk->count().' Aktien fehlgeschlagen: '.$exception->getMessage());

                continue;
            }

            foreach ($chunk as $stock) {
                $generation = $batch[$stock->symbol] ?? null;
                if ($generation === null) {
                    $failed++;
                    $this->error("{$stock->symbol}: fehlte in der Batch-Antwort.");

                    continue;
                }

                $result = $generation['result'];

                DB::table('stock_ai_assessments')->updateOrInsert(
                    ['instrument_id' => $stock->instrument_id, 'assessment_date' => $today],
                    [
                        'prediction_id' => null,
                        'model' => mb_substr($generation['model'], 0, 100),
                        'recommendation' => $result['recommendation'],
                        'confidence' => $result['confidence'],
                        'summary' => $result['summary'],
                        'opportunities' => json_encode($result['opportunities'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'risks' => json_encode($result['risks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'key_factors' => json_encode($result['key_factors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'input_snapshot' => json_encode($generation['input_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'raw_response' => json_encode($generation['raw_response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $generated++;
            }
        }

        $this->info("Generated: {$generated}, failed: {$failed}.");

        return self::SUCCESS;
    }
}

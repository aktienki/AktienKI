<?php

namespace App\Console\Commands;

use App\Jobs\GenerateExternalBuyReview;
use App\Models\ExternalBuyReview;
use App\Models\Instrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GenerateServingBuyReviews extends Command
{
    protected $signature = 'reports:serving-buy-reviews {--backfill-current : Review every current BUY once, then continue transition-only}';
    protected $description = 'Queue Twelve Data/Luna reviews for genuine Serving transitions to BUY';

    public function handle(): int
    {
        $rows = DB::connection('serving')->table('serving_current_stock_signals as signal')
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'signal.instrument_id')
            ->join('serving_active_models as active', 'active.instrument_id', '=', 'instrument.id')
            ->where('instrument.instrument_type', 'stock')->where('instrument.is_active', true)
            ->where('instrument.is_tradeable', true)
            ->get(['signal.*', 'instrument.symbol', 'instrument.provider_symbol', 'instrument.name',
                'instrument.isin', 'instrument.exchange', 'instrument.country_code']);

        $main = Instrument::query()->where('type', 'stock')->whereNull('deleted_at')->get()
            ->flatMap(fn (Instrument $item) => collect([$item->symbol, $item->provider_symbol])->filter()
                ->mapWithKeys(fn ($symbol) => [strtoupper((string) $symbol) => $item]));
        $reviewedSymbols = ExternalBuyReview::query()->whereIn('status', ['pending', 'running', 'completed'])
            ->with('instrument:id,symbol,provider_symbol')->get()->flatMap(fn (ExternalBuyReview $review) =>
                collect([$review->instrument?->symbol, $review->instrument?->provider_symbol])->filter()->map(fn ($s) => strtoupper((string) $s))
            )->flip();

        $queued = 0; $transitions = 0; $skipped = 0;
        DB::transaction(function () use ($rows, $main, $reviewedSymbols, &$queued, &$transitions, &$skipped): void {
            foreach ($rows as $row) {
                $signal = strtoupper((string) $row->signal);
                $state = DB::table('external_buy_review_signal_states')->where('serving_instrument_id', $row->instrument_id)->lockForUpdate()->first();
                $isTransition = $state && strtoupper((string) $state->last_signal) !== 'BUY' && $signal === 'BUY';
                $isBackfill = $this->option('backfill-current') && ! $state && $signal === 'BUY';

                if ($isTransition || $isBackfill) {
                    $transitions++;
                    $instrument = $main->get(strtoupper((string) $row->provider_symbol))
                        ?? $main->get(strtoupper((string) $row->symbol));
                    $alreadyReviewed = $reviewedSymbols->has(strtoupper((string) $row->provider_symbol))
                        || $reviewedSymbols->has(strtoupper((string) $row->symbol));
                    if ($instrument && ! $alreadyReviewed) {
                        $review = ExternalBuyReview::query()->firstOrCreate(
                            ['serving_instrument_id' => $row->instrument_id, 'serving_batch_id' => $row->batch_id],
                            [
                                'instrument_id' => $instrument->id, 'prediction_id' => null,
                                'previous_prediction_id' => null, 'triggered_at' => $row->as_of,
                                'status' => 'pending', 'provider' => 'openai', 'model' => 'gpt-5.6-luna',
                                'prompt_version' => 'buy-twelve-data-luna-v3',
                                'request_identity' => array_filter([
                                    'company_name' => $row->name, 'ticker' => $row->provider_symbol ?: $row->symbol,
                                    'isin' => $row->isin, 'exchange_code' => $row->exchange, 'country' => $row->country_code,
                                ]),
                                'signal_scope' => [
                                    'source' => 'aktienki_serving_next', 'serving_batch_id' => $row->batch_id,
                                    'aktienki_ml_analysis' => [
                                        'ticker' => $row->symbol, 'signal' => 'BUY', 'buy_rating' => $row->buy_rating,
                                        'best_buy_quality' => $row->best_buy_quality,
                                        'buy_confirmations' => (int) $row->buy_confirmations,
                                        'buy_scopes' => json_decode((string) $row->buy_scopes, true) ?: [],
                                    ],
                                ],
                            ],
                        );
                        if ($review->wasRecentlyCreated) {
                            GenerateExternalBuyReview::dispatch($review->id);
                            $queued++;
                        }
                    } else $skipped++;
                }

                DB::table('external_buy_review_signal_states')->updateOrInsert(
                    ['serving_instrument_id' => $row->instrument_id],
                    ['last_signal' => $signal, 'last_batch_id' => $row->batch_id, 'last_seen_at' => now(),
                        'created_at' => $state?->created_at ?? now(), 'updated_at' => now()],
                );
            }
        });

        $this->info("Serving stocks: {$rows->count()}, BUY transitions/backfills: {$transitions}, queued: {$queued}, skipped: {$skipped}");
        return self::SUCCESS;
    }
}

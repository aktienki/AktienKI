<?php

namespace App\Console\Commands;

use App\Services\ServingChartCacheService;
use App\Services\TwelveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Many strategy-tester/backtest features (RunFilteredBacktest,
 * AutomatedPortfolioService::candidates()) implicitly require several years
 * of daily price_bars history per instrument. Stocks that never received a
 * full backfill are silently invisible there. This loads standard 1d bars
 * (interval='1d', source='twelve_data' - the same as every other stock)
 * for instruments that still have less than the requested history.
 */
final class BackfillShortHistoryStocks extends Command
{
    protected $signature = 'stocks:backfill-short-history {--years=3} {--days=800} {--limit=25} {--symbol=*}';

    protected $description = 'Backfills 1d price_bars for active stocks that have less than N years of history';

    public function handle(TwelveDataService $marketData, ServingChartCacheService $charts): int
    {
        $years = max(1, min(10, (int) $this->option('years')));
        $days = max(20, min(5000, (int) $this->option('days')));
        $threshold = now()->subYears($years);

        $coverage = DB::table('price_bars')
            ->where('interval', '1d')
            ->selectRaw('instrument_id, COUNT(*) AS bar_count, MIN(bar_time) AS earliest')
            ->groupBy('instrument_id');

        $query = DB::table('instruments as instrument')
            ->leftJoinSub($coverage, 'coverage', 'coverage.instrument_id', '=', 'instrument.id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->where(fn ($q) => $q->whereNull('coverage.earliest')->orWhere('coverage.earliest', '>', $threshold))
            // The main instruments table has no display_metadata column (that
            // lives on serving_instruments); providerSymbol() falls back to
            // german_listing_symbol / provider_symbol / symbol without it.
            ->select(
                'instrument.id', 'instrument.symbol', 'instrument.provider_symbol',
                'instrument.german_listing_symbol', 'instrument.german_listing_exchange',
            )
            ->orderByRaw('COALESCE(coverage.bar_count, 0) ASC')
            ->orderBy('instrument.id');

        $symbols = collect($this->option('symbol'))->map(fn ($symbol) => strtoupper(trim((string) $symbol)))->filter()->values();
        if ($symbols->isNotEmpty()) {
            $query->whereIn(DB::raw('UPPER(instrument.symbol)'), $symbols->all());
        } elseif ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $completed = $failed = 0;
        foreach ($query->cursor() as $instrument) {
            $providerSymbol = $charts->providerSymbol($instrument);
            try {
                $history = $marketData->dailyHistory($providerSymbol, $days);
                if ($history === []) {
                    throw new \RuntimeException('Keine Tageskurse geliefert.');
                }
                $rows = collect($history)->map(fn (array $bar): array => [
                    'instrument_id' => $instrument->id,
                    'interval' => '1d',
                    'bar_time' => date('Y-m-d H:i:sP', (int) $bar['timestamp']),
                    'open' => $bar['open'], 'high' => $bar['high'], 'low' => $bar['low'],
                    'close' => $bar['close'], 'adjusted_close' => $bar['adjusted_close'],
                    'volume' => $bar['volume'], 'source' => 'twelve_data',
                    'created_at' => now(), 'updated_at' => now(),
                ])->all();
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('price_bars')->upsert($chunk, ['instrument_id', 'interval', 'bar_time'], [
                        'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                    ]);
                }
                $earliest = Carbon::parse(collect($rows)->min('bar_time'))->toDateString();
                $completed++;
                $this->info("{$instrument->symbol} ({$providerSymbol}): ".count($rows)." Kurse, ab {$earliest}");
            } catch (Throwable $error) {
                $failed++;
                $this->warn("{$instrument->symbol} ({$providerSymbol}): {$error->getMessage()}");
            }
        }

        $this->line("Abgeschlossen: {$completed} erfolgreich, {$failed} fehlgeschlagen.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

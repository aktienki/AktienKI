<?php

namespace App\Console\Commands;

use App\Services\TwelveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RefreshCommodityHistory extends Command
{
    protected $signature = 'commodities:refresh-history {--days=365 : Rolling calendar-day retention window}';

    protected $description = 'Refresh active commodity daily prices and remove commodity bars outside the retention window';

    /**
     * serving_instruments.provider_symbol carries Yahoo-style futures
     * tickers (e.g. GC=F) that TwelveData doesn't accept - verified live
     * against TwelveData's quote endpoint: bare tickers like "CL"/"BZ"
     * silently resolve to unrelated equities instead of failing, so a wrong
     * guess here would corrupt data rather than error. XAUUSD's own
     * provider_symbol (XAU/USD) already happens to match TwelveData's
     * forex-style spot-commodity format and needs no override.
     */
    private const TWELVE_DATA_SYMBOL_OVERRIDES = [
        'XAU_EUR' => 'XAU/EUR',
        'WTI' => 'WTI/USD',
        'BRENT' => 'XBR/USD',
    ];

    public function handle(TwelveDataService $marketData): int
    {
        $days = max(30, min(3650, (int) $this->option('days')));
        $cutoff = now()->subDays($days)->startOfDay();
        $commodities = DB::connection('serving')->table('serving_instruments')
            ->where('instrument_type', 'commodity')
            ->where('is_active', true)
            ->orderBy('symbol')
            ->get(['symbol', 'provider_symbol', 'name', 'currency']);

        $updated = 0;
        $failed = [];
        foreach ($commodities as $commodity) {
            $symbol = strtoupper(trim((string) $commodity->symbol));
            $providerSymbol = self::TWELVE_DATA_SYMBOL_OVERRIDES[$symbol]
                ?? trim((string) ($commodity->provider_symbol ?: $symbol));
            try {
                $instrumentId = DB::table('instruments')
                    ->where('type', 'commodity')
                    ->whereRaw('UPPER(symbol) = ?', [$symbol])
                    ->value('id');
                if (! $instrumentId) {
                    $instrumentId = DB::table('instruments')->insertGetId([
                        'type' => 'commodity', 'symbol' => $symbol, 'provider_symbol' => $providerSymbol,
                        'name' => (string) ($commodity->name ?: $symbol), 'short_name' => $symbol,
                        'currency' => (string) ($commodity->currency ?: 'USD'),
                        'is_active' => true, 'is_tradeable' => false,
                        'meta' => json_encode(['source' => 'serving', 'history_provider' => 'twelve_data']),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('instruments')->where('id', $instrumentId)->update([
                        'provider_symbol' => $providerSymbol, 'is_active' => true, 'updated_at' => now(),
                    ]);
                }

                $history = collect($marketData->dailyHistory($providerSymbol, min(5000, $days + 50)))
                    ->filter(fn (array $bar): bool => isset($bar['timestamp'])
                        && Carbon::createFromTimestampUTC((int) $bar['timestamp'])->gte($cutoff));
                if ($history->isEmpty()) {
                    throw new \RuntimeException('Der Kursanbieter lieferte keine Tageskurse.');
                }
                $rows = $history->map(fn (array $bar): array => [
                    'instrument_id' => $instrumentId,
                    'interval' => '1d',
                    'bar_time' => Carbon::createFromTimestampUTC((int) $bar['timestamp']),
                    'open' => $bar['open'], 'high' => $bar['high'], 'low' => $bar['low'], 'close' => $bar['close'],
                    'adjusted_close' => $bar['adjusted_close'] ?? $bar['close'], 'volume' => $bar['volume'] ?? null,
                    'source' => 'twelve_data_commodity_history', 'created_at' => now(), 'updated_at' => now(),
                ])->all();
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('price_bars')->upsert($chunk, ['instrument_id', 'interval', 'bar_time'], [
                        'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                    ]);
                }
                $updated++;
                $this->info("{$symbol}: ".count($rows).' Tageskurse');
            } catch (Throwable $error) {
                report($error);
                $failed[] = $symbol;
                $this->error("{$symbol}: {$error->getMessage()}");
            }
        }

        $commodityIds = DB::table('instruments')->where('type', 'commodity')->pluck('id');
        $deleted = $commodityIds->isEmpty() ? 0 : DB::table('price_bars')
            ->whereIn('instrument_id', $commodityIds)
            ->where('bar_time', '<', $cutoff)
            ->delete();
        $this->line("Aktualisiert: {$updated}/{$commodities->count()} · ältere Kurszeilen gelöscht: {$deleted}");

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}

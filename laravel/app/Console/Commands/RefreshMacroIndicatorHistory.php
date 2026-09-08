<?php

namespace App\Console\Commands;

use App\Services\YahooIndexService;
use App\Services\TwelveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefreshMacroIndicatorHistory extends Command
{
    protected $signature = 'markets:refresh-macro-history {--range=3y}';

    protected $description = 'Aktualisiert die täglichen DAX-, VDAX- und AGG-Zeitreihen der Makroindikatoren';

    public function handle(YahooIndexService $marketData, TwelveDataService $twelveData): int
    {
        $range = in_array($this->option('range'), ['1y', '3y', '5y'], true)
            ? (string) $this->option('range')
            : '3y';

        foreach ([
            ['^GDAXI', 'DAX', 'index', 'EUR'],
            ['AGG', 'iShares Core U.S. Aggregate Bond ETF', 'etf', 'USD'],
        ] as [$symbol, $name, $type, $currency]) {
            $instrumentId = $this->instrumentId($symbol, $name, $type, $currency);
            Cache::forget('yahoo_index_daily_history_'.sha1(strtoupper($symbol).$range));
            $history = $marketData->dailyHistory($symbol, $range);
            if ($history === []) {
                throw new RuntimeException("Keine Tageskurse für {$symbol} empfangen.");
            }

            $rows = collect($history)->map(fn (array $bar): array => [
                'instrument_id' => $instrumentId,
                'interval' => '1d',
                'bar_time' => date('Y-m-d H:i:sP', (int) $bar['timestamp']),
                'open' => $bar['open'],
                'high' => $bar['high'],
                'low' => $bar['low'],
                'close' => $bar['close'],
                'adjusted_close' => $bar['adjusted_close'],
                'volume' => $bar['volume'],
                'source' => 'yahoo_daily_rest',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('price_bars')->upsert($chunk->all(), ['instrument_id', 'interval', 'bar_time'], [
                    'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                ]);
            }
            $this->info("{$symbol}: {$rows->count()} Tageskurse aktualisiert.");
        }

        foreach ([
            ['EXS1:XETR', 'iShares Core DAX UCITS ETF (DE)', 'EUR', 'XETR', 'DE'],
            ['SPY', 'SPDR S&P 500 ETF Trust', 'USD', 'ARCX', 'US'],
            ['QQQ', 'Invesco QQQ Trust', 'USD', 'XNAS', 'US'],
        ] as [$proxySymbol, $proxyName, $proxyCurrency, $proxyMic, $proxyCountry]) {
            $proxyId = $this->instrumentId($proxySymbol, $proxyName, 'etf', $proxyCurrency, $proxyMic, $proxyCountry);
            $proxyHistory = $twelveData->dailyHistory($proxySymbol, $range === '1y' ? 280 : ($range === '5y' ? 1320 : 800));
            if ($proxyHistory === []) throw new RuntimeException("Keine Twelve-Data-Tageskurse für {$proxySymbol} empfangen.");
            $proxyRows = collect($proxyHistory)->map(fn (array $bar): array => [
                'instrument_id' => $proxyId, 'interval' => '1d',
                'bar_time' => date('Y-m-d H:i:sP', (int) $bar['timestamp']),
                'open' => $bar['open'], 'high' => $bar['high'], 'low' => $bar['low'], 'close' => $bar['close'],
                'adjusted_close' => $bar['adjusted_close'], 'volume' => $bar['volume'], 'source' => 'twelve_data',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($proxyRows->chunk(500) as $chunk) {
                DB::table('price_bars')->upsert($chunk->all(), ['instrument_id', 'interval', 'bar_time'], [
                    'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                ]);
            }
            $this->info("{$proxySymbol}: {$proxyRows->count()} Twelve-Data-Tageskurse aktualisiert.");
        }

        $vdaxStatus = $this->call('markets:import-vdax-history', ['--years' => 3]);
        Cache::forget('market_reference_index_quotes');

        return $vdaxStatus === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    private function instrumentId(string $symbol, string $name, string $type, string $currency, ?string $mic = null, ?string $country = null): int
    {
        $instrumentId = DB::table('instruments')
            ->where('symbol', $symbol)
            ->whereNull('deleted_at')
            ->value('id');
        if ($instrumentId) return (int) $instrumentId;

        $exchangeId = ($mic ? DB::table('exchanges')->where('mic', $mic)->value('id') : null)
            ?? DB::table('exchanges')->where('mic', 'ARCX')->value('id')
            ?? DB::table('exchanges')->where('code', 'NYSE_ARCA')->value('id');

        return (int) DB::table('instruments')->insertGetId([
            'exchange_id' => $exchangeId,
            'type' => $type,
            'symbol' => $symbol,
            'provider_symbol' => $symbol,
            'name' => $name,
            'short_name' => $symbol,
            'country' => $country ?? ($type === 'index' ? 'DE' : 'US'),
            'currency' => $currency,
            'is_active' => true,
            'is_tradeable' => false,
            'meta' => json_encode(['reference_type' => 'macro_indicator']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

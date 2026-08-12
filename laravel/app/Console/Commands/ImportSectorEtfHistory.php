<?php

namespace App\Console\Commands;

use App\Services\TwelveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportSectorEtfHistory extends Command
{
    protected $signature = 'sectors:import-etf-history {--days=5000}';

    protected $description = 'Importiert die Tageshistorien der elf Sektor-SPDRs direkt von Twelve Data';

    private const ETFS = [
        'Communication Services' => ['XLC', 'Communication Services Select Sector SPDR Fund'],
        'Consumer Cyclical' => ['XLY', 'Consumer Discretionary Select Sector SPDR Fund'],
        'Consumer Defensive' => ['XLP', 'Consumer Staples Select Sector SPDR Fund'],
        'Energy' => ['XLE', 'Energy Select Sector SPDR Fund'],
        'Financial Services' => ['XLF', 'Financial Select Sector SPDR Fund'],
        'Healthcare' => ['XLV', 'Health Care Select Sector SPDR Fund'],
        'Industrials' => ['XLI', 'Industrial Select Sector SPDR Fund'],
        'Basic Materials' => ['XLB', 'Materials Select Sector SPDR Fund'],
        'Real Estate' => ['XLRE', 'Real Estate Select Sector SPDR Fund'],
        'Technology' => ['XLK', 'Technology Select Sector SPDR ETF'],
        'Utilities' => ['XLU', 'Utilities Select Sector SPDR Fund'],
    ];

    public function handle(TwelveDataService $marketData): int
    {
        $days = max(20, min(5000, (int) $this->option('days')));
        $exchangeId = DB::table('exchanges')->where('mic', 'ARCX')->value('id');
        if (! $exchangeId) {
            $exchangeId = DB::table('exchanges')->insertGetId([
                'code' => 'NYSE_ARCA', 'mic' => 'ARCX', 'name' => 'NYSE Arca',
                'country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $failures = [];
        foreach (self::ETFS as $sector => [$symbol, $name]) {
            try {
                $instrumentId = DB::table('instruments')->where('type', 'etf')->where('symbol', $symbol)->whereNull('deleted_at')->value('id');
                if (! $instrumentId) {
                    $instrumentId = DB::table('instruments')->insertGetId([
                        'exchange_id' => $exchangeId, 'type' => 'etf', 'symbol' => $symbol,
                        'provider_symbol' => $symbol, 'name' => $name, 'short_name' => $symbol,
                        'country' => 'US', 'currency' => 'USD', 'sector' => $sector,
                        'is_active' => true, 'is_tradeable' => false,
                        'meta' => json_encode(['provider' => 'twelve_data', 'reference_type' => 'sector_etf']),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('market_sectors')->where('name', $sector)->update([
                    'reference_etf_symbol' => $symbol,
                    'reference_instrument_id' => $instrumentId,
                    'updated_at' => now(),
                ]);

                $history = $marketData->dailyHistory($symbol, $days);
                if ($history === []) {
                    throw new \RuntimeException('Twelve Data lieferte keine Tageskurse.');
                }
                $rows = collect($history)->map(fn (array $bar): array => [
                    'instrument_id' => $instrumentId, 'interval' => '1d',
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
                $this->info("{$sector}: {$symbol} – ".count($rows).' Kurse');
            } catch (Throwable $error) {
                $failures[] = $symbol;
                $this->error("{$sector}: {$symbol} – {$error->getMessage()}");
            }
        }

        $this->line('Abgeschlossen: '.(count(self::ETFS) - count($failures)).'/'.count(self::ETFS));
        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}

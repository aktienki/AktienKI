<?php

namespace App\Console\Commands;

use App\Services\TwelveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class BackfillGermanListingHistory extends Command
{
    protected $signature = 'stocks:backfill-eur-history {--days=800} {--limit=25} {--symbol=*}';

    protected $description = 'Lädt historische Kurse verifizierter deutscher EUR-Listings in das Intervall 1d_eur';

    public function handle(TwelveDataService $marketData): int
    {
        $days = max(20, min(5000, (int) $this->option('days')));
        $query = DB::table('instruments as instrument')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->whereNotNull('instrument.german_listing_symbol')
            ->whereRaw("UPPER(COALESCE(instrument.german_listing_currency, '')) = 'EUR'")
            ->select('instrument.id', 'instrument.symbol', 'instrument.german_listing_symbol')
            ->orderByRaw("(SELECT COUNT(*) FROM price_bars bar WHERE bar.instrument_id=instrument.id AND bar.interval='1d_eur') ASC")
            ->orderBy('instrument.id');

        $symbols = collect($this->option('symbol'))->map(fn ($symbol) => strtoupper(trim((string) $symbol)))->filter()->values();
        if ($symbols->isNotEmpty()) {
            $query->whereIn(DB::raw('UPPER(instrument.symbol)'), $symbols->all());
        } elseif ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $completed = $failed = 0;
        foreach ($query->cursor() as $instrument) {
            try {
                $history = $marketData->dailyHistory((string) $instrument->german_listing_symbol, $days);
                if ($history === []) {
                    throw new \RuntimeException('Keine EUR-Tageskurse geliefert.');
                }
                $rows = collect($history)->map(fn (array $bar): array => [
                    'instrument_id' => $instrument->id,
                    'interval' => '1d_eur',
                    'bar_time' => date('Y-m-d H:i:sP', (int) $bar['timestamp']),
                    'open' => $bar['open'], 'high' => $bar['high'], 'low' => $bar['low'],
                    'close' => $bar['close'], 'adjusted_close' => $bar['adjusted_close'],
                    'volume' => $bar['volume'], 'source' => 'twelve_data_german_listing',
                    'created_at' => now(), 'updated_at' => now(),
                ])->all();
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('price_bars')->upsert($chunk, ['instrument_id', 'interval', 'bar_time'], [
                        'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
                    ]);
                }
                $completed++;
                $this->info("{$instrument->symbol} → {$instrument->german_listing_symbol}: ".count($rows).' EUR-Kurse');
            } catch (Throwable $error) {
                $failed++;
                $this->warn("{$instrument->symbol}: {$error->getMessage()}");
            }
        }

        $this->line("Abgeschlossen: {$completed} erfolgreich, {$failed} fehlgeschlagen.");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

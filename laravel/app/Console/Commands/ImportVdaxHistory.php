<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportVdaxHistory extends Command
{
    protected $signature = 'markets:import-vdax-history {--years=3}';

    protected $description = 'Importiert die echte tägliche VDAX-Historie von Eurex/Onvista';

    public function handle(): int
    {
        $years = max(1, min(35, (int) $this->option('years')));
        $instrument = DB::table('instruments')
            ->where('type', 'index')
            ->where(function ($query): void {
                $query->where('isin', 'DE000A0DMX99')
                    ->orWhere('symbol', 'VDAX');
            })
            ->whereNull('deleted_at')
            ->orderByDesc('is_active')
            ->first();

        if (! $instrument) {
            $this->error('Das VDAX-Instrument wurde nicht gefunden.');
            return self::FAILURE;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (compatible; AktienKI/1.0)',
            'Referer' => 'https://www.onvista.de/index/VDAX-NEW-Index-12105789',
        ])->timeout(30)->retry(2, 1000)->get(
            'https://api.onvista.de/api/v1/instruments/FUND/12105789/eod_history',
            [
                'idNotation' => 12105789,
                'range' => $years > 10 ? 'MAX' : 'Y'.$years,
                'startDate' => now()->subYears($years)->toDateString(),
            ],
        );

        $data = $response->successful() ? $response->json() : [];
        if (($data['entityType'] ?? null) !== 'INDEX'
            || (int) ($data['idNotation'] ?? 0) !== 12105789
            || empty($data['datetimeLast'])) {
            throw new RuntimeException('Die Quelle lieferte keine validierte VDAX-Indexhistorie.');
        }

        $cutoffTimestamp = now()->subYears($years)->startOfDay()->timestamp;
        $timestamps = $data['datetimeLast'];
        $rows = collect($timestamps)->map(function ($timestamp, int $index) use ($data, $instrument): ?array {
            $open = $data['first'][$index] ?? null;
            $high = $data['high'][$index] ?? null;
            $low = $data['low'][$index] ?? null;
            $close = $data['last'][$index] ?? null;
            if (! is_numeric($timestamp) || ! is_numeric($open) || ! is_numeric($high) || ! is_numeric($low) || ! is_numeric($close)) {
                return null;
            }

            return [
                'instrument_id' => $instrument->id,
                'interval' => '1d',
                'bar_time' => Carbon::createFromTimestampUTC((int) $timestamp),
                'open' => (float) $open,
                'high' => (float) $high,
                'low' => (float) $low,
                'close' => (float) $close,
                'adjusted_close' => (float) $close,
                'volume' => is_numeric($data['volume'][$index] ?? null) ? (float) $data['volume'][$index] : null,
                'source' => 'onvista_eurex',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->filter(fn (array $row): bool => $row['bar_time']->timestamp >= $cutoffTimestamp)->values();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('price_bars')->upsert($chunk->all(), ['instrument_id', 'interval', 'bar_time'], [
                'open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at',
            ]);
        }

        DB::table('instruments')->where('id', $instrument->id)->update([
            'isin' => 'DE000A0DMX99',
            'provider_symbol' => 'V1X',
            'currency' => 'EUR',
            'meta' => json_encode([
                ...((array) json_decode((string) ($instrument->meta ?? '{}'), true)),
                'history_provider' => 'onvista_eurex',
                'onvista_id_notation' => 12105789,
            ]),
            'updated_at' => now(),
        ]);

        $this->info("VDAX: {$rows->count()} echte Tageskurse für {$years} Jahre importiert.");
        return self::SUCCESS;
    }
}

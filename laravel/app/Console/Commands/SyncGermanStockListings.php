<?php

namespace App\Console\Commands;

use App\Services\TwelveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SyncGermanStockListings extends Command
{
    protected $signature = 'stocks:sync-german-listings {--force} {--limit=0} {--symbol=*}';

    protected $description = 'Prüft deutsche EUR-Listings und setzt das in Deutschland handelbare Aktienuniversum.';

    public function handle(TwelveDataService $marketData): int
    {
        $query = DB::table('instruments as instrument')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->where('instrument.type', 'stock')->where('instrument.is_active', true)->whereNull('instrument.deleted_at')
            ->select('instrument.id', 'instrument.symbol', 'instrument.provider_symbol', 'instrument.name', 'instrument.isin',
                'instrument.country', 'instrument.currency', 'instrument.german_listing_symbol', 'instrument.german_listing_exchange',
                'instrument.german_listing_mic', 'instrument.german_listing_currency', 'instrument.german_listing_checked_at',
                'exchange.country as exchange_country', 'exchange.currency as exchange_currency', 'exchange.mic as exchange_mic')
            ->orderBy('instrument.id');

        $symbols = collect($this->option('symbol'))->filter()->map(fn ($value) => strtoupper((string) $value))->all();
        if ($symbols !== []) {
            $query->whereIn(DB::raw('UPPER(instrument.symbol)'), $symbols);
        } elseif (! $this->option('force')) {
            $query->whereNull('instrument.german_listing_checked_at');
        }
        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $found = $missing = $failed = 0;
        foreach ($query->cursor() as $instrument) {
            $primaryGerman = strtoupper((string) $instrument->country) === 'DE'
                || strtoupper((string) $instrument->exchange_country) === 'DE'
                || in_array(strtoupper((string) $instrument->exchange_mic), ['XETR', 'XFRA', 'XGAT', 'XMUN', 'XBER', 'XDUS', 'XSTU', 'XHAM', 'XHAN'], true);
            try {
                $listing = $primaryGerman ? [
                    'symbol' => $instrument->provider_symbol ?: $instrument->symbol,
                    'exchange' => null,
                    'mic_code' => $instrument->exchange_mic,
                    'currency' => 'EUR',
                ] : ($instrument->german_listing_symbol ? [
                    'symbol' => $instrument->german_listing_symbol,
                    'exchange' => $instrument->german_listing_exchange,
                    'mic_code' => $instrument->german_listing_mic,
                    'currency' => $instrument->german_listing_currency,
                ] : $marketData->germanListing($instrument->isin, (string) $instrument->name, (string) $instrument->symbol));

                $available = $listing && strtoupper((string) ($listing['currency'] ?? '')) === 'EUR';
                DB::table('instruments')->where('id', $instrument->id)->update([
                    'german_listing_symbol' => $available ? $listing['symbol'] : null,
                    'german_listing_exchange' => $available ? ($listing['exchange'] ?: null) : null,
                    'german_listing_mic' => $available ? ($listing['mic_code'] ?: null) : null,
                    'german_listing_currency' => $available ? 'EUR' : null,
                    'german_listing_verified_at' => $available ? now() : null,
                    'german_listing_checked_at' => now(),
                    'is_german_tradeable' => $available,
                    'is_tradeable' => $available,
                    'updated_at' => now(),
                ]);
                $available ? $found++ : $missing++;
                $this->line($instrument->symbol.': '.($available ? $listing['symbol'].' · EUR' : 'kein deutsches Listing'));
            } catch (Throwable $error) {
                $failed++;
                $this->warn($instrument->symbol.': Prüfung fehlgeschlagen – '.$error->getMessage());
            }
        }

        $this->info("Abgeschlossen: {$found} handelbar, {$missing} nicht gefunden, {$failed} Fehler.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

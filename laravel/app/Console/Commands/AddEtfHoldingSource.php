<?php

namespace App\Console\Commands;

use App\Services\EtfHoldingImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddEtfHoldingSource extends Command
{
    protected $signature = 'etfs:add-source
        {provider : ETF-Anbieter, z. B. iShares}
        {name : Vollständiger ETF-Name}
        {url : Direkte URL der offiziellen Bestandsdatei}
        {--isin= : ETF-ISIN}
        {--symbol= : ETF-Symbol}
        {--exchange= : Deutscher Handelsplatz (Pflicht für sichtbare ETFs)}
        {--mic= : Deutscher MIC, z. B. XETR, XFRA, XGAT oder XMUN}
        {--listing-symbol= : Symbol am deutschen Handelsplatz}
        {--currency=EUR : Fondswährung}
        {--format=csv : csv oder json}
        {--not-german-tradeable : Nicht als in Deutschland handelbar markieren}
        {--sync : Bestand sofort importieren}';
    protected $description = 'Hinterlegt eine offizielle ETF-Anbieterquelle für den automatischen Bestandsimport';

    public function handle(EtfHoldingImportService $importer): int
    {
        $isin = strtoupper(trim((string) $this->option('isin')));
        if ($isin !== '' && ! preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin)) {
            $this->error('Die ETF-ISIN ist ungültig.');
            return self::FAILURE;
        }
        $identity = $isin !== ''
            ? ['isin' => $isin]
            : ['provider' => trim($this->argument('provider')), 'symbol' => strtoupper(trim((string) $this->option('symbol')))];
        if (($identity['symbol'] ?? null) === '') {
            $this->error('Ohne ISIN muss --symbol angegeben werden.');
            return self::FAILURE;
        }
        $mic = strtoupper(trim((string) $this->option('mic')));
        $germanMics = ['XETR', 'XFRA', 'XGAT', 'XMUN', 'XBER', 'XDUS', 'XHAM', 'XHAN', 'XSTU'];
        $isGermanTradeable = ! $this->option('not-german-tradeable') && in_array($mic, $germanMics, true);
        if (! $this->option('not-german-tradeable') && ! $isGermanTradeable) {
            $this->error('Für einen sichtbaren ETF ist ein deutscher --mic erforderlich (z. B. XETR oder XFRA).');
            return self::FAILURE;
        }

        DB::table('etf_funds')->updateOrInsert($identity, [
            'provider' => trim($this->argument('provider')),
            'name' => trim($this->argument('name')),
            'symbol' => strtoupper(trim((string) $this->option('symbol'))) ?: null,
            'exchange' => trim((string) $this->option('exchange')) ?: null,
            'mic_code' => $mic ?: null,
            'german_listing_symbol' => strtoupper(trim((string) $this->option('listing-symbol'))) ?: null,
            'currency' => strtoupper(trim((string) $this->option('currency'))) ?: null,
            'is_german_tradeable' => $isGermanTradeable,
            'german_tradeability_verified_at' => $isGermanTradeable ? now() : null,
            'german_tradeability_source' => $isGermanTradeable ? 'issuer/exchange reference' : null,
            'source_url' => trim($this->argument('url')),
            'source_format' => Str::lower((string) $this->option('format')),
            'is_active' => true, 'updated_at' => now(), 'created_at' => now(),
        ]);
        $fund = DB::table('etf_funds')->where($identity)->first();
        $this->info("ETF-Quelle #{$fund->id} gespeichert.");

        if ($this->option('sync')) {
            $result = $importer->sync($fund);
            $this->info("{$result['matched']}/{$result['imported']} Bestände dem Aktienuniversum zugeordnet.");
        }
        return self::SUCCESS;
    }
}

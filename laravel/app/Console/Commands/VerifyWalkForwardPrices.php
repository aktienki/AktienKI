<?php

namespace App\Console\Commands;

use App\Services\YahooIndexService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The walk-forward training pipeline (an external system - not part of this
 * codebase) writes entry/exit prices for EUR cross-listed instruments into
 * walk_forward_backtest_trades. That feed has been observed to contain
 * badly corrupted points for thinly-traded secondary listings (e.g. a
 * Sony Frankfurt cross-listing priced at 5.49 EUR when its real EUR-
 * equivalent price that day was ~19.69 EUR, turning a real ~-13% trade
 * into a fabricated +211% one). This command can't fix the training
 * pipeline itself, but it can detect and correct the fallout already
 * copied into our own database, using Yahoo Finance as an independent
 * cross-check.
 */
final class VerifyWalkForwardPrices extends Command
{
    protected $signature = 'walk-forward:verify-prices {--fix : Apply corrections instead of only reporting them} {--threshold=25 : Percent deviation from the Yahoo price that flags a trade} {--limit=200 : Maximum number of instruments to check} {--symbol= : Check only this one instrument symbol}';

    protected $description = 'Cross-checks walk_forward_backtest_trades entry/exit prices for EUR cross-listings against Yahoo Finance and flags or corrects large deviations';

    private const YAHOO_SUFFIXES = ['DE', 'F', 'MU', 'SG', 'DU', 'HA', 'BE'];

    public function handle(YahooIndexService $yahoo): int
    {
        $threshold = max(1, (float) $this->option('threshold')) / 100;
        $fix = $this->option('fix');

        $instruments = DB::table('instruments')
            ->where('currency', 'EUR')
            ->whereNotNull('german_listing_symbol')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($this->option('symbol'), fn ($q, $symbol) => $q->where('symbol', $symbol))
            ->select('id', 'symbol', 'german_listing_symbol')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->info("Prüfe {$instruments->count()} EUR-Cross-Listing-Kandidaten (Schwelle: ".($threshold * 100)."%)...");

        $checked = 0;
        $flagged = 0;
        $corrected = 0;

        foreach ($instruments as $instrument) {
            $trades = DB::table('walk_forward_backtest_trades')
                ->where('instrument_id', $instrument->id)
                ->select('id', 'signal_date', 'exit_date', 'entry_price', 'exit_price', 'gross_return', 'net_return')
                ->get();

            if ($trades->isEmpty()) {
                continue;
            }

            $baseSymbol = preg_replace('/:.*/', '', (string) $instrument->german_listing_symbol);
            $yahooCloses = $this->fetchYahooEurCloses($yahoo, $baseSymbol);
            if ($yahooCloses === null) {
                continue;
            }
            $checked++;

            foreach ($trades as $trade) {
                $yahooEntry = $yahooCloses[$this->dateKey($trade->signal_date)] ?? null;
                $yahooExit = $yahooCloses[$this->dateKey($trade->exit_date)] ?? null;

                $entryBad = $yahooEntry !== null && $this->deviates((float) $trade->entry_price, $yahooEntry, $threshold);
                $exitBad = $yahooExit !== null && $this->deviates((float) $trade->exit_price, $yahooExit, $threshold);

                if (! $entryBad && ! $exitBad) {
                    continue;
                }

                $flagged++;
                $this->warn(sprintf(
                    '%s Trade #%d (%s → %s): DB Einstieg=%.2f/Ausstieg=%.2f vs. Yahoo Einstieg=%s/Ausstieg=%s',
                    $instrument->symbol,
                    $trade->id,
                    $trade->signal_date,
                    $trade->exit_date,
                    (float) $trade->entry_price,
                    (float) $trade->exit_price,
                    $yahooEntry !== null ? number_format($yahooEntry, 2) : '?',
                    $yahooExit !== null ? number_format($yahooExit, 2) : '?',
                ));

                if (! $fix) {
                    continue;
                }

                $newEntry = $entryBad ? $yahooEntry : (float) $trade->entry_price;
                $newExit = $exitBad ? $yahooExit : (float) $trade->exit_price;
                $newGross = ($newExit - $newEntry) / $newEntry;
                // Preserve the trade's original absolute cost drag rather
                // than assuming a fixed rate.
                $costDrag = (float) $trade->gross_return - (float) $trade->net_return;
                $newNet = $newGross - $costDrag;

                DB::table('walk_forward_backtest_trades')->where('id', $trade->id)->update([
                    'entry_price' => $newEntry,
                    'exit_price' => $newExit,
                    'gross_return' => $newGross,
                    'net_return' => $newNet,
                ]);

                DB::table('backtest_trades')
                    ->where('instrument_id', $instrument->id)
                    ->where('entry_date', $trade->signal_date)
                    ->where('exit_date', $trade->exit_date)
                    ->update([
                        'entry_price' => $newEntry,
                        'exit_price' => $newExit,
                        'gross_return' => $newGross,
                        'net_return' => $newNet,
                        'transaction_cost' => $costDrag,
                    ]);

                $corrected++;
            }
        }

        $this->info("Geprüft: {$checked} Instrumente mit Yahoo-Abdeckung. Auffällig: {$flagged} Trades.".
            ($fix ? " Korrigiert: {$corrected}." : ' (Dry-Run - mit --fix anwenden)'));

        return self::SUCCESS;
    }

    /** @return array<string, float>|null date (Y-m-d) => close, or null if no Yahoo symbol variant matched */
    private function fetchYahooEurCloses(YahooIndexService $yahoo, string $baseSymbol): ?array
    {
        foreach (self::YAHOO_SUFFIXES as $suffix) {
            $history = $yahoo->dailyHistory("{$baseSymbol}.{$suffix}", '5y', 'EUR');
            if ($history !== []) {
                return collect($history)->mapWithKeys(fn (array $bar): array => [
                    $this->dateKey($bar['timestamp']) => $bar['close'],
                ])->all();
            }
        }

        return null;
    }

    private function dateKey(string|int $value): string
    {
        return is_numeric($value)
            ? Carbon::createFromTimestamp((int) $value)->toDateString()
            : Carbon::parse($value)->toDateString();
    }

    private function deviates(float $dbPrice, float $yahooPrice, float $threshold): bool
    {
        if ($yahooPrice <= 0) {
            return false;
        }

        return abs($dbPrice - $yahooPrice) / $yahooPrice > $threshold;
    }
}

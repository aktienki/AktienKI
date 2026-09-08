<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPanelPredictions extends Command
{
    protected $signature = 'panel:import-predictions {path : CSV produced by panel_dump.py} {--truncate-version : delete existing rows for this model_version first}';

    protected $description = 'Load cross-sectional panel-model predictions into panel_predictions, linked to instruments.';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        // panel symbol (bare XETRA ticker, e.g. IFX) -> instrument_id.
        // The panel model is scored on the XETRA/Frankfurt EUR series, so a
        // foreign-primary instrument (AAPL on NASDAQ) is reached through its
        // german_listing_symbol (APC). A real "<SYM>:XETR" instrument always
        // wins the key over a foreign listing pointing at the same series.
        $primary = [];
        $secondary = [];
        $tertiary = [];
        foreach (DB::table('instruments')
            ->whereNull('deleted_at')
            ->where('type', 'stock')
            ->get(['id', 'symbol', 'provider_symbol', 'german_listing_symbol', 'german_listing_currency']) as $row) {
            $prov = (string) $row->provider_symbol;
            if (str_ends_with($prov, ':XETR')) {
                $primary[substr($prov, 0, -5)] = $row->id;
            }
            $gls = strtoupper(trim((string) $row->german_listing_symbol));
            if ($gls !== '' && strtoupper((string) $row->german_listing_currency) === 'EUR') {
                $secondary[preg_replace('/\.[A-Z]+$/', '', $gls)] ??= $row->id;
            }
            // last-resort: the instrument's own symbol, for names scored from
            // DB price_bars against the S&P 500 (no XETRA series exists).
            $sym = strtoupper(trim((string) $row->symbol));
            if ($sym !== '') {
                $tertiary[$sym] ??= $row->id;
            }
        }
        $map = $primary + $secondary + $tertiary; // real :XETR wins, then german listing, then raw symbol
        $this->info(count($primary).' XETR + '.count(array_diff_key($secondary, $primary)).' german-listing + '
            .count(array_diff_key($tertiary, $primary + $secondary)).' raw-symbol instruments mapped ('.count($map).' total).');

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        $idx = array_flip($header);
        $need = ['model_version', 'as_of_date', 'symbol', 'raw_score', 'xsec_pctile', 'decile',
            'target_demeaned', 'fwd_ret_20d', 'univ_mean_fwd', 'ret_20', 'ret_120',
            'rv_20', 'rv_50', 'beta_60', 'ivol_60', 'dd_120', 'rsi_14'];
        foreach ($need as $c) {
            if (! isset($idx[$c])) {
                $this->error("Missing column: {$c}");
                return self::FAILURE;
            }
        }

        $version = null;
        $rows = [];
        $inserted = 0;
        $unmatched = [];
        $skipped = 0;
        $now = now();

        $num = fn ($v) => ($v === '' || $v === null) ? null : (float) $v;

        // Multiple source rows can resolve to the same instrument+date (a symbol
        // that maps two ways, or duplicate price_bars rows). Key the buffer so a
        // batch never carries a duplicate constrained tuple into ON CONFLICT.
        while (($r = fgetcsv($fh)) !== false) {
            $sym = $r[$idx['symbol']];
            $version ??= $r[$idx['model_version']];
            if (! isset($map[$sym])) {
                $unmatched[$sym] = ($unmatched[$sym] ?? 0) + 1;
                continue;
            }
            // Drop non-physical rows from a broken (e.g. unadjusted / wrong-unit)
            // price series so they cannot pollute the cross-section.
            $rv50 = $num($r[$idx['rv_50']]);
            $beta = $num($r[$idx['beta_60']]);
            if (($rv50 !== null && $rv50 > 1.0) || ($beta !== null && abs($beta) > 8.0)
                || abs((float) $r[$idx['raw_score']]) > 0.5) {
                $skipped++;
                continue;
            }
            $key = $r[$idx['model_version']].'|'.$map[$sym].'|'.substr($r[$idx['as_of_date']], 0, 10);
            $rows[$key] = [
                'model_version'   => $r[$idx['model_version']],
                'instrument_id'   => $map[$sym],
                'as_of_date'      => substr($r[$idx['as_of_date']], 0, 10),
                'raw_score'       => $num($r[$idx['raw_score']]),
                'xsec_pctile'     => $num($r[$idx['xsec_pctile']]),
                'decile'          => $r[$idx['decile']] === '' ? null : (int) $r[$idx['decile']],
                'target_demeaned' => $num($r[$idx['target_demeaned']]),
                'fwd_ret_20d'     => $num($r[$idx['fwd_ret_20d']]),
                'univ_mean_fwd'   => $num($r[$idx['univ_mean_fwd']]),
                'ret_20'          => $num($r[$idx['ret_20']]),
                'ret_120'         => $num($r[$idx['ret_120']]),
                'rv_20'           => $num($r[$idx['rv_20']]),
                'rv_50'           => $num($r[$idx['rv_50']]),
                'beta_60'         => $num($r[$idx['beta_60']]),
                'ivol_60'         => $num($r[$idx['ivol_60']]),
                'dd_120'          => $num($r[$idx['dd_120']]),
                'rsi_14'          => $num($r[$idx['rsi_14']]),
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
            if (count($rows) >= 2000) {
                DB::table('panel_predictions')->upsert(array_values($rows), ['model_version', 'instrument_id', 'as_of_date']);
                $inserted += count($rows);
                $rows = [];
                if ($inserted % 50000 === 0) {
                    $this->line("  {$inserted} rows…");
                }
            }
        }
        if ($rows) {
            DB::table('panel_predictions')->upsert(array_values($rows), ['model_version', 'instrument_id', 'as_of_date']);
            $inserted += count($rows);
        }
        fclose($fh);

        $this->info("Inserted/updated {$inserted} rows for model_version '{$version}'.".($skipped ? " Skipped {$skipped} non-physical rows." : ''));
        if ($unmatched) {
            arsort($unmatched);
            $this->warn(count($unmatched).' symbols had no XETR instrument: '
                .implode(', ', array_slice(array_keys($unmatched), 0, 20)).(count($unmatched) > 20 ? ' …' : ''));
        }
        $total = DB::table('panel_predictions')->where('model_version', $version)->count();
        $stocks = DB::table('panel_predictions')->where('model_version', $version)->distinct('instrument_id')->count('instrument_id');
        $this->info("panel_predictions now holds {$total} rows across {$stocks} instruments for this version.");

        return self::SUCCESS;
    }
}

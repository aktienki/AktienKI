<?php

namespace App\Console\Commands;

use App\Models\Instrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exports panel_predictions' features (rv_20, rv_50, beta_60, ivol_60,
 * dd_120, rsi_14) plus its already-realized target (fwd_ret_20d) as a
 * plain CSV - the actual model training happens on the separate Python
 * pipeline (Mac-mini), not in this Laravel app; this command's job is
 * just to hand that pipeline a clean, ready-to-train dataset instead of
 * it having to reconstruct one from the raw table itself.
 */
class ExportPanelTrainingDataset extends Command
{
    protected $signature = 'panel:export-training-dataset
        {--path= : Output CSV path (default: storage/app/exports/panel-training-dataset-<timestamp>.csv)}
        {--symbol= : Restrict to one instrument symbol}
        {--model-version= : Restrict to one panel model_version}';

    protected $description = 'Export panel_predictions features + fwd_ret_20d target as a CSV for external ML training';

    /** @var list<string> */
    private const COLUMNS = [
        'instrument_id', 'symbol', 'as_of_date', 'model_version',
        'raw_score', 'xsec_pctile', 'decile', 'fwd_ret_20d',
        'ret_20', 'ret_120', 'rv_20', 'rv_50', 'beta_60', 'ivol_60', 'dd_120', 'rsi_14',
    ];

    public function handle(): int
    {
        $instrumentId = null;
        if ($symbol = $this->option('symbol')) {
            $instrument = Instrument::where('symbol', $symbol)->first();
            if (! $instrument) {
                $this->error("Symbol {$symbol} nicht gefunden.");

                return self::FAILURE;
            }
            $instrumentId = $instrument->id;
        }

        $path = $this->option('path') ?: storage_path('app/exports/panel-training-dataset-'.now()->format('Y-m-d_His').'.csv');
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Konnte {$path} nicht zum Schreiben öffnen.");

            return self::FAILURE;
        }

        fputcsv($handle, self::COLUMNS);

        $query = DB::table('panel_predictions as p')
            ->join('instruments as i', 'i.id', '=', 'p.instrument_id')
            ->whereNotNull('p.fwd_ret_20d')
            ->select([
                'p.id', 'p.instrument_id', 'i.symbol', 'p.as_of_date', 'p.model_version',
                'p.raw_score', 'p.xsec_pctile', 'p.decile', 'p.fwd_ret_20d',
                'p.ret_20', 'p.ret_120', 'p.rv_20', 'p.rv_50', 'p.beta_60', 'p.ivol_60', 'p.dd_120', 'p.rsi_14',
            ])
            ->orderBy('p.id');

        if ($instrumentId !== null) {
            $query->where('p.instrument_id', $instrumentId);
        }

        if ($modelVersion = $this->option('model-version')) {
            $query->where('p.model_version', $modelVersion);
        }

        $rowCount = 0;
        // chunkById needs the raw id column ('p.id', qualified because of
        // the join) plus its alias in the *result* row ('id', since that's
        // what it's selected as) to track the last-seen id between chunks -
        // get either wrong and it silently repeats or skips pages.
        $query->chunkById(5000, function ($rows) use ($handle, &$rowCount): void {
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn (string $column) => $row->{$column}, self::COLUMNS));
                $rowCount++;
            }
        }, 'p.id', 'id');

        fclose($handle);

        if ($rowCount === 0) {
            unlink($path);
            $this->warn('Keine passenden panel_predictions-Zeilen gefunden - keine Datei geschrieben.');

            return self::FAILURE;
        }

        $this->info("Export abgeschlossen: {$rowCount} Zeilen -> {$path} (".round(filesize($path) / 1_048_576, 2).' MB)');

        return self::SUCCESS;
    }
}

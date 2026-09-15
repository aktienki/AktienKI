<?php

namespace App\Console\Commands;

use App\Models\Instrument;
use App\Services\PanelScoreDriftStatsService;
use Illuminate\Console\Command;

/**
 * "Does the panel model's own historical score actually predict the
 * subsequent move" - for the whole universe, or, with --symbol, for one
 * specific stock's own history. Uses panel_predictions.fwd_ret_20d, which
 * the training pipeline already computed - no separate backfill needed,
 * unlike the earnings-drift pipeline.
 */
class PanelScoreDriftReport extends Command
{
    protected $signature = 'panel:score-drift-report
        {--symbol= : Restrict to one instrument symbol instead of the whole universe}
        {--model-version= : Restrict to one panel model_version (default: all versions in the table)}';

    protected $description = 'Report average 20-day forward return by panel-score decile, for the whole universe or one stock';

    public function handle(PanelScoreDriftStatsService $stats): int
    {
        $modelVersion = $this->option('model-version');
        $instrumentId = null;
        $label = 'Gesamtes Universum';

        if ($symbol = $this->option('symbol')) {
            $instrument = Instrument::where('symbol', $symbol)->first();
            if (! $instrument) {
                $this->error("Symbol {$symbol} nicht gefunden.");

                return self::FAILURE;
            }
            $instrumentId = $instrument->id;
            $label = ($instrument->name ?: $instrument->symbol).' ('.$instrument->symbol.')';
        }

        $decileRows = $stats->decileBreakdown($instrumentId, $modelVersion);

        if ($decileRows->isEmpty()) {
            $this->warn("Keine panel_predictions-Daten für {$label} gefunden.");

            return self::FAILURE;
        }

        $this->info("Panel-Score-Analyse: {$label}");
        $this->newLine();

        $this->table(
            ['Dezil', 'n', 'Ø 20T-Return', 'Streuung (SD)'],
            $decileRows->map(fn (array $row) => [
                $row['decile'],
                $row['n'],
                $this->formatPercent($row['avgForwardReturn']),
                $row['stdDev'] === null ? '—' : $this->formatPercent($row['stdDev']),
            ])->all(),
        );

        $correlation = $stats->correlation($instrumentId, $modelVersion);
        if ($correlation !== null) {
            $this->newLine();
            $this->info(sprintf(
                'Korrelation Perzentil <-> 20T-Return: r = %s · Rohscore <-> 20T-Return: r = %s (n = %d)',
                $correlation['corrPercentile'] === null ? '—' : number_format($correlation['corrPercentile'], 3, ',', '.'),
                $correlation['corrRawScore'] === null ? '—' : number_format($correlation['corrRawScore'], 3, ',', '.'),
                $correlation['n'],
            ));
        }

        return self::SUCCESS;
    }

    private function formatPercent(float $value): string
    {
        return sprintf('%s%s %%', $value >= 0 ? '+' : '', number_format($value * 100, 2, ',', '.'));
    }
}

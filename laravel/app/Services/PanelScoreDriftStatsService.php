<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Correlates the panel model's own score (xsec_pctile / decile / raw_score)
 * with its already-stored realized forward return (panel_predictions.
 * fwd_ret_20d - no extra price join needed, the training pipeline computed
 * it) - "does the model's own historical score actually predict the
 * subsequent move", for the whole universe or one specific stock.
 */
class PanelScoreDriftStatsService
{
    /**
     * @return Collection<int, array{decile: int, n: int, avgForwardReturn: float, stdDev: ?float}>
     */
    public function decileBreakdown(?int $instrumentId = null, ?string $modelVersion = null): Collection
    {
        return collect(
            $this->baseQuery($instrumentId, $modelVersion)
                ->whereNotNull('decile')
                ->selectRaw('decile, count(*) as n, avg(fwd_ret_20d) as avg_ret, stddev(fwd_ret_20d) as sd')
                ->groupBy('decile')
                ->orderBy('decile')
                ->get(),
        )->map(fn (object $row): array => [
            'decile' => (int) $row->decile,
            'n' => (int) $row->n,
            'avgForwardReturn' => (float) $row->avg_ret,
            'stdDev' => $row->sd !== null ? (float) $row->sd : null,
        ]);
    }

    /**
     * @return array{corrPercentile: ?float, corrRawScore: ?float, n: int}|null
     */
    public function correlation(?int $instrumentId = null, ?string $modelVersion = null): ?array
    {
        $row = $this->baseQuery($instrumentId, $modelVersion)
            ->selectRaw('corr(xsec_pctile, fwd_ret_20d) as corr_pctile, corr(raw_score, fwd_ret_20d) as corr_raw, count(*) as n')
            ->first();

        if ($row === null || (int) $row->n === 0) {
            return null;
        }

        return [
            'corrPercentile' => $row->corr_pctile !== null ? (float) $row->corr_pctile : null,
            'corrRawScore' => $row->corr_raw !== null ? (float) $row->corr_raw : null,
            'n' => (int) $row->n,
        ];
    }

    private function baseQuery(?int $instrumentId, ?string $modelVersion)
    {
        $query = DB::table('panel_predictions')->whereNotNull('fwd_ret_20d');

        if ($instrumentId !== null) {
            $query->where('instrument_id', $instrumentId);
        }

        if ($modelVersion !== null) {
            $query->where('model_version', $modelVersion);
        }

        return $query;
    }
}

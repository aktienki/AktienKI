<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

class IndicatorEntryGateService
{
    /** Inputs come from the verified raw resolver, never from a submitted pass flag. */
    public function assessSource(array $source): array
    {
        $row = DB::connection('serving')->selectOne(
            'SELECT serving_indicator_entry_check(CAST(? AS jsonb)) AS assessment',
            [json_encode($source, JSON_THROW_ON_ERROR)],
        );
        $result = is_array($row->assessment ?? null) ? $row->assessment
            : json_decode((string) ($row->assessment ?? ''), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result) || ! is_bool($result['passed'] ?? null)) {
            throw new LogicException('INDICATOR_EVALUATION_INVALID');
        }

        return $result;
    }

    /** Guard the legacy automation path before any purchase or reservation release. */
    public function assessLegacyPrediction(int $predictionId): array
    {
        $legacy = DB::table('predictions as p')->join('instruments as i', 'i.id', '=', 'p.instrument_id')
            ->where('p.id', $predictionId)
            ->first(['i.symbol', 'p.prediction_horizon_minutes', 'p.metadata', 'p.prediction_time']);
        if ($legacy === null) {
            return ['passed' => false, 'reason_codes' => ['INDICATOR_LEGACY_SOURCE_MISSING']];
        }
        $minutes = (int) $legacy->prediction_horizon_minutes;
        $scope = DB::connection('serving')->table('serving_indicator_entry_filters as f')
            ->join('serving_instruments as i', 'i.id', '=', 'f.instrument_id')
            ->where('i.symbol', $legacy->symbol)->where('f.horizon', intdiv($minutes, 1440))
            ->where('f.variant', 'standard')->where('f.enabled', true)->first(['f.release_id']);
        if ($scope === null) {
            return ['passed' => true, 'applied' => false, 'reason_codes' => []];
        }
        // Legacy model IDs are not serving releases. A matching symbol alone
        // must never borrow another model's indicator approval.
        $meta = is_array($legacy->metadata) ? $legacy->metadata : json_decode((string) $legacy->metadata, true);
        $id = $meta['serving_prediction_id'] ?? null;
        if (! is_int($id) || $id <= 0) {
            return ['passed' => false, 'applied' => true, 'reason_codes' => ['INDICATOR_LEGACY_SOURCE_UNBOUND']];
        }
        $source = DB::connection('serving')->table('serving_predictions as p')
            ->join('serving_instruments as i', 'i.id', '=', 'p.instrument_id')->where('p.id', $id)
            ->where('i.symbol', $legacy->symbol)->where('p.horizon', intdiv($minutes, 1440))
            ->where('p.variant', 'standard')->where('p.release_id', $scope->release_id)
            ->first(['p.*']);
        if ($source === null || strtotime((string) $source->as_of) !== strtotime((string) $legacy->prediction_time)) {
            return ['passed' => false, 'applied' => true, 'reason_codes' => ['INDICATOR_LEGACY_SOURCE_UNBOUND']];
        }
        $source = (array) $source;
        $source['compact_context'] = is_array($source['compact_context']) ? $source['compact_context'] : json_decode($source['compact_context'], true);

        return $this->assessSource($source);
    }
}

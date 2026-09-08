<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Resolves and verifies one exact raw event; aggregate signal views are forbidden. */
final class ServingRawEntryEventResolver
{
    public function __construct(private readonly FinalEntryCanonicalizer $canonicalizer) {}

    /**
     * @param  array<string, mixed>  $claim  Only prediction id is trusted as a lookup key.
     * @return array{source: array<string,mixed>, metrics: array<string,mixed>, mapping: array<string,mixed>}
     */
    public function resolve(
        int $localInstrumentId,
        array $claim,
        DateTimeImmutable $evaluatedAt,
        DateTimeImmutable $cutoverAt,
    ): array {
        $predictionId = filter_var($claim['id'] ?? null, FILTER_VALIDATE_INT);
        if ($predictionId === false || $predictionId <= 0) {
            throw new LogicException('SOURCE_PREDICTION_ID_INVALID');
        }

        $row = DB::connection('serving')
            ->table('serving_predictions as prediction')
            ->join('serving_prediction_batches as batch', function ($join): void {
                $join->on('batch.id', '=', 'prediction.batch_id')
                    ->where('batch.status', '=', 'complete');
            })
            ->join('serving_prediction_scopes as scope', function ($join): void {
                $join->on('scope.instrument_id', '=', 'prediction.instrument_id')
                    ->on('scope.release_id', '=', 'prediction.release_id')
                    ->on('scope.horizon', '=', 'prediction.horizon')
                    ->on('scope.variant', '=', 'prediction.variant')
                    ->where('scope.selected_for_prediction', '=', true)
                    ->where('scope.prediction_enabled', '=', true);
            })
            ->join('serving_instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->join('serving_active_models as active', function ($join): void {
                $join->on('active.instrument_id', '=', 'prediction.instrument_id')
                    ->on('active.release_id', '=', 'prediction.release_id');
            })
            ->join('serving_releases as release', function ($join): void {
                $join->on('release.id', '=', 'prediction.release_id')
                    ->on('release.instrument_id', '=', 'prediction.instrument_id');
            })
            ->where('prediction.id', (int) $predictionId)
            ->first([
                'prediction.id', 'prediction.batch_id', 'prediction.instrument_id',
                'prediction.release_id', 'prediction.as_of', 'prediction.horizon',
                'prediction.variant', 'prediction.signal', 'prediction.expected_return',
                'prediction.target_price', 'prediction.calibrated_score',
                'prediction.risk_score', 'prediction.confidence',
                'prediction.compact_context', 'prediction.created_at',
                'batch.status as batch_status', 'batch.finished_at as batch_completed_at',
                'batch.calculation_date', 'batch.pipeline_version',
                'scope.model_quality_class', 'scope.model_quality_label',
                'scope.quality_gate_passed', 'scope.entry_policy', 'scope.performance',
                'instrument.symbol', 'instrument.provider_symbol', 'instrument.isin',
                'instrument.exchange', 'instrument.country_code', 'instrument.sector_code',
            ]);

        if ($row === null || $row->batch_status !== 'complete') {
            throw new LogicException('SOURCE_EVENT_NOT_ACTIVE_OR_BATCH_NOT_COMPLETE');
        }

        $sourceAsOf = $this->canonicalizer->dateTime((string) $row->as_of);
        $batchCompletedAt = $this->canonicalizer->dateTime((string) $row->batch_completed_at);
        if ($sourceAsOf > $batchCompletedAt
            || $batchCompletedAt < $cutoverAt
            || $sourceAsOf < $cutoverAt
            || $batchCompletedAt > $evaluatedAt
            || $evaluatedAt > new DateTimeImmutable('+5 minutes')) {
            throw new LogicException('SOURCE_EVENT_OUTSIDE_SERVER_CUTOVER_OR_TIME_ORDER');
        }

        $mapping = $this->resolveMapping($localInstrumentId, $row);
        $this->assertClaimMatches($claim, $row);

        $source = [
            'source_system' => 'serving',
            'id' => (int) $row->id,
            'batch_id' => (string) $row->batch_id,
            'instrument_id' => (int) $row->instrument_id,
            'release_id' => (string) $row->release_id,
            'as_of' => $this->canonicalizer->databaseTimestamp($sourceAsOf),
            'batch_completed_at' => $this->canonicalizer->databaseTimestamp($batchCompletedAt),
            'market_date' => $sourceAsOf
                ->setTimezone(new DateTimeZone($mapping['session_timezone']))
                ->format('Y-m-d'),
            'horizon' => (int) $row->horizon,
            'variant' => (string) $row->variant,
            'signal' => strtoupper(trim((string) $row->signal)),
            'expected_return' => $row->expected_return,
            'target_price' => $row->target_price,
            'calibrated_score' => $row->calibrated_score,
            'risk_score' => $row->risk_score,
            'confidence' => $row->confidence,
            'compact_context' => $this->jsonValue($row->compact_context),
            'created_at' => (string) $row->created_at,
            'batch_status' => 'complete',
            'batch_calculation_date' => (string) $row->calculation_date,
            'pipeline_version' => (string) $row->pipeline_version,
            'scope_active' => true,
            'release_active' => true,
            'model_quality_class' => $row->model_quality_class,
            'model_quality_label' => $row->model_quality_label,
            'quality_gate_passed' => (bool) $row->quality_gate_passed,
            'entry_policy' => $this->jsonValue($row->entry_policy),
            'performance' => $this->jsonValue($row->performance),
            'symbol' => (string) $row->symbol,
            'provider_symbol' => (string) $row->provider_symbol,
            'isin' => $row->isin,
            'exchange' => (string) $row->exchange,
            'mapping' => $mapping,
        ];

        return [
            'source' => $source,
            'metrics' => $this->normalizedMetrics($row),
            'mapping' => $mapping,
        ];
    }

    private function resolveMapping(int $localInstrumentId, object $source): array
    {
        $local = DB::table('instruments as instrument')
            ->join('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->where('instrument.id', $localInstrumentId)
            ->first([
                'instrument.id', 'instrument.exchange_id', 'instrument.symbol', 'instrument.provider_symbol',
                'instrument.isin', 'exchange.code as exchange_code',
                'exchange.mic as exchange_mic', 'exchange.timezone as session_timezone',
            ]);
        if ($local === null || trim((string) $local->session_timezone) === '') {
            throw new LogicException('LOCAL_INSTRUMENT_OR_TIMEZONE_MISSING');
        }

        $method = null;
        $isin = $this->normalizeIdentity($source->isin ?? null);
        if ($isin !== '') {
            $mainMatches = DB::table('instruments')
                ->whereRaw("UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?", [$isin])
                ->count();
            $servingMatches = DB::connection('serving')->table('serving_instruments')
                ->whereRaw("UPPER(REGEXP_REPLACE(COALESCE(isin, ''), '[^A-Za-z0-9]', '', 'g')) = ?", [$isin])
                ->count();
            if ($mainMatches === 1 && $servingMatches === 1
                && $this->normalizeIdentity($local->isin ?? null) === $isin) {
                $method = 'unique_isin';
            }
        }

        if ($method === null) {
            $provider = strtoupper(trim((string) $source->provider_symbol));
            $exchange = strtoupper(trim((string) $source->exchange));
            $localExchange = [
                strtoupper(trim((string) $local->exchange_code)),
                strtoupper(trim((string) $local->exchange_mic)),
            ];
            $mainMatches = DB::table('instruments')
                ->whereRaw('UPPER(provider_symbol) = ?', [$provider])
                ->where('exchange_id', (int) $local->exchange_id)
                ->count();
            $servingMatches = DB::connection('serving')->table('serving_instruments')
                ->whereRaw('UPPER(provider_symbol) = ?', [$provider])
                ->whereRaw('UPPER(exchange) = ?', [$exchange])
                ->count();
            if ($provider === '' || $mainMatches !== 1 || $servingMatches !== 1
                || strtoupper(trim((string) $local->provider_symbol)) !== $provider
                || ! in_array($exchange, $localExchange, true)) {
                throw new LogicException('INSTRUMENT_MAPPING_NOT_UNIQUE');
            }
            $method = 'unique_provider_symbol_exchange';
        }

        $mapping = [
            'version' => 'serving-main-instrument-map-v1',
            'method' => $method,
            'local_instrument_id' => $localInstrumentId,
            'serving_instrument_id' => (int) $source->instrument_id,
            'isin' => $isin === '' ? null : $isin,
            'provider_symbol' => strtoupper(trim((string) $source->provider_symbol)),
            'serving_exchange' => strtoupper(trim((string) $source->exchange)),
            'main_exchange_code' => strtoupper(trim((string) $local->exchange_code)),
            'main_exchange_mic' => strtoupper(trim((string) $local->exchange_mic)),
            'session_timezone' => (string) $local->session_timezone,
        ];
        $mapping['sha256'] = $this->canonicalizer->sha256($mapping);

        return $mapping;
    }

    private function assertClaimMatches(array $claim, object $row): void
    {
        $checks = [
            'batch_id' => (string) $row->batch_id,
            'instrument_id' => (string) $row->instrument_id,
            'release_id' => (string) $row->release_id,
            'horizon' => (string) $row->horizon,
            'variant' => (string) $row->variant,
        ];
        foreach ($checks as $key => $actual) {
            if (array_key_exists($key, $claim) && (string) $claim[$key] !== $actual) {
                throw new LogicException('SOURCE_CLAIM_MISMATCH:'.$key);
            }
        }
    }

    private function normalizedMetrics(object $row): array
    {
        $score = is_numeric($row->calibrated_score) ? (float) $row->calibrated_score : null;
        if ($score !== null) {
            $score = $score <= 1 ? $score * 10 : ($score <= 10 ? $score : $score / 10);
        }
        $confidence = is_numeric($row->confidence) ? (float) $row->confidence : null;
        if ($confidence !== null && $confidence <= 1) {
            $confidence *= 100;
        }
        $risk = is_numeric($row->risk_score) ? abs((float) $row->risk_score) : null;
        if ($risk !== null && $risk <= 1) {
            $risk *= 100;
        }

        return [
            'raw_signal' => strtoupper(trim((string) $row->signal)),
            'prediction_score_10' => $score,
            'confidence_percent' => $confidence,
            'risk_percent' => $risk,
            'predicted_return_percent' => is_numeric($row->expected_return)
                ? (float) $row->expected_return * 100
                : null,
            'quality_tier' => strtoupper(trim((string) $row->model_quality_class)),
            'quality_gate_passed' => (bool) $row->quality_gate_passed,
            'country' => strtoupper(trim((string) $row->country_code)),
            'exchange' => strtoupper(trim((string) $row->exchange)),
            'sector' => strtoupper(trim((string) $row->sector_code)),
            'quality_horizons' => [(int) $row->horizon],
        ];
    }

    private function normalizeIdentity(mixed $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    private function jsonValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}

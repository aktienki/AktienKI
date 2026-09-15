<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Whether a serving_model_configurations entry (symbol + horizon_days +
 * variant, as picked on the "Strategietester" / model overview page from the
 * external serving database) can ever be matched by
 * AutomatedPortfolioService::candidates() against the LOCAL, server-scheduled
 * prediction pipeline. The picker and the automation execution engine read
 * from two entirely different systems - this is the missing link between
 * them, so a strategy is no longer saved silently dead.
 */
class LocalModelFeasibilityService
{
    /** Horizons the local horizon-fusion pipeline actually produces (ApplyHorizonFusion). */
    private const LOCAL_HORIZON_DAYS = [5, 10, 15, 20];

    /**
     * @return array{feasible: bool, reason: ?string, local_model_name: ?string}
     */
    public function check(string $symbol, int $horizonDays, string $variant, ?string $modelName = null): array
    {
        if (! in_array($horizonDays, self::LOCAL_HORIZON_DAYS, true)) {
            // Mirrors ApplyHorizonFusion, which only ever produces 5/10/15/20
            // day rows - a 40-day configuration can never match a local
            // prediction, regardless of the symbol.
            return $this->result(false, 'unsupported_horizon');
        }

        $local = DB::table('instruments')
            ->join('predictions', 'predictions.instrument_id', '=', 'instruments.id')
            ->leftJoin('trained_models', 'trained_models.id', '=', 'predictions.trained_model_id')
            ->leftJoin('model_definitions', 'model_definitions.id', '=', 'trained_models.model_definition_id')
            ->whereRaw('UPPER(instruments.symbol) = ?', [strtoupper(trim($symbol))])
            ->where('predictions.prediction_horizon_minutes', $horizonDays * 1440)
            ->orderByDesc('predictions.id')
            ->first(['model_definitions.name', 'model_definitions.public_alias']);

        if ($local === null) {
            // No local trained model has ever produced a prediction for this
            // exact symbol/horizon - common for smaller-cap stocks the
            // external serving system covers but the local training pipeline
            // never trained.
            return $this->result(false, 'no_local_model');
        }

        $localName = (string) ($local->name ?? '');
        $localAlias = (string) ($local->public_alias ?? '');

        if ($variant === 'pure_tcn') {
            // Same rule AutomatedPortfolioService::candidates() applies: a
            // pure_tcn configuration only ever matches a local model whose
            // name/alias literally contains "tcn".
            $isTcn = str_contains(mb_strtolower($localAlias), 'tcn') || str_contains(mb_strtolower($localName), 'tcn');

            return $this->result($isTcn, $isTcn ? null : 'no_local_tcn_variant', $localName ?: $localAlias);
        }

        $needle = mb_strtolower(trim((string) $modelName));
        if ($needle === '') {
            return $this->result(true, null, $localName ?: $localAlias);
        }

        $matches = mb_strtolower($localAlias) === $needle
            || mb_strtolower($localName) === $needle
            || str_contains(mb_strtolower($localAlias), $needle)
            || str_contains(mb_strtolower($localName), $needle);

        return $this->result($matches, $matches ? null : 'local_model_changed', $localName ?: $localAlias);
    }

    /**
     * Human-readable German explanation for the given reason code, filled in
     * with the concrete symbol/horizon/model names.
     */
    public function explain(string $reason, string $symbol, int $horizonDays, ?string $configuredModelName, ?string $localModelName): string
    {
        return match ($reason) {
            'unsupported_horizon' => __('Die lokale, automatisch ausgeführte Prognose-Pipeline kennt nur die Horizonte 5/10/15/20 Tage - :horizon Tage werden dort nie berechnet. Diese Konfiguration könnte nie automatisch kaufen.', ['horizon' => $horizonDays]),
            'no_local_model' => __('Für :symbol existiert beim :horizon-Tage-Horizont lokal aktuell kein trainiertes Modell. Diese Konfiguration könnte nie automatisch kaufen.', ['symbol' => $symbol, 'horizon' => $horizonDays]),
            'no_local_tcn_variant' => __('Für :symbol/:horizonT gibt es lokal aktuell kein aktives Pure-TCN-Modell (aktuell aktiv: :model). Diese Konfiguration könnte nie automatisch kaufen.', ['symbol' => $symbol, 'horizon' => $horizonDays, 'model' => $localModelName ?: '—']),
            'local_model_changed' => __('Das lokal aktive Modell für :symbol/:horizonT heißt aktuell „:local", nicht „:configured". Diese Konfiguration würde die Automatisierung daher nie zuordnen.', ['symbol' => $symbol, 'horizon' => $horizonDays, 'local' => $localModelName ?: '—', 'configured' => $configuredModelName ?: '—']),
            default => __('Diese Konfiguration konnte lokal nicht bestätigt werden.'),
        };
    }

    /**
     * @return array{feasible: bool, reason: ?string, local_model_name: ?string}
     */
    private function result(bool $feasible, ?string $reason, ?string $localModelName = null): array
    {
        return [
            'feasible' => $feasible,
            'reason' => $reason,
            'local_model_name' => $localModelName,
        ];
    }
}

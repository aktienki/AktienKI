<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class FinalizeStockTraining extends Command
{
    protected $signature = 'training:finalize-stock
        {symbol : Symbol der vollständig trainierten Aktie}
        {--feature-version=triple_daily_macro_v1}';

    protected $description = 'Schließt Training, Kalibrierung, Postfilter, Freigabe und Score-/Risikoberechnung verbindlich ab.';

    public function handle(): int
    {
        $symbol = strtoupper(trim((string) $this->argument('symbol')));
        $instrument = DB::table('instruments')->whereRaw('UPPER(symbol) = ?', [$symbol])
            ->where('type', 'stock')->whereNull('deleted_at')->first(['id', 'symbol', 'meta']);
        if (! $instrument) {
            $this->error("Aktie nicht gefunden: {$symbol}");
            return self::FAILURE;
        }

        $this->stage('Vollständigkeit prüfen');
        if ($this->call('training:verify-complete', [
            'symbols' => [$symbol], '--feature-version' => (string) $this->option('feature-version'),
        ]) !== self::SUCCESS) {
            $this->documentIncomplete($instrument, 'pipeline_incomplete');
            return self::FAILURE;
        }

        $this->stage('MacBook-ARIMA-/Zeitreihenabgleich prüfen');
        if (! $this->hasCurrentArimaValidation($instrument)) {
            $this->documentIncomplete($instrument, 'arima_validation_missing_or_stale');
            $this->error("{$symbol}: verpflichtender ARIMA-Abgleich des MacBook-Finalizers fehlt oder ist älter als die aktiven Modelle.");
            return self::FAILURE;
        }

        $index = DB::table('index_memberships as membership')
            ->join('market_indices as market_index', 'market_index.id', '=', 'membership.market_index_id')
            ->where('membership.instrument_id', $instrument->id)->whereNull('membership.removed_at')
            ->where('market_index.is_active', true)->orderByRaw('market_index.global_rank NULLS LAST')
            ->orderBy('market_index.id')->first(['market_index.symbol', 'market_index.name']);
        if (! $index) $this->warn("{$symbol}: Heimatindex fehlt; Indexfilter wird dokumentiert übersprungen.");

        // A previous rollout restriction must never survive a new complete
        // stock pipeline. Activation is nevertheless decided only below by
        // the individual OOS calibration and its non-degrading postfilters.
        $this->clearLegacyRolloutRestriction($instrument);

        $this->stage($index
            ? "Individuelle 20T-Kalibrierung gegen {$index->symbol}"
            : 'Individuelle 20T-Kalibrierung ohne optionalen Heimatindex');
        $arguments = [
            '--horizon' => 20,
            '--instrument' => (int) $instrument->id,
            '--recalibrate' => true,
        ];
        if ($index) $arguments['index'] = (string) $index->symbol;
        if ($this->call('thresholds:calibrate-index', $arguments) !== self::SUCCESS) {
            $this->documentIncomplete($instrument, 'calibration_failed');
            return self::FAILURE;
        }

        $this->stage('Index-, Sektor-, 60T- und Noise-Filter ohne Verschlechterung prüfen');
        $contextArguments = ['instrument' => $symbol];
        if ($index) $contextArguments['--index'] = (string) $index->symbol;
        if ($this->call('thresholds:evaluate-context-filters', $contextArguments) !== self::SUCCESS) {
            $this->documentIncomplete($instrument, 'post_filter_evaluation_failed');
            return self::FAILURE;
        }

        $threshold = DB::table('stock_individual_thresholds')->where('instrument_id', $instrument->id)
            ->where('horizon_days', 20)->orderByDesc('updated_at')->orderByDesc('id')->first();
        if (! $threshold) {
            $this->documentIncomplete($instrument, 'threshold_missing_after_calibration');
            return self::FAILURE;
        }

        $this->stage('Finalen KI-Score und Risikoklasse speichern');
        $this->call('scores:recalculate', ['--instrument' => (int) $instrument->id]);
        $this->call('stocks:classify-risk', ['--instrument' => (int) $instrument->id]);

        $released = (bool) $threshold->validation_passed;
        $this->line(json_encode([
            'symbol' => $symbol,
            'home_index' => $index?->symbol,
            'status' => $threshold->status,
            'quality_class' => str_replace(['_active', '_documented'], '', (string) $threshold->status),
            'released' => $released,
            'minimum_ai_score' => $threshold->minimum_ai_score,
        ], JSON_UNESCAPED_SLASHES));

        $this->info($released
            ? "{$symbol}: Pipeline vollständig und Aktie freigegeben."
            : "{$symbol}: Pipeline vollständig; Qualitätsentscheidung dokumentiert, Aktie nicht freigegeben.");
        return self::SUCCESS;
    }

    private function stage(string $label): void
    {
        $this->newLine();
        $this->info("→ {$label}");
    }

    private function clearLegacyRolloutRestriction(object $instrument): void
    {
        $meta = is_string($instrument->meta) ? (json_decode($instrument->meta, true) ?: []) : (array) $instrument->meta;
        if (($meta['deactivated_reason'] ?? null) !== 'dax_only_rollout') return;
        unset($meta['deactivated_reason'], $meta['deactivated_at']);
        DB::table('instruments')->where('id', $instrument->id)->update([
            'meta' => json_encode($meta, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    }

    private function hasCurrentArimaValidation(object $instrument): bool
    {
        $meta = is_string($instrument->meta) ? (json_decode($instrument->meta, true) ?: []) : (array) $instrument->meta;
        $validation = (array) ($meta['arima_validation'] ?? []);
        if (! str_starts_with((string) ($validation['status'] ?? ''), 'completed')
            || ($validation['version'] ?? null) !== 'arima-timeseries-oos-v1'
            || (int) ($validation['horizon_days'] ?? 0) !== 20
            || strtoupper((string) ($validation['symbol'] ?? '')) !== strtoupper((string) $instrument->symbol)
            || blank($validation['selected_variant'] ?? null)
            || blank($validation['completed_at'] ?? null)) {
            return false;
        }

        $latestModel = DB::table('trained_models')
            ->where('instrument_id', $instrument->id)->whereNull('deleted_at')->where('status', 'active')
            ->max('created_at');
        if (! $latestModel) return false;

        try {
            return CarbonImmutable::parse((string) $validation['completed_at'])
                ->greaterThanOrEqualTo(CarbonImmutable::parse((string) $latestModel));
        } catch (\Throwable) {
            return false;
        }
    }

    private function documentIncomplete(object $instrument, string $reason): void
    {
        $meta = is_string($instrument->meta) ? (json_decode($instrument->meta, true) ?: []) : (array) $instrument->meta;
        $meta['training_pipeline_status'] = 'incomplete';
        $meta['training_pipeline_reason'] = $reason;
        $meta['training_pipeline_checked_at'] = now()->toIso8601String();
        DB::table('instruments')->where('id', $instrument->id)->update([
            'is_active' => false, 'meta' => json_encode($meta, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    }
}

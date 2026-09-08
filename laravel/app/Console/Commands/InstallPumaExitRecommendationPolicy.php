<?php

namespace App\Console\Commands;

use App\Services\PumaExitRecommendationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class InstallPumaExitRecommendationPolicy extends Command
{
    protected $signature = 'puma-exit:install
        {--dry-run : Identität und SQL-Datei prüfen, ohne etwas zu schreiben}
        {--json : Ergebnis als JSON ausgeben}';

    protected $description = 'Installiert und aktiviert die feste PUMA Exit-Empfehlung in der Serving-Datenbank.';

    public function handle(PumaExitRecommendationService $recommendations): int
    {
        try {
            PumaExitRecommendationService::assertFrozenPolicyFingerprint();
            $sqlPath = database_path('serving/028_puma_exit_recommendation_policy.sql');
            if (! is_file($sqlPath)) {
                throw new RuntimeException("Serving SQL file is missing: {$sqlPath}");
            }

            $sql = file_get_contents($sqlPath);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException("Serving SQL file is empty: {$sqlPath}");
            }

            $connection = DB::connection('serving');
            $instrument = $connection->table('serving_instruments')
                ->where('id', PumaExitRecommendationService::INSTRUMENT_ID)
                ->first(['id', 'isin', 'provider_symbol']);
            PumaExitRecommendationService::assertCanonicalInstrument($instrument);

            $beforeVersion = $connection->table('serving_schema_versions')->max('version');
            $result = [
                'ok' => true,
                'dry_run' => (bool) $this->option('dry-run'),
                'database' => $connection->getDatabaseName(),
                'instrument' => [
                    'id' => PumaExitRecommendationService::INSTRUMENT_ID,
                    'isin' => PumaExitRecommendationService::ISIN,
                    'provider_symbol' => PumaExitRecommendationService::PROVIDER_SYMBOL,
                ],
                'policy_name' => PumaExitRecommendationService::POLICY_NAME,
                'policy_version' => PumaExitRecommendationService::POLICY_VERSION,
                'core_policy_fingerprint' => PumaExitRecommendationService::CORE_POLICY_FINGERPRINT,
                'bundle_manifest_sha256' => PumaExitRecommendationService::BUNDLE_MANIFEST_SHA256,
                'bundle_policy_json_sha256' => PumaExitRecommendationService::BUNDLE_POLICY_JSON_SHA256,
                'sql_sha256' => hash('sha256', $sql),
                'schema_version_before' => $beforeVersion === null ? null : (int) $beforeVersion,
            ];

            if (! $this->option('dry-run')) {
                try {
                    $connection->unprepared($sql);
                } catch (Throwable $exception) {
                    // The SQL file owns its transaction so it remains safe when
                    // executed by psql as well. Clear a possibly aborted PDO
                    // session before returning the installation error.
                    try {
                        $connection->unprepared('ROLLBACK');
                    } catch (Throwable) {
                        // Preserve the original installation error.
                    }
                    throw $exception;
                }
                $current = $recommendations->current();
                $result['schema_version_after'] = (int) $connection
                    ->table('serving_schema_versions')->max('version');
                $result['policy_status'] = $current['policy_status'];
                $result['decision'] = $current['decision'];
                $result['automatic_execution'] = (bool) $current['automatic_execution'];
                $result['release_gate_passed'] = (bool) $current['release_gate_passed'];
                $result['user_override'] = (bool) $current['user_override'];
                $result['comparison_baseline'] = $current['comparison_baseline'];
                $result['maximum_holding_sessions'] = $current['maximum_holding_sessions'];
            }
        } catch (Throwable $exception) {
            $result = [
                'ok' => false,
                'error' => $exception->getMessage(),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($result['ok']) {
            if ($result['dry_run']) {
                $this->info('PUMA identity and serving SQL passed the dry-run checks; no data was changed.');
            } else {
                $this->info(
                    "PUMA policy {$result['policy_name']} is active as a recommendation; "
                    .'automatic execution remains disabled.'
                );
            }
        } else {
            $this->error((string) $result['error']);
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}

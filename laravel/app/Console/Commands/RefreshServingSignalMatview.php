<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Safety net for serving_current_stock_signals_materialized.
 *
 * The view is normally refreshed by triggers on serving_prediction_batches
 * (status = 'complete') and serving_active_models. This command guarantees the
 * dashboard/screener snapshot is current even when a publish path bypasses a
 * trigger condition (bulk COPY, no status transition). Scheduled after the
 * daily finalize + serving publish.
 */
final class RefreshServingSignalMatview extends Command
{
    protected $signature = 'serving:refresh-signal-matview {--no-concurrent : Blockierendes REFRESH statt CONCURRENTLY}';

    protected $description = 'Frischt serving_current_stock_signals_materialized auf der serving-Datenbank auf.';

    private const MATVIEW = 'serving_current_stock_signals_materialized';

    public function handle(): int
    {
        try {
            $connection = DB::connection('serving');
        } catch (Throwable $e) {
            $this->error('serving-Verbindung nicht verfügbar: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $exists = $connection->selectOne('SELECT to_regclass(?) AS oid', [self::MATVIEW]);
            if (($exists->oid ?? null) === null) {
                $this->warn(self::MATVIEW.' existiert nicht – übersprungen.');

                return self::SUCCESS;
            }

            $concurrent = ! $this->option('no-concurrent');
            $started = microtime(true);
            try {
                // SECURITY DEFINER wrapper (serving migration 031): the matview
                // is owned by `postgres`, the app role may only refresh it here.
                $connection->statement('SELECT serving_refresh_current_signals(?)', [$concurrent]);
            } catch (Throwable $e) {
                if (! $concurrent) {
                    throw $e;
                }
                // CONCURRENTLY fails on a never-populated view – fall back once.
                $this->warn('CONCURRENTLY fehlgeschlagen ('.$e->getMessage().'), blockierender Refresh …');
                $connection->statement('SELECT serving_refresh_current_signals(false)');
            }

            $rows = (int) ($connection->selectOne('SELECT count(*) AS c FROM '.self::MATVIEW)->c ?? 0);
            $this->info(sprintf(
                '%s aktualisiert: %d Zeilen in %.2fs.',
                self::MATVIEW, $rows, microtime(true) - $started
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Refresh fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

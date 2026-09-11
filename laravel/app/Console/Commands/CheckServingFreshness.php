<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Guards against a silent serving freeze: the dashboard and screener read the
 * serving snapshot, which only advances when a prediction batch reaches
 * status = 'complete'. This alerts when the newest complete batch (or the
 * materialised signal view) is older than the allowed age.
 */
final class CheckServingFreshness extends Command
{
    protected $signature = 'serving:check-freshness
        {--max-hours=30 : Höchstalter des jüngsten complete-Batches in Stunden}';

    protected $description = 'Prüft, ob die Serving-Predictions/Matview aktuell sind, und meldet einen Freeze.';

    public function handle(): int
    {
        try {
            $serving = DB::connection('serving');
        } catch (Throwable $e) {
            return $this->alertFreeze('serving-Verbindung nicht verfügbar: '.$e->getMessage());
        }

        $maxHours = max(1, (int) $this->option('max-hours'));

        try {
            $lastComplete = $serving->table('serving_prediction_batches')
                ->where('status', 'complete')
                ->max('finished_at');
            $lastAny = $serving->table('serving_prediction_batches')->max('finished_at');
            $lastBatchStatus = $serving->table('serving_prediction_batches')
                ->orderByDesc('finished_at')
                ->value('status');
            $matviewRows = (int) $serving->table('serving_current_stock_signals_materialized')->count();
        } catch (Throwable $e) {
            return $this->alertFreeze('Serving-Status nicht lesbar: '.$e->getMessage());
        }

        if ($lastComplete === null) {
            return $this->alertFreeze('Kein einziger complete-Batch in serving_prediction_batches.');
        }

        $ageHours = Carbon::parse($lastComplete)->diffInHours(now());
        $line = sprintf(
            'Jüngster complete-Batch: %s (%dh alt) · letzter Batch-Status: %s · Matview-Zeilen: %d',
            $lastComplete, $ageHours, $lastBatchStatus ?? '—', $matviewRows
        );

        if ($ageHours > $maxHours) {
            return $this->alertFreeze(sprintf(
                'SERVING FREEZE: %s · Grenze %dh. Letzter Batch insgesamt: %s (Status %s).',
                $line, $maxHours, $lastAny ?? '—', $lastBatchStatus ?? '—'
            ));
        }

        if ($lastBatchStatus !== null && $lastBatchStatus !== 'complete') {
            $this->warn('Hinweis: '.$line);
            Log::warning('serving:check-freshness – jüngster Batch nicht complete: '.$line);

            return self::SUCCESS;
        }

        $this->info('OK · '.$line);

        return self::SUCCESS;
    }

    private function alertFreeze(string $message): int
    {
        $this->error($message);
        Log::error('serving:check-freshness – '.$message);

        $recipient = trim((string) config('aktienki.training_report_email', ''));
        if ($recipient !== '') {
            try {
                Mail::raw(
                    "Serving-Snapshot (Dashboard/Screener) ist eingefroren oder nicht lesbar.\n\n{$message}\n\n"
                    .'Prüfen: serving_prediction_batches (Status/finished_at), serving_current_stock_signals_materialized.',
                    function ($mail) use ($recipient): void {
                        $mail->to($recipient)->subject('⚠️ AktienKI · Serving-Freeze erkannt');
                    }
                );
            } catch (Throwable $e) {
                Log::error('serving:check-freshness – Alarm-Mail fehlgeschlagen: '.$e->getMessage());
            }
        }

        return self::FAILURE;
    }
}

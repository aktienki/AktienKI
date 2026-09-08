<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ShowFinalEntryShadowStatus extends Command
{
    protected $signature = 'signals:shadow-final-entry-status';

    protected $description = 'Show read-only status of the isolated final-entry shadow pipeline';

    public function handle(): int
    {
        try {
            $required = [
                'entry_signal_runtime_config',
                'entry_signal_session_feed_states',
                'entry_signal_decisions',
                'entry_signal_lifecycles',
                'entry_signal_shadow_watermarks',
                'entry_signal_shadow_processed_batches',
                'entry_signal_shadow_session_events',
            ];
            $present = [];
            foreach ($required as $table) {
                $present[$table] = Schema::hasTable($table);
            }
            if (in_array(false, $present, true)) {
                $this->line(json_encode([
                    'schema_ready' => false,
                    'tables' => $present,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }

            $runtime = DB::table('entry_signal_runtime_config')
                ->where('singleton', true)
                ->first(['evaluator_version', 'cutover_at']);
            $watermark = DB::table('entry_signal_shadow_watermarks')
                ->where('worker_key', 'final-entry-shadow-v1')
                ->first([
                    'worker_key', 'evaluator_version', 'cutover_at',
                    'last_batch_id', 'last_batch_completed_at',
                    'last_calculation_date', 'processed_batches', 'updated_at',
                ]);
            $decisionStatuses = DB::table('entry_signal_decisions')
                ->selectRaw('decision_status, COUNT(*) AS total')
                ->groupBy('decision_status')
                ->orderBy('decision_status')
                ->pluck('total', 'decision_status')
                ->map(static fn (mixed $count): int => (int) $count)
                ->all();
            $lifecycleStatuses = DB::table('entry_signal_lifecycles')
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('total', 'status')
                ->map(static fn (mixed $count): int => (int) $count)
                ->all();

            $this->line(json_encode([
                'schema_ready' => $runtime !== null,
                'scheduler_enabled' => (bool) config(
                    'aktienki.final_entry_shadow.enabled',
                    false,
                ),
                'runtime' => $runtime,
                'watermark' => $watermark,
                'counts' => [
                    'feed_states' => DB::table('entry_signal_session_feed_states')->count(),
                    'decisions' => DB::table('entry_signal_decisions')->count(),
                    'decision_statuses' => $decisionStatuses,
                    'lifecycles' => DB::table('entry_signal_lifecycles')->count(),
                    'lifecycle_statuses' => $lifecycleStatuses,
                    'processed_batches' => DB::table(
                        'entry_signal_shadow_processed_batches',
                    )->count(),
                    'materialized_session_events' => DB::table(
                        'entry_signal_shadow_session_events',
                    )->count(),
                    'current_final_buy_signals' => DB::table(
                        'current_final_entry_signals',
                    )->count(),
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Final-entry status failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

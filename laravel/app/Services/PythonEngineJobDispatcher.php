<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PythonEngineJobDispatcher
{
    public function dispatchFilteredBacktest(int $userId, int $runId, int $sourceRunId, array $filters, array $settings): string
    {
        $publicId = (string) Str::uuid();
        DB::table('python_engine_jobs')->insert([
            'public_id' => $publicId,
            'user_id' => $userId,
            'backtest_run_id' => $runId,
            'type' => 'filtered_backtest',
            'calculation_version' => 'portfolio-v1',
            'status' => 'queued',
            'progress' => 0,
            'payload' => json_encode([
                'source_run_id' => $sourceRunId,
                'target_run_id' => $runId,
                'filters' => $filters,
                'settings' => $settings,
                'rules' => [
                    'whole_shares' => true,
                    'fee_per_transaction' => true,
                    'reinvest_realized_profits' => true,
                    'max_positions_counts_instruments' => true,
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }
}

<?php

namespace App\Services;

use App\Models\SystemRun;
use Throwable;

class SystemRunService
{
    public function start(string $type, array $meta = []): SystemRun
    {
        return SystemRun::query()->create([
            'run_type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'meta' => $meta,
        ]);
    }

    public function finish(SystemRun $run, int $processed = 0, int $errors = 0, ?string $message = null): SystemRun
    {
        $run->update([
            'status' => $errors > 0 ? 'finished_with_errors' : 'finished',
            'finished_at' => now(),
            'processed_count' => $processed,
            'error_count' => $errors,
            'message' => $message,
        ]);

        return $run->fresh();
    }

    public function fail(SystemRun $run, Throwable|string $error): SystemRun
    {
        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_count' => max(1, (int) $run->error_count),
            'message' => $error instanceof Throwable ? $error->getMessage() : $error,
        ]);

        return $run->fresh();
    }
}

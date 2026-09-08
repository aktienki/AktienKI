<?php

namespace App\Console\Commands;

use App\Services\FinalEntryShadowWriter;
use Illuminate\Console\Command;
use Throwable;

final class ShadowFinalEntrySignals extends Command
{
    protected $signature = 'signals:shadow-final-entry
        {--batches= : Maximum number of completed serving batches}
        {--force : Run manually even when the scheduler flag is disabled}';

    protected $description = 'Evaluate final entry signals into isolated shadow tables';

    public function handle(FinalEntryShadowWriter $writer): int
    {
        if (! config('aktienki.final_entry_shadow.enabled', false)
            && ! $this->option('force')) {
            $this->components->info('Final-entry shadow writer is disabled.');

            return self::SUCCESS;
        }

        $configuredLimit = max(
            1,
            min(50, (int) config('aktienki.final_entry_shadow.batch_limit', 1)),
        );
        $option = $this->option('batches');
        if ($option !== null
            && (filter_var($option, FILTER_VALIDATE_INT) === false
                || (int) $option < 1 || (int) $option > 50)) {
            $this->components->error('--batches must be an integer between 1 and 50.');

            return self::INVALID;
        }
        $limit = $option === null ? $configuredLimit : (int) $option;

        try {
            $summary = $writer->run($limit);
            $this->line(json_encode(
                $summary,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('Final-entry shadow run failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

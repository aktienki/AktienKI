<?php

namespace App\Console\Commands;

use App\Services\PumExitMarketSeriesExportService;
use Illuminate\Console\Command;
use JsonException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Throwable;

final class ExportPumExitMarketSeries extends Command
{
    protected $signature = 'pum:export-exit-market-series
        {--days=800 : Number of valid daily observations required per series}
        {--pretty : Pretty-print the JSON envelope}';

    protected $description = 'Exportiert die vier kanonischen PUMA-Exit-Marktreihen als read-only JSON';

    public function handle(PumExitMarketSeriesExportService $exporter): int
    {
        try {
            $rawDays = $this->option('days');
            if (! is_scalar($rawDays) || preg_match('/^[1-9][0-9]*$/D', (string) $rawDays) !== 1) {
                throw new \InvalidArgumentException('days must be a positive integer');
            }

            $payload = $exporter->export((int) $rawDays);
            $flags = JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION;
            if ($this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }
            $this->line(json_encode($payload, $flags));

            return self::SUCCESS;
        } catch (JsonException $exception) {
            return $this->failure('PUMA market-series export failed: JSON encoding error');
        } catch (Throwable $exception) {
            return $this->failure('PUMA market-series export failed: '.$exception->getMessage());
        }
    }

    private function failure(string $message): int
    {
        $output = $this->getOutput();
        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln("<error>{$message}</error>");
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}

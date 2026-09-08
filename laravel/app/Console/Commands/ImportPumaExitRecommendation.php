<?php

namespace App\Console\Commands;

use App\Services\PumaExitPayloadDecoder;
use App\Services\PumaExitRecommendationService;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Throwable;

final class ImportPumaExitRecommendation extends Command
{
    protected $signature = 'puma-exit:import
        {file? : One JSON object or chronological NDJSON; omit only with --stdin}
        {--stdin : Read JSON or chronological NDJSON from standard input}';

    protected $description = 'Importiert PUMA Exit-Beobachtungen atomar und idempotent aus JSON oder NDJSON.';

    public function handle(PumaExitRecommendationService $recommendations): int
    {
        try {
            $file = $this->argument('file');
            $stdin = (bool) $this->option('stdin');
            if ($stdin === ($file !== null)) {
                throw new RuntimeException('Provide exactly one input source: file or --stdin.');
            }

            if ($stdin) {
                $raw = $this->readStandardInput();
            } else {
                $path = (string) $file;
                if (! is_file($path)) {
                    throw new RuntimeException("PUMA exit input file is missing: {$path}");
                }
                $raw = file_get_contents($path);
                if ($raw === false) {
                    throw new RuntimeException('PUMA exit input file could not be read.');
                }
            }

            $payloads = PumaExitPayloadDecoder::decode($raw);
            $result = $recommendations->importMany($payloads);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function readStandardInput(): string
    {
        $stream = $this->input instanceof StreamableInputInterface
            ? $this->input->getStream()
            : null;
        $raw = is_resource($stream)
            ? stream_get_contents($stream)
            : file_get_contents('php://stdin');

        if ($raw === false) {
            throw new RuntimeException('PUMA exit standard input could not be read.');
        }

        return $raw;
    }
}

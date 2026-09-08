<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class FredDailySeriesService
{
    private const CSV_URL = 'https://fred.stlouisfed.org/graph/fredgraph.csv';

    private const ALLOWED_SYMBOLS = ['DGS2', 'VIXCLS'];

    public function csv(string $symbol, string $startDate, string $endDate): string
    {
        if (! in_array($symbol, self::ALLOWED_SYMBOLS, true)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $startDate) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $endDate) !== 1) {
            throw new InvalidArgumentException('Invalid fixed FRED series request.');
        }

        $process = new Process([
            '/usr/bin/curl',
            '--fail',
            '--silent',
            '--show-error',
            '--location',
            '--http1.1',
            '--retry', '3',
            '--retry-delay', '1',
            '--retry-all-errors',
            '--connect-timeout', '10',
            '--max-time', '45',
            '--get',
            '--data-urlencode', "id={$symbol}",
            '--data-urlencode', "cosd={$startDate}",
            '--data-urlencode', "coed={$endDate}",
            self::CSV_URL,
        ]);
        $process->setTimeout(150);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                '%s provider request failed: %s',
                $symbol,
                trim($process->getErrorOutput()) ?: 'curl exited unsuccessfully',
            ));
        }
        $body = $process->getOutput();
        if (trim($body) === '') {
            throw new RuntimeException("{$symbol} provider returned an empty CSV");
        }

        return $body;
    }
}

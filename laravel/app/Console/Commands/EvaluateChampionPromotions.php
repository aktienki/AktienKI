<?php

namespace App\Console\Commands;

use App\Services\Champion\ChampionSchedulerService;
use Illuminate\Console\Command;

class EvaluateChampionPromotions extends Command
{
    protected $signature = 'aktienki:evaluate-champions
        {--limit= : Maximale Anzahl zu prüfender Vergleiche}
        {--dry-run : Nur prüfen, keine Promotion ausführen}';

    protected $description =
        'Prüft empfohlene Challenger und führt zulässige Champion-Promotionen aus.';

    public function handle(
        ChampionSchedulerService $service,
    ): int {
        $limit = $this->option('limit');

        if (
            $limit !== null
            && (! ctype_digit((string) $limit) || (int) $limit < 1)
        ) {
            $this->error('--limit muss eine positive Ganzzahl sein.');

            return self::FAILURE;
        }

        $result = $service->run(
            limit: $limit === null ? null : (int) $limit,
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->table(
            ['Gesamt', 'Promoted', 'Abgelehnt', 'Übersprungen', 'Fehler'],
            [[
                $result['total'],
                $result['promoted'],
                $result['rejected'],
                $result['skipped'],
                $result['failed'],
            ]],
        );

        foreach ($result['items'] as $item) {
            $this->line(json_encode(
                $item,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ));
        }

        return $result['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}

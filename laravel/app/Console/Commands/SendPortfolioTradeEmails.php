<?php

namespace App\Console\Commands;

use App\Services\PortfolioTradeEmailService;
use Illuminate\Console\Command;

final class SendPortfolioTradeEmails extends Command
{
    protected $signature = 'portfolios:send-trade-emails {--limit=100}';
    protected $description = 'Sendet E-Mails für neue automatische Musterdepot-Transaktionen';

    public function handle(PortfolioTradeEmailService $service): int
    {
        $stats = $service->sendPending((int) $this->option('limit'));
        $this->info(sprintf(
            'Geprüft: %d, gesendet: %d, fehlgeschlagen: %d, deaktiviert: %d',
            $stats['checked'], $stats['sent'], $stats['failed'], $stats['disabled'],
        ));
        return self::SUCCESS;
    }
}

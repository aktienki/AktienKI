<?php

namespace App\Console\Commands;

use App\Services\SignalEmailService;
use Illuminate\Console\Command;

class SendSignalEmails extends Command
{
    protected $signature = 'signals:send-emails {--since=1440 : Look-back window in minutes}';
    protected $description = 'Queue emails for new signal changes matching saved user strategies';

    public function handle(SignalEmailService $service): int
    {
        $stats = $service->scan(max(1, (int) $this->option('since')));
        $this->info(sprintf(
            'Checked %d predictions; %d changes; %d emails queued; %d unchanged skipped.',
            $stats['checked'], $stats['changes'], $stats['queued'], $stats['skipped']
        ));

        return self::SUCCESS;
    }
}

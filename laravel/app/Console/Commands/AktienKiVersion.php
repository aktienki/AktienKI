<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AktienKiVersion extends Command
{
    protected $signature = 'aktienki:version';

    protected $description = 'Zeigt Version, Build und Umgebung von AktienKI an.';

    public function handle(): int
    {
        $this->components->info('AktienKI');
        $this->table(
            ['Eigenschaft', 'Wert'],
            [
                ['Version', (string) config('aktienki.version')],
                ['Build', (string) config('aktienki.build')],
                ['Kanal', (string) config('aktienki.release_channel')],
                ['Laravel', app()->version()],
                ['PHP', PHP_VERSION],
                ['Umgebung', app()->environment()],
            ],
        );

        return self::SUCCESS;
    }
}

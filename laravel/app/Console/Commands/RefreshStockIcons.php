<?php

namespace App\Console\Commands;

use App\Models\Instrument;
use App\Services\StockIconService;
use Illuminate\Console\Command;

class RefreshStockIcons extends Command
{
    protected $signature = 'stocks:refresh-icons
        {--force : Bereits vorhandene Symbole erneut laden}
        {--limit=0 : Maximale Anzahl zu prüfender Aktien}';

    protected $description = 'Aktualisiert den lokalen Symbol-Cache für aktive Aktien';

    public function handle(StockIconService $icons): int
    {
        $query = Instrument::query()
            ->where('is_active', true)
            ->where('type', 'stock')
            ->orderBy('id');

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $instruments = $query->get();
        $updated = 0;
        $cached = 0;
        $missing = 0;
        $bar = $this->output->createProgressBar($instruments->count());
        $bar->start();

        foreach ($instruments as $instrument) {
            $existing = $icons->findCached($instrument);

            if ($existing && ! $this->option('force')) {
                $cached++;
            } else {
                $path = $this->option('force')
                    ? $icons->refresh($instrument)
                    : $icons->findOrDownload($instrument);

                $path ? $updated++ : $missing++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Aktualisiert: {$updated} · bereits vorhanden: {$cached} · ohne Symbol: {$missing}");

        return self::SUCCESS;
    }
}

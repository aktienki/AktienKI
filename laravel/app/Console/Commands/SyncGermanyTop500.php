<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncGermanyTop500 extends Command
{
    protected $signature = 'indices:sync-germany-top500 {--limit=500 : Maximale Zahl deutscher Aktien}';

    protected $description = 'Synchronisiert das Deutschland-Top-500-Universum aus aktiven deutschen Aktien nach Marktkapitalisierung';

    public function handle(): int
    {
        $limit = min(500, max(1, (int) $this->option('limit')));
        $now = now();

        $stocks = DB::table('instruments')
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('country', 'DE')
            ->orderByRaw('market_cap DESC NULLS LAST')
            ->orderBy('symbol')
            ->limit($limit)
            ->get(['id', 'market_cap']);

        if ($stocks->isEmpty()) {
            $this->error('Keine aktiven deutschen Aktien gefunden.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($stocks, $now): void {
            DB::table('market_indices')->updateOrInsert(
                ['symbol' => 'DE-TOP500'],
                [
                    'name' => 'Deutschland Top 500',
                    'country' => 'DE',
                    'currency' => 'EUR',
                    'region' => 'Europa',
                    'global_rank' => 13,
                    'description' => 'Die bis zu 500 größten im AktienKI-Datenbestand verfügbaren deutschen Aktien, geordnet nach Marktkapitalisierung.',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $indexId = (int) DB::table('market_indices')->where('symbol', 'DE-TOP500')->value('id');
            $selectedIds = $stocks->pluck('id')->map(fn ($id) => (int) $id)->all();
            $totalMarketCap = max(1.0, (float) $stocks->sum('market_cap'));
            $existingAddedDates = DB::table('index_memberships')
                ->where('market_index_id', $indexId)
                ->pluck('added_at', 'instrument_id');

            DB::table('index_memberships')
                ->where('market_index_id', $indexId)
                ->whereNull('removed_at')
                ->whereNotIn('instrument_id', $selectedIds)
                ->update(['removed_at' => $now->toDateString(), 'updated_at' => $now]);

            foreach ($stocks as $stock) {
                DB::table('index_memberships')->updateOrInsert(
                    ['market_index_id' => $indexId, 'instrument_id' => $stock->id],
                    [
                        'weight' => is_numeric($stock->market_cap) && (float) $stock->market_cap > 0
                            ? round(((float) $stock->market_cap / $totalMarketCap) * 100, 6)
                            : null,
                        'added_at' => $existingAddedDates->get($stock->id) ?: $now->toDateString(),
                        'removed_at' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        });

        Cache::forget('index_screener_charts_1y_v2');
        $this->info($stocks->count().' deutsche Aktien wurden dem Deutschland-Top-500-Universum zugeordnet.');

        if ($stocks->count() < 500) {
            $this->warn('Der aktuelle Datenbestand enthält nur '.$stocks->count().' deutsche Aktien; das Universum wächst automatisch bis maximal 500.');
        }

        return self::SUCCESS;
    }
}

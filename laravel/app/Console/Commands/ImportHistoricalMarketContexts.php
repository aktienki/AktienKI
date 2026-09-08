<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ImportHistoricalMarketContexts extends Command
{
    protected $signature = 'predictions:import-historical-contexts {report : Sector walk-forward JSON report} {--max-index-members=25}';
    protected $description = 'Persistiert Point-in-Time-Sektorwerte und daraus abgeleitete Indexwerte für reproduzierbare Backtests.';

    public function handle(): int
    {
        $path = (string) $this->argument('report');
        if (! is_file($path)) {
            $this->error("Report nicht gefunden: {$path}");
            return self::FAILURE;
        }
        $report = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $version = 'sector-pit-'.substr(hash_file('sha256', $path), 0, 12);
        $byDate = [];
        foreach ((array) ($report['walk_forward'] ?? []) as $fold) {
            foreach ((array) data_get($fold, 'metrics._point_in_time', []) as $point) {
                $sector = trim((string) ($point['sector'] ?? ''));
                $date = (string) ($point['date'] ?? '');
                if ($sector === '' || $date === '' || ! is_numeric($point['probability_60d'] ?? null)) continue;
                $probability = max(0.0, min(1.0, (float) $point['probability_60d']));
                $byDate[$date][$sector] = $probability;
            }
        }
        $now = now();
        $sectorRows = [];
        foreach ($byDate as $date => $sectors) foreach ($sectors as $sector => $probability) {
            $sectorRows[] = [
                'prediction_date' => $date, 'scope_type' => 'sector60', 'scope_key' => $sector,
                'score' => $probability * 10, 'confidence' => abs($probability - .5) * 200,
                'signal' => $probability >= .55 ? 'BUY' : ($probability <= .45 ? 'SELL' : 'HOLD'),
                'member_count' => count((array) data_get($report, "sectors.{$sector}.members", [])),
                'meta' => json_encode(['source' => 'sector_gru_walk_forward_point_in_time', 'context_only' => true, 'horizon_days' => 60, 'probability_up' => $probability, 'version' => $version], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($sectorRows, 500) as $chunk) DB::table('market_context_predictions')->upsert(
            $chunk, ['prediction_date', 'scope_type', 'scope_key'], ['score', 'confidence', 'signal', 'member_count', 'meta', 'updated_at']
        );

        $max = max(1, (int) $this->option('max-index-members'));
        $indices = DB::table('market_indices')->where('is_active', true)->get(['id']);
        $indexSectorWeights = [];
        foreach ($indices as $index) {
            $members = DB::table('index_memberships as membership')->join('instruments as instrument', 'instrument.id', '=', 'membership.instrument_id')
                ->where('membership.market_index_id', $index->id)->whereNull('membership.removed_at')->whereNotNull('instrument.sector')
                ->where('instrument.is_active', true)->orderByDesc('instrument.market_cap')->limit($max)->pluck('instrument.sector');
            $indexSectorWeights[(string) $index->id] = $members->countBy()->all();
        }
        $indexRows = [];
        foreach ($byDate as $date => $sectors) foreach ($indexSectorWeights as $indexId => $weights) {
            $sum = 0.0; $covered = 0;
            foreach ($weights as $sector => $weight) if (isset($sectors[$sector])) { $sum += $sectors[$sector] * $weight; $covered += $weight; }
            if ($covered === 0) continue;
            $probability = $sum / $covered;
            $indexRows[] = [
                'prediction_date' => $date, 'scope_type' => 'index60', 'scope_key' => $indexId,
                'score' => $probability * 10, 'confidence' => abs($probability - .5) * 200,
                'signal' => $probability >= .55 ? 'BUY' : ($probability <= .45 ? 'SELL' : 'HOLD'), 'member_count' => $covered,
                'meta' => json_encode(['source' => 'sector_gru_walk_forward_member_weighted', 'context_only' => true, 'horizon_days' => 60, 'probability_up' => $probability, 'maximum_members' => $max, 'version' => $version], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($indexRows, 500) as $chunk) DB::table('market_context_predictions')->upsert(
            $chunk, ['prediction_date', 'scope_type', 'scope_key'], ['score', 'confidence', 'signal', 'member_count', 'meta', 'updated_at']
        );
        $this->info(sprintf('%d Sektor- und %d Index-Snapshots gespeichert (%d Handelstage).', count($sectorRows), count($indexRows), count($byDate)));
        return self::SUCCESS;
    }
}

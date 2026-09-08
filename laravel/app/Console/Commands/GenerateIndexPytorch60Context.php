<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GenerateIndexPytorch60Context extends Command
{
    protected $signature = 'predictions:index-pytorch60-context {--dry-run} {--minimum-members=5} {--max-members=25} {--show-skipped}';
    protected $description = 'Aggregiert den PyTorch-60T-Sektorkontext über die abgedeckten Mitglieder je Index.';

    public function handle(): int
    {
        $latestSectorDates = DB::table('market_context_predictions')
            ->where('scope_type', 'sector60')
            ->selectRaw('scope_key, MAX(prediction_date) AS prediction_date')
            ->groupBy('scope_key');
        $contexts = DB::table('market_context_predictions as context')
            ->joinSub($latestSectorDates, 'latest_sector', function ($join): void {
                $join->on('latest_sector.scope_key', '=', 'context.scope_key')
                    ->on('latest_sector.prediction_date', '=', 'context.prediction_date');
            })
            ->where('context.scope_type', 'sector60')
            ->get(['context.*'])->keyBy('scope_key');
        $snapshotDate = $contexts->max('prediction_date');
        if (! $snapshotDate || $contexts->isEmpty()) {
            $this->error('Kein sector60-Snapshot vorhanden.');

            return self::FAILURE;
        }

        $sectorValues = $contexts->map(function (object $context): array {
            $meta = json_decode((string) ($context->meta ?? '{}'), true) ?: [];

            return [
                'probability' => max(0.0, min(1.0, ((float) $context->score) / 10.0)),
                'quality_gate' => (array) ($meta['quality_gate'] ?? []),
            ];
        });

        $indices = DB::table('market_indices')->where('is_active', true)->orderBy('global_rank')->get(['id', 'symbol', 'name']);
        $written = 0;
        foreach ($indices as $index) {
            $members = DB::table('index_memberships as membership')
                ->join('instruments as instrument', 'instrument.id', '=', 'membership.instrument_id')
                ->where('membership.market_index_id', $index->id)->whereNull('membership.removed_at')
                // Market context is infrastructure, not presentation. Hidden or
                // not-yet-published stocks must still contribute their already
                // computed sector context to the home index.
                ->whereNull('instrument.deleted_at')
                ->orderByDesc('instrument.market_cap')->orderBy('instrument.symbol')
                ->get(['instrument.id', 'instrument.sector']);
            $maxMembers = max(1, (int) $this->option('max-members'));
            $coveredMembers = $members->filter(
                fn (object $member): bool => $sectorValues->has((string) $member->sector)
            )->take($maxMembers)->values();
            $probabilities = [];
            foreach ($coveredMembers as $member) {
                $context = $sectorValues->get((string) $member->sector);
                if ($context) {
                    $probabilities[] = (float) $context['probability'];
                }
            }
            $minimumMembers = max(1, (int) $this->option('minimum-members'));
            if (count($probabilities) < $minimumMembers) {
                if ($this->option('show-skipped')) {
                    $this->warn(sprintf('%s: skipped coverage=%d/%d', $index->symbol, count($probabilities), $members->count()));
                }
                continue;
            }
            $probability = array_sum($probabilities) / count($probabilities);
            $targetMembers = min($members->count(), $maxMembers);
            $coverage = $targetMembers === 0 ? 0.0 : count($probabilities) / $targetMembers;
            $directionConfidence = abs($probability - 0.5) * 200.0;
            $confidence = $directionConfidence * min(1.0, $coverage / 0.5);
            $signal = $probability >= 0.55 ? 'BUY' : ($probability <= 0.45 ? 'SELL' : 'HOLD');
            $meta = [
                'source' => 'pytorch_sector_gru_60t_member_weighted',
                'context_only' => true,
                'horizon_days' => 60,
                'probability_up' => $probability,
                'coverage_ratio' => $coverage,
                'covered_members' => count($probabilities),
                'total_members' => $members->count(),
                'maximum_members' => $maxMembers,
                'selection' => 'market_cap_desc_then_symbol',
                'sector_snapshot_date' => (string) $snapshotDate,
            ];
            $this->line(sprintf(
                '%s: p_up=%.4f coverage=%d/%d (%.1f%%), index_members=%d',
                $index->symbol, $probability, count($probabilities), $targetMembers,
                $coverage * 100, $members->count()
            ));
            if (! $this->option('dry-run')) {
                DB::table('market_context_predictions')->upsert([[
                    'prediction_date' => $snapshotDate,
                    'scope_type' => 'index60',
                    'scope_key' => (string) $index->id,
                    'score' => $probability * 10.0,
                    'confidence' => $confidence,
                    'signal' => $signal,
                    'member_count' => count($probabilities),
                    'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['prediction_date', 'scope_type', 'scope_key'], [
                    'score', 'confidence', 'signal', 'member_count', 'meta', 'updated_at',
                ]);
            }
            $written++;
        }
        $this->info("{$written} Index-60T-Kontexte ".($this->option('dry-run') ? 'geprüft.' : 'gespeichert.'));

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SignalDistributionDeltaService
{
    public function changes(): array
    {
        $user = auth()->user();
        $riskService = app(StockRiskClassificationService::class);
        $level = $user ? $riskService->userLevel($user) : 'balanced';
        $profileScope = $user ? 'user-'.$user->getKey() : 'guest';

        return Cache::remember("markets.signal-distribution-delta.v2.{$profileScope}.{$level}", now()->addMinutes(2), function () use ($user, $riskService): array {
            $dailyLatest = DB::table('predictions as prediction')
                ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
                ->where('instrument.type', 'stock')
                ->where('instrument.is_active', true)
                ->whereNull('instrument.deleted_at');

            // Exakt dieselbe Profilfreigabe wie für das aktive Portfolio nutzen.
            $riskService->applyVisibility($dailyLatest, $user, 'instrument.risk_status');

            $dailyLatest = $dailyLatest
                ->selectRaw('DISTINCT ON (prediction.instrument_id, DATE(prediction.prediction_time)) prediction.instrument_id, DATE(prediction.prediction_time) AS prediction_day, prediction.ai_score, prediction.prediction_score, prediction.id')
                ->orderBy('prediction.instrument_id')
                ->orderByRaw('DATE(prediction.prediction_time) DESC')
                ->orderByDesc('prediction.id');

            $ranked = DB::query()->fromSub($dailyLatest, 'daily_prediction')
                ->select('daily_prediction.*')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY instrument_id ORDER BY prediction_day DESC, id DESC) AS snapshot_rank');

            $rows = DB::query()->fromSub($ranked, 'ranked_prediction')
                ->whereIn('snapshot_rank', [1, 2])
                ->get();

            $counts = fn (int $rank): array => $this->distribution(
                $rows->where('snapshot_rank', $rank)->map(fn (object $row) => \App\Support\AiScore::toTen(
                    is_numeric($row->ai_score) ? $row->ai_score : $row->prediction_score
                ))->filter(fn ($score) => is_numeric($score))
            );
            $current = $counts(1);
            $previous = $counts(2);

            return collect(['SELL', 'WAIT', 'HOLD', 'WATCH', 'BUY'])
                ->mapWithKeys(fn (string $signal): array => [$signal => $current[$signal] - $previous[$signal]])
                ->all();
        });
    }

    private function distribution($scores): array
    {
        return [
            'SELL' => $scores->filter(fn (float $score): bool => $score >= 0 && $score < 2)->count(),
            'WAIT' => $scores->filter(fn (float $score): bool => $score >= 2 && $score < 4)->count(),
            'HOLD' => $scores->filter(fn (float $score): bool => $score >= 4 && $score < 6)->count(),
            'WATCH' => $scores->filter(fn (float $score): bool => $score >= 6 && $score < 8)->count(),
            'BUY' => $scores->filter(fn (float $score): bool => $score >= 8 && $score <= 10)->count(),
        ];
    }
}

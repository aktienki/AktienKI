<?php

namespace App\Services;

use App\Models\MarketSnapshot;
use Illuminate\Support\Collection;

class MarketDashboardService
{
    public function latest(): ?array
    {
        $snapshot = MarketSnapshot::query()
            ->with([
                'assets' => fn ($query) => $query->orderBy('category')->orderBy('name'),
                'sectors' => fn ($query) => $query->orderBy('rank'),
                'statistics',
            ])
            ->latest('snapshot_time')
            ->latest('id')
            ->first();

        if (! $snapshot) {
            return null;
        }

        return [
            'id' => $snapshot->id,
            'snapshot_time' => $snapshot->snapshot_time?->toIso8601String(),
            'market_score' => $snapshot->market_score,
            'risk_mode' => $snapshot->risk_mode,
            'market_trend' => $snapshot->market_trend,
            'volatility' => $snapshot->volatility,
            'breadth_score' => $snapshot->breadth_score,
            'signal_counts' => [
                'buy' => $snapshot->buy_signals,
                'sell' => $snapshot->sell_signals,
                'hold' => $snapshot->hold_signals,
            ],
            'winning_sectors' => $snapshot->winning_sectors ?? [],
            'losing_sectors' => $snapshot->losing_sectors ?? [],
            'assets' => $snapshot->assets->map(fn ($asset) => [
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'category' => $asset->category,
                'price' => $asset->price,
                'change_percent' => $asset->change_percent,
                'volume' => $asset->volume,
                'signal' => $asset->signal,
                'trend' => $asset->trend,
                'score' => $asset->score,
                'observed_at' => $asset->observed_at?->toIso8601String(),
            ])->values()->all(),
            'sectors' => $snapshot->sectors->map(fn ($sector) => [
                'sector' => $sector->sector,
                'rank' => $sector->rank,
                'trend' => $sector->trend,
                'average_return' => $sector->average_return,
                'average_score' => $sector->average_score,
                'buy_ratio' => $sector->buy_ratio,
                'sell_ratio' => $sector->sell_ratio,
                'companies_count' => $sector->companies_count,
            ])->values()->all(),
            'statistics' => $snapshot->statistics ? [
                'companies_total' => $snapshot->statistics->companies_total,
                'buy_count' => $snapshot->statistics->buy_count,
                'sell_count' => $snapshot->statistics->sell_count,
                'hold_count' => $snapshot->statistics->hold_count,
                'average_score' => $snapshot->statistics->average_score,
                'average_confidence' => $snapshot->statistics->average_confidence,
                'average_prediction' => $snapshot->statistics->average_prediction,
                'average_hitrate' => $snapshot->statistics->average_hitrate,
            ] : null,
        ];
    }

    public function history(int $limit = 30): Collection
    {
        $safeLimit = max(1, min($limit, 365));

        return MarketSnapshot::query()
            ->latest('snapshot_time')
            ->latest('id')
            ->limit($safeLimit)
            ->get([
                'id', 'snapshot_time', 'market_score', 'risk_mode', 'market_trend',
                'volatility', 'breadth_score', 'buy_signals', 'sell_signals', 'hold_signals',
            ])
            ->map(fn (MarketSnapshot $snapshot) => [
                'id' => $snapshot->id,
                'snapshot_time' => $snapshot->snapshot_time?->toIso8601String(),
                'market_score' => $snapshot->market_score,
                'risk_mode' => $snapshot->risk_mode,
                'market_trend' => $snapshot->market_trend,
                'volatility' => $snapshot->volatility,
                'breadth_score' => $snapshot->breadth_score,
                'buy_signals' => $snapshot->buy_signals,
                'sell_signals' => $snapshot->sell_signals,
                'hold_signals' => $snapshot->hold_signals,
            ]);
    }
}

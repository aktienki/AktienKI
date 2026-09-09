<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ServingMarketSnapshotService
{
    /** @return array<string, mixed> */
    public function snapshot(?array $instrumentIds = null): array
    {
        $scope = $instrumentIds === null ? 'global' : sha1(implode(',', collect($instrumentIds)->sort()->values()->all()));

        return $this->cache()->remember('serving.market-snapshot.v1.'.$scope.'.'.app()->getLocale(), now()->addMinutes(2), function () use ($instrumentIds, $scope): array {
            try {
                $rows = DB::connection('serving')
                    ->table(ServingCurrentSignalSource::relation().' as signal')
                    ->join('serving_instruments as instrument', 'instrument.id', '=', 'signal.instrument_id')
                    ->where('instrument.instrument_type', 'stock')
                    ->where('instrument.is_active', true)
                    ->where('instrument.is_tradeable', true)
                    ->when($instrumentIds !== null, fn ($query) => $query->whereIn('signal.instrument_id', $instrumentIds))
                    ->get([
                        'signal.*', 'instrument.name', 'instrument.country_code',
                        'instrument.sector_code', 'instrument.home_index_symbol',
                    ]);
            } catch (Throwable) {
                return $this->emptySnapshot();
            }

            if ($rows->isEmpty()) {
                return $this->emptySnapshot();
            }

            $batchIds = $rows->pluck('batch_id')->filter()->unique()->values()->all();
            $predictions = DB::connection('serving')->table('serving_predictions')
                ->whereIn('batch_id', $batchIds)
                ->whereIn('instrument_id', $rows->pluck('instrument_id'))
                ->get()
                ->groupBy('instrument_id');

            $enriched = $rows->map(function (object $row) use ($predictions): object {
                $items = collect($predictions->get((int) $row->instrument_id, collect()))
                    ->filter(fn (object $prediction): bool => (string) $prediction->batch_id === (string) $row->batch_id);
                $forecast = $this->selectForecast($items);
                $rating = (string) (($row->buy_rating ?? null) ?: ($row->underlying_buy_rating ?? null) ?: '3');

                $row->normalized_signal = $this->normalizeSignal((string) $row->signal);
                $row->rating_percent = $this->ratingPercent($rating);
                $row->expected_return_percent = is_numeric($forecast?->expected_return)
                    ? (float) $forecast->expected_return * 100
                    : null;
                $row->forecast_horizon = is_numeric($forecast?->horizon) ? (int) $forecast->horizon : null;
                $row->forecast_confidence = is_numeric($forecast?->confidence) ? (float) $forecast->confidence * 100 : null;

                return $row;
            });

            $count = $enriched->count();
            $buyCount = $enriched->where('normalized_signal', 'BUY')->count();
            $watchCount = $enriched->where('normalized_signal', 'WATCH')->count();
            $holdCount = $enriched->where('normalized_signal', 'HOLD')->count();
            $sellCount = $enriched->where('normalized_signal', 'SELL')->count();
            $score = round((float) ($enriched->avg('rating_percent') ?? 50) / 10, 1);
            $averageReturn = round((float) ($enriched->pluck('expected_return_percent')->filter(fn ($value) => is_numeric($value))->avg() ?? 0), 2);
            $averageRisk = round((float) ($enriched->pluck('risk_score')->filter(fn ($value) => is_numeric($value))->avg() ?? 0), 2);
            $breadthPercent = $count > 0 ? ($buyCount / $count) * 100 : 0.0;
            [$status, $tone] = match (true) {
                $breadthPercent >= 60 && $score >= 6.5 => [__('Positiv'), 'positive'],
                $breadthPercent < 30 || $score < 4.0 => [__('Vorsichtig'), 'cautious'],
                default => [__('Neutral'), 'neutral'],
            };
            $qualityCount = $enriched->filter(fn (object $row): bool => (bool) $row->has_quality_gate_buy)->count();
            $calculationDate = (string) $enriched->max('calculation_date');

            $dailyScores = $this->dailyScores($scope, $calculationDate, $score);
            $distribution = [
                'SELL' => $sellCount,
                'HOLD' => $holdCount,
                'WAIT' => 0,
                'WATCH' => $watchCount,
                'BUY' => $buyCount,
            ];
            $distributionChanges = $this->distributionChanges($scope, $calculationDate, $distribution);
            $transitionStats = $this->transitionStats($instrumentIds, $distribution, $distributionChanges);

            $riskName = match (true) {
                $averageRisk >= 4.25 => __('hoch'),
                $averageRisk >= 3.25 => __('erhöht'),
                default => __('moderat'),
            };
            $summary = __(':buy von :total Aktien liefern im aktuellen Serving-Lauf ein BUY-Signal. Der mittlere Modellscore liegt bei :score von 10 und die durchschnittliche kalibrierte Prognose bei :return %.', [
                'buy' => $buyCount,
                'total' => $count,
                'score' => number_format($score, 1, ',', '.'),
                'return' => ($averageReturn >= 0 ? '+' : '').number_format($averageReturn, 2, ',', '.'),
            ]);

            return [
                'available' => true,
                'batch_id' => (string) $enriched->first()->batch_id,
                'calculation_date' => $calculationDate,
                'daily_scores' => $dailyScores,
                'assessment' => [
                    'source' => 'serving',
                    'score' => $score,
                    'status' => $status,
                    'tone' => $tone,
                    'summary' => $summary,
                    'averageChange' => $averageReturn,
                    'averageVolatility' => $averageRisk,
                    'positiveMarkets' => $buyCount,
                    'marketCount' => $count,
                    'riskName' => $riskName,
                    'calculationDate' => $calculationDate,
                ],
                'analysis' => $this->analysis(
                    $enriched,
                    $calculationDate,
                    $status,
                    $tone,
                    $score,
                    $averageReturn,
                    $averageRisk,
                    $qualityCount,
                ),
                'transition_stats' => $transitionStats,
            ];
        });
    }

    private function selectForecast(Collection $predictions): ?object
    {
        $preferredHorizon = $predictions->where('horizon', 20)->isNotEmpty()
            ? 20
            : ($predictions->where('horizon', 40)->isNotEmpty() ? 40 : 10);

        return $predictions->where('horizon', $preferredHorizon)
            ->sortByDesc(function (object $prediction): float {
                $context = $this->json($prediction->compact_context ?? null);

                return ((bool) data_get($context, 'quality_gate.passed', false) ? 10_000 : 0)
                    + (strtoupper((string) $prediction->signal) === 'BUY' ? 1_000 : 0)
                    + ((float) ($prediction->confidence ?? 0) * 100)
                    + ((float) ($prediction->expected_return ?? 0) * 10);
            })
            ->first();
    }

    /** @return array<string, mixed> */
    private function analysis(
        Collection $rows,
        string $calculationDate,
        string $status,
        string $tone,
        float $score,
        float $averageReturn,
        float $averageRisk,
        int $qualityCount,
    ): array {
        $count = $rows->count();
        $buyCount = $rows->where('normalized_signal', 'BUY')->count();
        $qualityRate = $count > 0 ? ($qualityCount / $count) * 100 : 0.0;
        $ranked = $rows->sortByDesc(fn (object $row): float => ((float) $row->rating_percent * 100) + (float) ($row->expected_return_percent ?? 0));
        $risks = $rows->sortByDesc(fn (object $row): float => ((float) ($row->risk_score ?? 0) * 100) - (float) $row->rating_percent);
        $sectors = $rows->groupBy(fn (object $row): string => (string) ($row->sector_code ?: __('Unbekannt')))
            ->map(function (Collection $items, string $sector): array {
                return [
                    'sector' => $sector,
                    'count' => $items->count(),
                    'return' => (float) ($items->pluck('expected_return_percent')->filter(fn ($value) => is_numeric($value))->avg() ?? 0),
                ];
            })
            ->sortByDesc('return')
            ->values();
        $bestSector = $sectors->first();
        $weakestSector = $sectors->last();

        return [
            'date' => $calculationDate,
            'model' => 'Serving · aktueller vollständiger Lauf',
            'outlook' => strtoupper($tone === 'positive' ? 'BULLISH' : ($tone === 'cautious' ? 'BEARISH' : 'NEUTRAL')),
            'confidence' => (int) round($qualityRate),
            'riskLevel' => $averageRisk >= 4.25 ? 'HIGH' : ($averageRisk >= 3.25 ? 'MEDIUM' : 'LOW'),
            'headline' => __('Aktuelles Lagebild aus der Service Datenbank'),
            'summary' => __('Der aktuelle vollständige Serving-Lauf umfasst :count Aktien. :buy davon sind als BUY eingestuft; der mittlere KI-Score beträgt :score von 10. Die durchschnittliche kalibrierte Prognose des bevorzugten Horizonts liegt bei :return %.', [
                'count' => $count,
                'buy' => $buyCount,
                'score' => number_format($score, 1, ',', '.'),
                'return' => ($averageReturn >= 0 ? '+' : '').number_format($averageReturn, 2, ',', '.'),
            ]),
            'breadth' => $bestSector && $weakestSector
                ? __('Stärkster Sektor: :best (:bestReturn %). Schwächster Sektor: :weak (:weakReturn %). Grundlage ist ausschließlich der aktuelle Serving-Batch.', [
                    'best' => $bestSector['sector'],
                    'bestReturn' => sprintf('%+.1f', $bestSector['return']),
                    'weak' => $weakestSector['sector'],
                    'weakReturn' => sprintf('%+.1f', $weakestSector['return']),
                ])
                : __('Für den Sektorvergleich liegen noch nicht genügend Serving-Prognosen vor.'),
            'sectors' => $sectors->all(),
            'opportunities' => $ranked->where('normalized_signal', 'BUY')->take(5)->map(fn (object $row): string => $this->stockLine($row))->values()->all(),
            'risks' => $risks->take(5)->map(fn (object $row): string => $this->riskLine($row))->values()->all(),
            'watchlist' => $rows->where('normalized_signal', 'WATCH')->sortByDesc('rating_percent')->take(5)->map(fn (object $row): string => $this->stockLine($row))->values()->all(),
            'metrics' => [
                ['label' => __('Abdeckung'), 'value' => (string) $count, 'detail' => __('Aktien im Serving-Lauf')],
                ['label' => __('BUY-Breite'), 'value' => number_format($count > 0 ? $buyCount / $count * 100 : 0, 0, ',', '.').' %', 'detail' => "{$buyCount} von {$count}"],
                ['label' => __('Ø Prognose'), 'value' => sprintf('%+.1f %%', $averageReturn), 'detail' => __('bevorzugter Horizont')],
                ['label' => __('Quality Gate'), 'value' => number_format($qualityRate, 0, ',', '.').' %', 'detail' => "{$qualityCount} von {$count}"],
            ],
        ];
    }

    private function stockLine(object $row): string
    {
        $return = is_numeric($row->expected_return_percent) ? sprintf('%+.1f %%', $row->expected_return_percent) : '—';

        return sprintf('%s (%s): %s · Rating %s · Prognose %s.', $row->name ?: $row->symbol, $row->symbol, $row->normalized_signal, $row->buy_rating ?: $row->underlying_buy_rating ?: '—', $return);
    }

    private function riskLine(object $row): string
    {
        return sprintf('%s (%s): Risiko %s · %s · Rating %s.', $row->name ?: $row->symbol, $row->symbol, $row->risk_score ?: '—', $row->risk_label ?: '—', $row->buy_rating ?: $row->underlying_buy_rating ?: '—');
    }

    /** @return list<array{x:string,y:float}> */
    private function dailyScores(string $scope, string $date, float $score): array
    {
        $cache = $this->cache();
        $cache->put("serving.market-score.{$scope}.{$date}", $score, now()->addDays(45));

        return collect(range(19, 1))->map(function (int $days) use ($cache, $scope, $date): ?array {
            $day = Carbon::parse($date)->subDays($days)->toDateString();
            $value = $cache->get("serving.market-score.{$scope}.{$day}");

            return is_numeric($value) ? ['x' => $day, 'y' => round((float) $value, 2)] : null;
        })->push(['x' => $date, 'y' => $score])->filter()->values()->all();
    }

    /** @param array<string, int> $distribution @return array<string, int> */
    private function distributionChanges(string $scope, string $date, array $distribution): array
    {
        $cache = $this->cache();
        $previous = null;
        foreach (range(1, 20) as $days) {
            $previousDate = Carbon::parse($date)->subDays($days)->toDateString();
            $candidate = $cache->get("serving.market-distribution.{$scope}.{$previousDate}");
            if (is_array($candidate)) {
                $previous = $candidate;
                break;
            }
        }
        $cache->put("serving.market-distribution.{$scope}.{$date}", $distribution, now()->addDays(45));

        return collect($distribution)->mapWithKeys(fn (int $count, string $signal): array => [
            $signal => $previous === null ? 0 : $count - (int) ($previous[$signal] ?? 0),
        ])->all();
    }

    /** @param array<string, int> $distribution @param array<string, int> $distributionChanges */
    private function transitionStats(?array $instrumentIds, array $distribution, array $distributionChanges): array
    {
        $transitions = DB::connection('serving')->table('serving_signal_transitions')
            ->where('changed_at', '>=', now()->subDays(5))
            ->when($instrumentIds !== null, fn ($query) => $query->whereIn('instrument_id', $instrumentIds))
            ->get();
        $rank = ['SELL' => 0, 'HOLD' => 1, 'WAIT' => 1, 'NEUTRAL' => 1, 'WATCH' => 2, 'BUY' => 3];
        $movements = $transitions->map(function (object $row) use ($rank): array {
            $from = $rank[strtoupper((string) $row->from_signal)] ?? 1;
            $to = $rank[strtoupper((string) $row->to_signal)] ?? 1;

            return ['from' => $from, 'to' => $to, 'direction' => $to <=> $from];
        })->filter(fn (array $movement): bool => $movement['direction'] !== 0);
        $matrix = $movements->countBy(fn (array $movement): string => $movement['from'].'-'.$movement['to'])->all();

        return [
            'source' => 'serving',
            'transition_count' => $movements->count(),
            'positive_count' => $movements->where('direction', 1)->count(),
            'negative_count' => $movements->where('direction', -1)->count(),
            'average' => round((float) ($movements->avg('direction') ?? 0), 2),
            'matrix' => $matrix,
            'max_count' => max([0, ...array_values($matrix)]),
            'distribution' => $distribution,
            'distribution_changes' => $distributionChanges,
            'distribution_total' => array_sum($distribution),
            'distribution_max' => max([0, ...array_values($distribution)]),
        ];
    }

    private function normalizeSignal(string $signal): string
    {
        return match (strtoupper(trim($signal))) {
            'BUY' => 'BUY', 'WATCH' => 'WATCH', 'SELL' => 'SELL', default => 'HOLD',
        };
    }

    private function ratingPercent(string $rating): float
    {
        return match (str_replace('−', '-', trim($rating))) {
            '1++' => 99, '1+' => 95, '1' => 90, '1-' => 85,
            '2+' => 78, '2' => 72, '2-' => 65,
            '3+' => 58, '3' => 52, '3-' => 45,
            '4+' => 38, '4' => 32, '4-' => 25,
            '5+' => 18, '5' => 12, '5-' => 5,
            default => 50,
        };
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('aktienki.serving.read_cache_store', 'file'));
    }

    /** @return array<string, mixed> */
    private function emptySnapshot(): array
    {
        return [
            'available' => false,
            'batch_id' => null,
            'calculation_date' => null,
            'daily_scores' => [],
            'assessment' => [
                'source' => 'serving', 'score' => null, 'status' => __('Keine Serving-Daten'),
                'tone' => 'neutral', 'summary' => __('Der aktuelle Serving-Lauf ist nicht verfügbar.'),
                'averageChange' => 0.0, 'averageVolatility' => 0.0,
                'positiveMarkets' => 0, 'marketCount' => 0, 'riskName' => '—',
            ],
            'analysis' => [
                'date' => null, 'headline' => __('Keine Serving-Daten verfügbar'),
                'summary' => __('Der aktuelle Serving-Lauf ist nicht verfügbar.'),
                'opportunities' => [], 'risks' => [], 'watchlist' => [], 'metrics' => [],
            ],
            'transition_stats' => [
                'source' => 'serving', 'transition_count' => 0, 'positive_count' => 0,
                'negative_count' => 0, 'average' => 0.0, 'matrix' => [], 'max_count' => 0,
                'distribution' => ['SELL' => 0, 'HOLD' => 0, 'WAIT' => 0, 'WATCH' => 0, 'BUY' => 0],
                'distribution_changes' => ['SELL' => 0, 'HOLD' => 0, 'WAIT' => 0, 'WATCH' => 0, 'BUY' => 0],
                'distribution_total' => 0, 'distribution_max' => 0,
            ],
        ];
    }
}

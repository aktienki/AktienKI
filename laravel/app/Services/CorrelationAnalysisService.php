<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class CorrelationAnalysisService
{
    /** @return array{samples:int,pearson:?float,spearman:?float,strength:string,direction:string} */
    public function analyze(Collection $rows, string $xKey, string $yKey): array
    {
        $pairs = $rows->map(fn ($row): array => [
            'x' => data_get($row, $xKey),
            'y' => data_get($row, $yKey),
        ])->filter(fn (array $pair): bool => is_numeric($pair['x']) && is_numeric($pair['y']))
            ->map(fn (array $pair): array => ['x' => (float) $pair['x'], 'y' => (float) $pair['y']])
            ->values();

        $samples = $pairs->count();
        $pearson = $this->pearson($pairs->pluck('x')->all(), $pairs->pluck('y')->all());
        $spearman = $this->pearson(
            $this->ranks($pairs->pluck('x')->all()),
            $this->ranks($pairs->pluck('y')->all()),
        );
        $reference = $spearman ?? $pearson;
        $absolute = abs((float) ($reference ?? 0));
        $strength = match (true) {
            $reference === null => 'nicht berechenbar',
            $absolute < .20 => 'kein klarer',
            $absolute < .40 => 'schwacher',
            $absolute < .60 => 'mittlerer',
            $absolute < .80 => 'starker',
            default => 'sehr starker',
        };
        $direction = match (true) {
            $reference === null || $absolute < .05 => 'neutraler',
            $reference > 0 => 'positiver',
            default => 'negativer',
        };

        return compact('samples', 'pearson', 'spearman', 'strength', 'direction');
    }

    /** @param list<float> $x @param list<float> $y */
    private function pearson(array $x, array $y): ?float
    {
        $count = count($x);
        if ($count < 3 || $count !== count($y)) {
            return null;
        }
        $meanX = array_sum($x) / $count;
        $meanY = array_sum($y) / $count;
        $covariance = $varianceX = $varianceY = 0.0;
        foreach ($x as $index => $value) {
            $deltaX = $value - $meanX;
            $deltaY = $y[$index] - $meanY;
            $covariance += $deltaX * $deltaY;
            $varianceX += $deltaX ** 2;
            $varianceY += $deltaY ** 2;
        }
        if ($varianceX <= 0 || $varianceY <= 0) {
            return null;
        }

        return round(max(-1, min(1, $covariance / sqrt($varianceX * $varianceY))), 4);
    }

    /** @param list<float> $values @return list<float> */
    private function ranks(array $values): array
    {
        $ordered = $values;
        asort($ordered, SORT_NUMERIC);
        $ranks = [];
        $position = 1;
        $keys = array_keys($ordered);
        for ($offset = 0, $count = count($keys); $offset < $count;) {
            $end = $offset;
            while ($end + 1 < $count && abs($ordered[$keys[$end + 1]] - $ordered[$keys[$offset]]) < 1e-12) {
                $end++;
            }
            $averageRank = ($position + ($position + ($end - $offset))) / 2;
            for ($index = $offset; $index <= $end; $index++) {
                $ranks[$keys[$index]] = $averageRank;
            }
            $position += $end - $offset + 1;
            $offset = $end + 1;
        }
        ksort($ranks);

        return array_values($ranks);
    }
}

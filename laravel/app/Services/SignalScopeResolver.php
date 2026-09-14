<?php

namespace App\Services;

final class SignalScopeResolver
{
    /**
     * Picks the horizon+variant that actually produced a displayed signal
     * (from buy_scopes / watch_context / negative_top_scopes on the current
     * signal row), instead of a hardcoded default horizon.
     *
     * Every page that shows a horizon-specific figure (a signal-quality
     * donut, a composite/ranking score, a historical trade chart, ...) next
     * to a headline BUY/WATCH signal must call this same resolver rather
     * than deriving its own "primary horizon" independently - two pages
     * disagreeing about which model actually produced the result they're
     * both describing is exactly how a stock whose ACTUAL triggering model
     * failed its quality gate can still show up with a near-perfect
     * composite score, if the score's own resolution silently picked a
     * different, unrelated horizon/variant that happened to pass.
     *
     * @param  list<int>  $availableHorizonDays
     * @return array{horizon: int, variant: string}|null
     */
    public function resolve(string $signal, ?object $currentSignal, array $availableHorizonDays): ?array
    {
        if (! $currentSignal) {
            return null;
        }

        if ($signal === 'BUY') {
            $scopes = $this->jsonList($currentSignal->buy_scopes ?? null);
            if ($scopes !== []) {
                $bestQuality = (string) ($currentSignal->best_buy_quality ?? '');
                $candidates = $bestQuality !== ''
                    ? array_values(array_filter($scopes, fn (array $scope): bool => (string) ($scope['quality'] ?? '') === $bestQuality))
                    : [];
                $candidates = $candidates !== [] ? $candidates : $scopes;
                usort($candidates, fn (array $a, array $b): int => (float) ($b['expected_return'] ?? 0) <=> (float) ($a['expected_return'] ?? 0));
                $chosen = $candidates[0] ?? null;
                if ($chosen && in_array((int) ($chosen['horizon'] ?? 0), $availableHorizonDays, true)) {
                    return ['horizon' => (int) $chosen['horizon'], 'variant' => (string) ($chosen['variant'] ?? '')];
                }
            }
        }

        if ($signal === 'WATCH') {
            $chosen = $this->jsonList($currentSignal->watch_context ?? null)[0] ?? null;
            if (is_array($chosen) && isset($chosen['later_buy_horizon'])
                && in_array((int) $chosen['later_buy_horizon'], $availableHorizonDays, true)) {
                return ['horizon' => (int) $chosen['later_buy_horizon'], 'variant' => (string) ($chosen['later_buy_variant'] ?? '')];
            }
        }

        $chosen = $this->jsonList($currentSignal->negative_top_scopes ?? null)[0] ?? null;
        if (is_array($chosen) && isset($chosen['horizon'])
            && in_array((int) $chosen['horizon'], $availableHorizonDays, true)) {
            return ['horizon' => (int) $chosen['horizon'], 'variant' => (string) ($chosen['variant'] ?? '')];
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}

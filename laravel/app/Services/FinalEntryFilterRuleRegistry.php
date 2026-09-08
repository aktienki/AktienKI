<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Authoritative catalogue for FINAL-BUY eligibility rules.
 *
 * Callers provide only immutable PIT metrics and configured rule values. They
 * cannot assert that a rule is known, that an input exists, or that it passed.
 */
final class FinalEntryFilterRuleRegistry
{
    /** @var array<string, array{metric: string, operator: string, kind: string}> */
    private const ENTRY_RULES = [
        'signal' => ['metric' => 'raw_signal', 'operator' => 'eq', 'kind' => 'enum'],
        'score_min' => ['metric' => 'prediction_score_10', 'operator' => 'gte', 'kind' => 'score_10'],
        'confidence_min' => ['metric' => 'confidence_percent', 'operator' => 'gte', 'kind' => 'number'],
        'drawdown_max' => ['metric' => 'drawdown_percent', 'operator' => 'lte', 'kind' => 'number'],
        'risk_max' => ['metric' => 'risk_percent', 'operator' => 'lte', 'kind' => 'number'],
        'profit_per_trade_min' => ['metric' => 'profit_per_trade_percent', 'operator' => 'gte', 'kind' => 'number'],
        'median_return_min' => ['metric' => 'median_net_trade_percent', 'operator' => 'gte', 'kind' => 'number'],
        'volatility_max' => ['metric' => 'volatility_percent', 'operator' => 'lte', 'kind' => 'number'],
        'minimum_trades' => ['metric' => 'historical_trades', 'operator' => 'gte', 'kind' => 'number'],
        'sector_score_min' => ['metric' => 'sector_score', 'operator' => 'gte', 'kind' => 'number'],
        'predicted_return_min' => ['metric' => 'predicted_return_percent', 'operator' => 'gte', 'kind' => 'number'],
        'noise_score_min' => ['metric' => 'noise_score', 'operator' => 'gte', 'kind' => 'number'],
        'profit_factor_min' => ['metric' => 'profit_factor', 'operator' => 'gte', 'kind' => 'number'],
        'signal_quality_min' => ['metric' => 'signal_quality', 'operator' => 'gte', 'kind' => 'number'],
        'model_quality_min' => ['metric' => 'model_quality', 'operator' => 'gte', 'kind' => 'number'],
        'pe_max' => ['metric' => 'pe_ratio', 'operator' => 'lte', 'kind' => 'number'],
        'dividend_yield_min' => ['metric' => 'dividend_yield_percent', 'operator' => 'gte', 'kind' => 'number'],
        'market_cap_min' => ['metric' => 'market_cap_billions', 'operator' => 'gte', 'kind' => 'number'],
        'revenue_growth_min' => ['metric' => 'revenue_growth_percent', 'operator' => 'gte', 'kind' => 'number'],
        'hit_rate_min' => ['metric' => 'hit_rate_percent', 'operator' => 'gte', 'kind' => 'number'],
        'country' => ['metric' => 'country', 'operator' => 'in', 'kind' => 'set'],
        'exchange' => ['metric' => 'exchange', 'operator' => 'in', 'kind' => 'set'],
        'sector' => ['metric' => 'sector', 'operator' => 'in', 'kind' => 'set'],
        'ai_type' => ['metric' => 'ai_type', 'operator' => 'in', 'kind' => 'set'],
        'model' => ['metric' => 'model_id', 'operator' => 'in', 'kind' => 'set'],
        // Tier and QG are optional strategy rules, never implicit eligibility.
        'quality_tier' => ['metric' => 'quality_context', 'operator' => 'matches', 'kind' => 'app_quality_tier'],
        'service_quality_gate' => ['metric' => 'quality_gate_passed', 'operator' => 'eq', 'kind' => 'quality_gate'],
        'quality_horizons_present' => ['metric' => 'quality_horizons', 'operator' => 'required_if_true', 'kind' => 'presence_toggle'],
        'quality_horizons' => ['metric' => 'quality_horizons', 'operator' => 'contains_all', 'kind' => 'set'],
        'market_cap_group' => ['metric' => 'market_cap_group', 'operator' => 'in', 'kind' => 'set'],
        'heatmap_selection' => ['metric' => 'heatmap_bucket', 'operator' => 'not_contains', 'kind' => 'set'],
        'positive_prediction_required' => ['metric' => 'predicted_return_percent', 'operator' => 'positive_if_true', 'kind' => 'positive_toggle'],
        'ensemble_veto_required' => ['metric' => 'ensemble_veto_passed', 'operator' => 'required_if_true', 'kind' => 'boolean_toggle'],
        // A failed wait never reuses the same event: only a later raw BUY event
        // may carry entry_wait_new_event_passed=true after a fresh evaluation.
        'entry_wait_5d_enabled' => ['metric' => 'entry_wait_new_event_passed', 'operator' => 'required_if_true', 'kind' => 'boolean_toggle'],
        'indicator_matrix_entry' => ['metric' => 'indicator_matrix_entry_passed', 'operator' => 'eq', 'kind' => 'boolean'],
        'indicator_probability_min' => ['metric' => 'indicator_probability_percent', 'operator' => 'gte', 'kind' => 'number'],
    ];

    /** @var list<string> */
    private const NON_ELIGIBILITY_KEYS = [
        'q', 'symbols', 'index', 'smart_label', 'sort', 'direction',
        'gate_mode', 'quality_setup', 'quality_gate_profile',
        'sector_score_rotation', 'index_score_rotation',
        'entry_strategy', 'entry_risk_style', 'automatic_strategy_comparison',
        'automatic_selected_strategy', 'forecast_score_rotation_5d_enabled',
        'strategy_priority', 'combined_area_forecast_priority',
        'stock_forecast_weight', 'sector_forecast_weight', 'index_forecast_weight',
        'drawdown_penalty_weight', 'initial_capital', 'trade_cost', 'max_positions',
        'position_factor', 'dynamic_capital_weighting', 'automatic_optimization',
        'optimization_goal', 'dividend_yield_operator',
        'exit_strategy', 'fixed_20d_exit_enabled', 'dynamic_horizon_exit_enabled',
        'support_stop_enabled', 'resistance_trailing_stop_enabled',
        'signal_change_exit_enabled', 'forecast_below_price_exit_enabled',
        'indicator_matrix_usage', 'indicator_matrix_preset',
        'indicator_matrix_macd_min', 'indicator_matrix_macd_max',
        'indicator_matrix_stoch_min', 'indicator_matrix_stoch_max',
        'indicator_matrix_macd_direction', 'serving_model_configurations',
        'serving_quality_symbols', 'optimized_backtest_run', 'display_icon',
        'display_color', 'trade_capacity',
    ];
    /**
     * Compile persisted settings into actual entry rules. Defaults, ranking,
     * execution and exit settings remain in the audit snapshot but never gate.
     *
     * @param array<string,mixed> $filters
     * @return array{active_rules:list<array<string,mixed>>,audit:array<string,mixed>}
     */
    public function compile(array $filters): array
    {
        foreach (array_keys($filters) as $key) {
            if (! is_string($key)
                || (! array_key_exists($key, self::ENTRY_RULES)
                    && ! in_array($key, self::NON_ELIGIBILITY_KEYS, true))) {
                throw new InvalidArgumentException('Unknown persisted filter setting: '.(string) $key);
            }
        }

        $defaults = [
            'q' => '', 'country' => '', 'exchange' => '', 'sector' => '',
            'ai_type' => '', 'model' => [], 'quality_tier' => '',
            'service_quality_gate' => '', 'signal' => '', 'score_min' => 0,
            'confidence_min' => 0, 'drawdown_max' => 50,
            'profit_per_trade_min' => 0, 'volatility_max' => 100,
            // Null means inactive. Zero remains available as the meaningful
            // rule "historical median must be non-negative".
            'median_return_min' => null,
            'minimum_trades' => 0, 'sector_score_min' => -1,
            'predicted_return_min' => 0.5,
            'noise_score_min' => 0, 'profit_factor_min' => 0,
            'signal_quality_min' => 0, 'model_quality_min' => 0,
            'heatmap_selection' => '', 'pe_max' => 100,
            'market_cap_min' => 0, 'market_cap_group' => 'all',
            'revenue_growth_min' => -50, 'hit_rate_min' => 0,
            'risk_max' => 100, 'dividend_yield_min' => 0,
            'positive_prediction_required' => false,
            'ensemble_veto_required' => false, 'entry_wait_5d_enabled' => false,
            'indicator_probability_min' => 0,
        ];
        $rules = [];
        foreach (self::ENTRY_RULES as $key => $definition) {
            if (in_array($key, [
                'indicator_matrix_entry', 'indicator_probability_min',
                'quality_horizons_present', 'quality_horizons',
            ], true) || ! array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if ((array_key_exists($key, $defaults)
                    && $this->sameSetting($value, $defaults[$key]))
                || $value === '' || $value === [] || $value === null) {
                continue;
            }
            $operator = $key === 'dividend_yield_min'
                ? (($filters['dividend_yield_operator'] ?? 'gte') === 'lte' ? 'lte' : 'gte')
                : $definition['operator'];
            $normalizedValue = $this->normalizeConfiguredValue($key, $value);
            if ($key === 'heatmap_selection' && $normalizedValue === []) {
                continue;
            }
            $rules[] = [
                'key' => $key, 'operator' => $operator,
                'value' => $normalizedValue,
            ];
        }

        $qualityFilterActive = trim((string) ($filters['quality_tier'] ?? '')) !== ''
            || trim((string) ($filters['service_quality_gate'] ?? '')) !== '';
        if ($qualityFilterActive
            && $this->toBool($filters['quality_horizons_present'] ?? false)) {
            $rules[] = [
                'key' => 'quality_horizons_present',
                'operator' => 'required_if_true', 'value' => true,
            ];
            $horizons = $this->normalizeConfiguredValue(
                'quality_horizons',
                $filters['quality_horizons'] ?? [],
            );
            $rules[] = [
                'key' => 'quality_horizons',
                'operator' => 'contains_all', 'value' => $horizons,
            ];
        }

        if (($filters['indicator_matrix_usage'] ?? 'off') === 'entry') {
            $rules[] = [
                'key' => 'indicator_matrix_entry',
                'operator' => 'eq', 'value' => true,
            ];
            if (is_numeric($filters['indicator_probability_min'] ?? null)
                && (float) $filters['indicator_probability_min'] > 0) {
                $rules[] = [
                    'key' => 'indicator_probability_min', 'operator' => 'gte',
                    'value' => $this->normalizeConfiguredValue(
                        'indicator_probability_min',
                        $filters['indicator_probability_min'],
                    ),
                ];
            }
        }

        return ['active_rules' => $rules, 'audit' => $filters];
    }


    /**
     * @param list<array<string, mixed>> $activeRules
     * @param array<string, mixed> $metrics
     * @return array{passed: bool, results: list<array<string, mixed>>, reasons: list<string>}
     */
    public function evaluate(array $activeRules, array $metrics): array
    {
        try {
            $metrics = $this->normalizeMetrics($metrics);
        } catch (InvalidArgumentException) {
            return [
                'passed' => false,
                'results' => [],
                'reasons' => ['FILTER_METRICS_INVALID'],
            ];
        }
        $metrics['quality_context'] = [
            'model_quality_class' => strtolower(trim((string) (
                $metrics['model_quality_class'] ?? $metrics['quality_tier'] ?? ''
            ))),
            'quality_gate_passed' => $metrics['quality_gate_passed'] ?? null,
        ];
        $results = [];
        $reasons = [];
        $seen = [];

        foreach ($activeRules as $rule) {
            if (! is_array($rule)) {
                $reasons[] = 'INVALID_ENTRY_FILTER_DEFINITION';
                continue;
            }

            $key = trim((string) ($rule['key'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                $reasons[] = 'INVALID_ENTRY_FILTER_DEFINITION'.($key === '' ? '' : ':'.$key);
                continue;
            }
            $seen[$key] = true;

            if (in_array($key, self::NON_ELIGIBILITY_KEYS, true)) {
                $results[] = [
                    'key' => $key,
                    'scope' => 'non_entry',
                    'evaluated' => false,
                    'passed' => null,
                ];
                continue;
            }

            $definition = self::ENTRY_RULES[$key] ?? null;
            if ($definition === null) {
                $reasons[] = 'UNKNOWN_ENTRY_FILTER:'.$key;
                continue;
            }

            $operator = (string) ($rule['operator'] ?? $definition['operator']);
            $allowedOperators = $key === 'dividend_yield_min'
                ? ['gte', 'lte']
                : [$definition['operator']];
            if (! in_array($operator, $allowedOperators, true)) {
                $reasons[] = 'FILTER_OPERATOR_INVALID:'.$key;
                continue;
            }
            if (! array_key_exists('value', $rule)) {
                $reasons[] = 'FILTER_CONFIG_INVALID:'.$key;
                continue;
            }
            try {
                $configuredValue = $this->normalizeConfiguredValue($key, $rule['value']);
            } catch (InvalidArgumentException) {
                $reasons[] = 'FILTER_CONFIG_INVALID:'.$key;
                continue;
            }

            $metric = $definition['metric'];
            if (in_array($definition['kind'], [
                'boolean_toggle', 'positive_toggle', 'presence_toggle',
            ], true) && $configuredValue === false) {
                $results[] = [
                    'key' => $key,
                    'scope' => 'entry',
                    'evaluated' => true,
                    'metric' => $metric,
                    'operator' => $operator,
                    'configured_value' => false,
                    'observed_value' => null,
                    'passed' => true,
                ];
                continue;
            }

            if (! array_key_exists($metric, $metrics) || $metrics[$metric] === null) {
                $reasons[] = 'FILTER_INPUT_MISSING:'.$key;
                continue;
            }

            try {
                $passed = $this->compare(
                    $metrics[$metric],
                    $configuredValue,
                    $operator,
                    $definition['kind'],
                );
            } catch (InvalidArgumentException) {
                $reasons[] = 'FILTER_CONFIG_INVALID:'.$key;
                continue;
            }

            $results[] = [
                'key' => $key,
                'scope' => 'entry',
                'evaluated' => true,
                'metric' => $metric,
                'operator' => $operator,
                'configured_value' => $configuredValue,
                'observed_value' => $metrics[$metric],
                'passed' => $passed,
            ];
            if (! $passed) {
                $reasons[] = 'FILTER_REJECTED:'.$key;
            }
        }

        $reasons = array_values(array_unique($reasons));

        return ['passed' => $reasons === [], 'results' => $results, 'reasons' => $reasons];
    }

    /** @return list<string> */
    public function entryKeys(): array
    {
        return array_keys(self::ENTRY_RULES);
    }

    /** @return list<string> */
    public function nonEligibilityKeys(): array
    {
        return self::NON_ELIGIBILITY_KEYS;
    }

    public function classify(string $key): string
    {
        if (array_key_exists($key, self::ENTRY_RULES)) {
            return 'entry';
        }

        if (in_array($key, self::NON_ELIGIBILITY_KEYS, true)) {
            return 'non_entry';
        }

        return 'unknown';
    }

    private function compare(mixed $actual, mixed $configured, string $operator, string $kind): bool
    {
        if ($kind === 'number' || $kind === 'score_10') {
            if (! is_numeric($actual) || ! is_numeric($configured)) {
                throw new InvalidArgumentException('Numeric rule expected.');
            }
            $left = (float) $actual;
            $right = (float) $configured;
            if (! is_finite($left) || ! is_finite($right)) {
                throw new InvalidArgumentException('Finite numeric rule expected.');
            }
            if ($kind === 'score_10'
                && ($left < 0 || $left > 10 || $right < 0 || $right > 10)) {
                throw new InvalidArgumentException('Score must use the 0..10 unit.');
            }

            return match ($operator) {
                'gte' => $left >= $right,
                'gt' => $left > $right,
                'lte' => $left <= $right,
                default => throw new InvalidArgumentException('Unsupported operator.'),
            };
        }

        if ($kind === 'boolean') {
            if (! is_bool($actual) || ! is_bool($configured) || $operator !== 'eq') {
                throw new InvalidArgumentException('Boolean rule expected.');
            }

            return $actual === $configured;
        }

        if ($kind === 'boolean_toggle') {
            if (! is_bool($configured) || ! is_bool($actual)
                || $operator !== 'required_if_true') {
                throw new InvalidArgumentException('Boolean toggle expected.');
            }

            return ! $configured || $actual;
        }

        if ($kind === 'positive_toggle') {
            if (! is_bool($configured) || ! is_numeric($actual)
                || ! is_finite((float) $actual)
                || $operator !== 'positive_if_true') {
                throw new InvalidArgumentException('Positive-return toggle expected.');
            }

            return ! $configured || (float) $actual > 0;
        }

        if ($kind === 'presence_toggle') {
            if (! is_bool($configured) || ! is_array($actual)
                || $operator !== 'required_if_true') {
                throw new InvalidArgumentException('Presence toggle expected.');
            }

            return ! $configured || $actual !== [];
        }

        if ($kind === 'quality_gate') {
            if (! is_bool($actual) || $operator !== 'eq') {
                throw new InvalidArgumentException('Quality-gate state expected.');
            }
            $wanted = strtolower(trim((string) $configured));
            if (! in_array($wanted, ['passed', 'failed'], true)) {
                throw new InvalidArgumentException('Quality-gate filter expected.');
            }

            return $actual === ($wanted === 'passed');
        }

        if ($kind === 'enum') {
            if ($operator !== 'eq') {
                throw new InvalidArgumentException('Enum rule expected.');
            }

            return strtoupper(trim((string) $actual)) ===
                strtoupper(trim((string) $configured));
        }

        if ($kind === 'app_quality_tier') {
            if (! is_array($actual) || $operator !== 'matches') {
                throw new InvalidArgumentException('Application quality context expected.');
            }
            $class = strtolower(trim((string) ($actual['model_quality_class'] ?? '')));
            $gate = $actual['quality_gate_passed'] ?? null;
            $wanted = strtolower(trim((string) $configured));
            if (! in_array($wanted, ['top', 'strong', 'solid', 'test', 'unqualified'], true)) {
                throw new InvalidArgumentException('Application quality tier expected.');
            }
            if ($wanted === 'strong') {
                return is_bool($gate) && $gate;
            }
            if (! in_array($class, ['quality', 'solid', 'basic', 'underperform'], true)) {
                throw new InvalidArgumentException('Application quality class expected.');
            }

            return match ($wanted) {
                'top' => $class === 'quality',
                'solid' => in_array($class, ['quality', 'solid'], true),
                'test' => in_array($class, ['quality', 'solid', 'basic'], true),
                'unqualified' => $class === 'underperform',
                default => false,
            };
        }

        if ($operator === 'not_contains') {
            return $this->passesHeatmapExclusion($actual, $configured);
        }

        $actualValues = is_array($actual) ? $actual : [$actual];
        $configuredValues = is_array($configured) ? $configured : [$configured];
        $actualValues = array_map(static fn (mixed $value): string => strtoupper(trim((string) $value)), $actualValues);
        $configuredValues = array_map(static fn (mixed $value): string => strtoupper(trim((string) $value)), $configuredValues);
        if ($configuredValues === [] || in_array('', $configuredValues, true)) {
            throw new InvalidArgumentException('Set rule expected.');
        }

        return match ($operator) {
            'in' => array_intersect($actualValues, $configuredValues) !== [],
            'contains' => array_intersect($actualValues, $configuredValues) !== [],
            'contains_all' => array_diff($configuredValues, $actualValues) === [],
            default => throw new InvalidArgumentException('Unsupported operator.'),
        };
    }

    private function sameSetting(mixed $value, mixed $default): bool
    {
        if (is_bool($default)) {
            try {
                return $this->toBool($value) === $default;
            } catch (InvalidArgumentException) {
                return false;
            }
        }
        if (is_numeric($value) && is_numeric($default)) {
            $left = (float) $value;
            $right = (float) $default;

            return is_finite($left) && is_finite($right) && $left === $right;
        }
        if (is_array($value) || is_array($default)) {
            return $value === $default;
        }

        return trim((string) $value) === trim((string) $default);
    }

    private function toBool(mixed $value): bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1' => true,
            $value === false, $value === 0, $value === '0' => false,
            default => throw new InvalidArgumentException('Strict boolean expected.'),
        };
    }

    private function normalizeConfiguredValue(string $key, mixed $value): mixed
    {
        if (in_array($key, [
            'positive_prediction_required', 'ensemble_veto_required',
            'entry_wait_5d_enabled', 'quality_horizons_present',
            'indicator_matrix_entry',
        ], true)) {
            return $this->toBool($value);
        }
        if ($key === 'heatmap_selection') {
            return $this->heatmapTokens($value);
        }
        if ($key === 'quality_tier') {
            $tier = strtolower(trim((string) $value));
            if (! in_array($tier, ['top', 'strong', 'solid', 'test', 'unqualified'], true)) {
                throw new InvalidArgumentException('Unknown quality tier.');
            }

            return $tier;
        }
        if ($key === 'service_quality_gate') {
            $gate = strtolower(trim((string) $value));
            if (! in_array($gate, ['passed', 'failed'], true)) {
                throw new InvalidArgumentException('Unknown quality gate state.');
            }

            return $gate;
        }
        if ($key === 'signal') {
            $signal = strtoupper(trim((string) $value));
            if (! in_array($signal, ['BUY', 'WAIT', 'WATCH', 'HOLD', 'SELL'], true)) {
                throw new InvalidArgumentException('Unknown signal.');
            }

            return $signal;
        }
        if ($key === 'quality_horizons') {
            if (! is_array($value) || $value === []) {
                throw new InvalidArgumentException('Quality horizons expected.');
            }
            $horizons = [];
            foreach ($value as $horizon) {
                if (filter_var($horizon, FILTER_VALIDATE_INT) === false
                    || ! in_array((int) $horizon, [10, 20, 40], true)) {
                    throw new InvalidArgumentException('Unsupported quality horizon.');
                }
                $horizons[] = (int) $horizon;
            }
            $horizons = array_values(array_unique($horizons));
            sort($horizons, SORT_NUMERIC);

            return $horizons;
        }

        $definition = self::ENTRY_RULES[$key] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Unknown entry filter.');
        }
        if (in_array($definition['kind'], ['number', 'score_10'], true)) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Finite numeric setting expected.');
            }
            $number = (float) $value;
            if ($definition['kind'] === 'score_10' && ($number < 0 || $number > 10)) {
                throw new InvalidArgumentException('Score must use the 0..10 unit.');
            }

            return $number;
        }
        if ($definition['kind'] === 'boolean') {
            return $this->toBool($value);
        }
        if ($definition['kind'] === 'set') {
            $values = is_array($value) ? $value : [$value];
            $values = array_values(array_unique(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $values,
            )));
            if ($values === [] || in_array('', $values, true)) {
                throw new InvalidArgumentException('Non-empty set expected.');
            }

            return $values;
        }

        return $value;
    }

    /** @param array<string,mixed> $metrics @return array<string,mixed> */
    private function normalizeMetrics(array $metrics): array
    {
        $normalized = $metrics;
        $finite = static function (mixed $value, string $name): float {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Finite '.$name.' expected.');
            }

            return (float) $value;
        };
        $put = static function (array &$target, string $key, float $value): void {
            if (array_key_exists($key, $target) && $target[$key] !== null) {
                if (! is_numeric($target[$key]) || ! is_finite((float) $target[$key])
                    || abs((float) $target[$key] - $value) > 0.000000001) {
                    throw new InvalidArgumentException('Conflicting metric units for '.$key.'.');
                }

                return;
            }
            $target[$key] = $value;
        };

        $fractionAliases = [
            'confidence_fraction' => 'confidence_percent',
            'predicted_return_fraction' => 'predicted_return_percent',
            'drawdown_fraction' => 'drawdown_percent',
            'profit_per_trade_fraction' => 'profit_per_trade_percent',
            'median_net_trade_fraction' => 'median_net_trade_percent',
            'volatility_fraction' => 'volatility_percent',
            'dividend_yield_fraction' => 'dividend_yield_percent',
            'revenue_growth_fraction' => 'revenue_growth_percent',
            'hit_rate_fraction' => 'hit_rate_percent',
            'model_quality_fraction' => 'model_quality',
            'signal_quality_fraction' => 'signal_quality',
            'indicator_probability_fraction' => 'indicator_probability_percent',
        ];
        foreach ($fractionAliases as $source => $target) {
            if (array_key_exists($source, $metrics) && $metrics[$source] !== null) {
                $value = $finite($metrics[$source], $source);
                if (in_array($source, ['drawdown_fraction', 'volatility_fraction'], true)) {
                    $value = abs($value);
                }
                $put($normalized, $target, $value * 100);
            }
        }

        if (array_key_exists('confidence', $metrics) && $metrics['confidence'] !== null) {
            $value = $finite($metrics['confidence'], 'confidence');
            $put($normalized, 'confidence_percent', $value >= 0 && $value <= 1 ? $value * 100 : $value);
        }
        if (array_key_exists('expected_return', $metrics) && $metrics['expected_return'] !== null) {
            $value = $finite($metrics['expected_return'], 'expected_return');
            $put($normalized, 'predicted_return_percent', abs($value) <= 1 ? $value * 100 : $value);
        }
        if (array_key_exists('market_cap', $metrics) && $metrics['market_cap'] !== null) {
            $value = $finite($metrics['market_cap'], 'market_cap');
            if ($value < 0) {
                throw new InvalidArgumentException('Non-negative market cap expected.');
            }
            $put($normalized, 'market_cap_billions', $value / 1_000_000_000);
        }
        if (array_key_exists('dividend_yield', $metrics) && $metrics['dividend_yield'] !== null) {
            $put($normalized, 'dividend_yield_percent', $finite($metrics['dividend_yield'], 'dividend_yield') * 100);
        }
        if (array_key_exists('revenue_growth', $metrics) && $metrics['revenue_growth'] !== null) {
            $put($normalized, 'revenue_growth_percent', $finite($metrics['revenue_growth'], 'revenue_growth') * 100);
        }
        if (array_key_exists('technical_volatility_20', $metrics) && $metrics['technical_volatility_20'] !== null) {
            $put($normalized, 'volatility_percent', abs($finite($metrics['technical_volatility_20'], 'technical_volatility_20')) * 100);
        }
        if (array_key_exists('model_quality_score', $metrics) && $metrics['model_quality_score'] !== null) {
            $put($normalized, 'model_quality', $finite($metrics['model_quality_score'], 'model_quality_score') * 100);
        }
        if (array_key_exists('risk_score', $metrics) && $metrics['risk_score'] !== null) {
            $value = $finite($metrics['risk_score'], 'risk_score');
            if ($value < 1 || $value > 5) {
                throw new InvalidArgumentException('Risk score must use the 1..5 unit.');
            }
            $put($normalized, 'risk_percent', ($value - 1) * 25);
        }
        if (array_key_exists('calibrated_score', $metrics) && $metrics['calibrated_score'] !== null) {
            $put($normalized, 'prediction_score_10', $finite($metrics['calibrated_score'], 'calibrated_score'));
        }

        if (array_key_exists('performance', $metrics) && $metrics['performance'] !== null) {
            if (! is_array($metrics['performance'])) {
                throw new InvalidArgumentException('Performance snapshot expected.');
            }
            $performance = $metrics['performance'];
            foreach ([
                'hit_rate' => ['hit_rate_percent', 100, false],
                'average_net_trade' => ['profit_per_trade_percent', 100, false],
                'median_net_trade' => ['median_net_trade_percent', 100, false],
                'max_drawdown' => ['drawdown_percent', 100, true],
                'stddev_net_trade' => ['volatility_percent', 100, true],
                'profit_factor' => ['profit_factor', 1, false],
                'trades' => ['historical_trades', 1, false],
            ] as $source => [$target, $factor, $absolute]) {
                if (! array_key_exists($source, $performance) || $performance[$source] === null) {
                    continue;
                }
                $value = $finite($performance[$source], 'performance.'.$source);
                $put($normalized, $target, ($absolute ? abs($value) : $value) * $factor);
            }
        }

        $numericMetrics = [
            'prediction_score_10', 'confidence_percent', 'drawdown_percent',
            'risk_percent', 'profit_per_trade_percent', 'volatility_percent',
            'median_net_trade_percent',
            'historical_trades', 'sector_score', 'predicted_return_percent',
            'noise_score', 'profit_factor', 'signal_quality', 'model_quality',
            'pe_ratio', 'dividend_yield_percent', 'market_cap_billions',
            'revenue_growth_percent', 'hit_rate_percent',
            'indicator_probability_percent',
        ];
        foreach ($numericMetrics as $name) {
            if (array_key_exists($name, $normalized) && $normalized[$name] !== null) {
                $normalized[$name] = $finite($normalized[$name], $name);
            }
        }
        foreach (['drawdown_percent', 'volatility_percent'] as $name) {
            if (isset($normalized[$name])) {
                $normalized[$name] = abs($normalized[$name]);
            }
        }
        if (isset($normalized['prediction_score_10'])
            && ($normalized['prediction_score_10'] < 0 || $normalized['prediction_score_10'] > 10)) {
            throw new InvalidArgumentException('Prediction score must use the 0..10 unit.');
        }
        foreach (['confidence_percent', 'risk_percent', 'hit_rate_percent', 'model_quality', 'signal_quality', 'noise_score', 'indicator_probability_percent'] as $name) {
            if (isset($normalized[$name]) && ($normalized[$name] < 0 || $normalized[$name] > 100)) {
                throw new InvalidArgumentException($name.' must use the 0..100 unit.');
            }
        }
        foreach (['historical_trades', 'market_cap_billions'] as $name) {
            if (isset($normalized[$name]) && $normalized[$name] < 0) {
                throw new InvalidArgumentException($name.' must be non-negative.');
            }
        }

        foreach (['quality_gate_passed', 'ensemble_veto_passed', 'entry_wait_new_event_passed', 'indicator_matrix_entry_passed'] as $name) {
            if (array_key_exists($name, $normalized) && $normalized[$name] !== null
                && ! is_bool($normalized[$name])) {
                throw new InvalidArgumentException($name.' must be authoritative boolean evidence.');
            }
        }
        if (array_key_exists('quality_horizons', $normalized) && $normalized['quality_horizons'] !== null) {
            if (! is_array($normalized['quality_horizons'])) {
                throw new InvalidArgumentException('Quality horizons expected.');
            }
            $horizons = [];
            foreach ($normalized['quality_horizons'] as $horizon) {
                if (filter_var($horizon, FILTER_VALIDATE_INT) === false
                    || ! in_array((int) $horizon, [10, 20, 40], true)) {
                    throw new InvalidArgumentException('Unsupported observed quality horizon.');
                }
                $horizons[] = (int) $horizon;
            }
            $normalized['quality_horizons'] = array_values(array_unique($horizons));
            sort($normalized['quality_horizons'], SORT_NUMERIC);
        }
        if (array_key_exists('heatmap_bucket', $normalized) && $normalized['heatmap_bucket'] !== null) {
            $normalized['heatmap_bucket'] = $this->heatmapTokens($normalized['heatmap_bucket']);
        }

        // A success flag supplied by a caller is not proof that a wait was
        // resolved by a new raw event. Derive the flag exclusively from the
        // trusted resolver's immutable previous/candidate event evidence.
        unset($normalized['entry_wait_new_event_passed']);
        if (array_key_exists('entry_wait_context', $metrics)
            && $metrics['entry_wait_context'] !== null) {
            $normalized['entry_wait_new_event_passed'] = $this->freshLaterRawEvent(
                $metrics['entry_wait_context'],
            );
        }

        return $normalized;
    }

    private function passesHeatmapExclusion(mixed $actual, mixed $configured): bool
    {
        $actualTokens = $this->heatmapTokens($actual);
        $configuredTokens = $this->heatmapTokens($configured);
        if ($actualTokens === [] || $configuredTokens === []) {
            return false;
        }
        $actualMaps = array_values(array_unique(array_map(
            static fn (string $token): string => explode(':', $token, 2)[0],
            $actualTokens,
        )));
        $configuredMaps = array_values(array_unique(array_map(
            static fn (string $token): string => explode(':', $token, 2)[0],
            $configuredTokens,
        )));
        if (array_diff($configuredMaps, $actualMaps) !== []) {
            return false;
        }

        return array_intersect($actualTokens, $configuredTokens) === [];
    }

    private function freshLaterRawEvent(mixed $context): bool
    {
        if (! is_array($context)) {
            throw new InvalidArgumentException('Entry-wait event context expected.');
        }
        $previousKey = trim((string) ($context['previous_source_event_key'] ?? ''));
        $candidateKey = trim((string) ($context['candidate_source_event_key'] ?? ''));
        $previousAsOf = $context['previous_as_of'] ?? null;
        $candidateAsOf = $context['candidate_as_of'] ?? null;
        if (preg_match('/^[a-f0-9]{64}$/', $previousKey) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $candidateKey) !== 1
            || ! is_string($previousAsOf) || trim($previousAsOf) === ''
            || ! is_string($candidateAsOf) || trim($candidateAsOf) === ''
            || ! is_bool($context['previous_wait_failed'] ?? null)
            || ! is_bool($context['fresh_filter_evaluation_passed'] ?? null)) {
            throw new InvalidArgumentException('Complete entry-wait event evidence expected.');
        }
        try {
            $previousTime = new \DateTimeImmutable($previousAsOf);
            $candidateTime = new \DateTimeImmutable($candidateAsOf);
        } catch (\Exception) {
            throw new InvalidArgumentException('Valid entry-wait timestamps expected.');
        }

        return $candidateKey !== $previousKey
            && $candidateTime > $previousTime
            && strtoupper(trim((string) ($context['previous_raw_signal'] ?? ''))) === 'BUY'
            && strtoupper(trim((string) ($context['candidate_raw_signal'] ?? ''))) === 'BUY'
            && $context['previous_wait_failed']
            && $context['fresh_filter_evaluation_passed'];
    }

    /** @return list<string> */
    private function heatmapTokens(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if (preg_match('/^[a-z_]+:[0-9]-[0-9]$/', $trimmed) === 1) {
                return [$trimmed];
            }
            try {
                $value = json_decode($trimmed, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new InvalidArgumentException('Invalid heatmap JSON.');
            }
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException('Heatmap map expected.');
        }

        $knownMaps = [
            'profit_factor_hit_rate', 'signal_risk',
            'volatility_drawdown', 'trades_return',
        ];
        $tokens = [];
        if (array_is_list($value)) {
            foreach ($value as $token) {
                if (! is_string($token)
                    || preg_match('/^([a-z_]+):([0-9]-[0-9])$/', trim($token), $matches) !== 1
                    || ! in_array($matches[1], $knownMaps, true)) {
                    throw new InvalidArgumentException('Invalid heatmap token.');
                }
                $tokens[] = $matches[1].':'.$matches[2];
            }
        } else {
            foreach ($value as $map => $cells) {
                if (! is_string($map) || ! in_array($map, $knownMaps, true)) {
                    throw new InvalidArgumentException('Invalid heatmap selection.');
                }
                $cells = is_array($cells) ? $cells : [$cells];
                foreach ($cells as $cell) {
                    $cell = trim((string) $cell);
                    if (preg_match('/^[0-9]-[0-9]$/', $cell) !== 1) {
                        throw new InvalidArgumentException('Invalid heatmap cell.');
                    }
                    $tokens[] = $map.':'.$cell;
                }
            }
        }
        $tokens = array_values(array_unique($tokens));
        sort($tokens, SORT_STRING);

        return $tokens;
    }
}

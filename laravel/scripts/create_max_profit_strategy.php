<?php

declare(strict_types=1);

use App\Jobs\RunFilteredBacktest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rules = [
    10 => ['confidence_min' => .55, 'expected_return_min' => .03, 'profit_factor_min' => 1.50, 'hit_rate_min' => .50, 'median_return_min' => 0, 'average_return_min' => .005, 'stddev_max' => .10, 'drawdown_max' => .20, 'minimum_trades' => 10],
    20 => ['confidence_min' => .55, 'expected_return_min' => .05, 'profit_factor_min' => 1.50, 'hit_rate_min' => .50, 'median_return_min' => 0, 'average_return_min' => .010, 'stddev_max' => .15, 'drawdown_max' => .25, 'minimum_trades' => 10],
    40 => ['confidence_min' => .50, 'expected_return_min' => .05, 'profit_factor_min' => 1.50, 'hit_rate_min' => .52, 'median_return_min' => .005, 'average_return_min' => .020, 'stddev_max' => .20, 'drawdown_max' => .30, 'minimum_trades' => 10],
];
$strategyName = 'MAX Profit Multi-Horizont';
$tenDayProfiles = [
    'balanced' => ['confidence_min' => .50, 'expected_return_min' => .02, 'profit_factor_min' => 1.25, 'hit_rate_min' => .48, 'median_return_min' => -.002, 'average_return_min' => .0025, 'stddev_max' => .15, 'drawdown_max' => .30, 'minimum_trades' => 10],
    'broad' => ['confidence_min' => .45, 'expected_return_min' => .01, 'profit_factor_min' => 1.05, 'hit_rate_min' => .45, 'median_return_min' => -.005, 'average_return_min' => 0, 'stddev_max' => .20, 'drawdown_max' => .40, 'minimum_trades' => 5],
    'median-positive' => ['confidence_min' => .45, 'expected_return_min' => .01, 'profit_factor_min' => 1.05, 'hit_rate_min' => .45, 'median_return_min' => 0, 'average_return_min' => 0, 'stddev_max' => .20, 'drawdown_max' => .40, 'minimum_trades' => 5],
    'pf-quality' => ['confidence_min' => .45, 'expected_return_min' => .01, 'profit_factor_min' => 1.50, 'hit_rate_min' => .45, 'median_return_min' => -.005, 'average_return_min' => 0, 'stddev_max' => .20, 'drawdown_max' => .35, 'minimum_trades' => 10],
];
$twentyDayProfiles = [
    'balanced' => ['confidence_min' => .50, 'expected_return_min' => .03, 'profit_factor_min' => 1.25, 'hit_rate_min' => .48, 'median_return_min' => -.002, 'average_return_min' => .005, 'stddev_max' => .20, 'drawdown_max' => .35, 'minimum_trades' => 10],
    'broad' => ['confidence_min' => .45, 'expected_return_min' => .015, 'profit_factor_min' => 1.05, 'hit_rate_min' => .45, 'median_return_min' => -.005, 'average_return_min' => 0, 'stddev_max' => .25, 'drawdown_max' => .45, 'minimum_trades' => 5],
    'median-positive' => ['confidence_min' => .45, 'expected_return_min' => .015, 'profit_factor_min' => 1.05, 'hit_rate_min' => .45, 'median_return_min' => 0, 'average_return_min' => 0, 'stddev_max' => .25, 'drawdown_max' => .45, 'minimum_trades' => 5],
    'pf-quality' => ['confidence_min' => .45, 'expected_return_min' => .02, 'profit_factor_min' => 1.50, 'hit_rate_min' => .45, 'median_return_min' => -.005, 'average_return_min' => 0, 'stddev_max' => .22, 'drawdown_max' => .40, 'minimum_trades' => 10],
];
foreach ($argv as $argument) {
    if (! str_starts_with($argument, '--10t-profile=')) {
        continue;
    }
    $profile = substr($argument, strlen('--10t-profile='));
    if (! isset($tenDayProfiles[$profile])) {
        throw new InvalidArgumentException("Unknown 10T profile: $profile");
    }
    $rules = [10 => $tenDayProfiles[$profile]];
    $strategyName = 'Optimiert 10T '.ucwords(str_replace('-', ' ', $profile));
}
foreach ($argv as $argument) {
    if (! str_starts_with($argument, ($prefix = '--20t-profile='))) {
        continue;
    }
    $profile = substr($argument, strlen($prefix));
    if (! isset($twentyDayProfiles[$profile])) {
        throw new InvalidArgumentException("Unknown 20T profile: $profile");
    }
    $rules = [20 => $twentyDayProfiles[$profile]];
    $strategyName = 'Optimiert 20T '.ucwords(str_replace('-', ' ', $profile));
}

$serving = DB::connection('serving');
$latestDate = $serving->table('serving_prediction_batches')->where('status', 'complete')->max('calculation_date');
$predictions = $serving->table('serving_predictions as p')
    ->join('serving_instruments as i', 'i.id', '=', 'p.instrument_id')
    ->join('serving_releases as r', 'r.id', '=', 'p.release_id')
    ->where('i.instrument_type', 'stock')
    ->whereDate('p.as_of', '>=', date('Y-m-d', strtotime((string) $latestDate.' -7 days')))
    ->orderByDesc('p.as_of')->get(['p.*', 'i.symbol', 'r.compact_metrics']);

$chosen = [];
foreach ($predictions->unique(fn ($p) => "$p->instrument_id|$p->horizon|$p->variant") as $p) {
    $horizon = (int) $p->horizon;
    $rule = $rules[$horizon] ?? null;
    if ($rule === null) {
        continue;
    }
    $metrics = data_get(json_decode((string) $p->compact_metrics, true), "horizons.$horizon.$p->variant.metrics", []);
    $passes = (float) ($p->confidence ?? 0) >= $rule['confidence_min']
        && (float) ($p->expected_return ?? -999) >= $rule['expected_return_min']
        && (int) ($metrics['trades'] ?? 0) >= $rule['minimum_trades']
        && (float) ($metrics['profit_factor'] ?? 0) >= $rule['profit_factor_min']
        && (float) ($metrics['hit_rate'] ?? 0) >= $rule['hit_rate_min']
        && (float) ($metrics['median_net_trade'] ?? -999) >= $rule['median_return_min']
        && (float) ($metrics['average_net_trade'] ?? -999) >= $rule['average_return_min']
        && (float) ($metrics['stddev_net_trade'] ?? 999) <= $rule['stddev_max']
        && abs((float) ($metrics['max_drawdown'] ?? 999)) <= $rule['drawdown_max'];
    if (! $passes) {
        continue;
    }
    $release = json_decode((string) $p->compact_metrics, true);
    $chosen[] = array_filter([
        'symbol' => (string) $p->symbol,
        'release_id' => (string) $p->release_id,
        'horizon' => $horizon,
        'horizon_days' => $horizon,
        'variant' => (string) $p->variant,
        'model_name' => $p->variant === 'standard' ? data_get($release, "horizons.$horizon.standard.champion") : null,
    ], fn ($value) => $value !== null && $value !== '');
}

$filters = [
    'signal' => 'BUY', 'initial_capital' => 10000, 'trade_cost' => 10,
    'max_positions' => 5, 'position_factor' => 1, 'dynamic_capital_weighting' => 0,
    'confidence_min' => 50, 'predicted_return_min' => -50, 'profit_factor_min' => 0,
    'drawdown_max' => 50, 'volatility_max' => 100, 'minimum_trades' => 0,
    'quality_horizons' => array_keys($rules), 'serving_model_configurations' => $chosen,
    'max_profit_horizon_rules' => $rules,
    'automatic_optimization' => 1,
    'optimization_goal' => 'maximize_profit_robust_horizon_grid',
    'optimizer_result' => [
        'method' => 'provisional_robust_threshold_candidate',
        'status' => 'provisional_pending_multi_million_exhaustive_search',
        'metrics' => ['confidence', 'expected_return', 'profit_factor', 'hit_rate', 'median_net_trade', 'average_net_trade', 'stddev_net_trade', 'max_drawdown', 'trades'],
        'historical_expected_return_source' => 'entry_tcn_score_for_pure_tcn_only',
        'selected_configurations' => count($chosen),
        'selected_by_horizon' => collect($chosen)->countBy('horizon')->all(),
        'calculated_at' => now()->toIso8601String(),
    ],
];

$id = DB::table('saved_prediction_filters')->updateOrInsert(
    ['user_id' => 1, 'name' => $strategyName],
    ['filters' => json_encode($filters, JSON_THROW_ON_ERROR), 'visibility' => 'private',
        'description' => 'Horizontspezifische Max-Profit-Strategie mit Konfidenz, erwarteter Rendite, Median, Durchschnitt, Standardabweichung, Profit Factor, Trefferquote, Drawdown und Mindestanzahl Trades.',
        'updated_at' => now(), 'created_at' => now()]
);
$strategy = DB::table('saved_prediction_filters')->where('user_id', 1)->where('name', $strategyName)->first(['id']);

echo json_encode(['strategy_id' => $strategy->id, 'configurations' => count($chosen), 'by_horizon' => collect($chosen)->countBy('horizon')->all(), 'latest_batch_date' => $latestDate], JSON_PRETTY_PRINT).PHP_EOL;

if (in_array('--backtest', $argv, true)) {
    $sourceRunId = (int) DB::table('backtest_runs')
        ->whereIn('status', ['completed', 'completed_with_errors'])
        ->whereRaw("COALESCE(settings->>'run_type', 'system') <> 'user_filter'")
        ->where('trades_count', '>', 0)->orderByDesc('id')->value('id');
    $publicId = (string) Str::uuid();
    $runId = DB::table('backtest_runs')->insertGetId([
        'public_id' => $publicId, 'status' => 'queued', 'strategy' => 'filtered_multi_horizon',
        'timeframe' => '1d', 'horizon_days' => 20, 'started_at' => now(),
        'settings' => json_encode(['run_type' => 'user_filter', 'initiated_by_user_id' => 1,
            'source_run_id' => $sourceRunId, 'lookback_years' => 3,
            'selection_filters' => $filters, 'capital' => ['initial' => 10000, 'position' => 10000 / 5,
                'currency' => 'EUR', 'max_parallel_positions' => 5, 'trade_cost_eur' => 10]], JSON_THROW_ON_ERROR),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    RunFilteredBacktest::dispatchSync($runId, $sourceRunId, $filters);
    $run = DB::table('backtest_runs')->where('id', $runId)->first(['status', 'trades_count', 'settings']);
    echo json_encode(['backtest_run_id' => $runId, 'public_id' => $publicId,
        'status' => $run->status, 'trades' => $run->trades_count], JSON_PRETTY_PRINT).PHP_EOL;
}

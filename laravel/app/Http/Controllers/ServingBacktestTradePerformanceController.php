<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ServingBacktestTradePerformanceController extends Controller
{
    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $horizon = (int) $request->query('horizon', 0);
        $variant = strtolower(trim((string) $request->query('variant', '')));
        $search = trim((string) $request->query('q', ''));

        $query = $serving->currentStrategyTrades()
            ->when(in_array($horizon, [10, 20, 40], true), fn ($query) => $query->where('trade.horizon', $horizon))
            ->when($variant === 'standard', fn ($query) => $query->where('trade.strategy', 'not ilike', 'pure-tcn%'))
            ->when($variant === 'pure_tcn', fn ($query) => $query->where('trade.strategy', 'ilike', 'pure-tcn%'))
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(fn ($nested) => $nested
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term]));
            });

        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('COUNT(DISTINCT trade.instrument_id) AS stocks')
            ->selectRaw('AVG(CASE WHEN trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(trade.net_return) * 100 AS average_return')
            ->selectRaw('SUM(CASE WHEN trade.net_return > 0 THEN trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN trade.net_return < 0 THEN trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('SUM(trade.net_return) * 100 AS summed_return')
            ->first();

        $trades = $query
            ->select([
                'trade.id', 'trade.instrument_id', 'trade.horizon', 'trade.strategy',
                'trade.entry_signal', 'trade.entry_date', 'trade.entry_close_eur',
                'trade.exit_date', 'trade.exit_close_eur', 'trade.holding_days',
                'trade.net_return', 'trade.transaction_cost', 'trade.exit_reason',
                'trade.entry_tcn_score', 'trade.exit_tcn_score', 'trade.tcn_score_drop',
                'trade.tcn_exit_threshold', 'trade.scheduled_exit_date',
                'trade.scheduled_net_return', 'trade.tcn_exit_value_added',
                'strategy_run.calculation_date', 'strategy_run.strategy_version',
                'instrument.symbol', 'instrument.name', 'instrument.country_code',
            ])
            ->orderByDesc('trade.exit_date')
            ->orderByDesc('trade.id')
            ->paginate(100)
            ->withQueryString();

        return view('predictions.serving-backtest-trades', compact('trades', 'summary'));
    }
}

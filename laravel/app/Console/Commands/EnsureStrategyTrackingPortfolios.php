<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\SavedPredictionFilter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every strategy is meant to be tracked with real signals from now on, not
 * only the ones a user happens to have linked to an actual Musterdepot.
 * This creates a hidden "shadow" portfolio (type=strategy_tracking) for any
 * strategy that has none yet and assigns it, so the existing
 * AutomatedPortfolioService::scan() - the same engine real Musterdepots use,
 * including its full cash ledger (portfolio_cash_ledger) - picks it up on
 * its normal schedule without any separate tracking logic. These portfolios
 * are excluded from the user-facing depot list and never send trade emails
 * (see DepotController::portfolios() and PortfolioTradeEmailService).
 */
final class EnsureStrategyTrackingPortfolios extends Command
{
    public const PORTFOLIO_TYPE = 'strategy_tracking';

    protected $signature = 'strategies:ensure-tracking-portfolios';

    protected $description = 'Creates a hidden tracking portfolio for every strategy that has no portfolio assignment yet';

    public function handle(): int
    {
        $unassignedStrategies = SavedPredictionFilter::query()
            ->whereDoesntHave('portfolios')
            ->get(['id', 'user_id', 'name', 'filters']);

        $created = 0;
        foreach ($unassignedStrategies as $strategy) {
            DB::transaction(function () use ($strategy, &$created): void {
                $initialCapital = max(1000, (float) data_get($strategy->filters, 'initial_capital', 10000));
                $name = $this->uniquePortfolioName($strategy);

                $portfolio = Portfolio::query()->create([
                    'user_id' => $strategy->user_id,
                    'name' => $name,
                    'type' => self::PORTFOLIO_TYPE,
                    'currency' => 'EUR',
                    'description' => "Automatisches Signal-Tracking für die Strategie „{$strategy->name}“ - kein sichtbares Musterdepot, keine Benachrichtigungen.",
                    'is_default' => false,
                    'active' => true,
                    // live_enabled is what AutomatedPortfolioService::execute()
                    // actually gates on - safe to set unconditionally here
                    // since it only lives on this new, hidden portfolio, not on
                    // the shared strategy record or any real Musterdepot.
                    'meta' => ['automation' => ['initial_capital' => $initialCapital, 'live_enabled' => true]],
                ]);

                DB::table('portfolio_cash_accounts')->insert([
                    'portfolio_id' => $portfolio->id,
                    'currency' => $portfolio->currency,
                    'balance' => $initialCapital,
                    'reserved_balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('portfolio_strategy_assignments')->insert([
                    'portfolio_id' => $portfolio->id,
                    'saved_prediction_filter_id' => $strategy->id,
                    'enabled' => true,
                    'priority' => 100,
                    'capital_weight' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            });
        }

        $this->info("Strategies without a portfolio: {$unassignedStrategies->count()}, tracking portfolios created: {$created}.");

        return self::SUCCESS;
    }

    private function uniquePortfolioName(SavedPredictionFilter $strategy): string
    {
        $base = Str::limit('Tracking · '.$strategy->name, 76, '');
        $name = $base;
        $suffix = 2;
        while (Portfolio::query()->where('user_id', $strategy->user_id)->where('name', $name)->exists()) {
            $name = Str::limit($base, 76 - strlen(" #{$suffix}"), '')." #{$suffix}";
            $suffix++;
        }

        return $name;
    }
}

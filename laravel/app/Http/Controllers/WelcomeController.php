<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class WelcomeController extends Controller
{
    public function __invoke(): View
    {
        return $this->renderWelcome('welcome', true);
    }

    public function copy(): View
    {
        return $this->renderWelcome('welcome-copy', true);
    }

    private function renderWelcome(string $view, bool $showBetaNotice): View
    {
        $betaTesterLimit = 25;
        $betaTesterCount = 0;
        $welcomeCountries = [];
        $welcomeStats = [
            'stocks' => null,
            'indices' => null,
            'sectors' => null,
            'forecasts' => null,
            'data-points' => null,
        ];

        try {
            $data = Cache::remember('public.welcome.stats-v2', now()->addMinutes(15), function () use ($betaTesterLimit) {
                $countries = DB::table('instruments')
                    ->where('type', 'stock')
                    ->whereNull('deleted_at')
                    ->whereNotNull('country')
                    ->where('country', '<>', '')
                    ->groupBy('country')
                    ->orderBy('country')
                    ->selectRaw('country, COUNT(*) AS stocks_count')
                    ->pluck('stocks_count', 'country')
                    ->mapWithKeys(fn ($stocks, $country) => [strtoupper((string) $country) => (int) $stocks])
                    ->all();

                // Exact COUNT(*) scans over the historical tables can take longer than
                // PHP's request timeout on the remote database. PostgreSQL maintains
                // fast row estimates which are sufficiently accurate for public totals.
                $tableEstimates = collect(DB::select(<<<'SQL'
                    SELECT c.relname, GREATEST(c.reltuples, 0)::bigint AS row_count
                    FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = current_schema()
                      AND c.relname IN ('predictions', 'price_bars', 'instrument_fundamentals', 'technical_indicators')
                    SQL))
                    ->pluck('row_count', 'relname')
                    ->map(fn ($count) => (int) $count);

                $forecastCount = $tableEstimates->get('predictions', 0);

                return [
                    'betaTesterCount' => min(
                        $betaTesterLimit,
                        DB::table('users')->where('account_status', 'tester')->count()
                    ),
                    'countries' => $countries,
                    'stats' => [
                        'stocks' => DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')->count(),
                        'indices' => DB::table('instruments')->where('type', 'index')->whereNull('deleted_at')->count(),
                        'sectors' => DB::table('instruments')
                            ->where('type', 'stock')
                            ->whereNull('deleted_at')
                            ->whereNotNull('sector')
                            ->where('sector', '<>', '')
                            ->distinct()
                            ->count('sector'),
                        'forecasts' => $forecastCount,
                        'data-points' => $tableEstimates->sum(),
                    ],
                ];
            });

            $betaTesterCount = $data['betaTesterCount'];
            $welcomeCountries = $data['countries'];
            $welcomeStats = $data['stats'];
        } catch (Throwable) {
            // Keep the public welcome page available while the database is temporarily unavailable.
        }

        return view($view, compact(
            'welcomeCountries',
            'welcomeStats',
            'showBetaNotice',
            'betaTesterCount',
            'betaTesterLimit',
        ));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    public function original(): View
    {
        return $this->renderWelcome('welcome', true);
    }

    private function renderWelcome(string $view, bool $showBetaNotice): View
    {
        $betaTesterLimit = 20;
        $betaTesterCount = 0;
        $welcomeCountries = [];
        $welcomeStats = [
            'stocks' => null,
            'indices' => null,
            'sectors' => null,
            'countries' => null,
            'forecasts' => null,
            'data-points' => null,
            'analyzed-stocks' => null,
        ];
        $publicTradePerformance = null;
        if (Storage::disk('public')->exists('statistics/trade-performance-backtest.json')) {
            $decoded = json_decode(Storage::disk('public')->get('statistics/trade-performance-backtest.json'), true);
            if (is_array($decoded) && ($decoded['version'] ?? null) === 1) $publicTradePerformance = $decoded;
        }

        try {
            $data = Cache::remember('public.welcome.stats-v6', now()->addMinutes(15), function () use ($betaTesterLimit) {
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
                    'betaTesterCount' => min($betaTesterLimit,
                        DB::table('users')->where('account_status', 'tester')->count()
                        + DB::table('contact_messages as beta_request')
                            ->where('beta_request.meta->source', 'beta_request')
                            ->whereNotExists(function ($query): void {
                                $query->selectRaw('1')
                                    ->from('users')
                                    ->where('users.account_status', 'tester')
                                    ->whereRaw('LOWER(users.email) = LOWER(beta_request.email)');
                            })
                            ->selectRaw('LOWER(beta_request.email)')
                            ->distinct()
                            ->count()
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
                        'countries' => count($countries),
                        'forecasts' => $forecastCount,
                        'data-points' => $tableEstimates->sum(),
                        'analyzed-stocks' => DB::table('stock_ai_assessments')
                            ->join('instruments', 'instruments.id', '=', 'stock_ai_assessments.instrument_id')
                            ->where('instruments.type', 'stock')
                            ->whereNull('instruments.deleted_at')
                            ->distinct()
                            ->count('stock_ai_assessments.instrument_id'),
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
            'publicTradePerformance',
        ));
    }
}

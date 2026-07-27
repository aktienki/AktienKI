<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
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
        $betaTesterLimit = 50;
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
            $betaTesterCount = min(
                $betaTesterLimit,
                DB::table('users')->where('account_status', 'tester')->count()
            );
            $welcomeCountries = DB::table('instruments')
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

            $forecastCount = DB::table('predictions')->count();
            $marketDataPointCount = DB::table('price_bars')->count()
                + DB::table('instrument_fundamentals')->count()
                + DB::table('technical_indicators')->count()
                + $forecastCount;

            $welcomeStats = [
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
                'data-points' => $marketDataPointCount,
            ];
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

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DashboardDigestNotification;
use App\Services\IndexAiScoreService;
use App\Services\MarketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\RecommendationController;
use Illuminate\Http\Request;

final class SendDashboardDigest extends Command
{
    protected $signature = 'dashboard:send-digest {--user= : User ID; defaults to the most recently logged-in account}';
    protected $description = 'Send a dashboard-style market digest to an account email address';

    public function handle(MarketService $marketService, IndexAiScoreService $scores, RecommendationController $recommendations): int
    {
        $user = User::query()->whereNotNull('email')
            ->when($this->option('user'), fn ($query) => $query->whereKey((int) $this->option('user')))
            ->orderByDesc('last_login_at')->orderByDesc('id')->firstOrFail();
        $symbols = ['DAX' => '^GDAXI', 'NASDAQ' => '^IXIC', 'S&P 500' => '^GSPC', 'Japan' => '^N225', 'China' => '000001.SS'];
        $markets = collect($symbols)->map(function (string $symbol, string $name): array {
            $bars = DB::table('instruments as instrument')->join('price_bars as bar', 'bar.instrument_id', '=', 'instrument.id')
                ->where('instrument.symbol', $symbol)->where('bar.interval', '1d')
                ->orderByDesc('bar.bar_time')->limit(2)->get(['bar.close', 'instrument.currency']);
            $price = is_numeric($bars->get(0)?->close) ? (float) $bars->get(0)->close : null;
            $previous = is_numeric($bars->get(1)?->close) ? (float) $bars->get(1)->close : null;
            return ['name' => $name, 'symbol' => $symbol, 'price' => $price, 'currency' => $bars->get(0)?->currency ?? '',
                'change' => $price !== null && $previous ? (($price / $previous) - 1) * 100 : null];
        })->values()->all();
        $dailyScores = $scores->dailyAverages();
        $riskLevel = (string) data_get($user->meta, 'risk_profile.level', 'normal');
        $assessment = $marketService->overallAssessment($markets, $dailyScores, $riskLevel);
        $analysis = DB::table('daily_market_ai_analyses')->latest('analysis_date')->latest('id')->first();
        $decode = fn ($value): array => is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?: []) : []);
        $recommendationRequest = Request::create('/recommendations', 'GET');
        $recommendationRequest->setUserResolver(fn () => $user);
        $top = collect($recommendations($recommendationRequest)->getData()['recommendations'] ?? [])->first();
        $topStock = $top ? [
            'name' => (string) $top->name,
            'symbol' => (string) $top->symbol,
            'signal' => (string) ($top->personalized_signal ?: 'BUY'),
            'price' => (float) $top->current_price,
            'currency' => (string) ($top->currency ?: 'EUR'),
            'score' => (float) $top->score_10,
            'confidence' => (float) $top->confidence_percent,
            'expected_return' => is_numeric($top->expected_return_20d) ? (float) $top->expected_return_20d : null,
            'url' => route('stocks.show', ['symbol' => $top->symbol, 'prediction' => $top->prediction_id]),
        ] : null;
        $countryChanges = collect(DB::select(<<<'SQL'
            SELECT instrument.country,
                   AVG(((recent.closes[1] / NULLIF(recent.closes[2], 0)) - 1) * 100) AS change_percent
            FROM instruments instrument
            CROSS JOIN LATERAL (
                SELECT ARRAY_AGG(sample.close ORDER BY sample.bar_time DESC) AS closes
                FROM (
                    SELECT bar.close, bar.bar_time
                    FROM price_bars bar
                    WHERE bar.instrument_id = instrument.id AND bar.interval = '1d'
                    ORDER BY bar.bar_time DESC, bar.id DESC
                    LIMIT 2
                ) sample
            ) recent
            WHERE instrument.type = 'stock'
              AND instrument.is_active = true
              AND instrument.deleted_at IS NULL
              AND instrument.country IS NOT NULL
              AND ARRAY_LENGTH(recent.closes, 1) = 2
            GROUP BY instrument.country
        SQL))->mapWithKeys(fn (object $row): array => [strtoupper((string) $row->country) => (float) $row->change_percent])->all();

        $user->notifyNow(new DashboardDigestNotification([
            'markets' => $markets,
            'assessment' => $assessment,
            'marketComment' => $analysis?->executive_summary ?: ($assessment['summary'] ?? __('Noch kein Marktkommentar verfügbar.')),
            'headline' => $analysis?->headline,
            'confidence' => (int) ($analysis?->confidence ?? 0),
            'opportunities' => $decode($analysis?->opportunities),
            'risks' => $decode($analysis?->risks),
            'analysisUrl' => route('daily-market-analysis'),
            'dashboardUrl' => route('dashboard'),
            'dataDate' => $analysis?->analysis_date
                ? \Illuminate\Support\Carbon::parse($analysis->analysis_date)->format('d.m.Y')
                : now()->format('d.m.Y'),
            'topStock' => $topStock,
            'countryChanges' => $countryChanges,
        ]));
        $this->info('Dashboard digest sent.');
        return self::SUCCESS;
    }
}

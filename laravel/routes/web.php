<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SignalChangeController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\DepotController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockComparisonController;
use App\Http\Controllers\StockIconController;
use App\Http\Controllers\StockListController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AppleChartController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\MarketAssessmentController;
use App\Http\Controllers\DailyMarketAnalysisController;
use App\Http\Controllers\MarketOverviewController;
use App\Livewire\Stocks\Index as StocksIndex;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LivePriceSubscriptionController;
use App\Http\Controllers\MarketQuotesController;
use Illuminate\Http\Request;


Route::get('/', WelcomeController::class)->name('welcome');
Route::get('/welcome', WelcomeController::class)->name('welcome.page');
Route::get('/welcome-copy', [WelcomeController::class, 'copy'])->name('welcome.copy');
Route::view('/preise', 'pricing')->name('pricing');
Route::get('/features', function () {
    $featureStats = \Illuminate\Support\Facades\Cache::remember('public.features.market-stats-v4', now()->addMinutes(15), function () {
        $instruments = \Illuminate\Support\Facades\DB::table('instruments')
            ->whereNull('deleted_at')
            ->where('is_active', true);

        return [
            'countries' => (clone $instruments)->whereNotNull('country')->where('country', '!=', '')->distinct()->count('country'),
            'sectors' => (clone $instruments)->whereNotNull('sector')->where('sector', '!=', '')->distinct()->count('sector'),
            'stocks' => (clone $instruments)->where('type', 'stock')->count(),
            'indices' => (clone $instruments)->where('type', 'index')->count(),
            'horizon_models' => \Illuminate\Support\Facades\DB::table('model_definitions')
                ->where('is_active', true)
                ->where('is_public', true)
                ->where('ai_type', 'horizon')
                ->whereNotNull('public_alias')
                ->distinct()
                ->orderBy('public_alias')
                ->pluck('public_alias')
                ->values()
                ->all(),
            'pulse_models' => \Illuminate\Support\Facades\DB::table('model_definitions')
                ->where('is_active', true)
                ->where('is_public', true)
                ->where('ai_type', 'pulse')
                ->whereNotNull('public_alias')
                ->distinct()
                ->orderBy('public_alias')
                ->pluck('public_alias')
                ->values()
                ->all(),
        ];
    });

    return view('features', compact('featureStats'));
})->name('features');
Route::view('/roadmap', 'roadmap')->name('roadmap');
Route::get('/projektstatus', ProjectStatusController::class)
    ->middleware('auth')
    ->name('project-status');
Route::get('/kontakt', [ContactController::class, 'create'])->middleware('auth')->name('contact');
Route::post('/kontakt', [ContactController::class, 'store'])->middleware(['auth', 'throttle:5,1'])->name('contact.store');
Route::get('/bewertungen', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/bewertungen', [ReviewController::class, 'store'])->middleware(['auth', 'throttle:3,1'])->name('reviews.store');

Route::get('/scenes/{scene}/{locale}.svg', function (string $scene, string $locale) {
    $files = [
        'machine-learning' => 'scene-machine-learning.svg',
        'ai-score' => 'scene-ai-score.svg',
        'traders' => 'scene-traders.svg',
        'stock-chat' => 'scene-stock-chat.svg',
    ];

    abort_unless(isset($files[$scene]) && in_array($locale, ['de', 'en'], true), 404);

    $svg = file_get_contents(public_path('assets/'.$files[$scene]));

    if ($locale === 'en') {
        // Keep the product name intact while translating generic terms such as "Aktien".
        $svg = str_replace(['AktienKI.com', 'AKTIENKI.COM'], '__AKTIENKI_BRAND__', $svg);

        $svg = strtr($svg, [
            'Daten werden verarbeitet, Modelle trainiert und Signale erzeugt' => 'Data is processed, models are trained and signals generated',
            'DATENSTRÖME' => 'DATA STREAMS',
            'Kursdaten' => 'Price data',
            'OHLCV · Volumen' => 'OHLCV · Volume',
            'Fundamentaldaten' => 'Fundamental data',
            'Bilanz · Cashflow' => 'Balance sheet · Cash flow',
            'Makrodaten' => 'Macro data',
            'Rohstoffe · Währung' => 'Commodities · Currencies',
            'Features werden erstellt' => 'Features are generated',
            'KI ENGINE' => 'AI ENGINE',
            'Modelle' => 'Models',
            '0 bis 100' => '0 to 100',
            'Prognose' => 'Forecast',
            'Täglich' => 'Daily',
            'Risikoanalyse' => 'Risk analysis',
            'Volatilität · Verlust' => 'Volatility · Downside',
            'T&#xE4;glicher KI-Score' => 'Daily AI Score',
            'Der Markt wird automatisch bewertet und verdichtet' => 'The market is automatically evaluated and summarized',
            'MARKT' => 'MARKET',
            'Aktien' => 'Stocks',
            'Sektoren' => 'Sectors',
            'Rohstoffe' => 'Commodities',
            'W&#xE4;hrungen' => 'Currencies',
            'KI Score' => 'AI Score',
            'allgemeine Bewertung' => 'overall assessment',
            'Von Tradern für Trader' => 'By traders, for traders',
            'AktienKI verbindet praktische Markterfahrung mit intelligenter Technologie' => 'AktienKI combines practical market experience with intelligent technology',
            'PRAXIS · TECHNOLOGIE · VORSPRUNG' => 'PRACTICE · TECHNOLOGY · EDGE',
            'Entwickelt aus echter Markterfahrung' => 'Built from real market experience',
            'Markterfahrung' => 'Market experience',
            'Strategien aus der Praxis' => 'Strategies built in practice',
            '20+ Jahre Markterfahrung' => '20+ years of market experience',
            'Erfahrung in verschiedenen Märkten' => 'Experience across different markets',
            'ROHSTOFFE' => 'COMMODITIES',
            'Für deinen Handel' => 'Built for your trading',
            'Klar · intelligent · fokussiert' => 'Clear · intelligent · focused',
            'Aktie auswählen und mit der KI analysieren' => 'Select a stock and analyze it with AI',
            'Aktienauswahl anhand vorhandener Kennzahlen mit integriertem KI-Chat' => 'Stock selection based on available metrics with integrated AI chat',
            'Auswählen. Verstehen. Nachfragen.' => 'Select. Understand. Ask.',
            'Von vorhandenen Werten direkt zum KI-gestützten Dialog' => 'From available metrics directly to an AI-powered dialogue',
            'Aktie auswählen' => 'Select a stock',
            'Vergleiche verfügbare Werte auf einen Blick' => 'Compare available metrics at a glance',
            'Unternehmen oder Symbol suchen' => 'Search company or symbol',
            'AKTIE' => 'STOCK',
            'KURS' => 'PRICE',
            'TREND' => 'TREND',
            'Auswahl anhand transparenter Kennzahlen' => 'Selection based on transparent metrics',
            'dein KI-Analyseassistent' => 'your AI analysis assistant',
            'Empfiehl mir heute drei' => 'Recommend three interesting',
            'interessante Aktien.' => 'stocks to me today.',
            'Heute im Analyse-Fokus:' => 'Today’s analysis focus:',
            'KI-Score' => 'AI Score',
            'Frage zur ausgewählten Aktie stellen …' => 'Ask about the selected stock …',
            'Analysen verstehen und direkt vertiefen' => 'Understand analyses and explore them directly',
            'Fragenanzahl abhängig vom Chat-Tarif' => 'Question allowance depends on the chat plan',
        ]);

        if ($scene === 'traders') {
            $svg = str_replace(
                'class="text experience-title" x="212" y="342" text-anchor="middle" font-size="20"',
                'class="text experience-title" x="212" y="342" text-anchor="middle" font-size="16"',
                $svg
            );
        }

        $svg = str_replace('__AKTIENKI_BRAND__', 'AktienKI.com', $svg);
    }

    return response($svg, 200, [
        'Content-Type' => 'image/svg+xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=300, must-revalidate',
    ]);
})->name('scenes.localized');

Route::post('/locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, ['de', 'en'], true), 404);
    $request->session()->put('locale', $locale);

    return back();
})->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::post('/live-prices/subscribe', LivePriceSubscriptionController::class)->name('live-prices.subscribe');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/apple', AppleChartController::class)->name('stocks.apple');
    //Route::get('/stocks', StocksIndex::class)->name('stocks.index');
    //Route::get('/stocks/{symbol}', [StockController::class, 'show'])->name('stocks.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/setup/filter', [PredictionController::class, 'filterSetup'])->name('setup.filter');
    Route::post('/setup/filter/backtest', [PredictionController::class, 'startFilteredBacktest'])->name('setup.filter.backtest');
    Route::get('/setup/filter/backtest/{publicId}/result', [PredictionController::class, 'filteredBacktestResult'])->name('setup.filter.backtest.result');
    Route::get('/setup/filter/backtest/{publicId}/status', [PredictionController::class, 'filteredBacktestStatus'])->name('setup.filter.backtest.status');
    Route::post('/setup/filter/backtest/{publicId}/cancel', [PredictionController::class, 'cancelFilteredBacktest'])->name('setup.filter.backtest.cancel');
    Route::get('/setup/filter/backtest/{publicId}/report', [PredictionController::class, 'downloadFilteredBacktestReport'])->name('setup.filter.backtest.report');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/stocks', fn (Request $request) =>
        redirect()->route('predictions.index', $request->query())
    )->name('stocks.index');
    Route::get('/predictions', [PredictionController::class, 'index'])->name('predictions.index');
    Route::get('/predictions/heatmap', [PredictionController::class, 'heatmap'])->name('predictions.heatmap');
    Route::get('/predictions/heatmap/trades', [PredictionController::class, 'backtestTrades'])->name('predictions.heatmap.trades');
    Route::get('/signal-changes', SignalChangeController::class)->name('signal-changes.index');
    Route::get('/recommendations', RecommendationController::class)->name('recommendations.index');
    Route::get('/markteinschaetzung', MarketAssessmentController::class)->name('market-assessment');
    Route::get('/taegliche-marktanalyse', DailyMarketAnalysisController::class)->name('daily-market-analysis');
    Route::get('/maerkte', MarketOverviewController::class)->name('markets.index');
    Route::get('/maerkte/kurse', MarketQuotesController::class)->name('markets.quotes');
    Route::get('/depots', [DepotController::class, 'index'])->name('depots.index');
    Route::get('/musterdepots', [DepotController::class, 'paperIndex'])->name('paper-depots.index');
    Route::post('/depots', [DepotController::class, 'store'])->name('depots.store');
    Route::get('/depots/{portfolio}', [DepotController::class, 'show'])->name('depots.show');
    Route::get('/stocks/compare', StockComparisonController::class)->name('stocks.compare');
    Route::get('/stock-icons/{instrument}', StockIconController::class)->name('stocks.icon');
    Route::get('/stocks/{symbol}/chartanalyse', [StockController::class, 'chartAnalysis'])->name('stocks.chart-analysis');
    Route::get('/stocks/{symbol}/chart-data', [StockController::class, 'chartData'])->name('stocks.chart-data');
    Route::get('/stocks/{symbol}', [StockController::class, 'show'])->name('stocks.show');
    Route::get('/sektoren', [SectorController::class, 'index'])->name('sectors.index');
    Route::get('/watchlists', [WatchlistController::class, 'index'])->name('watchlists.index');
    Route::get('/watchlists-menu', [WatchlistController::class, 'menu'])->name('watchlists.menu');
    Route::post('/watchlists', [WatchlistController::class, 'store'])->name('watchlists.store');
    Route::get('/watchlists/{watchlist}', [WatchlistController::class, 'show'])->name('watchlists.show');
    Route::post('/watchlists/{watchlist}/items/{instrument}', [WatchlistController::class, 'toggleItem'])->name('watchlists.items.toggle');
    Route::patch('/watchlists/{watchlist}/items/{instrument}/move', [WatchlistController::class, 'moveItem'])->name('watchlists.items.move');
    Route::delete('/watchlists/{watchlist}', [WatchlistController::class, 'destroy'])->name('watchlists.destroy');
    Route::delete('/watchlists/{watchlist}/items/{instrument}', [WatchlistController::class, 'destroyItem'])->name('watchlists.items.destroy');
    });

require __DIR__.'/auth.php';

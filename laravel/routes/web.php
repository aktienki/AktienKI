<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\PredictionController;
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
use App\Http\Controllers\IndexScreenerController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\TradingIntegrationController;
use App\Http\Controllers\MarketAssessmentController;
use App\Http\Controllers\DailyMarketAnalysisController;
use App\Http\Controllers\MarketOverviewController;
use App\Livewire\Stocks\Index as StocksIndex;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LivePriceSubscriptionController;
use App\Http\Controllers\MarketQuotesController;
use App\Http\Controllers\SavedPredictionFilterController;
use App\Http\Controllers\SignalEmailPreviewController;
use App\Http\Controllers\QualityGateSetupController;
use App\Http\Controllers\SmartSelectionLabelController;
use App\Http\Controllers\ResearchLabController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\AkiChatController;
use App\Http\Controllers\BetaInvitationController;
use App\Http\Controllers\EasyAccessController;
use Illuminate\Http\Request;


Route::get('/', WelcomeController::class)->name('welcome');
Route::get('/welcome', WelcomeController::class)->name('welcome.page');
Route::get('/welcome-copy', [WelcomeController::class, 'copy'])->name('welcome.copy');
Route::get('/easy-access', [EasyAccessController::class, 'index'])->name('easy-access');
Route::post('/easy-access', [EasyAccessController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('easy-access.store');
Route::get('/preise', function () {
    $registeredUsers = \Illuminate\Support\Facades\DB::table('users')->count();

    return view('pricing', compact('registeredUsers'));
})->name('pricing');
Route::get('/features', function () {
    // Keep the public feature counters in sync with the current database state.
    $featureStats = (function () {
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
    })();

    $registeredUsers = \Illuminate\Support\Facades\DB::table('users')->count();

    return view('features', compact('featureStats', 'registeredUsers'));
})->name('features');
Route::get('/roadmap', function () {
    $registeredUsers = \Illuminate\Support\Facades\DB::table('users')->count();

    return view('roadmap', compact('registeredUsers'));
})->name('roadmap');
Route::get('/projektstatus', ProjectStatusController::class)
    ->middleware('auth')
    ->name('project-status');

// Beta invitations are deliberately restricted to administrators. The raw token is
// generated once and only its SHA-256 digest is stored in the database.
Route::middleware('auth')->group(function (): void {
    Route::get('/beta/einladungen', [BetaInvitationController::class, 'index'])
        ->name('beta.invitations');
    Route::post('/beta/einladungen', [BetaInvitationController::class, 'store'])
        ->name('beta.invitations.store');
});
Route::get('/kontakt', [ContactController::class, 'create'])->name('contact');
Route::post('/kontakt', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
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

Route::middleware(['auth', 'verified', 'beta'])->group(function () {
    Route::post('/aki/chat', AkiChatController::class)->middleware('throttle:10,1')->name('aki.chat');
    Route::post('/live-prices/subscribe', LivePriceSubscriptionController::class)->name('live-prices.subscribe');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/maerkte/marktlage', 'markets.situation')->name('markets.situation');
    Route::get('/apple', AppleChartController::class)->name('stocks.apple');
    //Route::get('/stocks', StocksIndex::class)->name('stocks.index');
    //Route::get('/stocks/{symbol}', [StockController::class, 'show'])->name('stocks.show');
});

Route::middleware(['auth', 'verified', 'beta'])->group(function () {
    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
    Route::patch('/community/alias', [CommunityController::class, 'updateAlias'])->name('community.alias.update');
    Route::post('/community/posts', [CommunityController::class, 'store'])->middleware('throttle:5,1')->name('community.posts.store');
    Route::delete('/community/posts/{post}', [CommunityController::class, 'destroy'])->name('community.posts.destroy');
    Route::get('/email-preview/signal', SignalEmailPreviewController::class)->name('email-preview.signal');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/setup/filter', [PredictionController::class, 'filterSetup'])->middleware('plan:pro')->name('setup.filter');
    Route::get('/setup/quality', [PredictionController::class, 'qualitySetup'])->middleware('plan:plus')->name('setup.quality');
    Route::get('/setup/research-lab', [ResearchLabController::class, 'index'])->middleware('plan:pro')->name('setup.research-lab');
    Route::post('/setup/research-lab', [ResearchLabController::class, 'start'])->middleware('plan:pro')->name('setup.research-lab.start');
    Route::get('/setup/research-lab/{experiment}/status', [ResearchLabController::class, 'status'])->middleware('plan:pro')->name('setup.research-lab.status');
    Route::get('/setup/labels', [SmartSelectionLabelController::class, 'index'])->middleware('plan:plus')->name('setup.labels.index');
    Route::post('/setup/quality/labels', [SmartSelectionLabelController::class, 'store'])->middleware('plan:plus')->name('setup.quality.labels.store');
    Route::patch('/setup/labels/{label}', [SmartSelectionLabelController::class, 'update'])->middleware('plan:plus')->name('setup.labels.update');
    Route::delete('/setup/labels/{label}', [SmartSelectionLabelController::class, 'destroy'])->middleware('plan:plus')->name('setup.labels.destroy');
    Route::get('/setup/short', [PredictionController::class, 'shortStrategySetup'])->middleware('plan:pro')->name('setup.short');
    Route::get('/setup/quality-gate', [QualityGateSetupController::class, 'edit'])->name('setup.quality-gate.edit');
    Route::put('/setup/quality-gate', [QualityGateSetupController::class, 'update'])->name('setup.quality-gate.update');
    Route::put('/setup/quality-gate/backtest', [QualityGateSetupController::class, 'backtest'])->middleware('plan:premium')->name('setup.quality-gate.backtest');
    Route::get('/setup/filters', [SavedPredictionFilterController::class, 'index'])->middleware('plan:pro')->name('setup.saved-filters.index');
    Route::post('/setup/filter/saved', [SavedPredictionFilterController::class, 'store'])->middleware('plan:pro')->name('setup.filter.saved.store');
    Route::patch('/setup/filter/saved/{savedFilter}', [SavedPredictionFilterController::class, 'update'])->middleware('plan:pro')->name('setup.filter.saved.update');
    Route::patch('/setup/filter/saved/{savedFilter}/link', [SavedPredictionFilterController::class, 'link'])->middleware('plan:pro')->name('setup.filter.saved.link');
    Route::patch('/setup/filter/saved/{savedFilter}/visibility', [SavedPredictionFilterController::class, 'updateVisibility'])->middleware('plan:pro')->name('setup.filter.saved.visibility');
    Route::post('/setup/filter/saved/{savedFilter}/import', [SavedPredictionFilterController::class, 'import'])->middleware('plan:pro')->name('setup.filter.saved.import');
    Route::delete('/setup/filter/saved/{savedFilter}', [SavedPredictionFilterController::class, 'destroy'])->middleware('plan:pro')->name('setup.filter.saved.destroy');
    Route::post('/setup/filter/backtest', [PredictionController::class, 'startFilteredBacktest'])->middleware('plan:plus')->name('setup.filter.backtest');
    Route::post('/setup/filter/optimize', [PredictionController::class, 'optimizeFilter'])->middleware('plan:plus')->name('setup.filter.optimize');
    Route::get('/setup/filter/backtest/{publicId}/result', [PredictionController::class, 'filteredBacktestResult'])->middleware('plan:plus')->name('setup.filter.backtest.result');
    Route::get('/setup/filter/backtest/{publicId}/status', [PredictionController::class, 'filteredBacktestStatus'])->middleware('plan:plus')->name('setup.filter.backtest.status');
    Route::post('/setup/filter/backtest/{publicId}/cancel', [PredictionController::class, 'cancelFilteredBacktest'])->middleware('plan:plus')->name('setup.filter.backtest.cancel');
    Route::get('/setup/filter/backtest/{publicId}/report', [PredictionController::class, 'downloadFilteredBacktestReport'])->middleware('plan:plus')->name('setup.filter.backtest.report');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/stocks', fn (Request $request) =>
        redirect()->route('predictions.index', $request->query())
    )->name('stocks.index');
Route::get('/predictions', [PredictionController::class, 'index'])->name('predictions.index');
Route::get('/predictions/signal-history', [\App\Http\Controllers\SignalTransitionController::class, 'index'])->name('predictions.signal-history');
Route::get('/reports/{analysisReport}/pdf', [\App\Http\Controllers\AnalysisReportController::class, 'pdf'])->middleware(['auth', 'verified', 'beta'])->name('analysis-reports.pdf');
Route::get('/reports/{analysisReport}', [\App\Http\Controllers\AnalysisReportController::class, 'show'])->middleware(['auth', 'verified', 'beta'])->name('analysis-reports.show');
    Route::post('/predictions/filters', [PredictionController::class, 'storeTableFilter'])->name('predictions.filters.store');
    Route::get('/predictions/heatmap', [PredictionController::class, 'heatmap'])->name('predictions.heatmap');
    Route::get('/predictions/heatmap/trades', [PredictionController::class, 'backtestTrades'])->name('predictions.heatmap.trades');
    Route::get('/recommendations/live-quotes', [RecommendationController::class, 'liveQuotes'])->name('recommendations.live-quotes');
    Route::get('/recommendations', RecommendationController::class)->name('recommendations.index');
    Route::get('/screener', [RecommendationController::class, 'screener'])->name('screener.index');
    Route::get('/screener/history', [RecommendationController::class, 'screeningHistory'])->name('screener.history');
    Route::get('/markteinschaetzung', MarketAssessmentController::class)->name('market-assessment');
    Route::get('/taegliche-marktanalyse', DailyMarketAnalysisController::class)->name('daily-market-analysis');
    Route::get('/maerkte', MarketOverviewController::class)->name('markets.index');
    Route::get('/maerkte/kurse', MarketQuotesController::class)->name('markets.quotes');
    Route::get('/depots', [DepotController::class, 'index'])->name('depots.index');
    Route::get('/musterdepots', [DepotController::class, 'paperIndex'])->name('paper-depots.index');
    Route::post('/depots', [DepotController::class, 'store'])->name('depots.store');
    Route::post('/musterdepots/{portfolio}/instruments/{instrument}', [DepotController::class, 'addInstrument'])->name('paper-depots.instruments.store');
    Route::post('/musterdepots/{portfolio}/positionen/{instrument}/verkaufen', [DepotController::class, 'sellInstrument'])->name('paper-depots.instruments.sell');
    Route::post('/depots/{portfolio}/simulation', [DepotController::class, 'startSimulation'])->name('depots.simulation.start');
    Route::post('/depots/{portfolio}/reset', [DepotController::class, 'reset'])->name('depots.reset');
    Route::put('/depots/{portfolio}/capital', [DepotController::class, 'updateCapital'])->name('depots.capital.update');
    Route::delete('/depots/{portfolio}', [DepotController::class, 'destroy'])->name('depots.destroy');
    Route::get('/depots/{portfolio}/simulation/{publicId}/status', [DepotController::class, 'simulationStatus'])->name('depots.simulation.status');
    Route::get('/depots/{portfolio}/simulation/{publicId}/report', [DepotController::class, 'simulationReport'])->name('depots.simulation.report');
    Route::get('/depots/{portfolio}', [DepotController::class, 'show'])->name('depots.show');
    Route::put('/depots/{portfolio}/strategies', [DepotController::class, 'updateStrategies'])->name('depots.strategies.update');
    Route::put('/depots/{portfolio}/automation', [DepotController::class, 'updateAutomation'])->name('depots.automation.update');
    Route::get('/stocks/compare', StockComparisonController::class)->name('stocks.compare');
    Route::get('/stock-icons/{instrument}', StockIconController::class)->name('stocks.icon');
    Route::get('/stocks/{symbol}/chartanalyse', [StockController::class, 'chartAnalysis'])->name('stocks.chart-analysis');
    Route::get('/stocks/{symbol}/chart-data', [StockController::class, 'chartData'])->name('stocks.chart-data');
    Route::get('/stocks/{symbol}/live-quote', [StockController::class, 'liveQuote'])->name('stocks.live-quote');
    Route::get('/stocks/{symbol}/report', [StockController::class, 'report'])->name('stocks.report');
    Route::get('/stocks/{symbol}', [StockController::class, 'show'])->name('stocks.show');
    Route::post('/stocks/{instrument}/entry-alert', [\App\Http\Controllers\EntrySignalAlertController::class, 'store'])->middleware('plan:pro')->name('stocks.entry-alert.store');
    Route::get('/sektoren', [SectorController::class, 'index'])->name('sectors.index');
    Route::get('/indizes', IndexScreenerController::class)->name('indices.index');
    Route::get('/watchlists', [WatchlistController::class, 'index'])->name('watchlists.index');
    Route::get('/watchlists-menu', [WatchlistController::class, 'menu'])->name('watchlists.menu');
    Route::post('/watchlists', [WatchlistController::class, 'store'])->name('watchlists.store');
    Route::get('/watchlists/{watchlist}', [WatchlistController::class, 'show'])->name('watchlists.show');
    Route::post('/watchlists/{watchlist}/items/{instrument}', [WatchlistController::class, 'toggleItem'])->name('watchlists.items.toggle');
    Route::patch('/watchlists/{watchlist}/items/{instrument}/move', [WatchlistController::class, 'moveItem'])->name('watchlists.items.move');
    Route::delete('/watchlists/{watchlist}', [WatchlistController::class, 'destroy'])->name('watchlists.destroy');
    Route::delete('/watchlists/{watchlist}/items/{instrument}', [WatchlistController::class, 'destroyItem'])->name('watchlists.items.destroy');
    Route::get('/integrationen', [TradingIntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/konten', [TradingIntegrationController::class, 'accounts'])->name('accounts.index');
    Route::get('/konten/{connection}/positionen', [TradingIntegrationController::class, 'accountPositions'])->middleware('throttle:30,1')->name('accounts.positions');
    Route::post('/integrationen/broker', [TradingIntegrationController::class, 'storeBroker'])->name('integrations.broker.store');
    Route::delete('/integrationen/broker/{connection}', [TradingIntegrationController::class, 'destroyBroker'])->name('integrations.broker.destroy');
    Route::post('/integrationen/broker/{connection}/test', [TradingIntegrationController::class, 'testBroker'])->name('integrations.broker.test');
    Route::get('/integrationen/ctrader/{connection}/authorize', [TradingIntegrationController::class, 'ctraderAuthorize'])->name('integrations.ctrader.authorize');
    Route::get('/integrationen/ctrader/callback', [TradingIntegrationController::class, 'ctraderCallback'])->name('integrations.ctrader.callback');
    Route::post('/integrationen/broker/{connection}/orders', [TradingIntegrationController::class, 'placeOrder'])->middleware('throttle:10,1')->name('integrations.orders.store');
    Route::post('/integrationen/whatsapp', [TradingIntegrationController::class, 'storeWhatsApp'])->name('integrations.whatsapp.store');
    Route::post('/integrationen/whatsapp/test', [TradingIntegrationController::class, 'testWhatsApp'])->middleware('throttle:3,1')->name('integrations.whatsapp.test');
    });

require __DIR__.'/auth.php';

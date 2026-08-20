<x-app-layout>
    @php
        $riskLabels = [
            'cautious' => __('Defensiv'),
            'normal' => __('Normal'),
            'dynamic' => __('Dynamisch'),
            'opportunity_oriented' => __('Chancenorientiert'),
            'opportunity' => __('Chancenorientiert'),
            'aggressive' => __('Offensiv'),
            'risk' => __('Risk'),
        ];
        $isOpportunityProfile = in_array($riskProfile, ['opportunity_oriented', 'opportunity', 'aggressive'], true);
        $dashboardDefaultTiles = ['paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'best-buy', 'best-wait'];
        $dashboardSelectedTiles = array_values(array_intersect(
            (array) data_get(auth()->user()->preferences, 'dashboard.personal_tiles', $dashboardDefaultTiles),
            ['paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'best-buy', 'best-wait', 'watchlist-screener', 'predictions', 'smart-screener', 'market-report', 'stock-comparison', 'mobile-view', 'news']
        ));
        $dashboardTileVisible = fn (string $id): bool => in_array($id, $dashboardSelectedTiles, true);
        $dashboardTileDescriptions = [
            'paper-depots' => __('Musterdepots öffnen und verwalten.'),
            'watchlists' => __('Gespeicherte Aktienlisten anzeigen.'),
            'strategies' => __('Eigene Anlagestrategien verwalten.'),
            'labels' => __('Aktien mit persönlichen Labels ordnen.'),
            'reminders' => __('E-Mail-Erinnerungen verwalten.'),
            'best-buy' => __('Aktuell stärkste BUY-Aktie öffnen.'),
            'best-wait' => __('Aktuell stärkste WAIT-Aktie öffnen.'),
            'watchlist-screener' => __('Watchlist-Aktien direkt filtern.'),
            'predictions' => __('Alle aktuellen Prognosen vergleichen.'),
            'smart-screener' => __('Aktien nach eigenen Kriterien finden.'),
            'market-report' => __('Die aktuelle Marktanalyse lesen.'),
            'stock-comparison' => __('Mehrere Aktien direkt vergleichen.'),
            'mobile-view' => __('Lege fest, welche Karten auf dem Smartphone erscheinen.'),
            'news' => __('Anzahl neuer Unternehmensmeldungen der letzten 24 Stunden.'),
        ];
        $dashboardCardDefaults = [
            ['id' => 'signal-cockpit', 'width' => 1, 'height' => 6],
            ['id' => 'strategy', 'width' => 1, 'height' => 1],
            ['id' => 'market', 'width' => 1, 'height' => 2],
            ['id' => 'personal', 'width' => 1, 'height' => 6],
            ['id' => 'models', 'width' => 1, 'height' => 2],
            ['id' => 'signals', 'width' => 1, 'height' => 2],
            ['id' => 'earnings', 'width' => 1, 'height' => 6],
            ['id' => 'market-summary', 'width' => 1, 'height' => 1],
            ['id' => 'community', 'width' => 1, 'height' => 1],
        ];
        $dashboardMainCardLabels = [
            'strategy' => __('Strategiedepot'), 'personal' => __('Persönlicher Bereich'),
            'community' => __('Community'), 'market' => __('Aktuelle Marktlage'),
            'models' => __('Letzte Prognosen'), 'signals' => __('Empfehlungen & Signalübergänge'),
            'earnings' => __('Aktuelle Quartalszahlen'),
            'market-summary' => __('Kompakter Marktbericht'),
            'schedule' => __('Termine & Erinnerungen'), 'signal-cockpit' => __('Signal-Cockpit'),
            'mobile-view' => __('Mobile Ansicht'),
        ];
        $dashboardMainCardDescriptions = [
            'strategy' => __('Depotwert, Kapital und Performance.'), 'personal' => __('Deine persönlichen Schnellzugriffe.'),
            'community' => __('Aktivität seit deinem letzten Login.'), 'market' => __('Ausblick und aktueller Marktbericht.'),
            'models' => __('Globale Modellläufe nach Regionen.'), 'signals' => __('Neue Empfehlungen und Signalwechsel.'),
            'earnings' => __('Aktuelle Quartalszahlen aus dem Aktienuniversum.'),
            'market-summary' => __('Aktuelle Marktlage kompakt zusammengefasst.'),
            'schedule' => __('Anstehende E-Mails und Aktionen.'),
            'signal-cockpit' => __('Kauf- und Watch-Signalwechsel, technische Signale und Chartmuster der letzten drei Handelstage.'),
            'mobile-view' => __('Lege fest, welche Karten auf dem Smartphone erscheinen.'),
        ];
        $dashboardMinimumHeights = [
            'strategy' => 1, 'community' => 2,
            'personal' => 6, 'market' => 2, 'signal-cockpit' => 6,
            'models' => 2, 'signals' => 2, 'earnings' => 6, 'market-summary' => 1, 'schedule' => 2, 'mobile-view' => 1,
        ];
        $dashboardFixedDimensions = [
            'strategy' => ['width' => 1, 'height' => 1],
            'community' => ['width' => 1, 'height' => 2],
            'personal' => ['width' => 1, 'height' => 6],
            'market' => ['width' => 1, 'height' => 2],
            'models' => ['width' => 1, 'height' => 2],
            'signals' => ['width' => 1, 'height' => 2],
            'earnings' => ['width' => 1, 'height' => 6],
            'market-summary' => ['width' => 1, 'height' => 1],
            'signal-cockpit' => ['width' => 1, 'height' => 6],
            'mobile-view' => ['width' => 1, 'height' => 1],
        ];
        $storedDashboardCards = data_get(auth()->user()->preferences, 'dashboard.cards');
        $dashboardCardsCustomized = is_array($storedDashboardCards) && count($storedDashboardCards) > 0;
        $dashboardCards = collect($dashboardCardsCustomized ? array_values($storedDashboardCards) : $dashboardCardDefaults)
            ->map(function (array $config) use ($dashboardFixedDimensions, $dashboardMinimumHeights): array {
                $legacyWidth = ['small' => 1, 'medium' => 2, 'large' => 3];
                $legacyHeight = ['small' => 1, 'medium' => 2, 'large' => 3];
                $legacySize = (string) ($config['size'] ?? 'medium');
                $width = is_numeric($config['width'] ?? null) ? (int) $config['width'] : ($legacyWidth[$config['width'] ?? ''] ?? ($legacySize === 'large' ? 2 : 1));
                $height = is_numeric($config['height'] ?? null) ? (int) $config['height'] : ($legacyHeight[$config['height'] ?? ''] ?? ($legacySize === 'small' ? 1 : 2));
                $fixed = $dashboardFixedDimensions[$config['id']] ?? null;
                return ['id' => $config['id'], 'width' => $fixed['width'] ?? max(1, min(3, $width)), 'height' => $fixed['height'] ?? max($dashboardMinimumHeights[$config['id']] ?? 1, min(6, $height))];
            })->values()->all();
        $dashboardCardConfig = collect($dashboardCards)->keyBy('id');
        $dashboardCardWidth = fn (string $id): string => (string) data_get($dashboardCardConfig->get($id), 'width', 1);
        $dashboardCardHeight = fn (string $id): string => (string) data_get($dashboardCardConfig->get($id), 'height', 2);
        $dashboardCardSize = fn (string $id): string => $dashboardCardHeight($id);
        $dashboardCardOrder = fn (string $id): int => (($index = array_search($id, array_column($dashboardCards, 'id'), true)) === false ? 99 : $index);
        $dashboardCardVisible = fn (string $id): bool => $dashboardCardConfig->has($id);
        $dashboardMarketVisible = $dashboardCardVisible('market') || $dashboardCardVisible('market-summary');
        $dashboardMobileCardIds = ['strategy', 'personal', 'community', 'market', 'signal-cockpit', 'models', 'signals', 'earnings', 'market-summary', 'schedule', 'mobile-view'];
        $storedMobileCards = data_get(auth()->user()->preferences, 'dashboard.mobile_cards');
        $dashboardMobileCards = is_array($storedMobileCards)
            ? array_values(array_intersect($dashboardMobileCardIds, $storedMobileCards))
            : array_values(array_intersect($dashboardMobileCardIds, array_column($dashboardCards, 'id')));
    @endphp

    <style>
        @media (max-width: 767px) {
            #personal-dashboard .dashboard-bento [data-dashboard-card] { display: none !important; }
            @foreach($dashboardMobileCards as $mobileCard)
                #personal-dashboard .dashboard-bento [data-dashboard-card="{{ $mobileCard }}"] { display: flex !important; }
            @endforeach
        }
    </style>

    <main id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)] xl:h-[calc(100dvh-89px)] xl:min-h-0 xl:overflow-hidden">
        <div class="ak-container flex min-h-[calc(100dvh-73px)] flex-col py-4 lg:py-5 xl:h-full xl:min-h-0">
            <header class="mb-4 flex shrink-0 flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-orange-400">{{ __('Persönliche Übersicht') }}</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">{{ __('Mein Dashboard') }}</h1>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" @if($canUsePlus) data-dashboard-aki-open @endif @disabled(!$canUsePlus) title="{{ $canUsePlus ? __('AKI fragen') : __('Ab Plus verfügbar') }}" class="relative inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-black transition {{ $canUsePlus ? 'border-orange-400/45 bg-orange-400/[.12] text-orange-300 shadow-[0_8px_24px_rgba(251,146,60,.10)] hover:border-orange-300 hover:bg-orange-400/[.2]' : 'cursor-not-allowed border-slate-500/25 bg-slate-500/[.06] text-slate-500 grayscale' }}">
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                        {{ __('AKI fragen') }}
                        @unless($canUsePlus)<span class="ak-plan-badge ak-plan-badge--plus">PLUS</span>@endunless
                    </button>
                    <div class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold text-[var(--ak-muted)] {{ $isOpportunityProfile ? 'border-rose-400/35 bg-rose-400/[.10] shadow-[0_8px_24px_rgba(251,113,133,.08)]' : 'border-orange-400/35 bg-orange-400/[.10] shadow-[0_8px_24px_rgba(251,146,60,.08)]' }}">
                        <x-heroicon-o-shield-check class="h-4 w-4 {{ $isOpportunityProfile ? 'text-rose-300' : 'text-orange-400' }}" />
                        {{ __('Risikoprofil') }}
                        <span class="{{ $isOpportunityProfile ? 'text-rose-300' : 'text-orange-400' }}">{{ $riskLabels[$riskProfile] ?? ucfirst($riskProfile) }}</span>
                    </div>
                </div>
            </header>

            <section class="dashboard-bento grid gap-3 sm:grid-cols-2 xl:min-h-0 xl:flex-1 xl:grid-cols-12" data-dashboard-card-layout="{{ $dashboardCardsCustomized ? 'custom' : 'default' }}">
                <div class="dashboard-personal-column contents sm:col-span-2">
                @if ($strategyPortfolio)
                    @php
                        $strategyPerformance = (float) $strategyPortfolio->dashboard_performance;
                        $strategyPortfolioActive = (bool) data_get($strategyPortfolio->meta, 'automation.live_enabled', false);
                        $strategyEmailActive = $strategyPortfolioActive
                            && (bool) data_get($strategyPortfolio->meta, 'automation.transaction_email_enabled', false);
                    @endphp
                    <a href="{{ $canUsePro ? route('depots.show', ['portfolio' => $strategyPortfolio, 'return_to' => 'paper']) : route('pricing') }}" data-dashboard-card="strategy" data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('strategy') }}" class="dashboard-bento-strategy ak-card ak-dashboard-card group relative flex min-h-[94px] overflow-hidden p-3 transition {{ $dashboardCardVisible('strategy') ? '' : 'hidden' }} {{ !$canUsePro ? 'dashboard-plan-locked' : ($strategyPortfolioActive ? 'border-orange-200/90 ring-1 ring-inset ring-orange-200/30 shadow-[0_0_35px_rgba(251,146,60,.24)] hover:border-orange-100' : 'border-orange-200/45 hover:border-orange-100/70') }}">
                        @unless($canUsePro)<span class="dashboard-plan-badge ak-plan-badge ak-plan-badge--pro">{{ __('Ab Pro') }}</span>@endunless
                        <span class="pointer-events-none absolute -left-8 -top-10 h-28 w-28 rounded-full {{ $strategyPortfolioActive ? 'bg-orange-400/35' : 'bg-orange-400/15' }} blur-2xl"></span>
                        <div class="relative flex w-full min-w-0 flex-col justify-between">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-200/45 bg-orange-400/20 text-orange-200"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2"><b class="truncate text-base text-[var(--ak-text)]">{{ $strategyPortfolio->name }}</b><small class="rounded bg-orange-400/20 px-1.5 py-0.5 text-[8px] font-black text-orange-200">{{ __('STRATEGIEDEPOT') }}</small></span>
                                        <span class="mt-0.5 flex min-w-0 gap-1 overflow-hidden">
                                            @foreach ($strategyPortfolio->strategies as $strategy)
                                                <small class="truncate rounded border px-1.5 py-0.5 text-[8px] font-black {{ $strategyPortfolioActive ? 'border-orange-200/60 bg-orange-400 text-slate-950' : 'border-orange-400/20 bg-orange-400/10 text-orange-400' }}">{{ $strategy->name }}</small>
                                            @endforeach
                                        </span>
                                    </span>
                                </div>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    <span class="flex items-center gap-1 rounded-md border px-2 py-1 text-[8px] font-black uppercase {{ $strategyEmailActive ? 'border-amber-300/60 bg-amber-300/20 text-amber-200' : 'border-slate-500/25 bg-slate-500/10 text-slate-400' }}" title="{{ __('E-Mail pro Transaktion') }}">
                                        <x-heroicon-o-envelope class="h-3 w-3" />{{ $strategyEmailActive ? __('E-Mail aktiv') : __('E-Mail aus') }}
                                    </span>
                                    @if ($strategyPortfolioActive)
                                        <span class="flex items-center gap-1.5 rounded-md border border-orange-200 bg-orange-400 px-2 py-1 text-[9px] font-black uppercase text-slate-950 shadow-[0_0_15px_rgba(251,146,60,.35)]"><i class="h-1.5 w-1.5 rounded-full bg-slate-950"></i>{{ __('Aktiv') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="mt-2 grid grid-cols-4 gap-1 border-t border-orange-400/15 pt-2 text-center">
                                @foreach ([
                                    [__('Depotwert'), $strategyPortfolio->dashboard_positions_value, $strategyPortfolio->currency, 'text-[var(--ak-text)]'],
                                    [__('Kapital'), $strategyPortfolio->dashboard_cash, $strategyPortfolio->currency, 'text-[var(--ak-text)]'],
                                    [__('Gesamtwert'), $strategyPortfolio->dashboard_total_value, $strategyPortfolio->currency, 'text-orange-400'],
                                    [__('Performance'), $strategyPerformance, '%', $strategyPerformance >= 0 ? 'text-orange-400' : 'text-rose-300'],
                                ] as [$label, $value, $suffix, $valueClass])
                                    <span class="min-w-0"><small class="block truncate text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $label }}</small><b class="block truncate text-xs tabular-nums {{ $valueClass }}">{{ $label === __('Performance') && $value >= 0 ? '+' : '' }}{{ number_format((float) $value, $suffix === '%' ? 1 : 0, ',', '.') }} {{ $suffix }}</b></span>
                                @endforeach
                            </div>
                        </div>
                    </a>
                @else
                    <article data-dashboard-card="strategy" data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('strategy') }}" class="dashboard-bento-strategy ak-card ak-dashboard-card relative flex min-h-[94px] flex-col justify-between overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('strategy') ? '' : 'hidden' }} {{ $canUsePro ? '' : 'dashboard-plan-locked' }}">
                        @unless($canUsePro)<span class="dashboard-plan-badge ak-plan-badge ak-plan-badge--pro">{{ __('Ab Pro') }}</span>@endunless
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Strategiedepot') }}</p>
                                <h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Noch kein Strategiedepot vorhanden') }}</h2>
                            </div>
                        </div>
                        <a href="{{ $canUsePro ? route('paper-depots.index') : route('pricing') }}" class="mt-3 text-[10px] font-black text-orange-400 hover:text-orange-200">{{ $canUsePro ? __('Depot einrichten') : __('Pro entdecken') }} →</a>
                    </article>
                @endif
                    <article data-dashboard-card="personal" data-dashboard-size="{{ $dashboardCardSize('personal') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('personal') }}" class="dashboard-bento-personal ak-card ak-dashboard-card shrink-0 overflow-hidden p-4 {{ $dashboardCardVisible('personal') ? '' : 'hidden' }}">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-9 w-9 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-squares-2x2 class="h-4.5 w-4.5" /></span>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Persönlicher Bereich') }}</p>
                                    <h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Überblick') }}</h2>
                                </div>
                            </div>
                            @if ($canUsePro)
                                <button type="button" data-dashboard-layout-open class="grid h-9 w-9 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/[.08] text-orange-400 transition hover:border-orange-300/50 hover:bg-orange-400/[.16]" title="{{ __('Persönlichen Bereich anpassen') }}" aria-label="{{ __('Persönlichen Bereich anpassen') }}"><x-heroicon-o-cog-6-tooth class="h-4.5 w-4.5" /></button>
                            @else
                                <a href="{{ route('pricing') }}" class="relative grid h-9 w-9 place-items-center rounded-lg border border-slate-500/25 bg-slate-500/[.06] text-slate-500 transition hover:border-amber-400/40 hover:text-amber-300" title="{{ __('Dashboard-Konfiguration ab Pro') }}" aria-label="{{ __('Dashboard-Konfiguration ab Pro') }}"><x-heroicon-o-cog-6-tooth class="h-4.5 w-4.5" /><span class="absolute -right-2 -top-2 rounded border border-amber-400/35 bg-[var(--ak-card)] px-1 py-0.5 text-[6px] font-black text-amber-300">PRO</span></a>
                            @endif
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ([
                                ['paper-depots', __('Musterdepots'), $overview['paper_depots'], 'heroicon-o-beaker', route('paper-depots.index'), true, null],
                                ['watchlists', __('Watchlists'), $overview['watchlists'], 'heroicon-o-star', route('watchlists.index'), true, null],
                                ['strategies', __('Strategien'), $overview['strategies'], 'heroicon-o-adjustments-horizontal', route('setup.saved-filters.index'), $canUsePro, 'PRO'],
                                ['labels', __('Labels'), $overview['labels'], 'heroicon-o-tag', route('setup.quality'), $canUsePlus, 'PLUS'],
                                ['news', __('News · 24h'), $overview['news'], 'heroicon-o-newspaper', route('news.index', ['days' => 1]), true, null],
                            ] as [$tileId, $label, $count, $icon, $url, $allowed, $requiredPlan])
                                <a href="{{ $allowed ? $url : route('pricing') }}" data-dashboard-tile="{{ $tileId }}" data-dashboard-tile-label="{{ $label }}" class="group relative min-w-0 rounded-xl border border-orange-400/20 bg-orange-400/[.045] px-3 py-3 transition {{ $dashboardTileVisible($tileId) && !($tileId === 'news' && !$companyNewsEnabled) ? '' : 'hidden' }} {{ $allowed ? 'hover:border-orange-300/45 hover:bg-orange-400/[.10]' : 'dashboard-plan-locked' }}" title="{{ $allowed ? $label : __('Ab :plan verfügbar', ['plan' => $requiredPlan]) }}">
                                    @unless($allowed)<span class="dashboard-plan-mini-badge">{{ $requiredPlan }}</span>@endunless
                                    <span class="flex items-center gap-2">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400 group-hover:border-orange-200/45"><x-dynamic-component :component="$icon" class="h-4 w-4" /></span>
                                        <b class="text-lg font-black tabular-nums text-[var(--ak-text)]">{{ number_format($count, 0, ',', '.') }}</b>
                                    </span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </a>
                            @endforeach
                            <button type="button" data-dashboard-tile="reminders" data-dashboard-tile-label="{{ __('E-Mail-Erinnerungen') }}" @if($canManageMessages) data-message-settings-open @endif class="group relative min-w-0 rounded-xl border border-orange-400/20 bg-orange-400/[.045] px-3 py-3 text-left transition {{ $dashboardTileVisible('reminders') ? '' : 'hidden' }} {{ $canManageMessages ? 'hover:border-orange-300/45 hover:bg-orange-400/[.10]' : 'cursor-not-allowed opacity-60' }}" title="{{ $canManageMessages ? __('Nachrichten verwalten') : __('Ab Pro verfügbar') }}">
                                @unless($canManageMessages)<span class="ak-plan-badge ak-plan-badge--pro absolute right-2 top-2">PRO</span>@endunless
                                <span class="flex items-center gap-2">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-envelope class="h-4 w-4" /></span>
                                    <b class="text-lg font-black tabular-nums text-[var(--ak-text)]">{{ $messageReminders->where('active', true)->count() }}</b>
                                </span>
                                <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('E-Mail-Erinnerungen') }}</small>
                            </button>
                            <a href="{{ route('profile.mobile-view') }}" data-dashboard-tile="mobile-view" data-dashboard-tile-label="{{ __('Mobile Ansicht') }}" class="group relative min-w-0 rounded-xl border border-cyan-400/20 bg-cyan-400/[.045] px-3 py-3 transition hover:border-cyan-300/45 hover:bg-cyan-400/[.10] {{ $dashboardTileVisible('mobile-view') ? '' : 'hidden' }}">
                                <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-device-phone-mobile class="h-4 w-4" /></span><x-heroicon-o-arrow-up-right class="ml-auto h-4 w-4 text-cyan-300" /></span>
                                <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Mobile Ansicht') }}</small>
                            </a>
                            @if ($topStockToday)
                                @php $topStockScore = \App\Support\AiScore::toTen(is_numeric($topStockToday->ai_score) ? $topStockToday->ai_score : $topStockToday->prediction_score); @endphp
                                <a href="{{ route('stocks.show', ['symbol' => $topStockToday->symbol, 'prediction' => $topStockToday->prediction_id, 'return_to' => '/dashboard']) }}" data-dashboard-tile="best-buy" data-dashboard-tile-label="{{ __('Beste BUY-Aktie') }}" class="group min-w-0 rounded-xl border border-emerald-400/25 bg-emerald-400/[.055] px-3 py-3 transition hover:border-emerald-300/50 hover:bg-emerald-400/[.11] {{ $dashboardTileVisible('best-buy') ? '' : 'hidden' }}" title="{{ $topStockToday->name }}">
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-emerald-400/25 bg-emerald-400/10 text-emerald-400"><x-heroicon-o-trophy class="h-4 w-4" /></span><span class="min-w-0"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $topStockToday->symbol }}</b><small class="block text-[8px] font-black text-emerald-400">{{ $topStockScore !== null ? number_format($topStockScore, 1, ',', '.').'/10' : '—' }}</small></span></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Beste BUY-Aktie') }}</small>
                                </a>
                            @else
                                <div data-dashboard-tile="best-buy" data-dashboard-tile-label="{{ __('Beste BUY-Aktie') }}" class="min-w-0 rounded-xl border border-orange-400/15 bg-orange-400/[.025] px-3 py-3 opacity-70 {{ $dashboardTileVisible('best-buy') ? '' : 'hidden' }}"><span class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg border border-orange-400/20 text-orange-400"><x-heroicon-o-trophy class="h-4 w-4" /></span><b class="text-lg text-[var(--ak-muted)]">—</b></span><small class="mt-2 block truncate text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Beste BUY-Aktie') }}</small></div>
                            @endif
                            @php
                                $topWaitScore = $topWaitStock ? \App\Support\AiScore::toTen(is_numeric($topWaitStock->ai_score) ? $topWaitStock->ai_score : $topWaitStock->prediction_score) : null;
                            @endphp
                            @if ($topWaitStock && $canManageMessages)
                                <a href="{{ route('stocks.show', ['symbol' => $topWaitStock->symbol, 'prediction' => $topWaitStock->prediction_id, 'return_to' => '/dashboard']) }}" data-dashboard-tile="best-wait" data-dashboard-tile-label="{{ __('Beste WAIT-Aktie') }}" class="group min-w-0 rounded-xl border border-amber-400/25 bg-amber-400/[.055] px-3 py-3 transition hover:border-amber-300/50 hover:bg-amber-400/[.11] {{ $dashboardTileVisible('best-wait') ? '' : 'hidden' }}" title="{{ $topWaitStock->name }}">
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-amber-400/25 bg-amber-400/10 text-amber-400"><x-heroicon-o-clock class="h-4 w-4" /></span><span class="min-w-0"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $topWaitStock->symbol }}</b><small class="block text-[8px] font-black text-amber-400">{{ $topWaitScore !== null ? number_format($topWaitScore, 1, ',', '.').'/10' : '—' }}</small></span></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Beste WAIT-Aktie') }}</small>
                                </a>
                            @else
                                <div data-dashboard-tile="best-wait" data-dashboard-tile-label="{{ __('Beste WAIT-Aktie') }}" class="relative min-w-0 rounded-xl border border-slate-500/20 bg-slate-500/[.035] px-3 py-3 opacity-55 {{ $dashboardTileVisible('best-wait') ? '' : 'hidden' }}" title="{{ $canManageMessages ? __('Keine WAIT-Aktie verfügbar') : __('Ab Pro verfügbar') }}">
                                    @unless($canManageMessages)<span class="ak-plan-badge ak-plan-badge--pro absolute right-2 top-2">PRO</span>@endunless
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-slate-500/25 text-slate-400"><x-heroicon-o-clock class="h-4 w-4" /></span><span class="min-w-0"><b class="block truncate text-sm font-black text-slate-400">{{ $topWaitStock?->symbol ?: '—' }}</b><small class="block text-[8px] font-black text-slate-500">{{ $topWaitScore !== null ? number_format($topWaitScore, 1, ',', '.').'/10' : '—' }}</small></span></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-slate-500">{{ __('Beste WAIT-Aktie') }}</small>
                                </div>
                            @endif
                            @foreach ([
                                ['watchlist-screener', __('Watchlist im Screener'), 'heroicon-o-funnel', route('screener.index', ['source' => 'watchlist'])],
                                ['predictions', __('Prognosetabelle'), 'heroicon-o-table-cells', route('predictions.index')],
                                ['smart-screener', __('Smart Screener'), 'heroicon-o-magnifying-glass', route('screener.index')],
                                ['market-report', __('Aktuelle Marktlage'), 'heroicon-o-globe-europe-africa', route('daily-market-analysis')],
                                ['stock-comparison', __('Aktien vergleichen'), 'heroicon-o-scale', route('stocks.compare')],
                            ] as [$tileId, $label, $icon, $url])
                                <a href="{{ $url }}" data-dashboard-tile="{{ $tileId }}" data-dashboard-tile-label="{{ $label }}" class="group min-w-0 rounded-xl border border-cyan-400/20 bg-cyan-400/[.045] px-3 py-3 transition hover:border-cyan-300/45 hover:bg-cyan-400/[.10] {{ $dashboardTileVisible($tileId) ? '' : 'hidden' }}">
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-dynamic-component :component="$icon" class="h-4 w-4" /></span><x-heroicon-o-arrow-up-right class="ml-auto h-4 w-4 text-cyan-300" /></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </a>
                            @endforeach
                        </div>
                    </article>
                    <a href="{{ route('community.index') }}" data-dashboard-card="community" data-dashboard-size="{{ $dashboardCardSize('community') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('community') }}" class="dashboard-bento-community ak-card ak-dashboard-card block shrink-0 overflow-hidden border-orange-400/35 p-4 transition hover:border-orange-200/60 {{ $dashboardCardVisible('community') ? '' : 'hidden' }}">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-9 w-9 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400">
                                    <x-heroicon-o-user-group class="h-4.5 w-4.5" />
                                </span>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Community') }}</p>
                                    <h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Seit deinem letzten Login') }}</h2>
                                </div>
                            </div>
                            <x-heroicon-o-arrow-up-right class="h-4 w-4 text-orange-400" />
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            @foreach ([
                                [__('Beiträge'), $communityOverview['posts'], 'heroicon-o-document-text'],
                                [__('Mitglieder'), $communityOverview['members'], 'heroicon-o-user-group'],
                                [__('Letzte 7 Tage'), $communityOverview['recent'], 'heroicon-o-clock'],
                                [__('News'), $communityOverview['news'], 'heroicon-o-newspaper'],
                            ] as [$label, $count, $icon])
                                <div class="rounded-xl border border-orange-400/15 bg-orange-400/[.04] px-3 py-3 {{ $label === __('News') && !$companyNewsEnabled ? 'hidden' : '' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/15 bg-orange-400/[.07]"><x-dynamic-component :component="$icon" class="h-4 w-4 text-orange-400" /></span>
                                        <b class="text-lg font-black tabular-nums text-[var(--ak-text)]">{{ $count }}</b>
                                    </span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </div>
                            @endforeach
                        </div>
                        @if (($communityOverview['posts'] + $communityOverview['members']) === 0)
                            <p class="mt-2 rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-2.5 py-2 text-[9px] text-[var(--ak-muted)]">{{ __('Noch keine Community-Daten vorhanden.') }}</p>
                        @endif
                    </a>
                </div>

                <article data-dashboard-card="market" data-dashboard-size="{{ $dashboardCardSize('market') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('market') }}" class="dashboard-bento-market ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/40 p-4 sm:col-span-2 {{ $dashboardMarketVisible ? '' : 'hidden' }}">
                    @php
                        $marketFactors = collect($marketFactorSnapshot['current'] ?? []);
                        $globalMarketFactor = $marketFactors->get('market');
                        $marketFactorHistory = collect($marketFactorSnapshot['history'] ?? []);
                        $factorBadge = function ($value): array {
                            if (!is_numeric($value)) return ['—', 'Berechnung ausstehend', 'border-slate-400/20 text-[var(--ak-muted)]'];
                            $score = (float) $value;
                            return match (true) {
                                $score >= 75 => ['↗↗', number_format($score, 0, ',', '.'), 'border-emerald-400/35 text-emerald-300'],
                                $score >= 60 => ['↗', number_format($score, 0, ',', '.'), 'border-green-400/35 text-green-300'],
                                $score >= 40 => ['→', number_format($score, 0, ',', '.'), 'border-amber-400/35 text-amber-300'],
                                $score >= 25 => ['↘', number_format($score, 0, ',', '.'), 'border-rose-400/35 text-rose-300'],
                                default => ['↘↘', number_format($score, 0, ',', '.'), 'border-red-500/40 text-red-300'],
                            };
                        };
                        $sparklinePoints = function (string $field) use ($marketFactorHistory): string {
                            $values = $marketFactorHistory->pluck($field)->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->values();
                            if ($values->count() < 2) return '';
                            $minimum = (float) $values->min();
                            $maximum = (float) $values->max();
                            $range = max(1.0, $maximum - $minimum);
                            $lower = $minimum - ($range * .18);
                            $upper = $maximum + ($range * .18);
                            return $values->map(function (float $value, int $index) use ($values, $lower, $upper): string {
                                $x = $index * (100 / max(1, $values->count() - 1));
                                $y = 28 - ((($value - $lower) / max(.01, $upper - $lower)) * 26);
                                return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
                            })->implode(' ');
                        };
                    @endphp
                    <section class="dashboard-market-indicator mb-3 shrink-0 rounded-xl border border-cyan-500/35 bg-transparent p-2.5" style="border-color:rgba(34,211,238,.28) !important;">
                        <div class="mb-2 flex items-center justify-between gap-2"><h3 class="text-[10px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Marktindikator') }}</h3><small class="text-[7px] font-bold text-[var(--ak-muted)]">{{ __('Trend & Stimmung') }} · 14 {{ __('Tage') }}</small></div>
                        @php $trendBadge = $factorBadge($globalMarketFactor?->trend_score); $timingBadge = $factorBadge($globalMarketFactor?->timing_score); @endphp
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex items-center justify-between rounded-lg border border-cyan-500/30 bg-transparent px-2.5 py-1.5"><small class="dashboard-market-badge-label text-[8px] font-black uppercase text-slate-700">{{ __('Globaler Trend') }}</small><span class="dashboard-market-direction inline-flex min-h-9 min-w-16 items-center justify-center rounded-lg border-2 bg-transparent px-3 text-xl font-black leading-none drop-shadow-[0_0_7px_currentColor] dark:min-h-8 dark:min-w-14 dark:border dark:px-2 dark:text-base dark:drop-shadow-none {{ $trendBadge[2] }}" title="{{ __('Trendwert') }}: {{ $trendBadge[1] }}" aria-label="{{ __('Trendwert') }} {{ $trendBadge[1] }}">{{ $trendBadge[0] }}</span></div>
                            <div class="flex items-center justify-between rounded-lg border border-cyan-500/30 bg-transparent px-2.5 py-1.5"><small class="dashboard-market-badge-label text-[8px] font-black uppercase text-slate-700">{{ __('Globale Stimmung') }}</small><span class="dashboard-market-direction inline-flex min-h-9 min-w-16 items-center justify-center rounded-lg border-2 bg-transparent px-3 text-xl font-black leading-none drop-shadow-[0_0_7px_currentColor] dark:min-h-8 dark:min-w-14 dark:border dark:px-2 dark:text-base dark:drop-shadow-none {{ $timingBadge[2] }}" title="{{ __('Stimmungswert') }}: {{ $timingBadge[1] }}" aria-label="{{ __('Stimmungswert') }} {{ $timingBadge[1] }}">{{ $timingBadge[0] }}</span></div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ([['trend_score', __('Trendverlauf'), '#0891b2'], ['timing_score', __('Stimmungsverlauf'), '#d97706']] as [$field, $label, $stroke])
                                @php $points = $sparklinePoints($field); @endphp
                                <div class="rounded-lg border border-cyan-500/30 bg-transparent px-2 py-1"><div class="flex items-center justify-between"><small class="dashboard-market-badge-label text-[8px] font-black uppercase text-slate-700">{{ $label }}</small><small class="text-[7px] font-bold text-slate-500 dark:text-slate-400">14T</small></div><svg viewBox="0 0 100 30" preserveAspectRatio="none" class="mt-0.5 h-9 w-full overflow-visible" aria-label="{{ $label }}"><line x1="0" y1="7.5" x2="100" y2="7.5" stroke="rgba(100,116,139,.18)" stroke-dasharray="2 2" /><line x1="0" y1="15" x2="100" y2="15" stroke="rgba(100,116,139,.28)" stroke-dasharray="2 2" /><line x1="0" y1="22.5" x2="100" y2="22.5" stroke="rgba(100,116,139,.18)" stroke-dasharray="2 2" />@if($points)<polyline points="{{ $points }}" fill="none" stroke="{{ $stroke }}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />@else<text x="50" y="18" text-anchor="middle" fill="#64748b" font-size="7">{{ __('Noch keine Daten') }}</text>@endif</svg></div>
                            @endforeach
                        </div>
                    </section>
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-orange-400/15 bg-transparent p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-orange-400/30 bg-orange-400/10 text-orange-400">
                                <x-heroicon-o-globe-europe-africa class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Aktuelle Marktlage') }}</p>
                                <h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ $marketSituation?->headline ?: __('Marktüberblick') }}</h2>
                            </div>
                        </div>
                        @if ($marketSituation?->analysis_date)
                            <time class="shrink-0 text-[8px] font-bold tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($marketSituation->analysis_date)->format('d.m.Y') }}</time>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach ([
                            [__('Ausblick'), $marketSituation?->market_outlook ? __($marketSituation->market_outlook) : '—', 'text-orange-400'],
                            [__('Konfidenz'), is_numeric($marketSituation?->confidence) ? number_format((float) $marketSituation->confidence, 0, ',', '.').' %' : '—', 'text-[var(--ak-text)]'],
                            [__('Risiko'), $marketSituation?->risk_level ? __($marketSituation->risk_level) : '—', $marketSituation?->risk_level === 'high' ? 'text-rose-300' : 'text-amber-300'],
                        ] as [$label, $value, $class])
                            <div class="rounded-lg border border-orange-400/15 bg-transparent px-2.5 py-2">
                                <small class="block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                <b class="mt-1 block truncate text-xs {{ $class }}">{{ $value }}</b>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">
                        {{ $marketSituation?->executive_summary ?: __('Noch keine aktuelle Marktanalyse verfügbar.') }}
                    </p>
                    <a href="{{ route('daily-market-analysis') }}" class="mt-auto inline-flex items-center gap-1 pt-3 text-[10px] font-black text-orange-400 hover:text-orange-200">
                        {{ __('Vollständiger Bericht') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                    </a>
                    </section>
                </article>

                <article data-dashboard-card="signal-cockpit" data-dashboard-width="1" data-dashboard-height="6" data-dashboard-size="6" style="--dashboard-card-order:{{ $dashboardCardOrder('signal-cockpit') }}" class="dashboard-bento-signal-cockpit ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-cyan-400/35 p-4 {{ $dashboardCardVisible('signal-cockpit') ? '' : 'hidden' }}">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-signal class="h-5 w-5" /></span><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-300">PRO · {{ __('Letzte 30 Handelstage') }}</p><h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ __('Signal-Cockpit') }}</h2></div></div>
                        <a href="{{ route('predictions.index') }}" class="text-[9px] font-black text-cyan-300">{{ __('Alle') }} →</a>
                    </div>
                    <div class="grid min-h-0 flex-1 gap-2" style="grid-template-rows:auto minmax(250px,1fr)">
                        @php
                            $profileUniverseLabel = match($profileUniverseStats['level'] ?? 'balanced') {
                                'defensive' => __('Defensiv'),
                                'opportunity' => __('Chancenorientiert'),
                                'risk' => __('Risk'),
                                default => __('Ausgewogen'),
                            };
                        @endphp
                        <section class="rounded-lg border border-cyan-400/15 bg-cyan-400/[.035] px-2 py-1.5" aria-labelledby="profile-universe-title">
                            <div class="grid grid-cols-[84px_76px_minmax(0,1fr)] items-center gap-2">
                                <div>
                                    <p id="profile-universe-title" class="truncate text-[9px] font-black uppercase tracking-[.08em] text-cyan-300">{{ __('Aktives Universum') }}</p>
                                    <div class="mt-0.5 flex items-end gap-1"><strong class="text-xl font-black leading-none tabular-nums text-[var(--ak-text)]">{{ number_format((int) ($profileUniverseStats['active_count'] ?? 0), 0, ',', '.') }}</strong><span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Aktien') }}</span></div>
                                    <p class="mt-1 truncate text-[8px] font-black uppercase tracking-wide text-cyan-400">{{ $profileUniverseLabel }} · Ø {{ is_numeric($profileUniverseStats['average_score'] ?? null) ? number_format($profileUniverseStats['average_score'], 1, ',', '.') : '—' }}</p>
                                </div>
                                <div class="rounded-md border border-amber-400/25 bg-amber-400/[.07] px-1.5 py-1 text-center" title="{{ __('Mindestens drei Prognosehorizonte und der KI-Score bewegen sich gemeinsam in Richtung eines neuen Signals.') }}">
                                    <p class="truncate text-[8px] font-black uppercase tracking-wide text-amber-500">{{ __('Wechsel nah') }}</p>
                                    <strong class="block text-lg font-black leading-5 tabular-nums text-[var(--ak-text)]">{{ $profileUniverseStats['transition_candidates'] ?? 0 }}</strong>
                                    <div class="mt-0.5 flex items-center justify-center gap-1.5 text-[8px] font-black tabular-nums"><span class="text-emerald-500">↑ {{ $profileUniverseStats['transition_to_buy'] ?? 0 }}</span><span class="text-rose-500">↓ {{ $profileUniverseStats['transition_to_sell'] ?? 0 }}</span></div>
                                </div>
                                <div class="min-w-0">
                                    <div class="mb-1 flex items-center justify-between text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ __('KI-Score') }}</span><span>0–10</span></div>
                                    <div class="relative mx-1 h-9 pt-3">
                                        <div class="h-1.5 rounded-full border border-white/10 bg-gradient-to-r from-rose-400 via-amber-300 to-emerald-400 shadow-inner"></div>
                                        @foreach(($profileUniverseStats['bins'] ?? []) as $index => $bin)
                                            @php
                                                $position = 10 + ($index * 20);
                                                $markerTone = ['border-rose-500 bg-rose-100 text-rose-700','border-orange-500 bg-orange-100 text-orange-700','border-amber-500 bg-amber-100 text-amber-800','border-lime-500 bg-lime-100 text-lime-800','border-emerald-500 bg-emerald-100 text-emerald-800'][$index] ?? 'border-cyan-500 bg-cyan-100 text-cyan-800';
                                            @endphp
                                            <span class="absolute top-1 -translate-x-1/2" style="left:{{ $position }}%" title="{{ $bin['label'] }}: {{ $bin['count'] }} {{ __('Aktien') }}">
                                                <span class="grid min-w-[22px] place-items-center rounded-full border px-1 py-0.5 text-[8px] font-black leading-none tabular-nums shadow-sm {{ $markerTone }}">{{ $bin['count'] }}</span>
                                                <span class="mx-auto block h-2 w-px bg-current opacity-50"></span>
                                            </span>
                                        @endforeach
                                        <div class="absolute inset-x-0 bottom-0 flex justify-between text-[8px] font-bold tabular-nums text-[var(--ak-muted)]"><span>0</span><span>2</span><span>4</span><span>6</span><span>8</span><span>10</span></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="min-h-0 overflow-hidden rounded-lg border border-cyan-400/15 bg-transparent p-2.5">
                            <div class="mb-2 flex items-center justify-between gap-2"><h3 class="text-[10px] font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Signalwechsel') }}</h3></div>
                            @php
                                $displaySignalChanges = collect($signalCockpit['signalChanges'])->take(5)->values();
                            @endphp
                            <div class="grid gap-1">
                                <div class="aki-signal-cockpit-grid aki-signal-cockpit-head items-center gap-1.5 border-b border-cyan-400/15 px-2 pb-1 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]" style="display:grid;grid-template-columns:minmax(140px,1.8fr) 36px repeat(4,minmax(0,1fr))">
                                    <span>{{ __('Aktie / Wechsel') }}</span><span>{{ __('Datum') }}</span><span class="text-center text-cyan-300">5T</span><span class="text-center text-cyan-300">10T</span><span class="text-center text-cyan-300">15T</span><span class="text-center text-cyan-300">20T</span>
                                </div>
                                @forelse($displaySignalChanges as $change)
                                    <a href="{{ route('stocks.show', ['symbol' => $change['symbol'], 'prediction' => $change['prediction_id'], 'return_to' => '/dashboard']) }}" title="{{ $change['name'] ?: $change['symbol'] }}" class="aki-signal-cockpit-grid aki-signal-cockpit-row items-center gap-1.5 rounded border border-cyan-400/10 bg-transparent px-2 py-1.5" style="display:grid;grid-template-columns:minmax(140px,1.8fr) 36px repeat(4,minmax(0,1fr))">
                                        <span class="aki-signal-stock min-w-0"><b class="block truncate text-[10px] text-[var(--ak-text)]">{{ $change['name'] ?: $change['symbol'] }}</b><small class="mt-0.5 flex items-center justify-between gap-2 whitespace-nowrap text-[8px] font-black text-[var(--ak-muted)]"><span>{{ $change['from'] }} → <span class="text-cyan-300">{{ $change['to'] }}</span></span><time class="aki-signal-date-mobile hidden tabular-nums">{{ \Illuminate\Support\Carbon::parse($change['at'])->format(app()->getLocale() === 'en' ? 'm/d' : 'd.m.') }}</time></small></span>
                                        <time class="aki-signal-date whitespace-nowrap text-[9px] tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($change['at'])->format(app()->getLocale() === 'en' ? 'm/d' : 'd.m.') }}</time>
                                        @foreach([5, 10, 15, 20] as $days)
                                            @php $forecast = $change['horizons'][$days] ?? null; @endphp
                                            <span data-forecast-tone="{{ $forecast === null ? 'empty' : ($forecast >= 0 ? 'positive' : 'negative') }}" class="aki-signal-forecast-badge whitespace-nowrap rounded border px-1 py-0.5 text-center text-[8px] font-black tabular-nums {{ $forecast === null ? 'border-slate-500/20 text-[var(--ak-muted)]' : ($forecast >= 0 ? 'border-emerald-400/20 bg-emerald-400/[.06] text-emerald-300' : 'border-rose-400/20 bg-rose-400/[.06] text-rose-300') }}">{{ $forecast === null ? '—' : (($forecast >= 0 ? '+' : '').number_format($forecast, 1, ',', '.').'%') }}</span>
                                        @endforeach
                                    </a>
                                @empty <small class="text-[10px] text-[var(--ak-muted)]">{{ __('Keine Wechsel in den letzten 30 Handelstagen.') }}</small> @endforelse
                            </div>
                        </section>
                        <section class="aki-chartview-panel mt-6 min-h-0 overflow-hidden rounded-lg border border-emerald-400/15 bg-emerald-400/[.035] px-2 py-1.5">
                            <div class="mb-1.5 flex items-center justify-between gap-2"><h3 class="truncate text-[10px] font-black uppercase tracking-[.12em] text-cyan-300">ChartView · {{ __('Top 5 steigende Kurse') }}</h3><a href="{{ route('predictions.chartview-signals') }}" class="shrink-0 text-[9px] font-black text-cyan-300">{{ __('Tabelle') }} →</a></div>
                            <div class="grid gap-0">
                                <div class="gap-1 border-b border-emerald-400/10 pb-1 text-[8px] font-black uppercase leading-4 text-[var(--ak-muted)]" style="display:grid;grid-template-columns:50px minmax(0,1fr) 58px"><span>{{ __('Aktie') }}</span><span>{{ __('Signal') }}</span><span class="text-right">{{ __('3J · steigt') }}</span></div>
                                @forelse(array_slice($signalCockpit['indicatorSignals'], 0, 5) as $event)
                                    <a href="{{ route('stocks.show', ['symbol' => $event['symbol'], 'prediction' => $event['prediction_id'], 'return_to' => '/dashboard']) }}" title="{{ __('Datenbasis') }}: {{ __($event['probability_scope']) }} · {{ __('Historische Stichprobe') }}: {{ number_format($event['sample_size'], 0, ',', '.') }}" class="items-center gap-1 border-b border-emerald-400/[.06] py-1 text-[9px] leading-4 last:border-0" style="display:grid;grid-template-columns:50px minmax(0,1fr) 58px"><b class="truncate text-[var(--ak-text)]">{{ $event['symbol'] }}</b><span class="truncate text-[var(--ak-muted)]">{{ __($event['label']) }}</span><span class="text-right font-black tabular-nums text-cyan-300">{{ is_numeric($event['rise_probability']) ? number_format($event['rise_probability'], 1, ',', '.').' % ↑' : '—' }}</span></a>
                                @empty <small class="text-[8px] text-[var(--ak-muted)]">{{ __('Keine wichtigen Indikatorwechsel in den letzten drei Handelstagen.') }}</small> @endforelse
                            </div>
                        </section>
                    </div>
                </article>

                <div class="contents sm:col-span-2">
                <article data-dashboard-card="models" data-dashboard-size="{{ $dashboardCardSize('models') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('models') }}" class="dashboard-bento-models ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/40 p-4 {{ $dashboardCardVisible('models') ? '' : 'hidden' }}">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-orange-400/30 bg-orange-400/10 text-orange-400"><x-heroicon-o-chart-bar-square class="h-5 w-5" /></span>
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-orange-400">{{ __('Globale Modellläufe') }}</p>
                                <h2 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Letzte Prognosen') }}</h2>
                            </div>
                        </div>
                        <a href="{{ route('predictions.index') }}" class="text-xs font-black text-orange-400 hover:text-orange-200">{{ __('Alle') }} →</a>
                    </div>

                    <div class="grid min-h-0 flex-1 gap-1.5">
                        @foreach ($continentPredictions as $continent)
                            <div class="aki-model-run-row grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-2 rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-2.5 py-2">
                                <span class="grid h-7 w-7 place-items-center rounded-md border border-orange-400/15 bg-orange-400/[.06] text-orange-400">
                                    <x-continent-icon :continent="$continent['key']" class="h-5 w-5" />
                                </span>
                                <span class="aki-model-run-meta min-w-0">
                                    <span class="flex items-center gap-2"><b class="truncate text-base text-[var(--ak-text)]">{{ $continent['label'] }}</b><small class="text-xs font-black tabular-nums text-orange-400">{{ number_format($continent['count'], 0, ',', '.') }}</small></span>
                                    <time class="block text-[11px] tabular-nums text-[var(--ak-muted)]">{{ $continent['latest_at'] ? \Illuminate\Support\Carbon::parse($continent['latest_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') : '—' }}</time>
                                </span>
                                <span class="aki-model-run-signals flex items-center gap-1 text-[10px] font-black tabular-nums">
                                    <a href="{{ route('predictions.index', ['signal' => 'BUY']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-cyan-400/20 bg-cyan-400/[.10] px-1 py-1 text-cyan-300 transition hover:border-cyan-300/60 hover:bg-cyan-400/20" title="{{ __('BUY-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('BUY-Aktien in der Prognosetabelle anzeigen') }}">B {{ $continent['buy'] }}</a>
                                    <a href="{{ route('predictions.index', ['signal' => 'WATCH']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-amber-400/25 bg-amber-400/[.10] px-1 py-1 text-amber-300 transition hover:border-amber-300/60 hover:bg-amber-400/20" title="{{ __('WATCH-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('WATCH-Aktien in der Prognosetabelle anzeigen') }}">W {{ $continent['watch'] }}</a>
                                    <a href="{{ route('predictions.index', ['signal' => 'HOLD']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-slate-400/25 bg-slate-400/[.10] px-1 py-1 text-slate-300 transition hover:border-slate-300/60 hover:bg-slate-400/20" title="{{ __('HOLD-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('HOLD-Aktien in der Prognosetabelle anzeigen') }}">H {{ $continent['hold'] }}</a>
                                    <a href="{{ route('predictions.index', ['signal' => 'SELL']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-rose-400/25 bg-rose-400/[.10] px-1 py-1 text-rose-300 transition hover:border-rose-300/60 hover:bg-rose-400/20" title="{{ __('SELL-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('SELL-Aktien in der Prognosetabelle anzeigen') }}">S {{ $continent['sell'] }}</a>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </article>
                <div class="dashboard-right-column min-h-0">
                <article data-dashboard-card="signals" data-dashboard-size="{{ $dashboardCardSize('signals') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('signals') }}" class="dashboard-bento-signals ak-card ak-dashboard-card flex min-h-[250px] flex-1 flex-col overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('signals') ? '' : 'hidden' }}">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Letzte 48 Stunden') }}</p>
                            <h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Empfehlungen & Signalübergänge') }}</h2>
                        </div>
                        <x-heroicon-o-arrow-path-rounded-square class="h-5 w-5 text-orange-400" />
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <a href="{{ route('predictions.index', ['signal' => 'BUY']) }}" class="rounded-lg border border-orange-400/20 bg-orange-400/[.06] p-2.5 transition hover:bg-orange-400/[.11]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-orange-400">{{ __('BUY') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['buy_count'] }}</b></span>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ __('Neue Kaufempfehlungen') }} · {{ implode(' · ', $recentSignalOverview['buy_symbols']) ?: __('Keine neuen Signale') }}</small>
                        </a>
                        <a href="{{ route('predictions.index', ['signal' => 'WAIT']) }}" class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.06] p-2.5 transition hover:bg-cyan-400/[.11]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-cyan-300">{{ __('WAIT') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['wait_count'] }}</b></span>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ __('Neue WAIT-Signale') }} · {{ implode(' · ', $recentSignalOverview['wait_symbols']) ?: __('Keine neuen Signale') }}</small>
                        </a>
                        <a href="{{ route('predictions.index', ['signal' => 'SELL']) }}" class="rounded-lg border border-rose-400/20 bg-rose-400/[.06] p-2.5 transition hover:bg-rose-400/[.11]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-rose-300">{{ __('SELL') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['sell_count'] }}</b></span>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ __('Neue Verkaufssignale') }} · {{ implode(' · ', $recentSignalOverview['sell_symbols']) ?: __('Keine neuen Signale') }}</small>
                        </a>
                        <a href="{{ route('predictions.index', ['signal' => 'HOLD']) }}" class="rounded-lg border border-slate-400/20 bg-slate-400/[.06] p-2.5 transition hover:bg-slate-400/[.11]">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-[9px] font-black uppercase text-slate-300">{{ __('HOLD') }}</span>
                                <b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['hold_count'] }}</b>
                            </div>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ __('Neue Haltesignale') }} · {{ implode(' · ', $recentSignalOverview['hold_symbols']) ?: __('Keine neuen Signale') }}</small>
                        </a>
                    </div>
                </article>
                <article data-dashboard-card="earnings" data-dashboard-width="1" data-dashboard-height="6" data-dashboard-size="6" style="--dashboard-card-order:{{ $dashboardCardOrder('earnings') }}" class="dashboard-bento-earnings ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-emerald-400/30 p-4 {{ $dashboardCardVisible('earnings') ? '' : 'hidden' }}">
                    @php
                        $earningsAbove = $recentEarnings->filter(fn ($earning) => is_numeric($earning->surprise_percent) && (float) $earning->surprise_percent >= 0)->count();
                        $earningsBelow = $recentEarnings->filter(fn ($earning) => is_numeric($earning->surprise_percent) && (float) $earning->surprise_percent < 0)->count();
                        $earningsAverage = $recentEarnings->filter(fn ($earning) => is_numeric($earning->surprise_percent))->avg(fn ($earning) => (float) $earning->surprise_percent);
                    @endphp
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-emerald-400/25 bg-emerald-400/10 text-emerald-300"><x-heroicon-o-presentation-chart-line class="h-4.5 w-4.5" /></span><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.16em] text-emerald-300">{{ __('Fundamentaldaten') }}</p><h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ __('Aktuelle Quartalszahlen') }}</h2></div></div>
                    </div>
                    <div class="mb-2 grid grid-cols-3 gap-1.5">
                        <div class="rounded-lg border border-emerald-400/20 bg-emerald-400/[.06] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Über Erwartung') }}</small><b class="text-sm tabular-nums text-emerald-300">{{ $earningsAbove }}</b></div>
                        <div class="rounded-lg border border-rose-400/20 bg-rose-400/[.05] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Unter Erwartung') }}</small><b class="text-sm tabular-nums text-rose-300">{{ $earningsBelow }}</b></div>
                        <div class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.05] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">Ø {{ __('Überraschung') }}</small><b class="text-sm tabular-nums {{ $earningsAverage === null ? 'text-[var(--ak-muted)]' : ($earningsAverage >= 0 ? 'text-emerald-300' : 'text-rose-300') }}">{{ $earningsAverage === null ? '—' : (($earningsAverage >= 0 ? '+' : '').number_format($earningsAverage, 1, ',', '.').'%') }}</b></div>
                    </div>
                    <div class="grid min-h-0 flex-1 grid-rows-2 gap-1.5">
                        @forelse($recentEarnings as $earning)
                            @php
                                $surprise = is_numeric($earning->surprise_percent) ? (float) $earning->surprise_percent : null;
                                $difference = is_numeric($earning->eps_actual) && is_numeric($earning->eps_estimate) ? (float) $earning->eps_actual - (float) $earning->eps_estimate : null;
                            @endphp
                            <a href="{{ route('stocks.show', ['symbol' => $earning->symbol, 'return_to' => '/dashboard']) }}" class="flex min-h-0 flex-col justify-center rounded-lg border border-emerald-400/12 bg-emerald-400/[.035] px-2.5 py-2 transition hover:border-emerald-300/35 hover:bg-emerald-400/[.07]">
                                <span class="flex items-start justify-between gap-2"><span class="min-w-0"><b class="block truncate text-[10px] text-[var(--ak-text)]">{{ $earning->name ?: $earning->symbol }}</b><small class="block text-[8px] text-[var(--ak-muted)]">{{ $earning->symbol }} · {{ date('d.m.Y', strtotime((string) $earning->earnings_date)) }}</small></span><span class="shrink-0 rounded-md border px-2 py-1 text-[8px] font-black {{ $surprise === null ? 'border-slate-400/20 text-[var(--ak-muted)]' : ($surprise >= 0 ? 'border-emerald-400/25 bg-emerald-400/[.08] text-emerald-300' : 'border-rose-400/25 bg-rose-400/[.08] text-rose-300') }}">{{ $surprise === null ? __('Ohne Vergleich') : ($surprise >= 0 ? __('ÜBER ERWARTUNG') : __('UNTER ERWARTUNG')) }}</span></span>
                                <span class="mt-2 grid grid-cols-3 gap-1.5 border-t border-emerald-400/10 pt-1.5"><span><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">EPS {{ __('Ist') }}</small><b class="text-[10px] tabular-nums text-[var(--ak-text)]">{{ number_format((float) $earning->eps_actual, 2, ',', '.') }}</b></span><span><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Erwartet') }}</small><b class="text-[10px] tabular-nums text-[var(--ak-muted)]">{{ is_numeric($earning->eps_estimate) ? number_format((float) $earning->eps_estimate, 2, ',', '.') : '—' }}</b></span><span class="text-right"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Abweichung') }}</small><b class="text-[10px] tabular-nums {{ $surprise === null ? 'text-[var(--ak-muted)]' : ($surprise >= 0 ? 'text-emerald-300' : 'text-rose-300') }}">{{ $surprise === null ? '—' : (($surprise >= 0 ? '+' : '').number_format($surprise, 1, ',', '.').'%') }}@if($difference !== null) <small>({{ $difference >= 0 ? '+' : '' }}{{ number_format($difference, 2, ',', '.') }})</small>@endif</b></span></span>
                            </a>
                        @empty
                            <div class="rounded-lg border border-dashed border-emerald-400/20 p-4 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Noch keine aktuellen Quartalszahlen gespeichert.') }}</div>
                        @endforelse
                    </div>
                </article>
                <article data-dashboard-card="market-summary" data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('market-summary') }}" class="dashboard-bento-market-summary ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('market-summary') ? '' : 'hidden' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-globe-europe-africa class="h-4.5 w-4.5" /></span><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Aktuelle Marktlage') }}</p><h2 class="mt-1 truncate text-sm font-black text-[var(--ak-text)]">{{ $marketSituation?->headline ?: __('Noch kein Marktbericht verfügbar') }}</h2></div></div>
                        <time class="shrink-0 text-[8px] font-black tabular-nums text-[var(--ak-muted)]">{{ $marketSituation?->analysis_date ? date('d.m.Y', strtotime((string) $marketSituation->analysis_date)) : '—' }}</time>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-1.5">
                        <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.045] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ausblick') }}</small><b class="text-[10px] uppercase text-cyan-300">{{ $marketSituation?->market_outlook ?: '—' }}</b></div>
                        <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.045] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Konfidenz') }}</small><b class="text-[10px] tabular-nums text-[var(--ak-text)]">{{ is_numeric($marketSituation?->confidence) ? number_format((float) $marketSituation->confidence, 0, ',', '.').' %' : '—' }}</b></div>
                        <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.045] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Risiko') }}</small><b class="text-[10px] uppercase text-amber-300">{{ $marketSituation?->risk_level ?: '—' }}</b></div>
                    </div>
                    <a href="{{ route('daily-market-analysis') }}" class="mt-3 inline-flex items-center gap-1 text-[9px] font-black text-orange-400 hover:text-orange-200">{{ __('Marktbericht öffnen') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" /></a>
                </article>
                </div>
                <article data-dashboard-card="schedule" data-dashboard-size="{{ $dashboardCardSize('schedule') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('schedule') }}" class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('schedule') ? '' : 'hidden' }}">
                    <div class="mb-3 flex items-center justify-between gap-3"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl border border-amber-400/25 bg-amber-400/10 text-amber-300"><x-heroicon-o-calendar-days class="h-5 w-5" /></span><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-amber-300">{{ __('Planung') }}</p><h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Termine & Erinnerungen') }}</h2></div></div><button type="button" data-message-settings-open class="grid h-8 w-8 place-items-center rounded-lg border border-amber-400/25 text-amber-300" title="{{ __('Erinnerungen verwalten') }}"><x-heroicon-o-cog-6-tooth class="h-4 w-4" /></button></div>
                    <div class="grid min-h-0 flex-1 gap-2 overflow-hidden">
                        @forelse ($dashboardScheduleItems->take(4) as $reminder)
                            @php
                                $scheduleEmailPreview = in_array($reminder['type'] ?? null, ['signal', 'prediction'], true);
                                $scheduleDeleteRoute = !$scheduleEmailPreview ? null : (($reminder['type'] ?? null) === 'signal'
                                    ? route('notifications.entry-alerts.destroy', $reminder['id'])
                                    : route('notifications.purchase-reminders.destroy', $reminder['id']));
                                $scheduleRescheduleRoute = ($reminder['type'] ?? null) === 'prediction'
                                    ? route('notifications.purchase-reminders.reschedule', $reminder['id'])
                                    : null;
                            @endphp
                            <div @if($scheduleEmailPreview) role="button" tabindex="0" data-message-preview data-preview-symbol="{{ $reminder['symbol'] }}" data-preview-name="{{ $reminder['name'] }}" data-preview-label="{{ $reminder['label'] }}" data-preview-schedule="{{ $reminder['schedule'] }}" data-preview-date="{{ $reminder['date'] ?? '' }}" data-preview-type="{{ $reminder['type'] }}" data-preview-delete-url="{{ $scheduleDeleteRoute }}" data-preview-reschedule-url="{{ $scheduleRescheduleRoute }}" @endif class="flex items-center gap-2 rounded-lg border border-amber-400/15 bg-amber-400/[.045] px-2.5 py-2 {{ $scheduleEmailPreview ? 'cursor-pointer transition hover:border-cyan-400/45' : '' }}"><span class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-amber-400/10 text-amber-300">@if(($reminder['type'] ?? null) === 'earnings')<x-heroicon-o-chart-bar class="h-3.5 w-3.5" />@else<x-heroicon-o-envelope class="h-3.5 w-3.5" />@endif</span><span class="min-w-0 flex-1"><b class="block truncate text-[10px] text-[var(--ak-text)]">{{ $reminder['symbol'] }} · {{ $reminder['label'] }}</b><small class="block truncate text-[8px] text-[var(--ak-muted)]">{{ $reminder['schedule'] }}</small></span>@if($scheduleEmailPreview)<x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 text-cyan-400" />@endif</div>
                        @empty
                            <div class="grid min-h-24 place-items-center rounded-xl border border-dashed border-amber-400/20 text-center"><span><x-heroicon-o-calendar class="mx-auto h-5 w-5 text-amber-300" /><small class="mt-2 block text-[9px] text-[var(--ak-muted)]">{{ __('Keine bevorstehenden Aktionen oder E-Mails.') }}</small></span></div>
                        @endforelse
                    </div>
                </article>
                <a href="{{ route('profile.mobile-view') }}" data-dashboard-card="mobile-view" data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('mobile-view') }}" class="dashboard-bento-mobile-view ak-card ak-dashboard-card group min-h-[94px] items-center gap-3 overflow-hidden border-cyan-400/30 p-4 transition hover:border-cyan-300/60 {{ $dashboardCardVisible('mobile-view') ? 'flex' : 'hidden' }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-device-phone-mobile class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1"><small class="block text-[9px] font-black uppercase tracking-[.16em] text-cyan-300">{{ __('Mobiles Dashboard') }}</small><b class="mt-1 block text-base text-[var(--ak-text)]">{{ __('Mobile Ansicht') }}</b><small class="mt-1 block truncate text-[9px] text-[var(--ak-muted)]">{{ __('Sichtbare Karten für das Handy festlegen.') }}</small></span>
                    <x-heroicon-o-arrow-right class="h-5 w-5 shrink-0 text-cyan-300 transition group-hover:translate-x-1" />
                </a>
                    </div>
            </section>

        </div>

        @if ($canUsePro)
        <div id="dashboard-cards-modal" class="fixed inset-0 z-[180] hidden place-items-center overflow-y-auto bg-slate-950/75 p-2 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-labelledby="dashboard-cards-title">
            <section class="flex w-full max-w-[1200px] flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl" style="height:min(720px,78vh);max-height:calc(100vh - 1rem)">
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-4 py-3">
                    <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-view-columns class="h-5 w-5" /></span><div><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">PRO · {{ __('12-Spalten-Raster') }}</p><h2 id="dashboard-cards-title" class="mt-1 text-lg font-black">{{ __('Gesamtes Dashboard anpassen') }}</h2></div></div>
                    <button type="button" data-dashboard-cards-close class="grid h-9 w-9 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)]"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div class="min-h-0 flex-1 overscroll-contain overflow-y-auto p-3 sm:p-4">
                    <p class="mb-3 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Ziehe Karten in die gewünschte Reihenfolge, blende sie aus oder wähle eine Größe. Auf Mobilgeräten werden die sichtbaren Karten automatisch untereinander angeordnet.') }}</p>
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(260px,1fr)]">
                        <section class="rounded-xl border border-cyan-400/25 bg-cyan-400/[.035] p-3"><div class="mb-3 flex items-center justify-between border-b border-cyan-400/20 pb-3"><h3 class="text-xs font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Sichtbare Dashboard-Karten') }}</h3><span class="flex items-center gap-3"><small class="text-[8px] font-black uppercase tracking-wide text-cyan-300/70">9 {{ __('Zeilen') }} × 3 {{ __('Spalten') }}</small><span data-dashboard-cards-count class="text-[10px] font-black text-[var(--ak-muted)]"></span></span></div><div data-dashboard-cards-active class="dashboard-card-layout-preview grid min-h-[1072px] gap-2 rounded-lg border border-cyan-400/25 p-2" style="grid-template-columns:repeat(3,minmax(0,1fr));grid-template-rows:repeat(9,112px);grid-auto-flow:row dense;background-image:linear-gradient(to right,rgba(34,211,238,.13) 1px,transparent 1px),linear-gradient(to bottom,rgba(34,211,238,.13) 1px,transparent 1px);background-size:calc(100% / 3) 100%,100% 120px;background-position:8px 8px"></div></section>
                        <section class="rounded-xl border border-slate-400/20 bg-slate-400/[.025] p-3"><h3 class="mb-3 border-b border-slate-400/20 pb-3 text-xs font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Ausgeblendete Karten') }}</h3><div data-dashboard-cards-hidden class="grid min-h-64 gap-2 rounded-lg border border-dashed border-slate-400/20 p-2"></div></section>
                    </div>
                    <p data-dashboard-cards-status class="mt-3 min-h-5 text-[10px] font-bold text-cyan-300"></p>
                </div>
                <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-cyan-400/20 bg-[var(--ak-card)] px-4 py-2.5 shadow-[0_-10px_24px_rgba(0,0,0,.16)]" style="position:sticky;bottom:0;z-index:10"><button type="button" data-dashboard-cards-reset class="rounded-lg border border-[var(--ak-border)] px-3 py-2 text-[10px] font-black text-[var(--ak-muted)]">{{ __('Standardlayout wiederherstellen') }}</button><div class="flex gap-2"><button type="button" data-dashboard-cards-close class="rounded-lg border border-[var(--ak-border)] px-4 py-2 text-xs font-black text-[var(--ak-muted)]">{{ __('Abbrechen') }}</button><button type="button" data-dashboard-cards-save class="rounded-lg bg-cyan-400 px-4 py-2 text-xs font-black text-slate-950 hover:bg-cyan-300">{{ __('Layout speichern') }}</button></div></footer>
            </section>
        </div>
        @endif

        @if ($canUsePro)
        <div id="dashboard-layout-modal" class="fixed inset-0 z-[185] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-layout-title">
            <form method="POST" action="{{ route('dashboard.layout.update') }}" class="flex h-[calc(100vh-2rem)] max-h-[820px] w-full max-w-[1500px] flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl">
                @csrf
                @method('PATCH')
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-5 py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-cog-6-tooth class="h-5 w-5" /></span>
                        <div><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-300">{{ __('Persönlicher Bereich') }}</p><h2 id="dashboard-layout-title" class="mt-1 text-lg font-black">{{ __('Dashboard anpassen') }}</h2></div>
                    </div>
                    <button type="button" data-dashboard-layout-close class="grid h-9 w-9 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)] transition hover:text-[var(--ak-text)]"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div class="min-h-0 flex-1 overscroll-contain overflow-y-auto p-4 sm:p-5">
                    <p class="mb-4 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Ziehe Kacheln zwischen den Bereichen. In der linken Spalte kannst du außerdem ihre Reihenfolge verändern.') }}</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <section class="rounded-xl border border-cyan-400/25 bg-cyan-400/[.035] p-3">
                            <div class="mb-3 flex items-center justify-between border-b border-cyan-400/20 pb-3"><h3 class="text-xs font-black uppercase tracking-[.12em] text-cyan-300">{{ __('Auf deinem Dashboard') }}</h3><span data-dashboard-layout-count class="text-[10px] font-black text-[var(--ak-muted)]"></span></div>
                            <div data-dashboard-layout-active class="dashboard-layout-dropzone grid min-h-48 grid-cols-3 justify-center gap-2 rounded-lg border border-dashed border-cyan-400/20 p-2"></div>
                        </section>
                        <section class="rounded-xl border border-slate-400/20 bg-slate-400/[.025] p-3">
                            <h3 class="mb-3 border-b border-slate-400/20 pb-3 text-xs font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Verfügbare Kacheln') }}</h3>
                            <div data-dashboard-layout-available class="dashboard-layout-dropzone grid min-h-48 grid-cols-3 justify-center gap-2 rounded-lg border border-dashed border-slate-400/20 p-2"></div>
                        </section>
                    </div>
                </div>
                <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-cyan-400/20 bg-[var(--ak-card)] px-5 py-3 shadow-[0_-10px_24px_rgba(0,0,0,.16)]">
                    <div class="flex min-w-0 items-center gap-3"><button type="button" data-dashboard-layout-reset class="shrink-0 rounded-lg border border-[var(--ak-border)] px-3 py-2 text-[10px] font-black text-[var(--ak-muted)] transition hover:text-[var(--ak-text)]">{{ __('Standard wiederherstellen') }}</button><p data-dashboard-layout-status class="min-w-0 text-[10px] font-bold text-cyan-300"></p></div>
                    <div class="flex gap-2"><button type="button" data-dashboard-layout-close class="rounded-lg border border-[var(--ak-border)] px-4 py-2 text-xs font-black text-[var(--ak-muted)]">{{ __('Abbrechen') }}</button><button type="submit" data-dashboard-layout-save class="rounded-lg bg-cyan-400 px-4 py-2 text-xs font-black text-slate-950 transition hover:bg-cyan-300">{{ __('Änderungen speichern') }}</button></div>
                </footer>
            </form>
        </div>
        @endif

        @if ($canManageMessages)
            <div id="message-settings-modal" class="fixed inset-0 z-[190] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="message-settings-title">
                <section class="flex max-h-[min(720px,90dvh)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl">
                    <header class="flex items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-400"><x-heroicon-o-bell class="h-5 w-5" /></span><div><p><span class="ak-plan-badge ak-plan-badge--pro">PRO</span></p><h2 id="message-settings-title" class="mt-1 text-lg font-black">{{ __('Termine & Erinnerungen verwalten') }}</h2></div></div>
                        <button type="button" data-message-settings-close class="grid h-9 w-9 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)]"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </header>
                    <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                        <p class="mb-4 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Lege fest, welche Inhalte in der Dashboard-Karte Termine & Erinnerungen angezeigt werden.') }}</p>
                        <div class="message-reminder-row mb-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-cyan-400/15 bg-cyan-400/[.035] p-3">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><b class="text-sm">{{ __('Unternehmensnachrichten') }}</b><span data-active="{{ $companyNewsEnabled ? 'true' : 'false' }}" class="message-reminder-status rounded-md border px-1.5 py-0.5 text-[8px] font-black {{ $companyNewsEnabled ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-300' : 'border-slate-500/25 bg-slate-500/10 text-slate-400' }}">{{ $companyNewsEnabled ? __('AKTIV') : __('DEAKTIVIERT') }}</span></div><small class="mt-1 block text-[10px] text-[var(--ak-muted)]">{{ __('Unternehmensnews im persönlichen Dashboard anzeigen.') }}</small></div>
                            <div class="message-reminder-actions message-company-news-actions flex items-center gap-2">
                                <form method="POST" action="{{ route('profile.company-news.update') }}">@csrf @method('PATCH')<input type="hidden" name="enabled" value="{{ $companyNewsEnabled ? 0 : 1 }}"><button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[10px] font-black transition {{ $companyNewsEnabled ? 'border-rose-400/25 bg-rose-400/[.07] text-rose-300' : 'border-emerald-400/25 bg-emerald-400/[.08] text-emerald-300' }}">@if($companyNewsEnabled)<x-heroicon-o-eye-slash class="h-4 w-4" />{{ __('Deaktivieren') }}@else<x-heroicon-o-eye class="h-4 w-4" />{{ __('Aktivieren') }}@endif</button></form>
                            </div>
                        </div>
                        <div class="message-reminder-row mb-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-cyan-400/15 bg-cyan-400/[.035] p-3">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><b class="text-sm">{{ __('E-Mails in Termine & Erinnerungen') }}</b><span data-active="{{ $scheduleEmailsEnabled ? 'true' : 'false' }}" class="message-reminder-status rounded-md border px-1.5 py-0.5 text-[8px] font-black {{ $scheduleEmailsEnabled ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-300' : 'border-slate-500/25 bg-slate-500/10 text-slate-400' }}">{{ $scheduleEmailsEnabled ? __('SICHTBAR') : __('AUSGEBLENDET') }}</span></div><small class="mt-1 block text-[10px] text-[var(--ak-muted)]">{{ __('Ändert nur die Anzeige in der Dashboard-Karte. Der E-Mail-Versand bleibt aktiv.') }}</small></div>
                            <div class="message-reminder-actions message-company-news-actions flex items-center gap-2">
                                <form method="POST" action="{{ route('profile.schedule-email-visibility.update') }}">@csrf @method('PATCH')<input type="hidden" name="enabled" value="{{ $scheduleEmailsEnabled ? 0 : 1 }}"><button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[10px] font-black transition {{ $scheduleEmailsEnabled ? 'border-rose-400/25 bg-rose-400/[.07] text-rose-300' : 'border-emerald-400/25 bg-emerald-400/[.08] text-emerald-300' }}">@if($scheduleEmailsEnabled)<x-heroicon-o-eye-slash class="h-4 w-4" />{{ __('Ausblenden') }}@else<x-heroicon-o-eye class="h-4 w-4" />{{ __('Einblenden') }}@endif</button></form>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div id="message-preview-modal" class="fixed inset-0 z-[205] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="message-preview-title">
                <section class="flex max-h-[90dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl">
                    <header class="flex items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-5 py-4">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 text-cyan-400"><x-heroicon-o-envelope-open class="h-5 w-5" /></span><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('E-Mail-Vorschau') }}</p><h2 id="message-preview-title" data-preview-title class="mt-1 truncate text-lg font-black"></h2></div></div>
                        <button type="button" data-message-preview-close class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)]"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </header>
                    <div class="min-h-0 overflow-y-auto p-4 sm:p-5">
                        <dl class="grid grid-cols-[5rem_minmax(0,1fr)] gap-x-3 gap-y-2 text-xs"><dt class="font-black text-[var(--ak-muted)]">{{ __('An') }}</dt><dd class="truncate font-bold">{{ auth()->user()->email }}</dd><dt class="font-black text-[var(--ak-muted)]">{{ __('Betreff') }}</dt><dd data-preview-subject class="font-bold"></dd><dt class="font-black text-[var(--ak-muted)]">{{ __('Zeitpunkt') }}</dt><dd data-preview-schedule class="font-bold"></dd></dl>
                        <form data-message-preview-reschedule method="POST" action="" class="mt-4 hidden items-end gap-2 rounded-xl border border-cyan-400/20 bg-cyan-400/[.035] p-3">@csrf @method('PATCH')<label class="min-w-0 flex-1"><span class="mb-1.5 block text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Termin verschieben') }}</span><input data-message-preview-date type="date" name="remind_on" min="{{ now()->toDateString() }}" required class="ak-input h-10 w-full px-3 text-sm"></label><button type="submit" class="h-10 shrink-0 rounded-lg bg-cyan-500 px-4 text-xs font-black text-slate-950 hover:bg-cyan-400">{{ __('Speichern') }}</button></form>
                        <article class="mt-4 rounded-xl border border-cyan-400/20 bg-cyan-400/[.035] p-4"><p class="text-xs text-[var(--ak-muted)]">{{ __('Hallo :name,', ['name' => auth()->user()->name]) }}</p><p data-preview-body class="mt-3 text-sm leading-6"></p><p class="mt-4 text-xs text-[var(--ak-muted)]">{{ __('Viele Grüße') }}<br><b class="text-[var(--ak-text)]">{{ __('Dein AktienKI-Team') }}</b></p></article>
                    </div>
                    <footer class="flex flex-col gap-3 border-t border-cyan-400/20 px-5 py-4"><p class="text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Beim Löschen wird nur diese einzelne Erinnerung entfernt. Deine übrigen E-Mail-Einstellungen und Benachrichtigungen bleiben unverändert.') }}</p><div class="flex items-center justify-between gap-3"><button type="button" data-message-preview-close class="rounded-lg border border-[var(--ak-border)] px-4 py-2 text-xs font-black text-[var(--ak-muted)]">{{ __('Schließen') }}</button><form data-message-preview-delete data-confirm="{{ __('Nur diese einzelne Erinnerung wird gelöscht. Deine übrigen E-Mail-Einstellungen bleiben unverändert. Möchtest du fortfahren?') }}" method="POST" action="">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-xs font-black text-white hover:bg-rose-500"><x-heroicon-o-trash class="h-4 w-4" />{{ __('Nachricht löschen') }}</button></form></div></footer>
                </section>
            </div>
        @endif

        <div id="dashboard-aki-modal" class="fixed inset-0 z-[200] hidden place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-aki-title">
            <section class="w-full max-w-2xl overflow-hidden rounded-2xl border border-teal-300/45 text-slate-100 shadow-2xl" style="background:linear-gradient(145deg,rgba(14,38,57,.98),rgba(8,25,42,.98)) !important;max-height:calc(100dvh - 2rem);display:flex;flex-direction:column;">
                <header class="flex items-center justify-between border-b border-teal-300/25 px-4 py-3" style="background:linear-gradient(110deg,rgba(6, 182, 212,.18),rgba(245,158,11,.12)) !important;">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-500">{{ __('Assistent') }}</p><h2 id="dashboard-aki-title" class="text-base font-black text-slate-100">{{ __('AKI fragen') }}</h2></div>
                    <button type="button" data-dashboard-aki-close class="rounded-lg p-2 text-slate-300 hover:bg-slate-700/60" aria-label="{{ __('Chat schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div id="dashboard-aki-messages" class="min-h-0 space-y-2 overscroll-contain p-5" style="background:rgba(9,28,45,.82) !important;height:45vh;max-height:500px;overflow-y:scroll;flex:0 1 auto;"><p class="max-w-[92%] rounded-xl border border-teal-300/20 bg-slate-700/70 px-3 py-2 text-xs leading-5 text-slate-100">{{ __('Du kannst zum Beispiel fragen: „Was kannst du mir heute empfehlen?“') }}</p></div>
                <form id="dashboard-aki-form" class="flex gap-2 border-t border-teal-300/20 p-3" style="background:rgba(10,30,47,.96) !important;">
                    <input id="dashboard-aki-input" type="text" class="min-w-0 flex-1 rounded-lg border border-teal-300/30 bg-slate-950/65 px-3 py-2 text-xs text-slate-100 placeholder:text-slate-400" placeholder="{{ __('Was kannst du mir heute empfehlen?') }}" autocomplete="off">
                    <button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-black text-white hover:bg-teal-500">{{ __('Senden') }}</button>
                </form>
            </section>
        </div>
    </main>
    <script>
        (() => {
            const modal = document.getElementById('dashboard-cards-modal');
            const grid = document.querySelector('.dashboard-bento');
            const activeZone = modal?.querySelector('[data-dashboard-cards-active]');
            const hiddenZone = modal?.querySelector('[data-dashboard-cards-hidden]');
            if (!modal || !grid || !activeZone || !hiddenZone) return;
            const labels = @json($dashboardMainCardLabels);
            const descriptions = @json($dashboardMainCardDescriptions);
            const minimumHeights = @json($dashboardMinimumHeights);
            const fixedDimensions = @json($dashboardFixedDimensions);
            const defaults = @json($dashboardCardDefaults);
            let selected = @json($dashboardCards);
            const catalog = new Map([...grid.querySelectorAll('[data-dashboard-card]')].map((card) => [card.dataset.dashboardCard, card]));
            let dragged = null;

            const normalizeConfig = (config) => {
                const legacySize = config.size || 'medium';
                const legacyWidths = { small: 1, medium: 2, large: 3 };
                const legacyHeights = { small: 1, medium: 2, large: 3 };
                const requestedWidth = Number(config.width) || legacyWidths[config.width] || (legacySize === 'large' ? 2 : 1);
                const requestedHeight = Number(config.height) || legacyHeights[config.height] || (legacySize === 'small' ? 1 : 2);
                const minimumHeight = minimumHeights[config.id] || 1;
                const fixed = fixedDimensions[config.id] || null;
                return {
                    ...config,
                    width: fixed?.width || Math.max(1, Math.min(3, requestedWidth)),
                    height: fixed?.height || Math.max(minimumHeight, Math.min(6, requestedHeight)),
                };
            };
            selected = selected.map(normalizeConfig);
            selected.forEach((config) => {
                const card = catalog.get(config.id);
                if (!card) return;
                card.dataset.dashboardWidth = config.width;
                card.dataset.dashboardHeight = config.height;
            });

            const createCard = (config, visible) => {
                const item = document.createElement('div');
                config = normalizeConfig(config);
                const legacySize = config.size || 'medium';
                item.dataset.cardId = config.id;
                item.dataset.cardWidth = config.width;
                item.dataset.cardHeight = config.height;
                item.draggable = true;
                item.className = 'dashboard-card-choice relative min-h-0 cursor-grab overflow-hidden rounded-xl border border-cyan-400/20 bg-cyan-400/[.045] p-3 active:cursor-grabbing';
                item.innerHTML = `<div class="flex items-start gap-2"><span class="text-lg leading-none text-cyan-300">⠿</span><span class="min-w-0 flex-1"><b class="block text-xs"></b><small class="mt-1 block text-[9px] leading-3 text-[var(--ak-muted)]"></small></span><button type="button" data-card-toggle class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/25 text-cyan-300">${visible ? '→' : '←'}</button></div><div class="mt-2 grid gap-1 border-t border-cyan-400/15 pt-2"><div class="flex min-w-0 flex-nowrap items-center gap-1"><span class="w-14 shrink-0 text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Spalten') }}</span><div data-card-widths class="grid min-w-0 flex-1 gap-1"></div></div><div class="flex min-w-0 flex-nowrap items-center gap-1"><span class="w-14 shrink-0 text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Zeilen') }}</span><div data-card-heights class="grid min-w-0 flex-1 gap-1"></div></div></div>`;
                item.querySelector('b').textContent = labels[config.id] || config.id;
                item.querySelector('small').textContent = descriptions[config.id] || '';
                if (fixedDimensions[config.id]) {
                    const badge = document.createElement('span');
                    badge.className = 'rounded-md border border-amber-400/25 bg-amber-400/10 px-1.5 py-0.5 text-[7px] font-black uppercase text-amber-300';
                    badge.style.cssText = 'position:absolute;right:3.25rem;top:.6rem;z-index:2;white-space:nowrap';
                    badge.textContent = '{{ __('Feste Größe') }}';
                    item.appendChild(badge);
                }
                [['width', '[data-card-widths]', [1,2,3]], ['height', '[data-card-heights]', [1,2,3,4,5,6]]].forEach(([dimension, selector, values]) => {
                    const target = item.querySelector(selector);
                    target.style.gridTemplateColumns = `repeat(${values.length},minmax(0,1fr))`;
                    values.forEach((size) => {
                        const button = document.createElement('button'); button.type = 'button'; button.dataset.dimension = dimension; button.dataset.size = String(size); button.textContent = String(size);
                        button.className = 'min-w-0 whitespace-nowrap rounded-md border px-1 py-1 text-[7px] font-black transition';
                        if (fixedDimensions[config.id]) {
                            button.disabled = true; button.title = '{{ __('Diese Karte benötigt eine feste Größe') }}'; button.classList.add('cursor-not-allowed', 'opacity-45');
                        } else if (dimension === 'height' && size < (minimumHeights[config.id] || 1)) {
                            button.disabled = true; button.title = '{{ __('Zu niedrig für den Mindestinhalt') }}'; button.classList.add('cursor-not-allowed', 'opacity-30');
                        }
                        button.addEventListener('click', () => { if (button.disabled) return; item.dataset[dimension === 'width' ? 'cardWidth' : 'cardHeight'] = size; refresh(); }); target.appendChild(button);
                    });
                });
                item.querySelector('[data-card-toggle]').addEventListener('click', () => { (item.parentElement === activeZone ? hiddenZone : activeZone).appendChild(item); refresh(); });
                item.addEventListener('dragstart', () => { dragged = item; item.classList.add('opacity-40'); });
                item.addEventListener('dragend', () => { item.classList.remove('opacity-40'); dragged = null; refresh(); });
                return item;
            };
            const refresh = () => {
                [...activeZone.querySelectorAll('[data-card-id]'), ...hiddenZone.querySelectorAll('[data-card-id]')].forEach((item) => {
                    const isActive = item.parentElement === activeZone;
                    item.querySelector('[data-card-toggle]').textContent = isActive ? '→' : '←';
                    if (isActive) {
                        const previewPosition = {
                            'signal-cockpit': ['2 / span 1', '1 / span 6'],
                            strategy: ['1 / span 1', '1 / span 1'],
                            personal: ['1 / span 1', '2 / span 6'],
                            community: ['1 / span 1', '8 / span 2'],
                        }[item.dataset.cardId];
                        item.style.gridColumn = previewPosition?.[0] || `span ${Number(item.dataset.cardWidth) || 1}`;
                        item.style.gridRow = previewPosition?.[1] || `span ${Number(item.dataset.cardHeight) || 2}`;
                    } else {
                        item.style.gridColumn = 'auto';
                        item.style.gridRow = 'auto';
                    }
                    item.querySelectorAll('[data-dimension]').forEach((button) => { const on = button.dataset.size === item.dataset[button.dataset.dimension === 'width' ? 'cardWidth' : 'cardHeight']; button.classList.toggle('border-cyan-300', on); button.classList.toggle('bg-cyan-400/20', on); button.classList.toggle('text-cyan-200', on); button.classList.toggle('border-slate-500/20', !on); button.classList.toggle('text-slate-400', !on); });
                });
                const cards = [...activeZone.querySelectorAll('[data-card-id]')];
                modal.querySelector('[data-dashboard-cards-count]').textContent = `${cards.length} / 11`;
                const area = cards.reduce((sum, item) => sum + ((Number(item.dataset.cardWidth) || 1) * (Number(item.dataset.cardHeight) || 2)), 0);
                modal.querySelector('[data-dashboard-cards-status]').textContent = area > 27 ? '{{ __('Die gewählten Größen passen nicht in das Raster mit drei Spalten und neun Zeilen.') }}' : '';
            };
            const populate = () => {
                activeZone.replaceChildren(); hiddenZone.replaceChildren();
                selected.forEach((config) => { if (catalog.has(config.id)) activeZone.appendChild(createCard(config, true)); });
                catalog.forEach((_, id) => { if (!selected.some((config) => config.id === id)) hiddenZone.appendChild(createCard({ id, width: 1, height: minimumHeights[id] || 1 }, false)); }); refresh();
            };
            const dragOver = (zone, event) => { event.preventDefault(); if (!dragged) return; const target = event.target.closest?.('[data-card-id]'); if (!target || target === dragged || target.parentElement !== zone) zone.appendChild(dragged); else { const rect = target.getBoundingClientRect(); zone.insertBefore(dragged, event.clientY > rect.top + rect.height / 2 ? target.nextSibling : target); } };
            [activeZone, hiddenZone].forEach((zone) => zone.addEventListener('dragover', (event) => dragOver(zone, event)));
            const open = () => { populate(); modal.classList.remove('hidden'); modal.classList.add('grid'); };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };
            document.querySelector('[data-dashboard-cards-open]')?.addEventListener('click', open);
            modal.querySelectorAll('[data-dashboard-cards-close]').forEach((button) => button.addEventListener('click', close));
            modal.querySelector('[data-dashboard-cards-reset]').addEventListener('click', () => { selected = structuredClone(defaults); populate(); });
            modal.querySelector('[data-dashboard-cards-save]').addEventListener('click', async (event) => {
                const cards = [...activeZone.querySelectorAll('[data-card-id]')].map((item) => ({ id: item.dataset.cardId, width: Number(item.dataset.cardWidth), height: Number(item.dataset.cardHeight) }));
                const area = cards.reduce((sum, item) => sum + (item.width * item.height), 0); const status = modal.querySelector('[data-dashboard-cards-status]');
                if (!cards.length) { status.textContent = '{{ __('Mindestens eine Karte muss sichtbar bleiben.') }}'; return; }
                if (area > 27) { status.textContent = '{{ __('Die gewählten Größen passen nicht in das Raster mit drei Spalten und neun Zeilen.') }}'; return; }
                const button = event.currentTarget; button.disabled = true; button.textContent = '{{ __('Speichert …') }}'; status.textContent = '{{ __('Wird gespeichert …') }}';
                try { const response = await fetch('{{ route('dashboard.card-layout.update') }}', { method:'PATCH', credentials:'same-origin', headers:{ 'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || '' }, body:JSON.stringify({ cards }) }); if (!response.ok) { const payload = await response.json().catch(() => ({})); throw new Error(payload.message || `HTTP ${response.status}`); } status.textContent = '{{ __('Gespeichert. Das Dashboard wird aktualisiert …') }}'; window.setTimeout(() => window.location.reload(), 300); }
                catch (error) { status.textContent = `{{ __('Das Layout konnte nicht gespeichert werden.') }} ${error.message || ''}`; button.disabled = false; button.textContent = '{{ __('Layout speichern') }}'; }
            });
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
        })();
        (() => {
            const modal = document.getElementById('dashboard-layout-modal');
            const tileGrid = document.querySelector('.dashboard-bento-personal > div:last-child');
            const activeZone = modal?.querySelector('[data-dashboard-layout-active]');
            const availableZone = modal?.querySelector('[data-dashboard-layout-available]');
            if (!modal || !tileGrid || !activeZone || !availableZone) return;

            const defaults = @json($dashboardDefaultTiles);
            const descriptions = @json($dashboardTileDescriptions);
            const catalog = new Map([...tileGrid.querySelectorAll('[data-dashboard-tile]')].map((tile) => [tile.dataset.dashboardTile, {
                id: tile.dataset.dashboardTile,
                label: tile.dataset.dashboardTileLabel || tile.dataset.dashboardTile,
                description: descriptions[tile.dataset.dashboardTile] || '',
                icon: tile.querySelector('svg')?.outerHTML || '',
                element: tile,
            }]));
            let selected = @json($dashboardSelectedTiles).filter((id) => catalog.has(id));
            let dragged = null;
            let dashboardTileSize = { width: 0, height: 0 };

            const measureDashboardTile = () => {
                const reference = [...tileGrid.querySelectorAll('[data-dashboard-tile]')].find((tile) => !tile.classList.contains('hidden'));
                if (!reference) return;
                const rect = reference.getBoundingClientRect();
                dashboardTileSize = { width: Math.round(rect.width), height: Math.max(Math.round(rect.height), 116) };
                [activeZone, availableZone].forEach((zone) => {
                    zone.style.gridTemplateColumns = `repeat(3, ${dashboardTileSize.width}px)`;
                    zone.style.gridAutoRows = `minmax(0, ${dashboardTileSize.height}px)`;
                });
            };

            const createChoice = (item, active) => {
                const choice = document.createElement('div');
                choice.draggable = true;
                choice.dataset.tileId = item.id;
                choice.className = 'dashboard-layout-choice relative flex h-full min-h-0 cursor-grab flex-col overflow-hidden rounded-xl border border-orange-400/20 bg-orange-400/[.045] p-3 shadow-[inset_0_1px_0_rgba(251,146,60,.04)] transition hover:border-orange-300/45 hover:bg-orange-400/[.09] active:cursor-grabbing';
                if (dashboardTileSize.height) { choice.style.height = `${dashboardTileSize.height}px`; choice.style.minHeight = '0'; }
                choice.innerHTML = `<span class="flex items-center justify-between gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400 [&>svg]:h-4 [&>svg]:w-4">${item.icon}</span><span class="mr-8 text-base leading-none text-orange-400" aria-hidden="true">⠿</span></span><span class="mt-2 min-w-0 pr-8"><b class="block truncate text-xs leading-4"></b><small class="mt-1 block overflow-hidden text-[9px] leading-3 text-[var(--ak-muted)]" style="display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;max-height:1.5rem"></small></span><button type="button" class="absolute bottom-3 right-3 grid h-8 w-8 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/[.08] text-orange-400 transition hover:bg-orange-400/[.18]" aria-label="${active ? '{{ __('Entfernen') }}' : '{{ __('Hinzufügen') }}'}">${active ? '→' : '←'}</button>`;
                choice.querySelector('b').textContent = item.label;
                choice.querySelector('small').textContent = item.description;
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'tiles[]'; input.value = item.id; input.disabled = !active;
                choice.appendChild(input);
                choice.querySelector('button').addEventListener('click', () => {
                    const adding = choice.parentElement === availableZone;
                    if (adding && activeZone.querySelectorAll('[data-tile-id]').length >= 12) {
                        const displaced = activeZone.querySelector('[data-tile-id]:last-child');
                        if (displaced) availableZone.appendChild(displaced);
                    }
                    (choice.parentElement === activeZone ? availableZone : activeZone).appendChild(choice);
                    render();
                });
                choice.addEventListener('dragstart', () => { dragged = choice; choice.classList.add('opacity-40'); });
                choice.addEventListener('dragend', () => { choice.classList.remove('opacity-40'); dragged = null; render(); });
                return choice;
            };

            const syncSelected = () => {
                selected = [...activeZone.querySelectorAll('[data-tile-id]')].map((item) => item.dataset.tileId);
                modal.querySelector('[data-dashboard-layout-count]').textContent = `${selected.length} / 12`;
            };
            const render = () => {
                [...activeZone.children].forEach((node) => {
                    const button = node.querySelector('button'); if (button) button.textContent = '→';
                    const input = node.querySelector('input[name="tiles[]"]'); if (input) input.disabled = false;
                });
                [...availableZone.children].forEach((node) => {
                    const button = node.querySelector('button'); if (button) button.textContent = '←';
                    const input = node.querySelector('input[name="tiles[]"]'); if (input) input.disabled = true;
                });
                syncSelected();
                if (selected.length < 1) modal.querySelector('[data-dashboard-layout-status]').textContent = '{{ __('Bitte wähle mindestens eine Kachel aus.') }}';
                else if (selected.length > 12) modal.querySelector('[data-dashboard-layout-status]').textContent = '{{ __('Bitte wähle höchstens zwölf Kacheln aus.') }}';
                else modal.querySelector('[data-dashboard-layout-status]').textContent = '';
            };
            const populate = () => {
                activeZone.replaceChildren(); availableZone.replaceChildren();
                selected.forEach((id) => { const item = catalog.get(id); if (item) activeZone.appendChild(createChoice(item, true)); });
                catalog.forEach((item, id) => { if (!selected.includes(id)) availableZone.appendChild(createChoice(item, false)); });
                render();
            };
            const dragOver = (zone, event) => {
                event.preventDefault();
                if (!dragged) return;
                const target = event.target.closest?.('[data-tile-id]');
                if (!target || target === dragged || target.parentElement !== zone) { zone.appendChild(dragged); return; }
                const rect = target.getBoundingClientRect();
                const after = event.clientY > rect.top + rect.height / 2
                    || (Math.abs(event.clientY - (rect.top + rect.height / 2)) < rect.height / 3 && event.clientX > rect.left + rect.width / 2);
                zone.insertBefore(dragged, after ? target.nextSibling : target);
            };
            [activeZone, availableZone].forEach((zone) => zone.addEventListener('dragover', (event) => dragOver(zone, event)));

            const open = () => { measureDashboardTile(); populate(); modal.classList.remove('hidden'); modal.classList.add('grid'); };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };
            document.querySelector('[data-dashboard-layout-open]')?.addEventListener('click', open);
            modal.querySelectorAll('[data-dashboard-layout-close]').forEach((button) => button.addEventListener('click', close));
            modal.querySelector('[data-dashboard-layout-reset]')?.addEventListener('click', () => { selected = [...defaults]; populate(); });
            modal.querySelector('form')?.addEventListener('submit', (event) => {
                syncSelected();
                const status = modal.querySelector('[data-dashboard-layout-status]');
                const saveButton = modal.querySelector('[data-dashboard-layout-save]');
                if (selected.length < 1) { event.preventDefault(); status.textContent = '{{ __('Bitte wähle mindestens eine Kachel aus.') }}'; return; }
                if (selected.length > 12) { event.preventDefault(); status.textContent = '{{ __('Bitte wähle höchstens zwölf Kacheln aus.') }}'; return; }
                status.textContent = '{{ __('Wird gespeichert …') }}';
                saveButton.disabled = true;
                saveButton.textContent = '{{ __('Speichert …') }}';
            });
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            selected.forEach((id) => { const tile = catalog.get(id)?.element; if (tile) tileGrid.appendChild(tile); });
        })();
        (() => {
            const modal = document.getElementById('message-settings-modal');
            if (!modal) return;
            const open = () => { modal.classList.remove('hidden'); modal.classList.add('grid'); };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };
            document.querySelectorAll('[data-message-settings-open]').forEach((button) => button.addEventListener('click', open));
            modal.querySelector('[data-message-settings-close]')?.addEventListener('click', close);
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        })();
        (() => {
            const modal = document.getElementById('message-preview-modal');
            if (!modal) return;
            const title = modal.querySelector('[data-preview-title]');
            const subject = modal.querySelector('[data-preview-subject]');
            const schedule = modal.querySelector('[data-preview-schedule]');
            const body = modal.querySelector('[data-preview-body]');
            const deleteForm = modal.querySelector('[data-message-preview-delete]');
            const rescheduleForm = modal.querySelector('[data-message-preview-reschedule]');
            const dateInput = modal.querySelector('[data-message-preview-date]');
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };
            const open = (row) => {
                const data = row.dataset;
                const symbol = (data.previewSymbol || '').trim();
                const name = (data.previewName || '').trim() || symbol || `{{ __('Diese Aktie') }}`;
                const label = (data.previewLabel || '').trim() || `{{ __('Kauferinnerung') }}`;
                const scheduledFor = (data.previewSchedule || '').trim() || `{{ __('Zum festgelegten Termin') }}`;
                const stock = symbol && name !== symbol ? `${name} (${symbol})` : name;
                title.textContent = `${symbol ? symbol + ' · ' : ''}${label}`;
                subject.textContent = `{{ __('AktienKI') }} – ${label} · ${stock}`;
                schedule.textContent = scheduledFor;
                body.textContent = data.previewType === 'signal'
                    ? `{{ __('Für :stock ist eine :type eingerichtet. Wir senden dir eine E-Mail, sobald die festgelegte Signaländerung eintritt.', ['stock' => '__STOCK__', 'type' => '__TYPE__']) }}`.replace('__STOCK__', stock).replace('__TYPE__', label)
                    : `{{ __('Du hast für :stock eine :type eingerichtet. Wir erinnern dich am :date per E-Mail und zeigen dir dann die aktuelle Prognose sowie deine gespeicherten Einstellungen.', ['stock' => '__STOCK__', 'type' => '__TYPE__', 'date' => '__DATE__']) }}`.replace('__STOCK__', stock).replace('__TYPE__', label).replace('__DATE__', scheduledFor.replace(/^E-Mail\s*·\s*/, ''));
                deleteForm.action = data.previewDeleteUrl || '';
                const canReschedule = Boolean(data.previewDate && data.previewRescheduleUrl);
                rescheduleForm.classList.toggle('hidden', !canReschedule);
                rescheduleForm.classList.toggle('flex', canReschedule);
                rescheduleForm.action = canReschedule ? data.previewRescheduleUrl : '';
                dateInput.value = canReschedule ? data.previewDate : '';
                modal.classList.remove('hidden'); modal.classList.add('grid');
            };
            document.querySelectorAll('[data-message-preview]').forEach((row) => {
                row.addEventListener('click', (event) => { if (!event.target.closest('button, form, a')) open(row); });
                row.addEventListener('keydown', (event) => { if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('button, form, a')) { event.preventDefault(); open(row); } });
            });
            modal.querySelectorAll('[data-message-preview-close]').forEach((button) => button.addEventListener('click', close));
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
        })();
        (() => {
            const modal = document.getElementById('dashboard-aki-modal');
            const messages = document.getElementById('dashboard-aki-messages');
            const input = document.getElementById('dashboard-aki-input');
            const form = document.getElementById('dashboard-aki-form');
            if (!modal || !form) return;
            const history = [];
            const open = () => { modal.classList.remove('hidden'); modal.classList.add('grid'); window.setTimeout(() => input?.focus(), 40); };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };
            document.querySelector('[data-dashboard-aki-open]')?.addEventListener('click', open);
            document.querySelector('[data-dashboard-aki-close]')?.addEventListener('click', close);
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const question = (input?.value || '').trim();
                if (!question) return;
                const user = document.createElement('p'); user.className = 'ml-auto max-w-[88%] rounded-xl bg-teal-500 px-3 py-2 text-xs text-white'; user.textContent = question; messages.appendChild(user); input.value = '';
                history.push({ role: 'user', content: question });
                const pending = document.createElement('p'); pending.className = 'flex max-w-[92%] items-center gap-2 rounded-xl border border-amber-300/20 bg-slate-700/70 px-3 py-2 text-xs text-amber-200'; pending.innerHTML = '<span>{{ __('AKI denkt') }}</span><span class="dashboard-aki-dots" aria-hidden="true">•••</span>'; messages.appendChild(pending); messages.scrollTop = messages.scrollHeight;
                try {
                    const response = await fetch('{{ route('aki.chat') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ question, messages: history.slice(-8), filters: {}, mode: 'standard' }) });
                    const payload = await response.json(); pending.remove();
                    const text = response.ok ? (payload.answer || '{{ __('Keine Antwort erhalten.') }}') : (payload.message || '{{ __('Die KI ist gerade nicht erreichbar.') }}');
                    const answer = document.createElement('p'); answer.className = 'max-w-[92%] whitespace-pre-line rounded-xl border border-teal-300/15 bg-slate-700/70 px-3 py-2 text-xs text-slate-100'; answer.textContent = text; messages.appendChild(answer); history.push({ role: 'assistant', content: text });
                    const suggestedSymbols = Array.isArray(payload.filter_suggestion?.symbols) ? payload.filter_suggestion.symbols.filter(Boolean) : [];
                    if (response.ok && suggestedSymbols.length) {
                        const params = new URLSearchParams();
                        suggestedSymbols.forEach((symbol) => params.append('symbols[]', symbol));
                        const link = document.createElement('a');
                        link.href = '{{ route('predictions.index') }}?' + params.toString();
                        link.className = 'inline-flex items-center gap-2 rounded-lg border border-teal-300/35 bg-teal-500/15 px-3 py-2 text-[10px] font-black text-teal-200 transition hover:bg-teal-500/25';
                        link.target = '_self';
                        link.innerHTML = '<span>{{ __('Empfohlene Aktien öffnen') }}</span><span aria-hidden="true">→</span>';
                        messages.appendChild(link);
                    }
                } catch (_) { pending.remove(); const error = document.createElement('p'); error.className = 'max-w-[92%] rounded-xl border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-xs text-rose-300'; error.textContent = '{{ __('Die Verbindung zur KI konnte nicht hergestellt werden.') }}'; messages.appendChild(error); }
                messages.scrollTop = messages.scrollHeight;
            });
        })();
    </script>
    <style>
        @media (max-width: 767px) {
            #personal-dashboard {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }
            #personal-dashboard > .ak-container {
                width: 100%;
                max-width: 100%;
                padding-inline: 1rem !important;
            }
            #personal-dashboard .dashboard-bento {
                width: calc(100% - .5rem);
                max-width: calc(100% - .5rem);
                margin-inline: auto;
                grid-template-columns: minmax(0, 1fr) !important;
            }
            #personal-dashboard .dashboard-bento > *,
            #personal-dashboard .dashboard-bento [data-dashboard-card] {
                width: 100%;
                min-width: 0 !important;
                max-width: 100%;
                grid-column: 1 / -1 !important;
                overflow-x: hidden;
            }
            #personal-dashboard .dashboard-bento [data-dashboard-card] :is(table, svg, section, article, div) {
                max-width: 100%;
                min-width: 0;
            }
        }
        @media (min-width: 1280px) {
            html:has(#personal-dashboard),
            body:has(#personal-dashboard) {
                height: 100dvh;
                overflow: hidden;
                overscroll-behavior: none;
            }
        }

        .ak-dashboard-card {
            background-color: color-mix(in srgb, var(--ak-card) 60%, transparent);
            border-color: color-mix(in srgb, #fb923c 32%, transparent);
            box-shadow: 0 12px 30px rgba(194, 65, 12, .10), inset 0 1px 0 rgba(251, 146, 60, .045);
            backdrop-filter: blur(8px);
        }
        .dashboard-plan-locked {
            filter: grayscale(.92) saturate(.18);
            opacity: .5;
            border-color: color-mix(in srgb, var(--ak-muted) 28%, transparent) !important;
            box-shadow: none !important;
        }
        .dashboard-plan-locked:hover { opacity: .66; }
        .dashboard-plan-badge,
        .dashboard-plan-mini-badge {
            position: absolute;
            z-index: 30;
            border: 1px solid rgba(251, 191, 36, .32);
            background: color-mix(in srgb, var(--ak-card) 88%, #fbbf24 12%);
            color: #fcd34d;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .dashboard-plan-badge { right: .7rem; top: .6rem; border-radius: .5rem; padding: .25rem .5rem; font-size: .48rem; }
        .dashboard-plan-mini-badge { right: .4rem; top: .35rem; border-radius: .35rem; padding: .15rem .3rem; font-size: .42rem; }
        .dashboard-bento-signal-cockpit section h3 { font-size: .72rem !important; line-height: 1rem; }
        .dashboard-bento-signal-cockpit section a { font-size: .68rem; line-height: 1rem; }
        .dashboard-bento-signal-cockpit section a b { font-size: .68rem !important; }
        .dashboard-bento-signal-cockpit section a span { font-size: .61rem; }
        .dashboard-bento-signal-cockpit .aki-horizon-signal,
        .dashboard-bento-signal-cockpit .aki-horizon-signal span { color: #67e8f9 !important; font-size: .72rem !important; line-height: .78rem; }
        .dashboard-bento-signal-cockpit svg,
        .dashboard-bento-signal-cockpit [aria-hidden="true"] { color: #67e8f9 !important; }
        .dashboard-bento-signal-cockpit section small { font-size: .61rem !important; line-height: .9rem; }
        .dashboard-bento-signal-cockpit section [style*="grid-template-columns:50px"] { font-size: .61rem !important; line-height: 1rem; }
        .dashboard-bento-signal-cockpit .aki-chartview-panel h3 { font-size: .72rem !important; line-height: 1rem; letter-spacing: .12em; color: #67e8f9 !important; }
        .dashboard-bento-signal-cockpit .aki-chartview-panel [style*="grid-template-columns:50px"] { font-size: .72rem !important; line-height: 1.1rem; }
        .dashboard-bento-signal-cockpit .aki-chartview-panel [style*="grid-template-columns:50px"] b,
        .dashboard-bento-signal-cockpit .aki-chartview-panel [style*="grid-template-columns:50px"] span { font-size: .72rem !important; }
        :root[data-theme="light"] #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="positive"] { background: #d1fae5 !important; border-color: #34d399 !important; color: #047857 !important; }
        :root[data-theme="light"] #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="negative"] { background: #ffe4e6 !important; border-color: #fb7185 !important; color: #be123c !important; }
        :root[data-theme="light"] #personal-dashboard .aki-signal-forecast-badge[data-forecast-tone="empty"] { background: #e2e8f0 !important; border-color: #94a3b8 !important; color: #475569 !important; }
        @media (min-width: 1280px) {
            .dashboard-personal-column {
                scrollbar-width: thin;
                scrollbar-color: rgba(34, 211, 238, .7) rgba(15, 23, 42, .25);
            }
            .dashboard-personal-column::-webkit-scrollbar { width: 7px; }
            .dashboard-personal-column::-webkit-scrollbar-track {
                background: rgba(15, 23, 42, .25);
                border-radius: 999px;
            }
            .dashboard-personal-column::-webkit-scrollbar-thumb {
                background: rgba(34, 211, 238, .7);
                border-radius: 999px;
            }
            .dashboard-bento-signal-cockpit { margin-bottom: 0; }
            .dashboard-bento {
                grid-template-rows: minmax(0, 1.7fr) repeat(8, minmax(0, 1fr));
            }
            .dashboard-bento-strategy { grid-column: 1 / span 4; grid-row: 1 / span 1; }
            .dashboard-bento-personal { grid-column: 1 / span 4; grid-row: 2 / span 6; }
            .dashboard-bento-community { grid-column: 1 / span 4; grid-row: 8 / span 2; align-self: stretch; height: 100%; }
            .dashboard-bento-market { grid-column: 5 / span 4; grid-row: 1 / -1; align-self: stretch; height: 100%; }
            .dashboard-bento-models { grid-column: 9 / span 4; grid-row: 1 / span 3; }
            .dashboard-bento-signal-cockpit { grid-column: 9 / span 4; grid-row: 1 / -1; align-self: stretch; height: 100%; }
            .dashboard-right-column { display: none; }
            .dashboard-right-column .dashboard-bento-signals { order: 1; flex: 0 0 auto; min-height: 0; height: auto !important; }
            .dashboard-right-column .dashboard-bento-market-summary { order: 2; flex: 0 0 auto; height: auto !important; }
            .dashboard-right-column .dashboard-bento-earnings { order: 3; flex: 1 1 0%; min-height: 0; height: auto !important; }
        }

        /* Compact desktop mode: retain the complete dashboard within one viewport. */
        @media (min-width: 1280px) and (max-height: 1100px) {
            html:has(#personal-dashboard),
            body:has(#personal-dashboard) {
                height: 100dvh;
                overflow: hidden;
                overscroll-behavior: none;
            }
            #personal-dashboard {
                height: calc(100dvh - 89px) !important;
                min-height: 0 !important;
                overflow: hidden !important;
            }
            #personal-dashboard > .ak-container {
                height: 100% !important;
                min-height: 0 !important;
                padding-top: .75rem;
                padding-bottom: .75rem;
            }
            #personal-dashboard header { margin-bottom: .65rem; }
            #personal-dashboard header h1 { margin-top: .1rem; font-size: 1.55rem; line-height: 1.8rem; }
            #personal-dashboard .dashboard-bento {
                flex: 1 1 0%;
                min-height: 0;
                gap: .6rem;
                grid-template-rows: minmax(0, 1.7fr) repeat(8, minmax(0, 1fr));
            }
            #personal-dashboard .ak-dashboard-card { padding: .7rem; }
            #personal-dashboard .dashboard-bento-strategy { min-height: 0; padding: .55rem .7rem; }
            #personal-dashboard .dashboard-bento-personal > div:last-child,
            #personal-dashboard .dashboard-bento-community > div:last-child { margin-top: .45rem; gap: .4rem; }
            #personal-dashboard .dashboard-bento-personal a,
            #personal-dashboard .dashboard-bento-personal button,
            #personal-dashboard .dashboard-bento-personal > div:last-child > div,
            #personal-dashboard .dashboard-bento-community > div:last-child > div { padding: .45rem .55rem; }
            #personal-dashboard .dashboard-bento-personal a small,
            #personal-dashboard .dashboard-bento-personal button small,
            #personal-dashboard .dashboard-bento-personal > div:last-child > div small,
            #personal-dashboard .dashboard-bento-community small { margin-top: .3rem; }
            #personal-dashboard .dashboard-bento-market > div:nth-child(2) { margin-top: .65rem; }
            #personal-dashboard .dashboard-bento-market > p { margin-top: .65rem; font-size: .78rem; line-height: 1.28rem; }
            #personal-dashboard .dashboard-bento-models > div:first-child,
            #personal-dashboard .dashboard-bento-signals > div:first-child { margin-bottom: .55rem; }
            #personal-dashboard .dashboard-bento-models > div:last-child { gap: .3rem; }
            #personal-dashboard .dashboard-bento-models > div:last-child > div { padding-top: .35rem; padding-bottom: .35rem; }
        }

        @media (min-width: 1280px) and (max-height: 850px) {
            #personal-dashboard > .ak-container { padding-top: .5rem; padding-bottom: .5rem; }
            #personal-dashboard header { margin-bottom: .4rem; }
            #personal-dashboard header h1 { font-size: 1.3rem; line-height: 1.5rem; }
            #personal-dashboard .dashboard-bento { gap: .45rem; }
            #personal-dashboard .ak-dashboard-card { padding: .55rem; }
            #personal-dashboard .dashboard-bento-personal > div:first-child,
            #personal-dashboard .dashboard-bento-community > div:first-child { transform: scale(.9); transform-origin: left top; }
            #personal-dashboard .dashboard-bento-personal a,
            #personal-dashboard .dashboard-bento-personal button,
            #personal-dashboard .dashboard-bento-personal > div:last-child > div,
            #personal-dashboard .dashboard-bento-community > div:last-child > div { padding: .3rem .45rem; }
            #personal-dashboard .dashboard-bento-personal .h-8,
            #personal-dashboard .dashboard-bento-community .h-8 { height: 1.55rem; width: 1.55rem; }
            #personal-dashboard .dashboard-bento-personal .text-lg,
            #personal-dashboard .dashboard-bento-community .text-lg { font-size: .9rem; }
            #personal-dashboard .dashboard-bento-market > p { font-size: .72rem; line-height: 1.12rem; }
        }
        @media (min-width: 1280px) {
            .dashboard-bento[data-dashboard-card-layout="custom"] {
                grid-auto-flow: dense;
                grid-template-columns: repeat(12, minmax(0, 1fr));
                grid-template-rows: minmax(0, 1.7fr) repeat(8, minmax(0, 1fr));
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card] {
                order: var(--dashboard-card-order);
                min-height: 0 !important;
                grid-column: span 4 !important;
                grid-row: span 3 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="signals"] {
                align-self: auto !important;
                height: auto !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="earnings"] {
                align-self: auto !important;
                height: auto !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="market-summary"] {
                align-self: auto !important;
                height: auto !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-size="small"] {
                grid-column: span 4 !important;
                grid-row: span 1 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-size="large"] {
                grid-column: span 8 !important;
                grid-row: span 3 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="small"] { grid-column: span 4 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="medium"] { grid-column: span 8 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="large"] { grid-column: span 12 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="small"] { grid-row: span 1 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="medium"] { grid-row: span 2 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="large"] { grid-row: span 3 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="1"] { grid-column: span 4 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="2"] { grid-column: span 8 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-width="3"] { grid-column: span 12 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="1"] { grid-row: span 1 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="2"] { grid-row: span 2 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="3"] { grid-row: span 3 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="4"] { grid-row: span 4 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="5"] { grid-row: span 5 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-height="6"] { grid-row: span 6 !important; }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="signal-cockpit"] {
                grid-column: 9 / span 4 !important;
                grid-row: 1 / -1 !important;
                align-self: stretch;
                height: 100%;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="market"] {
                grid-column: 5 / span 4 !important;
                grid-row: 1 / -1 !important;
                align-self: stretch;
                height: 100%;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="personal"] {
                grid-column: 1 / span 4 !important;
                grid-row: 2 / span 6 !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="community"] {
                grid-column: 1 / span 4 !important;
                grid-row: 8 / span 2 !important;
                align-self: stretch !important;
                height: 100% !important;
            }
            .dashboard-bento[data-dashboard-card-layout="custom"] [data-dashboard-card="strategy"] {
                grid-column: 1 / span 4 !important;
                grid-row: 1 / span 1 !important;
            }
        }
        @media (max-width: 1279px) {
            .dashboard-bento [data-dashboard-card] { order: var(--dashboard-card-order); }
        }
        .dashboard-aki-dots { display: inline-block; min-width: 1.6em; letter-spacing: .12em; animation: dashboard-aki-pulse 1.1s steps(4, end) infinite; }
        @@keyframes dashboard-aki-pulse { 0%,20% { opacity: .25; } 40% { opacity: .65; } 60%,100% { opacity: 1; } }
        #dashboard-aki-messages { scrollbar-width: thin; scrollbar-color: rgba(34, 211, 238,.7) rgba(15,23,42,.45); }
        #dashboard-aki-messages::-webkit-scrollbar { width: 8px; }
        #dashboard-aki-messages::-webkit-scrollbar-track { background: rgba(15,23,42,.45); border-radius: 999px; }
        #dashboard-aki-messages::-webkit-scrollbar-thumb { background: rgba(34, 211, 238,.7); border-radius: 999px; }
    </style>
</x-app-layout>

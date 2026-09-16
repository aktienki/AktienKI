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
        $dashboardDefaultTiles = ['paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'chartview', 'community-posts', 'community-members', 'community-recent', 'best-buy', 'best-wait'];
        $dashboardStoredTiles = (array) data_get(auth()->user()->preferences, 'dashboard.personal_tiles', $dashboardDefaultTiles);
        if (! in_array('chartview', $dashboardStoredTiles, true)) $dashboardStoredTiles[] = 'chartview';
        $dashboardStoredTiles = array_values(array_diff($dashboardStoredTiles, ['community']));
        foreach (['community-posts', 'community-members', 'community-recent'] as $communityTile) {
            if (! in_array($communityTile, $dashboardStoredTiles, true)) $dashboardStoredTiles[] = $communityTile;
        }
        $dashboardSelectedTiles = array_values(array_intersect(
            $dashboardStoredTiles,
            ['paper-depots', 'watchlists', 'strategies', 'labels', 'reminders', 'chartview', 'community-posts', 'community-members', 'community-recent', 'best-buy', 'best-wait', 'watchlist-screener', 'predictions', 'smart-screener', 'market-report', 'mobile-view', 'news']
        ));
        $dashboardTileVisible = fn (string $id): bool => in_array($id, $dashboardSelectedTiles, true);
        $dashboardTileDescriptions = [
            'paper-depots' => __('Musterdepots öffnen und verwalten.'),
            'watchlists' => __('Gespeicherte Aktienlisten anzeigen.'),
            'strategies' => __('Eigene Anlagestrategien verwalten.'),
            'labels' => __('Aktien mit persönlichen Labels ordnen.'),
            'reminders' => __('E-Mail-Erinnerungen verwalten.'),
            'chartview' => __('Technische Signale und Chartmuster öffnen.'),
            'community-posts' => __('Beiträge in der Community öffnen.'),
            'community-members' => __('Mitglieder der Community anzeigen.'),
            'community-recent' => __('Community-Aktivität der letzten sieben Tage anzeigen.'),
            'best-buy' => __('Aktuell stärkste POSITIV-Aktie öffnen.'),
            'best-wait' => __('Aktuell stärkste WATCH-Aktie öffnen.'),
            'watchlist-screener' => __('Watchlist-Aktien direkt filtern.'),
            'predictions' => __('Alle aktuellen Prognosen vergleichen.'),
            'smart-screener' => __('Aktien nach eigenen Kriterien finden.'),
            'market-report' => __('Die aktuelle Marktanalyse lesen.'),
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
            'community' => __('Community'), 'market' => __('Aktuelle Remote-Aktien'),
            'models' => __('Letzte Prognosen'), 'signals' => __('Extern bestätigte POSITIV-Signale'),
            'earnings' => __('Aktuelle Quartalszahlen'),
            'market-summary' => __('Kompakter Marktbericht'),
            'schedule' => __('Termine & Erinnerungen'), 'signal-cockpit' => __('Beste Alternativen'),
            'mobile-view' => __('Mobile Ansicht'), 'champion' => __('Drei-Faktoren-Champion'),
            'center-combined' => __('Champion & beste Alternativen'),
            'daily-tips' => __('Aufsteiger & Technik'),
        ];
        $dashboardMainCardDescriptions = [
            'strategy' => __('Depotwert, Kapital und Performance.'), 'personal' => __('Deine persönlichen Schnellzugriffe.'),
            'community' => __('Aktivität seit deinem letzten Login.'), 'market' => __('Ausblick und aktueller Marktbericht.'),
            'models' => __('Globale Modellläufe nach Regionen.'), 'signals' => __('Aktuelle POSITIV-Signale ohne wesentlichen externen Einwand.'),
            'earnings' => __('Aktuelle Quartalszahlen aus dem Portfolio.'),
            'market-summary' => __('Aktuelle Marktlage kompakt zusammengefasst.'),
            'schedule' => __('Anstehende E-Mails und Aktionen.'),
            'signal-cockpit' => __('Die Ranking-Plätze 2 bis 4 plus eine zusätzliche Alternativaktie.'),
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
        // Help text per card, shown in the top-right "?" modal. Each value is a
        // list of paragraphs; the modal title comes from $dashboardMainCardLabels.
        $dashboardCardHelp = [
            'signal-cockpit' => [
                __('Gezeigt werden die Plätze 2 bis 4 hinter dem Champion sowie eine zusätzliche Alternativaktie mit kurzfristigem Rücksetzer und positivem längerfristigem Ausblick.'),
                __('Das Ranking folgt dem gleichgewichteten Mittel aus KI-Rating, externer Bestätigung und Panel-Perzentil; fehlende externe Werte werden als nicht bestätigt ausgewiesen.'),
                __('Ein Klick auf eine Zeile öffnet die Aktienanalyse.'),
            ],
            'personal' => [
                __('Deine persönlichen Schnellzugriffe: Watchlists, Strategien, Labels, Erinnerungen, Chart-Ansicht und Community.'),
                __('Welche Kacheln hier erscheinen, legst du über „Kacheln verwalten" fest.'),
            ],
            'strategy' => [
                __('Überblick über dein automatisiertes Strategiedepot: aktueller Depotwert, eingesetztes Kapital und Wertentwicklung.'),
                __('Nur mit Pro-Tarif aktiv.'),
            ],
            'market' => [__('Größe deines aktiven Remote-Universums: wie viele Aktien eine aktuelle Prediction haben und wie viele noch ausstehen, plus die KI-Rating-Verteilung.')],
            'market-summary' => [__('Die aktuelle Marktlage in einem Satz zusammengefasst, plus kurzer Ausblick.')],
            'daily-tips' => [
                __('Zwei Zusatzkandidaten neben dem Champion. Oben: die Aktie mit dem stärksten Anstieg des KI-Scores über die letzten rund fünf Handelstage, die noch kein POSITIV-Signal hat (aus der lokalen Prognosehistorie).'),
                __('Unten: die Aktie mit der besten ChartView-Statistik – ein nach Fallzahl gewichteter Mittelwert der „Kurs danach gestiegen"-Wahrscheinlichkeit über ihre bullischen technischen Ereignisse (20-Tage-Horizont).'),
            ],
            'models' => [__('Status der globalen Modellläufe nach Regionen und die zuletzt erzeugten Prognosen.')],
            'signals' => [__('Aktuelle POSITIV-Signale, für deren exakte Serving-Version die externe Prüfung keinen wesentlichen Einwand gefunden hat.')],
            'earnings' => [__('Anstehende Quartalszahlen für Aktien aus deinem Portfolio und deinen Watchlists.')],
            'schedule' => [__('Anstehende E-Mails, Erinnerungen und geplante Aktionen mit Datum.')],
            'community' => [__('Neue Beiträge, Mitglieder und Aktivität in der Community seit deinem letzten Login.')],
            'mobile-view' => [__('Lege fest, welche Karten auf dem Smartphone angezeigt werden.')],
            'champion' => [__('Die beste Aktie im gleichgewichteten Mittel aus POSITIV-Rating, externer Bestätigung und Panel-Perzentil. Berücksichtigt werden nur extern bestätigte BUYs in den Panel-Dezilen 6 bis 10.')],
            'center-combined' => [
                __('Oben steht der Drei-Faktoren-Champion aus POSITIV-Rating, externer Bestätigung und Panel-Perzentil.'),
                __('Darunter folgen die Ranking-Plätze 2 bis 4 und eine zusätzliche Alternativaktie.'),
            ],
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
        $dashboardMobileCardIds = ['champion', 'market', 'market-summary', 'schedule', 'strategy', 'signal-cockpit', 'personal', 'community', 'mobile-view'];
        $storedMobileCards = data_get(auth()->user()->preferences, 'dashboard.mobile_cards');
        $storedMobileCardsVersion = (int) data_get(auth()->user()->preferences, 'dashboard.mobile_cards_version', 1);
        $dashboardMobileCards = is_array($storedMobileCards)
            ? array_values(array_intersect($dashboardMobileCardIds, $storedMobileCards))
            : array_values(array_diff($dashboardMobileCardIds, ['mobile-view']));
        if (is_array($storedMobileCards) && $storedMobileCardsVersion < 2 && ! in_array('champion', $dashboardMobileCards, true)) {
            array_unshift($dashboardMobileCards, 'champion');
        }
        // Die Konfiguration bleibt mobil erreichbar, auch wenn sie in einer
        // älteren gespeicherten Auswahl noch nicht enthalten ist.
        $dashboardMobileCards = array_values(array_unique([...$dashboardMobileCards, 'mobile-view']));
        $marketFactors = collect($marketFactorSnapshot['current'] ?? []);
        $globalMarketFactor = $marketFactors->get('market');
        $factorBadge = function ($value): array {
            if (!is_numeric($value)) return ['—', 'Berechnung ausstehend', 'border-slate-400/20 text-[var(--ak-muted)]'];
            $score = (float) $value;
            return match (true) {
                $score >= 75 => ['↗↗', number_format($score, 0, ',', '.'), 'border-emerald-400/35 text-emerald-400'],
                $score >= 60 => ['↗', number_format($score, 0, ',', '.'), 'border-green-400/35 text-green-400'],
                $score >= 40 => ['→', number_format($score, 0, ',', '.'), 'border-amber-400/35 text-amber-400'],
                $score >= 25 => ['↘', number_format($score, 0, ',', '.'), 'border-rose-400/35 text-rose-400'],
                default => ['↘↘', number_format($score, 0, ',', '.'), 'border-red-500/40 text-red-400'],
            };
        };
        $trendBadge = $factorBadge($globalMarketFactor?->trend_score);
        $timingBadge = $factorBadge($globalMarketFactor?->timing_score);
        $profileUniverseLabel = match($profileUniverseStats['level'] ?? 'balanced') {
            'defensive' => __('Defensiv'),
            'opportunity' => __('Chancenorientiert'),
            'risk' => __('Risk'),
            default => __('Ausgewogen'),
        };
        $dashboardPlanLabel = $canUsePro ? 'PRO' : ($canUsePlus ? 'PLUS' : 'FREE');
    @endphp

    <style>
        #personal-dashboard .dashboard-champion-donut .segmented-score { position:relative; display:grid; width:66px; height:66px; place-items:center; }
        #personal-dashboard .dashboard-champion-donut .segmented-score-ring { position:absolute; inset:0; width:100%; height:100%; transform:rotate(-90deg); }
        #personal-dashboard .dashboard-champion-donut .segmented-score-sector { fill:none; stroke-width:8px !important; stroke-linecap:butt; opacity:.5 !important; }
        #personal-dashboard .dashboard-champion-donut .segmented-score-sector.is-active { stroke-width:10px !important; opacity:.92 !important; }
        #personal-dashboard .dashboard-champion-donut .segmented-score-sector.is-end { stroke-width:15px !important; opacity:1 !important; filter:drop-shadow(0 0 5px currentColor) !important; }
        #personal-dashboard .dashboard-champion-donut .segmented-score b { position:relative; font-size:15px; font-weight:950; color:var(--ak-text); text-shadow:0 0 10px rgba(255,255,255,.12); }
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-champion-donut .segmented-score-sector[stroke="#dfe8ea"] { stroke:#40536a !important; opacity:.62 !important; }
        :root[data-theme="light"] #personal-dashboard .dashboard-champion-donut .segmented-score-sector[stroke="#dfe8ea"] { stroke:#b5c5cb !important; opacity:.85 !important; }
        /* Champion (#1) — a hint of gold, nothing loud */
        #personal-dashboard .dashboard-champion-entry {
            background: transparent;
            box-shadow: inset 0 0 0 1px rgba(251, 191, 36, .10), 0 6px 22px -12px rgba(217, 160, 30, .35);
        }
        :root[data-theme="light"] #personal-dashboard .dashboard-champion-entry {
            background: transparent;
            box-shadow: inset 0 0 0 1px rgba(212, 160, 30, .16), 0 8px 22px -12px rgba(212, 160, 30, .28);
        }
        #personal-dashboard .dashboard-profile-badge {
            color: #075985;
            border-color: #38bdf8;
            background: #e0f7ff;
            box-shadow: 0 2px 9px rgba(14, 165, 233, .18);
        }
        #personal-dashboard .dashboard-profile-badge[data-profile="risk"] {
            color: #9f1239 !important;
            border-color: #fb7185 !important;
            background: #ffe4e6 !important;
            box-shadow: 0 2px 10px rgba(244, 63, 94, .24);
        }
        #personal-dashboard .dashboard-plan-tier-badge {
            color: #5f4300;
            border-color: #d4af37;
            background: linear-gradient(135deg, #fff5b8, #e9c651);
            box-shadow: 0 2px 11px rgba(212, 175, 55, .30);
        }
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-profile-badge {
            color: #cffafe;
            border-color: #0891b2;
            background: rgba(8, 145, 178, .30);
        }
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-profile-badge[data-profile="risk"] {
            color: #ffe4e6 !important;
            border-color: #fb7185 !important;
            background: rgba(190, 24, 93, .38) !important;
        }
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-plan-tier-badge {
            color: #fff3b0;
            border-color: #e5bd47;
            background: linear-gradient(135deg, #654800, #9a7210);
        }
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-theme-switch,
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-theme-switch > span,
        :root:not([data-theme="light"]) #personal-dashboard .dashboard-theme-switch button[data-theme-choice="dark"] {
            background: transparent !important;
            box-shadow: none !important;
        }
        @media (max-width: 767px) {
            #personal-dashboard .dashboard-bento [data-dashboard-card],
            #personal-dashboard .dashboard-bento [data-mobile-dashboard-card] { display: none !important; }
            @foreach($dashboardMobileCards as $mobileCard)
                #personal-dashboard .dashboard-bento [data-dashboard-card="{{ $mobileCard }}"],
                #personal-dashboard .dashboard-bento [data-mobile-dashboard-card="{{ $mobileCard }}"] { display: flex !important; }
            @endforeach
        }
    </style>

    <main id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)] xl:h-[calc(100dvh-89px)] xl:min-h-0 xl:overflow-hidden">
        <div class="ak-container flex min-h-[calc(100dvh-73px)] flex-col py-4 lg:py-5 xl:h-full xl:min-h-0">
            <header class="dashboard-main-header mb-4 flex shrink-0 flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="dashboard-mobile-risk-label hidden items-center gap-1.5 font-black uppercase"><x-heroicon-o-shield-check class="h-4 w-4 {{ $riskProfile === 'risk' ? 'text-rose-600' : 'text-cyan-600' }}" /><span class="dashboard-profile-badge rounded-md border px-2 py-1 text-[8px] tracking-[.1em]" data-profile="{{ $riskProfile }}">{{ $riskLabels[$riskProfile] ?? ucfirst($riskProfile) }}</span><span class="dashboard-plan-tier-badge rounded-md border px-2.5 py-1 text-[9px] tracking-[.12em]" data-plan="{{ strtolower($dashboardPlanLabel) }}">{{ $dashboardPlanLabel }}</span></p>
                    <p class="dashboard-personal-eyebrow text-[10px] font-black uppercase tracking-[.2em] text-orange-400">{{ __('Persönliche Übersicht') }}</p>
                    <h1 class="dashboard-page-title mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">{{ __('Mein Dashboard') }}</h1>
                </div>
                <div class="dashboard-header-actions flex flex-wrap items-center justify-end gap-2">
                    <div class="dashboard-theme-switch flex items-center gap-2 rounded-xl border border-cyan-400/25 bg-[var(--ak-card)] px-2 py-1.5 shadow-[var(--ak-shadow)]" aria-label="{{ __('Darstellung') }}">
                        <span class="hidden text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)] sm:inline">{{ __('Theme') }}</span>
                        <span class="flex items-center rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-0.5">
                            <button type="button" data-theme-choice="light" class="flex h-8 items-center gap-1.5 rounded-md px-2.5 text-[10px] font-black text-[var(--ak-muted)] transition hover:text-[var(--ak-text)] [[data-theme=light]_&]:bg-white [[data-theme=light]_&]:text-slate-800 [[data-theme=light]_&]:shadow-sm" aria-label="{{ __('Light Theme aktivieren') }}"><x-heroicon-o-sun class="h-4 w-4" />{{ __('Light') }}</button>
                            <button type="button" data-theme-choice="dark" class="flex h-8 items-center gap-1.5 rounded-md px-2.5 text-[10px] font-black text-[var(--ak-muted)] transition hover:text-[var(--ak-text)] [[data-theme=dark]_&]:bg-slate-800 [[data-theme=dark]_&]:text-white [[data-theme=dark]_&]:shadow-sm" aria-label="{{ __('Dark Theme aktivieren') }}"><x-heroicon-o-moon class="h-4 w-4" />{{ __('Dark') }}</button>
                        </span>
                    </div>
                    <button type="button" @if($canUsePlus) data-dashboard-aki-open @endif @disabled(!$canUsePlus) title="{{ $canUsePlus ? __('AKI fragen') : __('Ab Plus verfügbar') }}" class="dashboard-aki-button relative inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-black transition {{ $canUsePlus ? 'border-orange-400/45 bg-orange-400/[.12] text-orange-300 shadow-[0_8px_24px_rgba(251,146,60,.10)] hover:border-orange-300 hover:bg-orange-400/[.2]' : 'cursor-not-allowed border-slate-500/25 bg-slate-500/[.06] text-slate-500 grayscale' }}">
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                        {{ __('AKI fragen') }}
                        @unless($canUsePlus)<span class="ak-plan-badge ak-plan-badge--plus">PLUS</span>@endunless
                    </button>
                    <div class="dashboard-risk-profile flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold text-[var(--ak-muted)] {{ $isOpportunityProfile ? 'border-rose-400/35 bg-rose-400/[.10] shadow-[0_8px_24px_rgba(251,113,133,.08)]' : 'border-orange-400/35 bg-orange-400/[.10] shadow-[0_8px_24px_rgba(251,146,60,.08)]' }}">
                        <x-heroicon-o-shield-check class="h-4 w-4 {{ $isOpportunityProfile ? 'text-rose-300' : 'text-orange-400' }}" />
                        {{ __('Risikoprofil') }}
                        <span class="{{ $isOpportunityProfile ? 'text-rose-300' : 'text-orange-400' }}">{{ $riskLabels[$riskProfile] ?? ucfirst($riskProfile) }}</span>
                    </div>
                </div>
            </header>

            @php
                $marketOutlookIsNeutral = strtolower(trim((string) ($marketSituation?->market_outlook ?? ''))) === 'neutral';
                $marketOutlookBadgeClass = $marketOutlookIsNeutral ? 'border-amber-400/30 text-amber-400' : 'border-cyan-400/25 text-cyan-400';
                $marketRiskIsHigh = strtolower(trim((string) ($marketSituation?->risk_level ?? ''))) === 'high';
                $marketRiskBadgeClass = $marketRiskIsHigh ? 'border-rose-400/30 text-rose-400' : 'border-amber-400/30 text-amber-400';
            @endphp
            <section class="dashboard-bento grid gap-3 sm:grid-cols-2 xl:min-h-0 xl:flex-1 xl:grid-cols-12" data-dashboard-card-layout="{{ $dashboardCardsCustomized ? 'custom' : 'default' }}">
                <div class="dashboard-personal-column contents sm:col-span-2">
                @if ($strategyPortfolio)
                    @php
                        $strategyPortfolioActive = (bool) data_get($strategyPortfolio->meta, 'automation.live_enabled', false);
                        $strategyEmailActive = $strategyPortfolioActive
                            && (bool) data_get($strategyPortfolio->meta, 'automation.transaction_email_enabled', false);
                    @endphp
                    <article data-dashboard-card="strategy" data-dashboard-top-row-card data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('strategy') }}" class="dashboard-bento-strategy ak-card ak-dashboard-card group relative flex min-h-[94px] overflow-hidden p-3 transition {{ $dashboardCardVisible('strategy') ? '' : 'hidden' }} {{ !$canUsePro ? 'dashboard-plan-locked' : ($strategyPortfolioActive ? 'border-orange-200/90 ring-1 ring-inset ring-orange-200/30 shadow-[0_0_35px_rgba(251,146,60,.24)] hover:border-orange-100' : 'border-orange-200/45 hover:border-orange-100/70') }}">
                        @unless($canUsePro)<span class="dashboard-plan-badge ak-plan-badge ak-plan-badge--pro">{{ __('Ab Pro') }}</span>@endunless
                        <span class="pointer-events-none absolute -left-8 -top-10 h-28 w-28 rounded-full {{ $strategyPortfolioActive ? 'bg-orange-400/35' : 'bg-orange-400/15' }} blur-2xl"></span>
                        <div class="relative flex w-full min-w-0 flex-col justify-between">
                            <div class="dashboard-collapsible-header flex items-center justify-between gap-2">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-200/45 bg-orange-400/20 text-orange-200"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ $canUsePro ? route('depots.show', ['portfolio' => $strategyPortfolio, 'return_to' => request()->getRequestUri()]) : route('pricing') }}" class="block truncate text-base font-bold text-[var(--ak-text)]">{{ $strategyPortfolio->name }}</a>
                                        <span class="mt-1 flex min-w-0 items-center gap-1 overflow-hidden">
                                            <small class="hidden shrink-0 rounded bg-orange-400/20 px-1.5 py-0.5 text-[7px] font-black text-orange-600 sm:inline-flex">{{ __('STRATEGIEDEPOT') }}</small>
                                            @foreach ($strategyPortfolio->strategies as $strategy)
                                                <small class="truncate rounded border px-1.5 py-0.5 text-[7px] font-black {{ $strategyPortfolioActive ? 'border-orange-200/60 bg-orange-400 text-slate-950' : 'border-orange-400/20 bg-orange-400/10 text-orange-500' }}">{{ $strategy->name }}</small>
                                            @endforeach
                                            <small class="flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 text-[7px] font-black uppercase {{ $strategyEmailActive ? 'border-amber-300/60 bg-amber-300/20 text-amber-600' : 'border-slate-500/25 bg-slate-500/10 text-slate-500' }}" title="{{ __('E-Mail pro Transaktion') }}"><x-heroicon-o-envelope class="h-3 w-3" />{{ $strategyEmailActive ? __('E-Mail aktiv') : __('E-Mail aus') }}</small>
                                        </span>
                                    </span>
                                </div>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    @if ($strategyPortfolioActive)
                                        <span class="hidden items-center gap-1.5 rounded-md border border-orange-200 bg-orange-400 px-2 py-1 text-[9px] font-black uppercase text-slate-950 shadow-[0_0_15px_rgba(251,146,60,.35)] sm:flex"><i class="h-1.5 w-1.5 rounded-full bg-slate-950"></i>{{ __('Aktiv') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="strategy-summary-metrics mt-2 border-t border-orange-400/15 pt-2 text-center">
                                <small class="block text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Depotwert') }}</small>
                                <b class="mt-0.5 block truncate text-lg font-black tabular-nums text-[var(--ak-text)]">{{ number_format((float) $strategyPortfolio->dashboard_positions_value, 0, ',', '.') }} {{ strtoupper((string) $strategyPortfolio->currency) === 'EUR' ? '€' : $strategyPortfolio->currency }}</b>
                            </div>
                        </div>
                    </article>
                @else
                    <article data-dashboard-card="strategy" data-dashboard-top-row-card data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('strategy') }}" class="dashboard-bento-strategy ak-card ak-dashboard-card relative flex min-h-[94px] flex-col justify-between overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('strategy') ? '' : 'hidden' }} {{ $canUsePro ? '' : 'dashboard-plan-locked' }}">
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
                    <article x-data="{ personalOpen: true }" data-dashboard-card="personal" data-dashboard-size="{{ $dashboardCardSize('personal') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('personal') }}" class="dashboard-bento-personal ak-card ak-dashboard-card shrink-0 overflow-hidden p-4 {{ $dashboardCardVisible('personal') ? '' : 'hidden' }}">
                        <div class="dashboard-collapsible-header flex items-center justify-between gap-3 mb-1.5">
                            <div class="flex min-w-0 flex-1 items-start gap-2.5 text-left">
                                <span class="grid h-9 w-9 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-squares-2x2 class="h-4.5 w-4.5" /></span>
                                <span class="min-w-0 pt-1"><span class="block text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Persönlicher Bereich') }}</span></span>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5" data-help-anchor="personal">
                                @if ($canUsePro)
                                    <button type="button" data-dashboard-layout-open class="hidden h-9 w-9 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/[.08] text-orange-400 transition hover:border-orange-300/50 hover:bg-orange-400/[.16] md:grid" title="{{ __('Persönlichen Bereich anpassen') }}" aria-label="{{ __('Persönlichen Bereich anpassen') }}"><x-heroicon-o-cog-6-tooth class="h-4.5 w-4.5" /></button>
                                @else
                                    <a href="{{ route('pricing') }}" class="relative hidden h-9 w-9 place-items-center rounded-lg border border-slate-500/25 bg-slate-500/[.06] text-slate-500 transition hover:border-amber-400/40 hover:text-amber-300 md:grid" title="{{ __('Dashboard-Konfiguration ab Pro') }}" aria-label="{{ __('Dashboard-Konfiguration ab Pro') }}"><x-heroicon-o-cog-6-tooth class="h-4.5 w-4.5" /><span class="absolute -right-2 -top-2 rounded border border-amber-400/35 bg-[var(--ak-card)] px-1 py-0.5 text-[6px] font-black text-amber-300">PRO</span></a>
                                @endif
                            </div>
                        </div>
                        <div x-show="personalOpen" x-cloak x-transition.opacity class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ([
                                ['paper-depots', __('Musterdepots'), $overview['paper_depots'], 'heroicon-o-beaker', route('paper-depots.index'), true, null],
                                ['watchlists', __('Watchlists'), $overview['watchlists'], 'heroicon-o-star', route('watchlists.index'), true, null],
                                ['strategies', __('Strategien'), $overview['strategies'], 'heroicon-o-adjustments-horizontal', route('setup.saved-filters.index'), $canUsePro, 'PRO'],
                                ['labels', __('Labels'), $overview['labels'], 'heroicon-o-tag', route('setup.labels.index'), $canUsePlus, 'PLUS'],
                                ['community-posts', __('Beiträge'), $communityOverview['posts'], 'heroicon-o-document-text', route('community.index'), true, null],
                                ['community-members', __('Mitglieder'), $communityOverview['members'], 'heroicon-o-user-group', route('community.index'), true, null],
                                ['community-recent', __('Letzte 7 Tage'), $communityOverview['recent'], 'heroicon-o-clock', route('community.index'), true, null],
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
                            <a href="{{ $canManageMessages ? '#' : route('pricing') }}" data-dashboard-tile="reminders" data-dashboard-tile-label="{{ __('E-Mail-Erinnerungen') }}" @if($canManageMessages) data-message-settings-open @endif class="group relative min-w-0 rounded-xl border border-orange-400/20 bg-orange-400/[.045] px-3 py-3 transition {{ $dashboardTileVisible('reminders') ? '' : 'hidden' }} {{ $canManageMessages ? 'hover:border-orange-300/45 hover:bg-orange-400/[.10]' : 'dashboard-plan-locked' }}" title="{{ $canManageMessages ? __('Nachrichten verwalten') : __('Ab Pro verfügbar') }}">
                                @unless($canManageMessages)<span class="dashboard-plan-mini-badge">PRO</span>@endunless
                                <span class="flex items-center gap-2">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400 group-hover:border-orange-200/45"><x-dynamic-component component="heroicon-o-envelope" class="h-4 w-4" /></span>
                                    <b class="text-lg font-black tabular-nums text-[var(--ak-text)]">{{ $messageReminders->where('active', true)->where('expired', false)->count() }}</b>
                                </span>
                                <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('E-Mail-Erinnerungen') }}</small>
                            </a>
                            <a href="{{ route('profile.mobile-view') }}" data-dashboard-tile="mobile-view" data-dashboard-tile-label="{{ __('Mobile Ansicht') }}" class="group relative min-w-0 rounded-xl border border-cyan-400/20 bg-cyan-400/[.045] px-3 py-3 transition hover:border-cyan-300/45 hover:bg-cyan-400/[.10] {{ $dashboardTileVisible('mobile-view') ? '' : 'hidden' }}">
                                <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-device-phone-mobile class="h-4 w-4" /></span><x-heroicon-o-arrow-up-right class="ml-auto h-4 w-4 text-cyan-300" /></span>
                                <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Mobile Ansicht') }}</small>
                            </a>
                            @if ($topStockToday)
                                @php $topStockScore = \App\Support\AiScore::toPercent(is_numeric($topStockToday->ai_score) ? $topStockToday->ai_score : $topStockToday->prediction_score); @endphp
                                <a href="{{ route('stocks.show', ['symbol' => $topStockToday->symbol, 'prediction' => $topStockToday->prediction_id, 'return_to' => '/dashboard']) }}" data-dashboard-tile="best-buy" data-dashboard-tile-label="{{ __('Beste POSITIV-Aktie') }}" class="group min-w-0 rounded-xl border border-emerald-400/25 bg-emerald-400/[.055] px-3 py-3 transition hover:border-emerald-300/50 hover:bg-emerald-400/[.11] {{ $dashboardTileVisible('best-buy') ? '' : 'hidden' }}" title="{{ $topStockToday->name }}">
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-emerald-400/25 bg-emerald-400/10 text-emerald-400"><x-heroicon-o-trophy class="h-4 w-4" /></span><span class="min-w-0"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $topStockToday->symbol }}</b><small class="block text-[8px] font-black text-emerald-400">{{ $topStockScore !== null ? number_format($topStockScore, 0, ',', '.').'/100' : '—' }}</small></span></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Beste POSITIV-Aktie') }}</small>
                                </a>
                            @else
                                <div data-dashboard-tile="best-buy" data-dashboard-tile-label="{{ __('Beste POSITIV-Aktie') }}" class="min-w-0 rounded-xl border border-orange-400/15 bg-orange-400/[.025] px-3 py-3 opacity-70 {{ $dashboardTileVisible('best-buy') ? '' : 'hidden' }}"><span class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg border border-orange-400/20 text-orange-400"><x-heroicon-o-trophy class="h-4 w-4" /></span><b class="text-lg text-[var(--ak-muted)]">—</b></span><small class="mt-2 block truncate text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Beste POSITIV-Aktie') }}</small></div>
                            @endif
                            @php
                                $topWatchScore = $topWatchStock ? \App\Support\AiScore::toPercent(is_numeric($topWatchStock->ai_score) ? $topWatchStock->ai_score : $topWatchStock->prediction_score) : null;
                            @endphp
                            @if ($topWatchStock && $canManageMessages)
                                <a href="{{ route('stocks.show', ['symbol' => $topWatchStock->symbol, 'prediction' => $topWatchStock->prediction_id, 'return_to' => '/dashboard']) }}" data-dashboard-tile="best-wait" data-dashboard-tile-label="{{ __('Beste WATCH-Aktie') }}" class="group min-w-0 rounded-xl border border-amber-400/25 bg-amber-400/[.055] px-3 py-3 transition hover:border-amber-300/50 hover:bg-amber-400/[.11] {{ $dashboardTileVisible('best-wait') ? '' : 'hidden' }}" title="{{ $topWatchStock->name }}">
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-amber-400/25 bg-amber-400/10 text-amber-400"><x-heroicon-o-eye class="h-4 w-4" /></span><span class="min-w-0"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $topWatchStock->symbol }}</b><small class="block text-[8px] font-black text-amber-400">{{ $topWatchScore !== null ? number_format($topWatchScore, 0, ',', '.').'/100' : '—' }}</small></span></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Beste WATCH-Aktie') }}</small>
                                </a>
                            @else
                                <div data-dashboard-tile="best-wait" data-dashboard-tile-label="{{ __('Beste WATCH-Aktie') }}" class="relative min-w-0 rounded-xl border border-slate-500/20 bg-slate-500/[.035] px-3 py-3 opacity-55 {{ $dashboardTileVisible('best-wait') ? '' : 'hidden' }}" title="{{ $canManageMessages ? __('Keine WATCH-Aktie verfügbar') : __('Ab Pro verfügbar') }}">
                                    @unless($canManageMessages)<span class="ak-plan-badge ak-plan-badge--pro absolute right-2 top-2">PRO</span>@endunless
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-slate-500/25 text-slate-400"><x-heroicon-o-eye class="h-4 w-4" /></span><span class="min-w-0"><b class="block truncate text-sm font-black text-slate-400">{{ $topWatchStock?->symbol ?: '—' }}</b><small class="block text-[8px] font-black text-slate-500">{{ $topWatchScore !== null ? number_format($topWatchScore, 0, ',', '.').'/100' : '—' }}</small></span></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-slate-500">{{ __('Beste WATCH-Aktie') }}</small>
                                </div>
                            @endif
                            @foreach ([
                                ['chartview', __('ChartView'), 'heroicon-o-chart-bar-square', route('predictions.chartview-signals')],
                                ['watchlist-screener', __('Watchlist im Screener'), 'heroicon-o-funnel', route('screener.index', ['bestand' => 'watchlists'])],
                                ['predictions', __('Prognosetabelle'), 'heroicon-o-table-cells', route('predictions.index')],
                                ['smart-screener', __('Smart Screener'), 'heroicon-o-magnifying-glass', route('screener.index')],
                                ['market-report', __('Aktuelle Marktlage'), 'heroicon-o-globe-europe-africa', route('daily-market-analysis')],
                            ] as [$tileId, $label, $icon, $url])
                                <a href="{{ $url }}" data-dashboard-tile="{{ $tileId }}" data-dashboard-tile-label="{{ $label }}" class="group min-w-0 rounded-xl border border-cyan-400/20 bg-cyan-400/[.045] px-3 py-3 transition hover:border-cyan-300/45 hover:bg-cyan-400/[.10] {{ $dashboardTileVisible($tileId) ? '' : 'hidden' }}">
                                    <span class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/10 text-cyan-300"><x-dynamic-component :component="$icon" class="h-4 w-4" /></span><x-heroicon-o-arrow-up-right class="ml-auto h-4 w-4 text-cyan-300" /></span>
                                    <small class="mt-2 block truncate text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </a>
                            @endforeach
                        </div>
                    </article>
                    <article data-dashboard-card="community" data-dashboard-width="1" data-dashboard-height="2" data-dashboard-size="2" style="--dashboard-card-order:{{ $dashboardCardOrder('community') }}" class="dashboard-bento-community dashboard-mobile-community ak-card ak-dashboard-card hidden min-h-0 flex-col overflow-hidden border-cyan-400/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ route('community.index') }}" class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-user-group class="h-5 w-5" /></span>
                                <span class="min-w-0">
                                    <span class="block text-[9px] font-black uppercase tracking-[.2em] text-cyan-300">{{ __('Community') }}</span>
                                    <strong class="mt-0.5 block truncate text-base font-black text-[var(--ak-text)]">{{ __('Community entdecken') }}</strong>
                                </span>
                            </a>
                            <a href="{{ route('community.index') }}" class="inline-flex shrink-0 items-center gap-1 text-[9px] font-black text-cyan-300">{{ __('Alle') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" /></a>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ([
                                [__('Beiträge'), $communityOverview['posts']],
                                [__('Mitglieder'), $communityOverview['members']],
                                [__('Letzte 7 Tage'), $communityOverview['recent']],
                            ] as [$label, $count])
                                <a href="{{ route('community.index') }}" class="min-w-0 rounded-xl border border-cyan-400/20 bg-cyan-400/[.045] px-2 py-2.5 text-center">
                                    <b class="block truncate text-base font-black tabular-nums text-[var(--ak-text)]">{{ number_format($count, 0, ',', '.') }}</b>
                                    <small class="mt-1 block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </a>
                            @endforeach
                        </div>
                    </article>
                </div>

                <style>
                    .dashboard-market-overview-grid { display:grid; grid-template-columns:minmax(0,1fr); gap:.25rem; align-content:start; }
                    #dashboard-middle-column > [data-dashboard-card="strategy"] { grid-column:auto !important; grid-row:auto !important; width:100%; height:auto !important; }
                    .aki-profile-universe-grid { grid-template-columns:minmax(0,1fr); }
                    .dashboard-bento-personal [data-dashboard-tile] { --personal-tile-accent: 34 211 238; }
                    .dashboard-bento-personal [data-dashboard-tile="strategies"] { --personal-tile-accent: 14 165 233; }
                    .dashboard-bento-personal [data-dashboard-tile="reminders"] { --personal-tile-accent: 245 158 11; }
                    .dashboard-bento-personal [data-dashboard-tile="watchlists"] { --personal-tile-accent: 168 85 247; }
                    .dashboard-bento-personal [data-dashboard-tile="watchlist-screener"] { --personal-tile-accent: 59 130 246; }
                    .dashboard-bento-personal [data-dashboard-tile="paper-depots"] { --personal-tile-accent: 16 185 129; }
                    .dashboard-bento-personal [data-dashboard-tile="market-report"] { --personal-tile-accent: 6 182 212; }
                    .dashboard-bento-personal [data-dashboard-tile="smart-screener"] { --personal-tile-accent: 20 184 166; }
                    .dashboard-bento-personal [data-dashboard-tile="labels"] { --personal-tile-accent: 234 179 8; }
                    .dashboard-bento-personal [data-dashboard-tile="chartview"] { --personal-tile-accent: 244 114 182; }
                    .dashboard-bento-personal [data-dashboard-tile] > span:first-of-type > span:first-child {
                        border-color: rgb(var(--personal-tile-accent) / .32) !important;
                        background: rgb(var(--personal-tile-accent) / .09) !important;
                        color: rgb(var(--personal-tile-accent)) !important;
                    }
                    .dashboard-bento-personal [data-dashboard-tile] > span:first-of-type > svg {
                        color: rgb(var(--personal-tile-accent)) !important;
                        opacity: .82;
                    }
                </style>
                @include('partials.dashboard-market-overview-column')

                <script>
                    (() => {
                        document.addEventListener('DOMContentLoaded', () => {
                            if (!window.matchMedia('(min-width: 1280px)').matches) return;
                            const marketShell = document.getElementById('dashboard-middle-column');
                            const marketOverview = document.getElementById('dashboard-market-overview-card');
                            const dailyTips = document.getElementById('dashboard-daily-tips-card');
                            const dashboardGrid = marketShell?.closest('.dashboard-bento');
                            if (!marketShell || !marketOverview || !dailyTips || !dashboardGrid) return;

                            // Preserve the original, independent cards in the right column.
                            dashboardGrid.appendChild(dailyTips);
                            dashboardGrid.appendChild(marketOverview);
                            marketShell.classList.add('dashboard-market-shell-empty');

                            const rankingRows = document.getElementById('dashboard-ranking-stocks-row');
                            const opportunityRows = document.getElementById('dashboard-best-stocks-row');
                            const alignStockRows = () => {
                                if (!rankingRows || !opportunityRows) return;
                                if (!window.matchMedia('(min-width: 1280px)').matches) {
                                    rankingRows.style.paddingTop = '';
                                    opportunityRows.style.paddingTop = '';
                                    return;
                                }

                                rankingRows.style.paddingTop = '0px';
                                opportunityRows.style.paddingTop = '0px';
                                window.requestAnimationFrame(() => {
                                    const rankingTop = rankingRows.getBoundingClientRect().top;
                                    const opportunityTop = opportunityRows.getBoundingClientRect().top;
                                    const targetTop = Math.max(rankingTop, opportunityTop);
                                    rankingRows.style.paddingTop = `${Math.max(0, targetTop - rankingTop)}px`;
                                    opportunityRows.style.paddingTop = `${Math.max(0, targetTop - opportunityTop)}px`;
                                });
                            };

                            alignStockRows();
                            window.addEventListener('load', alignStockRows, { once: true });
                            window.addEventListener('resize', alignStockRows, { passive: true });
                            document.fonts?.ready.then(alignStockRows);
                        });
                    })();
                </script>

                @include('partials.dashboard-middle-column')

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
                                    <a href="{{ route('predictions.index', ['signal' => 'BUY']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-cyan-400/20 bg-cyan-400/[.10] px-1 py-1 text-cyan-300 transition hover:border-cyan-300/60 hover:bg-cyan-400/20" title="{{ __('POSITIV-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('POSITIV-Aktien in der Prognosetabelle anzeigen') }}">P {{ $continent['buy'] }}</a>
                                    <a href="{{ route('predictions.index', ['signal' => 'WATCH']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-amber-400/25 bg-amber-400/[.10] px-1 py-1 text-amber-300 transition hover:border-amber-300/60 hover:bg-amber-400/20" title="{{ __('WATCH-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('WATCH-Aktien in der Prognosetabelle anzeigen') }}">W {{ $continent['watch'] }}</a>
                                    <a href="{{ route('predictions.index', ['signal' => 'HOLD']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-slate-400/25 bg-slate-400/[.10] px-1 py-1 text-slate-300 transition hover:border-slate-300/60 hover:bg-slate-400/20" title="{{ __('HOLD-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('HOLD-Aktien in der Prognosetabelle anzeigen') }}">H {{ $continent['hold'] }}</a>
                                    <a href="{{ route('predictions.index', ['signal' => 'SELL']) }}" class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-rose-400/25 bg-rose-400/[.10] px-1 py-1 text-rose-300 transition hover:border-rose-300/60 hover:bg-rose-400/20" title="{{ __('SELL-Aktien in der Prognosetabelle anzeigen') }}" aria-label="{{ __('SELL-Aktien in der Prognosetabelle anzeigen') }}">S {{ $continent['sell'] }}</a>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </article>
                @include('partials.dashboard-right-column')
                <article x-data="{ scheduleOpen: true }" data-dashboard-card="schedule" data-dashboard-size="{{ $dashboardCardSize('schedule') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('schedule') }}" class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-cyan-400/35 p-4 {{ $dashboardCardVisible('schedule') ? '' : 'hidden' }}">
                    <div class="dashboard-collapsible-header flex items-center justify-between gap-3 mb-3"><div class="flex min-w-0 flex-1 items-center gap-3 text-left"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-600"><x-heroicon-o-calendar-days class="h-5 w-5" /></span><span class="min-w-0"><span class="block text-[9px] font-black uppercase tracking-[.16em] text-cyan-600">{{ __('Planung') }}</span><span class="mt-1 block truncate text-base font-black text-[var(--ak-text)]">{{ __('Termine & Erinnerungen') }}</span><span class="mt-1 flex items-center gap-1 text-[8px] font-black text-amber-500"><x-heroicon-o-envelope class="h-3 w-3 text-amber-500" />{{ $dashboardScheduleItems->count() }} {{ __('Termine') }}</span></span></div></div>
                    <div x-show="scheduleOpen" x-cloak x-transition.opacity class="grid min-h-0 flex-1 gap-2 overflow-hidden">
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
                        <button type="button" data-message-settings-open class="mt-1 inline-flex items-center justify-center gap-2 rounded-lg border border-amber-400/25 px-3 py-2 text-[9px] font-black text-amber-500"><x-heroicon-o-cog-6-tooth class="h-4 w-4" />{{ __('Erinnerungen verwalten') }}</button>
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
        <div id="dashboard-cards-modal" class="ak-modal-overlay fixed inset-0 z-[180] hidden place-items-center overflow-y-auto bg-slate-950/75 p-2 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-labelledby="dashboard-cards-title">
            <section class="ak-modal-panel dashboard-modal-panel flex w-full max-w-[1200px] flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl" style="height:min(720px,78vh);max-height:calc(100vh - 1rem)">
                <header class="dashboard-modal-header flex shrink-0 items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-4 py-3">
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
        <div id="dashboard-layout-modal" class="ak-modal-overlay fixed inset-0 z-[185] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-layout-title">
            <form method="POST" action="{{ route('dashboard.layout.update') }}" class="ak-modal-panel dashboard-modal-panel flex h-[calc(100vh-2rem)] max-h-[820px] w-full max-w-[1500px] flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl">
                @csrf
                @method('PATCH')
                <header class="dashboard-modal-header flex shrink-0 items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-5 py-4">
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
            <div id="message-settings-modal" class="ak-modal-overlay fixed inset-0 z-[190] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="message-settings-title">
                <section class="ak-modal-panel dashboard-modal-panel flex max-h-[min(720px,90dvh)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl">
                    <header class="dashboard-modal-header flex items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-5 py-4">
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
                        <section class="mt-5 border-t border-cyan-400/20 pt-4">
                            <div class="mb-3 flex items-center justify-between gap-3"><div><h3 class="text-sm font-black">{{ __('Termine und Erinnerungen') }}</h3><p class="mt-1 text-[10px] text-[var(--ak-muted)]">{{ __('Persönliche E-Mail-Erinnerungen bleiben auch nach Versand oder Ablauf sichtbar, damit du sie löschen oder neu terminieren kannst. Unternehmenstermine werden automatisch aktualisiert.') }}</p></div><span class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.06] px-2.5 py-1 text-[10px] font-black text-cyan-300">{{ $allScheduleItems->count() }}</span></div>
                            <div class="grid gap-2">
                                @forelse($allScheduleItems as $item)
                                    @php
                                        $isPredictionReminder = ($item['type'] ?? null) === 'prediction';
                                        $isSignalReminder = ($item['type'] ?? null) === 'signal';
                                        $isCorporateEvent = ($item['type'] ?? null) === 'earnings';
                                        $isEditableReminder = $isPredictionReminder || $isSignalReminder;
                                        $isExpiredReminder = $isPredictionReminder && ($item['expired'] ?? false);
                                        $isSentReminder = $isPredictionReminder && ($item['status'] ?? null) === 'sent';
                                        $toggleRoute = $isPredictionReminder
                                            ? route(($item['active'] ?? false) ? 'notifications.purchase-reminders.disable' : 'notifications.purchase-reminders.enable', $item['id'])
                                            : ($isSignalReminder ? route(($item['active'] ?? false) ? 'notifications.entry-alerts.disable' : 'notifications.entry-alerts.enable', $item['id']) : null);
                                        $deleteRoute = $isPredictionReminder
                                            ? route('notifications.purchase-reminders.destroy', $item['id'])
                                            : ($isSignalReminder ? route('notifications.entry-alerts.destroy', $item['id']) : null);
                                    @endphp
                                    <article class="rounded-xl border border-cyan-400/15 bg-cyan-400/[.025] p-3">
                                        <div class="flex items-start gap-3">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $isEditableReminder ? 'bg-cyan-400/10 text-cyan-300' : 'bg-amber-400/10 text-amber-300' }}">@if($isEditableReminder)<x-heroicon-o-envelope class="h-4 w-4" />@else<x-heroicon-o-calendar-days class="h-4 w-4" />@endif</span>
                                            <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><b class="text-xs text-[var(--ak-text)]">{{ $item['name'] ?: $item['symbol'] }}</b><span class="rounded-md border border-[var(--ak-border)] px-1.5 py-0.5 text-[8px] font-black text-[var(--ak-muted)]">{{ $item['symbol'] }}</span>@if($isEditableReminder)<span class="rounded-md border px-1.5 py-0.5 text-[8px] font-black {{ ($isExpiredReminder || $isSentReminder) ? 'border-amber-400/25 bg-amber-400/10 text-amber-300' : (($item['active'] ?? false) ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-300' : 'border-slate-500/25 bg-slate-500/10 text-slate-400') }}">{{ $isSentReminder ? __('VERSENDET') : ($isExpiredReminder ? __('ABGELAUFEN') : (($item['active'] ?? false) ? __('AKTIV') : __('DEAKTIVIERT'))) }}</span>@endif</div><p class="mt-1 text-[10px] font-bold text-cyan-300">{{ $item['label'] }}</p><p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ $item['schedule'] }}</p></div>
                                        </div>
                                        @if($isEditableReminder)
                                            <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-cyan-400/10 pt-3">
                                                @if($isPredictionReminder && ! $isSentReminder)
                                                    <form method="POST" action="{{ route('notifications.purchase-reminders.reschedule', $item['id']) }}" class="flex min-w-[210px] flex-1 items-end gap-2">@csrf @method('PATCH')<label class="min-w-0 flex-1"><span class="mb-1 block text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Termin') }}</span><input type="date" name="remind_on" value="{{ $item['date'] }}" min="{{ now()->toDateString() }}" required class="ak-input h-9 w-full px-2 text-xs"></label><button type="submit" class="h-9 rounded-lg border border-cyan-400/25 bg-cyan-400/10 px-3 text-[9px] font-black text-cyan-300">{{ __('Speichern') }}</button></form>
                                                @endif
                                                @unless($isExpiredReminder || $isSentReminder)<form method="POST" action="{{ $toggleRoute }}">@csrf @method('PATCH')<button type="submit" class="h-9 rounded-lg border px-3 text-[9px] font-black {{ ($item['active'] ?? false) ? 'border-amber-400/25 text-amber-300' : 'border-emerald-400/25 text-emerald-300' }}">{{ ($item['active'] ?? false) ? __('Deaktivieren') : __('Aktivieren') }}</button></form>@endunless
                                                <form method="POST" action="{{ $deleteRoute }}" onsubmit="return confirm('{{ __('Nur diese einzelne Erinnerung wird gelöscht. Deine übrigen E-Mail-Einstellungen bleiben unverändert. Möchtest du fortfahren?') }}')">@csrf @method('DELETE')<button type="submit" class="grid h-9 w-9 place-items-center rounded-lg border border-rose-400/25 text-rose-300" title="{{ __('Löschen') }}"><x-heroicon-o-trash class="h-4 w-4" /></button></form>
                                            </div>
                                        @elseif($isCorporateEvent)
                                            <div class="mt-3 flex justify-end border-t border-cyan-400/10 pt-3">
                                                <form method="POST" action="{{ route('profile.dashboard-schedule-events.destroy', $item['id']) }}" onsubmit="return confirm('{{ __('Diesen automatischen Unternehmenstermin nur aus deinem Dashboard entfernen?') }}')">@csrf @method('DELETE')<button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-400/25 px-3 text-[9px] font-black text-rose-300" title="{{ __('Entfernen') }}"><x-heroicon-o-trash class="h-4 w-4" />{{ __('Entfernen') }}</button></form>
                                            </div>
                                        @endif
                                    </article>
                                @empty
                                    <div class="rounded-xl border border-dashed border-cyan-400/20 p-6 text-center text-xs text-[var(--ak-muted)]">{{ __('Keine anstehenden Termine oder Erinnerungen.') }}</div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </section>
            </div>

            <div id="message-preview-modal" class="ak-modal-overlay fixed inset-0 z-[205] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="message-preview-title">
                <section class="ak-modal-panel dashboard-modal-panel flex max-h-[90dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] text-[var(--ak-text)] shadow-2xl">
                    <header class="dashboard-modal-header flex items-center justify-between gap-3 border-b border-cyan-400/20 bg-cyan-400/[.06] px-5 py-4">
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

        <div id="dashboard-aki-modal" class="ak-modal-overlay fixed inset-0 z-[200] hidden place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-aki-title">
            <section class="ak-modal-panel dashboard-modal-panel w-full max-w-2xl overflow-hidden rounded-2xl border border-teal-300/45 bg-[#0d1b2d] text-slate-100 shadow-2xl" style="max-height:calc(100dvh - 2rem);display:flex;flex-direction:column;">
                <header class="dashboard-modal-header flex items-center justify-between border-b border-teal-300/25 px-4 py-3">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-500">{{ __('Assistent') }}</p><h2 id="dashboard-aki-title" class="text-base font-black text-slate-100">{{ __('AKI fragen') }}</h2></div>
                    <button type="button" data-dashboard-aki-close class="rounded-lg p-2 text-slate-300 hover:bg-white/10" aria-label="{{ __('Chat schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div id="dashboard-aki-messages" class="min-h-0 space-y-2 overscroll-contain p-5" style="background:rgba(9,28,45,.82) !important;height:45vh;max-height:500px;overflow-y:scroll;flex:0 1 auto;"><p class="max-w-[92%] rounded-xl border border-teal-300/20 bg-transparent px-3 py-2 text-xs leading-5 text-slate-100">{{ __('Du kannst zum Beispiel fragen: „Was kannst du mir heute empfehlen?“') }}</p></div>
                <form id="dashboard-aki-form" class="flex gap-2 border-t border-teal-300/20 p-3" style="background:rgba(10,30,47,.96) !important;">
                    <input id="dashboard-aki-input" type="text" class="min-w-0 flex-1 rounded-lg border border-teal-300/30 bg-slate-950/65 px-3 py-2 text-xs text-slate-100 placeholder:text-slate-400" placeholder="{{ __('Was kannst du mir heute empfehlen?') }}" autocomplete="off">
                    <button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-black text-white hover:bg-teal-500">{{ __('Senden') }}</button>
                </form>
            </section>
        </div>

        <div id="dashboard-card-help-modal" class="ak-modal-overlay fixed inset-0 z-[215] hidden place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-card-help-title">
            <section class="ak-modal-panel dashboard-modal-panel w-full max-w-md overflow-hidden rounded-2xl border border-cyan-300/45 bg-[#0d1b2d] shadow-2xl">
                <header class="dashboard-modal-header flex items-center justify-between gap-3 border-b border-cyan-300/25 px-4 py-3">
                    <div>
                        <p class="dashboard-card-help-eyebrow text-[10px] font-black uppercase tracking-[.16em]">{{ __('Hilfe') }}</p>
                        <h2 id="dashboard-card-help-title" class="text-base font-black"></h2>
                    </div>
                    <button type="button" data-dashboard-card-help-close class="rounded-lg p-2 hover:bg-white/10" aria-label="{{ __('Schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div id="dashboard-card-help-body" class="space-y-2 p-5 text-[13px] leading-6"></div>
            </section>
        </div>
    </main>
    <script>
        (() => {
            const help = @json($dashboardCardHelp);
            const titles = @json($dashboardMainCardLabels);
            const modal = document.getElementById('dashboard-card-help-modal');
            if (!modal) return;
            const titleEl = document.getElementById('dashboard-card-help-title');
            const bodyEl = document.getElementById('dashboard-card-help-body');

            const helpLabel = @json(__('Hilfe'));

            const open = (id) => {
                const paragraphs = help[id];
                if (!paragraphs) return;
                titleEl.textContent = titles[id] || '';
                bodyEl.replaceChildren(...paragraphs.map((text) => {
                    const p = document.createElement('p');
                    p.textContent = text;
                    return p;
                }));
                modal.classList.remove('hidden');
                modal.classList.add('grid');
            };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };

            const mount = () => {
                const done = new Set([...document.querySelectorAll('.dashboard-card-help-btn')].map((b) => b.dataset.helpFor));
                const add = (id, card) => {
                    if (!id || !help[id] || done.has(id) || !card) return;
                    // Prefer an explicit inline slot inside the card header, if one exists.
                    const anchor = document.querySelector('[data-help-anchor="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]');
                    const host = anchor || card;
                    if (host.querySelector(':scope > .dashboard-card-help-btn')) { done.add(id); return; }
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = anchor ? 'dashboard-card-help-btn dashboard-card-help-btn--inline' : 'dashboard-card-help-btn';
                    btn.dataset.helpFor = id;
                    btn.textContent = '?';
                    btn.setAttribute('aria-label', (titles[id] || id) + ' – ' + helpLabel);
                    btn.addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); open(id); });
                    anchor ? anchor.prepend(btn) : card.appendChild(btn);
                    done.add(id);
                };
                document.querySelectorAll('[data-help-card]').forEach((c) => add(c.dataset.helpCard, c));
                document.querySelectorAll('[data-dashboard-card], [data-mobile-dashboard-card]').forEach((c) => add(c.dataset.dashboardCard || c.dataset.mobileDashboardCard, c));
            };
            mount();
            window.addEventListener('load', mount);
            document.querySelectorAll('.dashboard-bento, #personal-dashboard').forEach((grid) => {
                new MutationObserver(mount).observe(grid, { childList: true });
            });

            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            modal.querySelector('[data-dashboard-card-help-close]')?.addEventListener('click', close);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) close();
            });
        })();
    </script>
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
            document.querySelectorAll('[data-message-settings-open]').forEach((button) => button.addEventListener('click', (event) => { event.preventDefault(); open(); }));
            modal.querySelector('[data-message-settings-close]')?.addEventListener('click', close);
            modal.querySelectorAll('form input[name="_method"][value="DELETE"]').forEach((method) => method.form?.addEventListener('submit', () => {
                if (method.form?.dataset.confirmApproved === '1') close();
            }));
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
            deleteForm.addEventListener('submit', () => {
                if (deleteForm.dataset.confirmApproved !== '1') return;
                close();
                const settingsModal = document.getElementById('message-settings-modal');
                settingsModal?.classList.add('hidden'); settingsModal?.classList.remove('grid');
            });
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
                const pending = document.createElement('p'); pending.className = 'flex max-w-[92%] items-center gap-2 rounded-xl border border-amber-300/20 bg-transparent px-3 py-2 text-xs text-amber-200'; pending.innerHTML = '<span>{{ __('AKI denkt') }}</span><span class="dashboard-aki-dots" aria-hidden="true">•••</span>'; messages.appendChild(pending); messages.scrollTop = messages.scrollHeight;
                try {
                    const response = await fetch('{{ route('aki.chat') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ question, messages: history.slice(-8), filters: {}, mode: 'standard' }) });
                    const payload = await response.json(); pending.remove();
                    const text = response.ok ? (payload.answer || '{{ __('Keine Antwort erhalten.') }}') : (payload.message || '{{ __('Die KI ist gerade nicht erreichbar.') }}');
                    const answer = document.createElement('p'); answer.className = 'max-w-[92%] whitespace-pre-line rounded-xl border border-teal-300/15 bg-transparent px-3 py-2 text-xs text-slate-100'; answer.textContent = text; messages.appendChild(answer); history.push({ role: 'assistant', content: text });
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
    @include('partials.dashboard-styles')
</x-app-layout>

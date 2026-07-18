<x-layouts.app title="Market Overview – aktienKI.com">
    @php
        $dashboard = $marketDashboard ?? $dashboard ?? [];
        $score = max(0, min(100, (float) data_get($dashboard, 'market_score', 82)));
        $trend = data_get($dashboard, 'market_trend', 'Bullish');
        $confidence = (float) data_get($dashboard, 'average_confidence', 87);
        if ($confidence <= 1) $confidence *= 100;

        $assets = collect(data_get($dashboard, 'assets', [
            ['symbol'=>'^GSPC','name'=>'S&P 500','price'=>'6.296,79','change_percent'=>0.54],
            ['symbol'=>'^IXIC','name'=>'NASDAQ','price'=>'20.885,65','change_percent'=>0.81],
            ['symbol'=>'^GDAXI','name'=>'DAX','price'=>'24.215,12','change_percent'=>0.22],
            ['symbol'=>'^VIX','name'=>'Volatilitätsindex','price'=>'16,32','change_percent'=>-2.10],
            ['symbol'=>'GC=F','name'=>'Gold','price'=>'3.351,80','change_percent'=>0.35],
            ['symbol'=>'CL=F','name'=>'Crude Oil','price'=>'66,71','change_percent'=>-0.44],
            ['symbol'=>'EURUSD=X','name'=>'EUR / USD','price'=>'1,1619','change_percent'=>0.12],
            ['symbol'=>'^TNX','name'=>'US 10Y Yield','price'=>'4,42 %','change_percent'=>0.05],
        ]));

        $factors = collect([
            ['title'=>'Momentum','text'=>'Breite Stärke in den Leitindizes','score'=>'+18'],
            ['title'=>'Makro','text'=>'Zins- und Währungslage bleibt stabil','score'=>'+11'],
            ['title'=>'Volatilität','text'=>'VIX signalisiert normales Risiko','score'=>'+8'],
            ['title'=>'KI-Konsens','text'=>'Mehrheit der Modelle bleibt positiv','score'=>'+15'],
        ]);

        $sectors = collect([
            ['name'=>'Technologie','score'=>84],
            ['name'=>'Industrie','score'=>72],
            ['name'=>'Finanzen','score'=>66],
            ['name'=>'Gesundheit','score'=>59],
            ['name'=>'Energie','score'=>43],
        ]);
    @endphp

    <section class="ak-page-header">
        <div>
            <div class="ak-badge">Global Market Intelligence</div>
            <h1 class="ak-title">Market <span>Overview</span></h1>
            <p class="ak-subtitle">
                Globale Märkte auf einen Blick – verdichtet durch Marktdaten,
                technische Faktoren und die AktienKI Market Intelligence Engine.
            </p>
        </div>

        <div class="ak-update">
            Letztes Update:
            {{ data_get($dashboard,'snapshot_time')
                ? \Carbon\Carbon::parse(data_get($dashboard,'snapshot_time'))->format('d.m.Y · H:i')
                : now()->format('d.m.Y · H:i') }}
        </div>
    </section>

    <section class="ak-card ak-market-hero">
        <div class="ak-score-panel">
            <div class="ak-score-ring" style="--score:{{ $score }}">
                <div class="ak-score-value">
                    <strong>{{ number_format($score,0,',','.') }}</strong>
                    <span>von 100</span>
                </div>
            </div>

            <div>
                <div class="ak-market-kicker">Overall Market Score</div>
                <div class="ak-market-state">Marktlage: <span>{{ $trend }}</span></div>
                <div class="ak-market-copy">
                    Momentum und Marktbreite stützen aktuell ein konstruktives Marktumfeld.
                    Die Volatilität bleibt im normalen Bereich.
                </div>
                <div class="ak-confidence">KI-Konfidenz {{ number_format($confidence,0,',','.') }} %</div>
            </div>
        </div>

        <div class="ak-main-chart">
            <svg viewBox="0 0 560 250" role="img" aria-label="Markttrend">
                <defs>
                    <linearGradient id="line" x1="0" x2="1">
                        <stop offset="0%" stop-color="#8b5cf6"/>
                        <stop offset="100%" stop-color="#22d3ee"/>
                    </linearGradient>
                    <linearGradient id="area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8b5cf6" stop-opacity=".28"/>
                        <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <g stroke="rgba(148,163,184,.13)" stroke-width="1">
                    <line x1="25" y1="42" x2="535" y2="42"/>
                    <line x1="25" y1="102" x2="535" y2="102"/>
                    <line x1="25" y1="162" x2="535" y2="162"/>
                    <line x1="25" y1="222" x2="535" y2="222"/>
                </g>
                <path d="M25 205 C70 190,95 178,132 186 S190 166,226 150 S281 164,320 125 S380 117,414 91 S482 75,535 48 L535 222 L25 222 Z" fill="url(#area)"/>
                <path d="M25 205 C70 190,95 178,132 186 S190 166,226 150 S281 164,320 125 S380 117,414 91 S482 75,535 48" fill="none" stroke="url(#line)" stroke-width="4" stroke-linecap="round"/>
                <circle cx="535" cy="48" r="7" fill="#22d3ee" stroke="#071020" stroke-width="5"/>
            </svg>
        </div>
    </section>

    <section class="ak-assets">
        @foreach($assets as $index => $asset)
            @php
                $change=(float)data_get($asset,'change_percent',0);
                $path=$index%3===0
                    ? 'M2 37 C20 32,30 34,42 25 S66 28,78 17 S100 13,118 6'
                    : ($index%3===1
                        ? 'M2 31 C18 25,31 28,44 18 S68 13,82 21 S103 12,118 8'
                        : 'M2 12 C20 18,33 14,49 23 S77 18,91 30 S108 27,118 36');
            @endphp

            <article class="ak-card ak-asset">
                <div class="ak-asset-top">
                    <span class="ak-symbol">{{ data_get($asset,'symbol') }}</span>
                    <span class="ak-change {{ $change >= 0 ? 'up' : 'down' }}">
                        {{ $change >= 0 ? '+' : '' }}{{ number_format($change,2,',','.') }} %
                    </span>
                </div>
                <div class="ak-price">{{ data_get($asset,'price',data_get($asset,'close','—')) }}</div>
                <div class="ak-name">{{ data_get($asset,'name') }}</div>
                <svg class="ak-spark" viewBox="0 0 120 44" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="spark{{ $index }}" x1="0" x2="1">
                            <stop offset="0%" stop-color="#8b5cf6"/>
                            <stop offset="100%" stop-color="#22d3ee"/>
                        </linearGradient>
                    </defs>
                    <path d="{{ $path }}" fill="none" stroke="url(#spark{{ $index }})" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </article>
        @endforeach
    </section>

    <section class="ak-grid-two">
        <article class="ak-card ak-section">
            <h2 class="ak-section-title">Warum diese Prognose?</h2>
            <p class="ak-section-sub">Die wichtigsten Einflussfaktoren der aktuellen Marktbewertung.</p>

            <div class="ak-factor-list">
                @foreach($factors as $factor)
                    <div class="ak-factor">
                        <div class="ak-factor-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M4 18V6m0 12h16M7 15l4-5 3 3 5-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <strong>{{ $factor['title'] }}</strong>
                            <small>{{ $factor['text'] }}</small>
                        </div>
                        <div class="ak-factor-score">{{ $factor['score'] }}</div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="ak-card ak-section">
            <h2 class="ak-section-title">Market Breadth</h2>
            <p class="ak-section-sub">Breite, Sektoren und Risikostimmung.</p>

            <div class="ak-breadth-cards">
                <div class="ak-mini">
                    <div class="ak-mini-label">Advance / Decline</div>
                    <div class="ak-mini-value">68 / 32</div>
                    <div class="ak-progress"><span style="width:68%"></span></div>
                </div>

                <div class="ak-mini">
                    <div class="ak-mini-label">Fear & Greed</div>
                    <div class="ak-mini-value">{{ data_get($dashboard,'fear_greed_label','Greed') }}</div>
                    <div class="ak-progress"><span style="width:72%"></span></div>
                </div>
            </div>

            <div class="ak-sector-list">
                @foreach($sectors as $sector)
                    <div class="ak-sector-row">
                        <span>{{ $sector['name'] }}</span>
                        <div class="ak-sector-bar"><span style="width:{{ $sector['score'] }}%"></span></div>
                        <strong>{{ $sector['score'] }}</strong>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="ak-card ak-scenarios">
        <h2 class="ak-section-title">Market Scenarios</h2>
        <p class="ak-section-sub">Wahrscheinlichkeitsgewichtete Szenarien für die nächste Marktphase.</p>

        <div class="ak-scenario-grid">
            <article class="ak-scenario">
                <div class="ak-scenario-top">
                    <div class="ak-scenario-name"><span class="ak-dot" style="background:#2ee6b8"></span>Bullish</div>
                    <strong>63 %</strong>
                </div>
                <p>Positive Marktbreite und stabiles Momentum setzen sich fort.</p>
            </article>

            <article class="ak-scenario">
                <div class="ak-scenario-top">
                    <div class="ak-scenario-name"><span class="ak-dot" style="background:#facc15"></span>Neutral</div>
                    <strong>24 %</strong>
                </div>
                <p>Seitwärtsphase mit wechselnder Sektorführung.</p>
            </article>

            <article class="ak-scenario">
                <div class="ak-scenario-top">
                    <div class="ak-scenario-name"><span class="ak-dot" style="background:#fb7185"></span>Bearish</div>
                    <strong>13 %</strong>
                </div>
                <p>Steigende Volatilität oder schwächere Makrodaten belasten den Markt.</p>
            </article>
        </div>
    </section>
</x-layouts.app>

@php
    $stockCount = $stockCount ?? 1250;
    $sectorCount = $sectorCount ?? 11;
    $dataPoints = $dataPoints ?? '100M+';
    $modelCount = $modelCount ?? 5;

    $topScores = $topScores ?? [
        ['symbol' => 'AAPL', 'name' => 'Apple', 'score' => 85, 'signal' => 'Kaufen'],
        ['symbol' => 'NVDA', 'name' => 'NVIDIA', 'score' => 78, 'signal' => 'Kaufen'],
        ['symbol' => 'MSFT', 'name' => 'Microsoft', 'score' => 65, 'signal' => 'Halten'],
        ['symbol' => 'BMW.DE', 'name' => 'BMW', 'score' => 32, 'signal' => 'Verkaufen'],
    ];
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AktienKI – Machine Learning Aktienanalyse</title>
    @vite(['resources/css/app.css', 'resources/css/welcome-premium.css', 'resources/js/app.js', 'resources/js/welcome-premium.js'])
</head>

<body class="ak-body">
    <div class="ak-bg-grid"></div>
    <div class="ak-noise"></div>
    <div class="ak-orb ak-orb-one"></div>
    <div class="ak-orb ak-orb-two"></div>
    <div class="ak-orb ak-orb-three"></div>

    <main class="ak-page">
        <nav class="ak-nav">
            <a href="{{ url('/') }}" class="ak-brand">
                <span class="ak-brand-mark">
                    <svg viewBox="0 0 48 48" aria-hidden="true">
                        <defs>
                            <linearGradient id="brandGradient" x1="0" x2="1" y1="0" y2="1">
                                <stop offset="0%" stop-color="#fb923c"/>
                                <stop offset="55%" stop-color="#38bdf8"/>
                                <stop offset="100%" stop-color="#a855f7"/>
                            </linearGradient>
                        </defs>
                        <rect x="6" y="6" width="36" height="36" rx="13" fill="url(#brandGradient)" opacity=".18"/>
                        <path d="M14 31V18M14 31H35M18 27L23 21L27 25L34 15" stroke="url(#brandGradient)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="34" cy="15" r="3" fill="#fb923c"/>
                    </svg>
                </span>
                <span>AktienKI</span>
            </a>

            <div class="ak-nav-links">
                <a href="#workflow">So funktioniert es</a>
                <a href="#features">Vorteile</a>
                @auth
                    <a class="ak-btn ak-btn-primary" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="ak-btn" href="{{ route('login') }}">Login</a>
                    <a class="ak-btn ak-btn-primary" href="{{ route('register') }}">Jetzt starten</a>
                @endauth
            </div>
        </nav>

        <section class="ak-hero">
            <div class="ak-hero-copy">
                <div class="ak-kicker">
                    <span></span>
                    Machine Learning · Multi Asset Analyse · KI Score
                </div>

                <h1>Aktienanalyse, die Zusammenhänge erkennt.</h1>

                <p>
                    AktienKI importiert Kursdaten und Fundamentaldaten der letzten 10 Jahre, trainiert
                    Machine-Learning-Modelle und erstellt täglich einen verständlichen Score für jede Aktie.
                </p>

                <div class="ak-hero-actions">
                    <a href="{{ route('register') }}" class="ak-cta">Als Tester registrieren</a>
                    <a href="#workflow" class="ak-ghost">Ablauf ansehen</a>
                </div>

                <div class="ak-stats">
                    <div><strong>{{ number_format($stockCount, 0, ',', '.') }}+</strong><span>Aktien</span></div>
                    <div><strong>{{ $sectorCount }}</strong><span>Sektoren</span></div>
                    <div><strong>{{ $dataPoints }}</strong><span>Datenpunkte</span></div>
                    <div><strong>{{ $modelCount }}+</strong><span>ML Modelle</span></div>
                </div>
            </div>

            <section id="workflow" class="ak-showcase" aria-label="AktienKI Workflow Animation">
                <div class="ak-showcase-header">
                    <div>
                        <span class="ak-panel-label">Live Workflow</span>
                        <h2>Vom Datenimport zum KI-Score</h2>
                    </div>

                    <div class="ak-step-pills">
                        <button class="ak-step-pill is-active" data-step="0">01 Daten</button>
                        <button class="ak-step-pill" data-step="1">02 Training</button>
                        <button class="ak-step-pill" data-step="2">03 Score</button>
                    </div>
                </div>

                <div class="ak-visual-shell">
                    <div class="ak-visual-lines" aria-hidden="true">
                        <svg viewBox="0 0 1000 560" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="lineBlue" x1="0" x2="1">
                                    <stop offset="0%" stop-color="#38bdf8" stop-opacity="0"/>
                                    <stop offset="50%" stop-color="#38bdf8" stop-opacity=".75"/>
                                    <stop offset="100%" stop-color="#a855f7" stop-opacity="0"/>
                                </linearGradient>
                                <linearGradient id="lineTeal" x1="0" x2="1">
                                    <stop offset="0%" stop-color="#fb923c" stop-opacity="0"/>
                                    <stop offset="50%" stop-color="#22d3ee" stop-opacity=".85"/>
                                    <stop offset="100%" stop-color="#22c55e" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path class="ak-main-flow ak-flow-a" d="M70 120 C260 80 330 240 500 210 C680 180 720 105 930 140" stroke="url(#lineBlue)" />
                            <path class="ak-main-flow ak-flow-b" d="M60 420 C230 310 340 420 505 360 C670 300 760 420 940 360" stroke="url(#lineTeal)" />
                            <path class="ak-main-flow ak-flow-c" d="M120 270 C260 210 380 290 500 280 C640 270 730 230 880 270" stroke="url(#lineBlue)" />
                        </svg>
                    </div>

                    <article class="ak-scene ak-scene-data is-active" data-scene="0">
                        <div class="ak-scene-copy">
                            <div class="ak-scene-number">01</div>
                            <h3>Kursdaten & Fundamentaldaten werden importiert</h3>
                            <p>Das System lädt große Mengen historischer Daten: Kurse, Volumen, Dividenden, Bilanzdaten, Cashflow, Margen und Bewertungskennzahlen über 10 Jahre.</p>
                        </div>

                        <div class="ak-data-stage">
                            <div class="ak-source-card ak-source-left">
                                <span>Kursdaten</span>
                                <strong>OHLCV · 10 Jahre</strong>
                                <small>Close, Open, High, Low, Volumen</small>
                            </div>
                            <div class="ak-source-card ak-source-right">
                                <span>Fundamentaldaten</span>
                                <strong>Bilanz · Cashflow</strong>
                                <small>KGV, Umsatz, Marge, Gewinn</small>
                            </div>

                            <svg class="ak-data-svg" viewBox="0 0 760 360" aria-hidden="true">
                                <defs>
                                    <linearGradient id="dataPipe" x1="0" x2="1">
                                        <stop offset="0" stop-color="#38bdf8" stop-opacity="0"/>
                                        <stop offset=".5" stop-color="#fb923c" stop-opacity=".9"/>
                                        <stop offset="1" stop-color="#a855f7" stop-opacity=".1"/>
                                    </linearGradient>
                                    <radialGradient id="dbGlow">
                                        <stop offset="0" stop-color="#fb923c" stop-opacity=".8"/>
                                        <stop offset="1" stop-color="#0f172a" stop-opacity=".15"/>
                                    </radialGradient>
                                </defs>

                                <path class="ak-pipe" d="M55 95 C230 70 285 150 380 165 C475 180 530 120 705 105" stroke="url(#dataPipe)"/>
                                <path class="ak-pipe ak-pipe-two" d="M55 260 C230 295 285 215 380 195 C475 175 540 255 705 245" stroke="url(#dataPipe)"/>

                                @for($i=0;$i<50;$i++)
                                    <circle class="ak-flying-dot" cx="{{ rand(40,700) }}" cy="{{ rand(70,285) }}" r="{{ rand(2,5) }}" style="animation-delay:-{{ rand(0,300)/100 }}s;" />
                                @endfor

                                <g class="ak-database" transform="translate(320 84)">
                                    <ellipse cx="60" cy="38" rx="86" ry="26" fill="rgba(56,189,248,.18)" stroke="#fb923c" stroke-width="2"/>
                                    <path d="M-26 38 V188 C-26 203 12 216 60 216 C108 216 146 203 146 188 V38" fill="rgba(15,23,42,.72)" stroke="#fb923c" stroke-width="2"/>
                                    <ellipse cx="60" cy="188" rx="86" ry="26" fill="rgba(56,189,248,.14)" stroke="#fb923c" stroke-width="2"/>
                                    <ellipse cx="60" cy="112" rx="86" ry="26" fill="none" stroke="rgba(251,146,60,.38)" stroke-width="2"/>
                                    <circle cx="60" cy="112" r="118" fill="url(#dbGlow)" opacity=".28"/>
                                    <text x="60" y="114" text-anchor="middle" fill="#e0f2fe" font-size="22" font-weight="800">DATA</text>
                                    <text x="60" y="140" text-anchor="middle" fill="#93c5fd" font-size="13">10 Jahre Historie</text>
                                </g>
                            </svg>

                            <div class="ak-history-chart">
                                <div class="ak-chart-head"><span>Historische Datenmenge</span><b>10 Jahre</b></div>
                                <svg viewBox="0 0 520 130" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#38bdf8" stop-opacity=".36"/>
                                            <stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M0 105 L45 98 L90 102 L135 86 L180 91 L225 72 L270 78 L315 58 L360 50 L405 37 L460 25 L520 14 L520 130 L0 130 Z" fill="url(#chartFill)"/>
                                    <path d="M0 105 L45 98 L90 102 L135 86 L180 91 L225 72 L270 78 L315 58 L360 50 L405 37 L460 25 L520 14" stroke="#38bdf8" stroke-width="4" fill="none"/>
                                </svg>
                            </div>
                        </div>
                    </article>

                    <article class="ak-scene ak-scene-ml" data-scene="1">
                        <div class="ak-scene-copy">
                            <div class="ak-scene-number">02</div>
                            <h3>Machine Learning trainiert Modelle</h3>
                            <p>Aus den importierten Daten entstehen Features. Mehrere Algorithmen lernen Muster, validieren Signale und speichern trainierte Modelle für spätere Prognosen.</p>
                        </div>

                        <div class="ak-ml-stage">
                            <svg class="ak-neural-svg" viewBox="0 0 820 420" aria-hidden="true">
                                <defs>
                                    <linearGradient id="neuralLine" x1="0" x2="1">
                                        <stop offset="0%" stop-color="#38bdf8" stop-opacity=".25"/>
                                        <stop offset="50%" stop-color="#a855f7" stop-opacity=".85"/>
                                        <stop offset="100%" stop-color="#fb923c" stop-opacity=".25"/>
                                    </linearGradient>
                                </defs>
                                @php
                                    $layers = [[110,90,160,230,300],[260,70,140,210,280,350],[430,105,185,265,345],[590,125,210,295]];
                                @endphp
                                @foreach($layers as $layerIndex => $layer)
                                    @if($layerIndex < count($layers)-1)
                                        @php $next = $layers[$layerIndex+1]; @endphp
                                        @for($i=1; $i<count($layer); $i++)
                                            @for($j=1; $j<count($next); $j++)
                                                <line class="ak-neural-line" x1="{{ $layer[0] }}" y1="{{ $layer[$i] }}" x2="{{ $next[0] }}" y2="{{ $next[$j] }}" />
                                            @endfor
                                        @endfor
                                    @endif
                                    @for($i=1; $i<count($layer); $i++)
                                        <circle class="ak-node" cx="{{ $layer[0] }}" cy="{{ $layer[$i] }}" r="9" style="animation-delay:-{{ ($layerIndex+$i)/3 }}s;" />
                                    @endfor
                                @endforeach

                                <g class="ak-ai-chip" transform="translate(335 150)">
                                    <rect x="0" y="0" width="150" height="120" rx="26" fill="rgba(88,28,135,.88)" stroke="#d8b4fe" stroke-width="2.4"/>
                                    <text x="75" y="58" text-anchor="middle" fill="#f5d0fe" font-size="38" font-weight="900">KI</text>
                                    <text x="75" y="84" text-anchor="middle" fill="#c4b5fd" font-size="13" font-weight="700">TRAINING</text>
                                    @for($i=0;$i<8;$i++)
                                        <line x1="-18" y1="{{ 18+$i*12 }}" x2="0" y2="{{ 18+$i*12 }}" stroke="#d8b4fe" stroke-width="2"/>
                                        <line x1="150" y1="{{ 18+$i*12 }}" x2="168" y2="{{ 18+$i*12 }}" stroke="#d8b4fe" stroke-width="2"/>
                                    @endfor
                                </g>
                            </svg>

                            <div class="ak-model-bank">
                                <div><span>LightGBM</span><b>gespeichert</b></div>
                                <div><span>XGBoost</span><b>gespeichert</b></div>
                                <div><span>Random Forest</span><b>gespeichert</b></div>
                                <div><span>LSTM</span><b>gespeichert</b></div>
                            </div>
                        </div>
                    </article>

                    <article class="ak-scene ak-scene-score" data-scene="2">
                        <div class="ak-scene-copy">
                            <div class="ak-scene-number">03</div>
                            <h3>Tägliche Marktauswertung & KI-Score</h3>
                            <p>Jeden Tag bewertet AktienKI Aktien, Sektoren, Rohstoffe und Währungen. Das Ergebnis ist ein klarer Score mit Kaufen-, Halten- oder Verkaufen-Signal.</p>
                        </div>

                        <div class="ak-score-stage">
                            <div class="ak-market-orbit">
                                <svg viewBox="0 0 500 360" aria-hidden="true">
                                    <defs>
                                        <radialGradient id="planetFill">
                                            <stop offset="0%" stop-color="#67e8f9" stop-opacity=".75"/>
                                            <stop offset="55%" stop-color="#0e7490" stop-opacity=".45"/>
                                            <stop offset="100%" stop-color="#020617" stop-opacity=".9"/>
                                        </radialGradient>
                                    </defs>
                                    <circle cx="250" cy="180" r="98" fill="url(#planetFill)" stroke="#67e8f9" stroke-width="2"/>
                                    <ellipse cx="250" cy="180" rx="170" ry="42" fill="none" stroke="rgba(94,234,212,.45)" stroke-width="2" transform="rotate(-13 250 180)"/>
                                    <ellipse cx="250" cy="180" rx="150" ry="68" fill="none" stroke="rgba(56,189,248,.25)" stroke-width="2" transform="rotate(18 250 180)"/>
                                    <circle class="ak-orbit-dot" cx="92" cy="142" r="7" fill="#fb923c"/>
                                    <circle class="ak-orbit-dot delay" cx="390" cy="225" r="7" fill="#22c55e"/>
                                    <text x="250" y="174" text-anchor="middle" fill="#e0f2fe" font-size="22" font-weight="900">MARKT</text>
                                    <text x="250" y="199" text-anchor="middle" fill="#a5f3fc" font-size="13">Daily Scan</text>
                                </svg>

                                <div class="ak-satellite s1">Aktien<span>1.250+</span></div>
                                <div class="ak-satellite s2">Sektoren<span>11</span></div>
                                <div class="ak-satellite s3">Rohstoffe<span>Gold · Öl</span></div>
                                <div class="ak-satellite s4">Währung<span>EUR/USD</span></div>
                            </div>

                            <div class="ak-score-card">
                                <div class="ak-score-head">
                                    <span>KI Score</span>
                                    <b>Heute</b>
                                </div>

                                @foreach($topScores as $row)
                                    @php
                                        $class = $row['score'] >= 70 ? 'buy' : ($row['score'] >= 45 ? 'hold' : 'sell');
                                    @endphp
                                    <div class="ak-score-row {{ $class }}">
                                        <div>
                                            <strong>{{ $row['symbol'] }}</strong>
                                            <small>{{ $row['name'] }}</small>
                                        </div>
                                        <b>{{ $row['score'] }}</b>
                                        <span class="ak-score-bar"><i style="width: {{ $row['score'] }}%"></i></span>
                                        <em>{{ $row['signal'] }}</em>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </section>

        <section id="features" class="ak-feature-grid">
            <div class="ak-feature-card">
                <span>01</span>
                <h3>10 Jahre Historie</h3>
                <p>Langfristige Kurs- und Fundamentaldaten bilden die Basis für stabile Modelle.</p>
            </div>
            <div class="ak-feature-card">
                <span>02</span>
                <h3>Korrelationsanalyse</h3>
                <p>Sektoren, Rohstoffe und Währungen werden in die Bewertung einbezogen.</p>
            </div>
            <div class="ak-feature-card">
                <span>03</span>
                <h3>Gespeicherte Modelle</h3>
                <p>Trainierte Machine-Learning-Modelle werden versioniert und wiederverwendet.</p>
            </div>
            <div class="ak-feature-card">
                <span>04</span>
                <h3>Täglicher Score</h3>
                <p>Komplexe Signale werden in einen verständlichen Score von 0 bis 100 übersetzt.</p>
            </div>
        </section>
    </main>
</body>
</html>

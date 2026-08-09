<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AktienKI – Smarte Aktienanalysen</title>

    @vite([
        'resources/css/app.css',
        'resources/css/welcome-animated-svg.css',
        'resources/js/app.js',
        'resources/js/welcome-animated-svg.js'
    ])
</head>

<body class="ak-body">
    <div class="ak-bg-grid"></div>
    <div class="ak-bg-glow ak-bg-glow-1"></div>
    <div class="ak-bg-glow ak-bg-glow-2"></div>

    <header class="ak-header">
        <a class="ak-brand" href="{{ url('/') }}">
            <img src="{{ asset('assets/svg/welcome/logo.svg') }}" alt="AktienKI Logo">
            <span>AktienKI Test</span>
        </a>

        <nav class="ak-nav">
            <a class="is-active" href="#">Home</a>
            <a href="#features">Features</a>
            <a href="#workflow">Ablauf</a>
            <a href="#preise">Preise</a>
            <a href="#kontakt">Kontakt</a>
        </nav>

        <div class="ak-actions">
            @auth
                <a class="ak-btn ak-btn-ghost" href="{{ route('dashboard') }}">Dashboard</a>
            @else
                <a class="ak-btn ak-btn-ghost" href="{{ route('login') }}">Anmelden</a>
                <a class="ak-btn ak-btn-primary" href="{{ route('register') }}">Jetzt starten</a>
            @endauth
        </div>
    </header>

    <main class="ak-main">
        <section class="ak-hero">
            <div class="ak-copy">
                <div class="ak-badge">KI-powered stock predictions</div>

                <h1>
                    Intelligente Analysen.<br>
                    Bessere Entscheidungen.<br>
                    <span>Powered by KI.</span>
                </h1>

                <p>
                    AktienKI analysiert Märkte, erkennt Chancen und zeigt dir datenbasierte
                    Zusammenhänge – verständlich, modern und automatisch.
                </p>

                <div class="ak-cta-row">
                    @guest
                        <a class="ak-cta-primary" href="{{ route('register') }}">
                            Als Tester registrieren
                            <span>→</span>
                        </a>
                    @else
                        <a class="ak-cta-primary" href="{{ route('dashboard') }}">
                            Zum Dashboard
                            <span>→</span>
                        </a>
                    @endguest

                    <a class="ak-cta-secondary" href="#workflow">Mehr erfahren</a>
                </div>
            </div>

            <section id="workflow" class="ak-visual-card">
                <button class="ak-slider-btn ak-slider-prev" type="button" aria-label="Vorherige Ansicht">‹</button>
                <button class="ak-slider-btn ak-slider-next" type="button" aria-label="Nächste Ansicht">›</button>

                <div class="ak-svg-stage">
                    <img class="ak-scene-svg is-active" data-scene="0" src="{{ asset('assets/svg/welcome/scene-data-import.svg') }}" alt="Datenimport Visualisierung">
                    <img class="ak-scene-svg" data-scene="1" src="{{ asset('assets/svg/welcome/scene-machine-learning.svg') }}" alt="Machine Learning Visualisierung">
                    <img class="ak-scene-svg" data-scene="2" src="{{ asset('assets/svg/welcome/scene-ai-score.svg') }}" alt="KI Score Visualisierung">
                </div>

                <div class="ak-scene-text">
                    <div class="ak-scene-copy is-active" data-copy="0">
                        <span>01 · Datenimport</span>
                        <strong>10 Jahre Kurs- und Fundamentaldaten</strong>
                    </div>
                    <div class="ak-scene-copy" data-copy="1">
                        <span>02 · Machine Learning</span>
                        <strong>Modelle erkennen Muster und Korrelationen</strong>
                    </div>
                    <div class="ak-scene-copy" data-copy="2">
                        <span>03 · KI Score</span>
                        <strong>Tägliche Marktauswertung als verständlicher Score</strong>
                    </div>
                </div>

                <div class="ak-dots">
                    <button class="is-active" type="button" data-dot="0" aria-label="Datenimport"></button>
                    <button type="button" data-dot="1" aria-label="Machine Learning"></button>
                    <button type="button" data-dot="2" aria-label="KI Score"></button>
                </div>
            </section>
        </section>

        <section id="features" class="ak-features">
            <article>
                <img src="{{ asset('assets/svg/welcome/icon-brain.svg') }}" alt="">
                <div>
                    <h3>KI-gestützte Analysen</h3>
                    <p>Moderne Algorithmen erkennen Trends, Muster und Korrelationen frühzeitig.</p>
                </div>
            </article>

            <article>
                <img src="{{ asset('assets/svg/welcome/icon-lightning.svg') }}" alt="">
                <div>
                    <h3>Echtzeitnahe Daten</h3>
                    <p>Aktuelle Marktdaten bilden die Grundlage für präzisere Auswertungen.</p>
                </div>
            </article>

            <article>
                <img src="{{ asset('assets/svg/welcome/icon-shield.svg') }}" alt="">
                <div>
                    <h3>Verlässliche Prognosen</h3>
                    <p>Mehrfache Validierung durch KI-Modelle sorgt für mehr Sicherheit.</p>
                </div>
            </article>

            <article>
                <img src="{{ asset('assets/svg/welcome/icon-clock.svg') }}" alt="">
                <div>
                    <h3>Zeit sparen</h3>
                    <p>Automatisierte Analysen helfen dir, schneller bessere Entscheidungen zu treffen.</p>
                </div>
            </article>
        </section>
    </main>
</body>
</html>

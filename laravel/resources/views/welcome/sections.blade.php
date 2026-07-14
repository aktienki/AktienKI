<section id="technology" class="welcome-section">
    <div class="welcome-container">
        <div class="welcome-section__intro">
            <p class="welcome-eyebrow">So arbeitet AktienKI</p>
            <h2>Vom Markt zur nachvollziehbaren KI-Entscheidung.</h2>
            <p>Jede Analyse folgt einem klaren, reproduzierbaren Prozess. Daten, Modelle, Bewertung und Ergebnis bleiben getrennt und überprüfbar.</p>
        </div>

        <div class="welcome-flow">
            @foreach ([
                ['01', 'Marktdaten', 'Kurse, Volumen, Fundamentaldaten und Cross-Asset-Informationen werden strukturiert zusammengeführt.'],
                ['02', 'Market Intelligence', 'Marktregime, Risiko, Momentum und Liquidität bilden den Kontext jeder Prognose.'],
                ['03', 'Adaptive Ensemble', 'Mehrere Modelle treten gegeneinander an. Champion und Challenger werden objektiv bewertet.'],
                ['04', 'AI Decision', 'Long- oder Short-Chance, Ziel, Risiko, Konfidenz und Erklärung werden getrennt berechnet.'],
            ] as [$number, $title, $text])
                <article class="welcome-flow-card">
                    <div class="welcome-flow-card__number">{{ $number }}</div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section id="features" class="welcome-section welcome-section--soft">
    <div class="welcome-container">
        <div class="welcome-section__intro welcome-section__intro--wide">
            <p class="welcome-eyebrow">Warum AktienKI?</p>
            <h2>Eine Plattform. Mehrere intelligente Ebenen.</h2>
            <p>AktienKI zeigt nicht nur eine Zahl, sondern erklärt, wie Marktumfeld, Modellqualität und Signalstärke zusammenwirken.</p>
        </div>

        <div class="welcome-feature-grid">
            @foreach ([
                ['Adaptive AI', 'XGBoost, LightGBM und CatBoost werden gemeinsam bewertet. Das beste Modell wird automatisch zum Champion.'],
                ['Marktintelligenz', 'VIX, Zinsen, Indizes, Währungen und Rohstoffe liefern den entscheidenden Markt-Kontext.'],
                ['Eigene Modelle', 'Premium-Nutzer können später Strategy Profiles konfigurieren und wöchentlich retrainieren.'],
                ['Nachvollziehbarkeit', 'Jede Prognose wird gespeichert, validiert und mit echten Marktdaten verglichen.'],
                ['Risikobewertung', 'Chance, Konfidenz, Volatilität und Marktregime werden getrennt dargestellt.'],
                ['Long & Short', 'Auch Short-Szenarien werden strukturiert berechnet und erklärt.'],
            ] as [$title, $text])
                <article class="welcome-feature-card">
                    <div class="welcome-feature-card__icon"><span></span></div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section id="preview" class="welcome-section">
    <div class="welcome-container welcome-preview">
        <div class="welcome-preview__copy">
            <p class="welcome-eyebrow">AI Intelligence Center</p>
            <h2>Alle wichtigen Signale in einer klaren Oberfläche.</h2>
            <p>Marktregime, Top-Signale, Champion-Modell, Ensemble-Ranking und historische Modellqualität werden in einer konsistenten Oberfläche verbunden.</p>

            <div class="welcome-preview__list">
                @foreach ([
                    'Top AI Picks mit Score, Chance und Risiko',
                    'Marktregime und Cross-Asset-Kontext',
                    'Champion-, Runner-up- und Challenger-Modelle',
                    'Validierte Prognosen und Modellhistorie',
                ] as $item)
                    <div><span>✓</span>{{ $item }}</div>
                @endforeach
            </div>
        </div>

        <div class="welcome-dashboard">
            <div class="welcome-dashboard__topbar">
                <div class="welcome-dashboard__dots"><span></span><span></span><span></span></div>
                <strong>AI Intelligence Center</strong>
                <span>Live</span>
            </div>

            <div class="welcome-dashboard__grid">
                <article class="welcome-dashboard-card welcome-dashboard-card--wide">
                    <div class="welcome-dashboard-card__head"><span>MARKTREGIME</span><b>Bull Market</b></div>
                    <div class="welcome-dashboard-metrics">
                        <div><small>Bull Score</small><strong>82</strong></div>
                        <div><small>Momentum</small><strong>74</strong></div>
                        <div><small>Risk</small><strong>28</strong></div>
                    </div>
                </article>

                <article class="welcome-dashboard-card">
                    <span>CHAMPION MODELL</span>
                    <strong class="welcome-dashboard-card__value">XGBoost</strong>
                    <small>Score 92 · ELO 1742</small>
                </article>

                <article class="welcome-dashboard-card">
                    <span>VALIDIERTE TREFFERQUOTE</span>
                    <strong class="welcome-dashboard-card__value">81,4%</strong>
                    <small>letzte 250 Signale</small>
                </article>

                <article class="welcome-dashboard-card welcome-dashboard-card--wide">
                    <div class="welcome-dashboard-card__head"><span>TOP AI PICK</span><b>NVIDIA · NVDA</b></div>
                    <div class="welcome-pick-row">
                        <div><small>AI Score</small><strong>97</strong></div>
                        <div><small>Chance</small><strong class="is-green">+12,6%</strong></div>
                        <div><small>Konfidenz</small><strong>89%</strong></div>
                        <div><small>Risiko</small><strong class="is-blue">Niedrig</strong></div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<section id="pricing" class="welcome-section welcome-section--soft">
    <div class="welcome-container">
        <div class="welcome-section__intro">
            <p class="welcome-eyebrow">Zugang</p>
            <h2>Kostenlos starten. Später gezielt erweitern.</h2>
            <p>Die Leistungsgrenzen und Preise werden vor dem öffentlichen Start finalisiert.</p>
        </div>

        <div class="welcome-pricing-grid">
            @foreach ([
                ['Free', '0 €', ['Ausgewählte Instrumente', 'Basis-Predictions', 'Begrenzte Rankings'], false],
                ['Premium', 'Bald verfügbar', ['Volle Predictions', 'Eigene Strategy Profiles', 'Wöchentliches Retraining'], true],
                ['Professional', 'Bald verfügbar', ['Erweiterte Historie', 'Exporte und API-Optionen', 'Priorisierte Rechenleistung'], false],
            ] as [$name, $price, $features, $featured])
                <article @class(['welcome-price-card', 'welcome-price-card--featured' => $featured])>
                    @if ($featured)<div class="welcome-price-card__badge">Empfohlen</div>@endif
                    <h3>{{ $name }}</h3>
                    <strong class="welcome-price-card__price">{{ $price }}</strong>
                    <div class="welcome-price-card__features">
                        @foreach ($features as $feature)
                            <div><span>✓</span>{{ $feature }}</div>
                        @endforeach
                    </div>

                    @if ($name === 'Free' && Route::has('register'))
                        <a href="{{ route('register') }}" class="welcome-button welcome-button--primary welcome-button--block">Kostenlos starten</a>
                    @else
                        <a href="#technology" class="welcome-button welcome-button--ghost welcome-button--block">Mehr erfahren</a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="welcome-final-cta">
    <div class="welcome-container">
        <div class="welcome-final-cta__box">
            <p class="welcome-eyebrow">AktienKI Intelligence Platform</p>
            <h2>Komplexe Märkte. Klare KI-Entscheidungen.</h2>
            <p>Verfolge Prognosen, verstehe die Gründe dahinter und prüfe später transparent, wie sie sich entwickelt haben.</p>

            <div class="welcome-final-cta__actions">
                @auth
                    <a href="{{ url('/dashboard') }}" class="welcome-button welcome-button--primary welcome-button--large">Intelligence Center öffnen</a>
                @else
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="welcome-button welcome-button--primary welcome-button--large">Kostenlos registrieren</a>
                    @endif
                    <a href="{{ route('login') }}" class="welcome-button welcome-button--ghost welcome-button--large">Anmelden</a>
                @endauth
            </div>
        </div>
    </div>
</section>

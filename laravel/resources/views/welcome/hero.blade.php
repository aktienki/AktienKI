<main class="welcome-hero">
    <div class="welcome-container welcome-hero__grid">
        <section class="welcome-hero__copy">
            <div class="welcome-pill">
                Institutionelle KI für private Investoren
            </div>

            <h1 class="welcome-title">
                Die nächste
                <br>
                Generation der
                <span>Aktienanalyse.</span>
            </h1>

            <p class="welcome-subtitle">
                Adaptive KI-Modelle. 10 Jahre Historie.
                <br class="welcome-subtitle__desktop">
                Marktintelligenz in Echtzeit.
            </p>

            <div class="welcome-benefits">
                <div>
                    <span class="welcome-check">✓</span>
                    Adaptive Ensemble KI
                </div>
                <div>
                    <span class="welcome-check">✓</span>
                    Marktregime-Erkennung
                </div>
                <div>
                    <span class="welcome-check">✓</span>
                    10 Jahre Trainingsdaten
                </div>
                <div>
                    <span class="welcome-check">✓</span>
                    Institutionelle Qualität
                </div>
            </div>

            <div class="welcome-hero__actions">
                @auth
                    <a href="{{ url('/dashboard') }}" class="welcome-button welcome-button--primary welcome-button--large">
                        Intelligence Center
                        <span aria-hidden="true">→</span>
                    </a>
                @else
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="welcome-button welcome-button--primary welcome-button--large">
                            Kostenlos starten
                            <span aria-hidden="true">→</span>
                        </a>
                    @endif

                    <a href="#hero-demo" class="welcome-button welcome-button--ghost welcome-button--large">
                        Demo ansehen
                        <span aria-hidden="true">▷</span>
                    </a>
                @endauth
            </div>

            <div class="welcome-trust">
                <div class="welcome-avatars" aria-hidden="true">
                    <span>A</span>
                    <span>M</span>
                    <span>S</span>
                </div>

                <div>
                    <div class="welcome-stars">★★★★★</div>
                    <p>vertraut von 8.429+ Investoren</p>
                </div>
            </div>
        </section>

        <section id="hero-demo" class="welcome-hero__visual">
            <div class="welcome-orb welcome-orb--one"></div>
            <div class="welcome-orb welcome-orb--two"></div>

            <div class="welcome-terminal">
                <div class="welcome-terminal__top">
                    <div class="welcome-terminal__label">
                        <span class="welcome-terminal__icon">▥</span>
                        <strong x-text="sceneLabel"></strong>
                    </div>

                    <div class="welcome-terminal__scene">
                        <span x-text="`SZENE ${activeScene + 1} / ${scenes.length}`"></span>
                        <div class="welcome-scene-dots">
                            <template x-for="(scene, index) in scenes" :key="scene.key">
                                <button
                                    type="button"
                                    :class="{ 'is-active': activeScene === index }"
                                    @click="setScene(index)"
                                    :aria-label="`Szene ${index + 1} anzeigen`"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="welcome-terminal__body">
                    <div
                        x-show="activeScene === 0"
                        x-transition.opacity.duration.500ms
                        class="welcome-scene welcome-scene--market"
                    >
                        <div class="welcome-metric-panel">
                            <div class="welcome-metric">
                                <div class="welcome-metric__head">
                                    <span class="welcome-metric__icon welcome-metric__icon--green">◌</span>
                                    <strong>BULL SCORE</strong>
                                    <b class="welcome-value welcome-value--green">82%</b>
                                </div>
                                <div class="welcome-bars">
                                    <template x-for="index in 20" :key="index">
                                        <span :class="{ 'is-on is-green': index <= 16 }"></span>
                                    </template>
                                </div>
                            </div>

                            <div class="welcome-metric">
                                <div class="welcome-metric__head">
                                    <span class="welcome-metric__icon welcome-metric__icon--orange">◇</span>
                                    <strong>RISK SCORE</strong>
                                    <b class="welcome-value welcome-value--orange">28%</b>
                                </div>
                                <div class="welcome-bars">
                                    <template x-for="index in 20" :key="index">
                                        <span :class="{ 'is-on is-orange': index <= 6 }"></span>
                                    </template>
                                </div>
                            </div>

                            <div class="welcome-metric">
                                <div class="welcome-metric__head">
                                    <span class="welcome-metric__icon welcome-metric__icon--blue">↗</span>
                                    <strong>MOMENTUM</strong>
                                    <b class="welcome-value welcome-value--blue">74%</b>
                                </div>
                                <div class="welcome-bars">
                                    <template x-for="index in 20" :key="index">
                                        <span :class="{ 'is-on is-blue': index <= 15 }"></span>
                                    </template>
                                </div>
                            </div>

                            <div class="welcome-regime-card">
                                <div>
                                    <span class="welcome-regime-card__label">MARKTREGIME</span>
                                    <strong>BULLISH</strong>
                                    <small>Wahrscheinlichkeit 68%</small>
                                </div>

                                <svg viewBox="0 0 180 80" role="img" aria-label="Aufwärtstrend">
                                    <path d="M4 70 C22 65, 28 56, 44 60 S68 75, 88 45 S118 14, 132 36 S156 48, 176 10" fill="none" stroke="currentColor" stroke-width="3"/>
                                </svg>
                            </div>
                        </div>

                        <div class="welcome-stock-card">
                            <div class="welcome-stock-card__top">
                                <span>★</span>
                                <strong>TOP RECOMMENDATION</strong>
                            </div>

                            <h2>NVIDIA</h2>

                            <div class="welcome-chip-visual">
                                <div class="welcome-chip-visual__core">AI</div>
                            </div>

                            <div class="welcome-ai-score">
                                <span>AI SCORE</span>
                                <strong>97</strong>
                                <small>/100</small>
                            </div>

                            <div class="welcome-stock-grid">
                                <div>
                                    <span>ERWARTETE RENDITE</span>
                                    <strong class="welcome-value--green">+12.6%</strong>
                                </div>
                                <div>
                                    <span>KONFIDENZ</span>
                                    <strong class="welcome-value--blue">89%</strong>
                                </div>
                            </div>

                            <div class="welcome-champion">
                                <span class="welcome-champion__icon">♛</span>
                                <span>
                                    <small>CHAMPION MODELL</small>
                                    <strong>XGBoost</strong>
                                </span>
                                <b>Trefferquote 92%</b>
                            </div>
                        </div>
                    </div>

                    <div
                        x-cloak
                        x-show="activeScene === 1"
                        x-transition.opacity.duration.500ms
                        class="welcome-scene welcome-scene--ensemble"
                    >
                        <div class="welcome-ensemble-card">
                            <div class="welcome-ensemble-card__header">
                                <div>
                                    <span>ADAPTIVE ENSEMBLE</span>
                                    <h2>Modell-Ranking</h2>
                                </div>
                                <b>3 Modelle aktiv</b>
                            </div>

                            <template x-for="(model, index) in models" :key="model.name">
                                <div class="welcome-model-row">
                                    <div class="welcome-model-row__rank" x-text="index + 1"></div>
                                    <div class="welcome-model-row__name">
                                        <strong x-text="model.name"></strong>
                                        <small x-text="model.role"></small>
                                    </div>
                                    <div class="welcome-model-row__bar">
                                        <span :style="`width:${model.score}%`"></span>
                                    </div>
                                    <div class="welcome-model-row__score" x-text="model.score"></div>
                                </div>
                            </template>

                            <div class="welcome-ensemble-summary">
                                <div>
                                    <span>CHAMPION</span>
                                    <strong>XGBoost</strong>
                                </div>
                                <div>
                                    <span>ENSEMBLE SCORE</span>
                                    <strong>92</strong>
                                </div>
                                <div>
                                    <span>ELO RATING</span>
                                    <strong>1742</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        x-cloak
                        x-show="activeScene === 2"
                        x-transition.opacity.duration.500ms
                        class="welcome-scene welcome-scene--decision"
                    >
                        <div class="welcome-decision-card">
                            <div class="welcome-decision-card__badge">AI DECISION</div>
                            <h2>NVIDIA</h2>
                            <p>NVDA · US Tech Momentum</p>

                            <div class="welcome-decision-score">
                                <span>97</span>
                                <small>AI SCORE</small>
                            </div>

                            <div class="welcome-decision-stats">
                                <div>
                                    <span>Chance</span>
                                    <strong>+12,6%</strong>
                                </div>
                                <div>
                                    <span>Konfidenz</span>
                                    <strong>89%</strong>
                                </div>
                                <div>
                                    <span>Risiko</span>
                                    <strong>Niedrig</strong>
                                </div>
                            </div>

                            <div class="welcome-decision-reasons">
                                <strong>Warum diese Chance?</strong>
                                <p>✓ Trendstruktur bestätigt Long-Szenario</p>
                                <p>✓ Bullisches Marktregime</p>
                                <p>✓ Champion-Modell mit hoher Trefferquote</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="welcome-terminal-caption">
                <div class="welcome-scene-dots welcome-scene-dots--large">
                    <template x-for="(scene, index) in scenes" :key="scene.key">
                        <button
                            type="button"
                            :class="{ 'is-active': activeScene === index }"
                            @click="setScene(index)"
                            :aria-label="`Szene ${index + 1} anzeigen`"
                        ></button>
                    </template>
                </div>
                <span>Wechselt automatisch alle 5 Sekunden</span>
            </div>
        </section>
    </div>

    <section class="welcome-stats">
        <div class="welcome-container welcome-stats__grid">
            <div class="welcome-stat">
                <span class="welcome-stat__icon">◔</span>
                <div><strong>5.142+</strong><small>Aktien</small></div>
            </div>
            <div class="welcome-stat">
                <span class="welcome-stat__icon">◎</span>
                <div><strong>41</strong><small>Börsen</small></div>
            </div>
            <div class="welcome-stat">
                <span class="welcome-stat__icon">✺</span>
                <div><strong>3</strong><small>KI-Modelle</small></div>
            </div>
            <div class="welcome-stat">
                <span class="welcome-stat__icon">◫</span>
                <div><strong>10</strong><small>Jahre Historie</small></div>
            </div>
            <div class="welcome-stat">
                <span class="welcome-stat__icon">◷</span>
                <div><strong>24/7</strong><small>KI-Analyse</small></div>
            </div>
        </div>
    </section>
</main>

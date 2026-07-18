<x-layouts.app title="Design System – aktienKI.com">
    <section class="ak-page-header">
        <div class="ak-eyebrow">Design System 1.0</div>
        <h1 class="ak-page-title">AktienKI UI Foundation</h1>
        <p class="ak-page-subtitle">
            Einheitliche Komponenten für Dashboard, Market Overview, Stocks,
            Predictions, AI Status und alle zukünftigen Seiten.
        </p>
    </section>

    <section class="ak-grid-3">
        <article class="ak-card">
            <div class="ak-card__body">
                <span class="ak-badge ak-badge--primary">Primary</span>
                <h2>Standard Card</h2>
                <p class="ak-muted">Fester Hintergrund, klare Lesbarkeit und identische Abstände.</p>
                <button class="ak-button ak-button--primary">Primary Button</button>
            </div>
        </article>

        <article class="ak-card">
            <div class="ak-card__body">
                <span class="ak-badge ak-badge--success">Bullish</span>
                <h2>Market Status</h2>
                <p class="ak-positive">Score 82 / 100</p>
                <button class="ak-button ak-button--secondary">Details</button>
            </div>
        </article>

        <article class="ak-card">
            <div class="ak-card__body">
                <label class="ak-label" for="theme-preview">Beispiel-Eingabe</label>
                <input id="theme-preview" class="ak-input" value="NVDA">
                <div style="margin-top:16px;">
                    <span class="ak-badge ak-badge--warning">Neutral</span>
                    <span class="ak-badge ak-badge--danger">Risk</span>
                </div>
            </div>
        </article>
    </section>
</x-layouts.app>

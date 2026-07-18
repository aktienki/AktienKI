<header style="position:sticky;top:0;z-index:100;border-bottom:1px solid var(--ak-border);background:color-mix(in srgb,var(--ak-page-bg) 84%,transparent);backdrop-filter:blur(18px);">
    <div class="ak-container" style="min-height:74px;display:flex;align-items:center;gap:28px;">
        <a
            href="{{ Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}"
            style="display:flex;align-items:center;gap:10px;color:var(--ak-text);text-decoration:none;font-size:22px;font-weight:900;letter-spacing:-.04em;"
        >
            <svg width="38" height="38" viewBox="0 0 64 64" aria-hidden="true">
                <defs>
                    <linearGradient id="akLogoGradient" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stop-color="var(--ak-primary-strong)"/>
                        <stop offset="100%" stop-color="var(--ak-primary-soft)"/>
                    </linearGradient>
                </defs>
                <path d="M8 51a24 24 0 1 1 41 0" fill="none" stroke="url(#akLogoGradient)" stroke-width="3.5"/>
                <rect x="17" y="36" width="7" height="14" rx="1.5" fill="url(#akLogoGradient)"/>
                <rect x="28" y="29" width="7" height="21" rx="1.5" fill="url(#akLogoGradient)"/>
                <rect x="39" y="20" width="7" height="30" rx="1.5" fill="url(#akLogoGradient)"/>
                <path d="M14 39c10-3 19-12 29-27" fill="none" stroke="url(#akLogoGradient)" stroke-width="3.5" stroke-linecap="round"/>
                <path d="m42 12 9-3-4 9z" fill="url(#akLogoGradient)"/>
            </svg>

            <span>aktien<span style="color:var(--ak-primary-soft)">KI</span>.com</span>
        </a>

        <nav style="display:flex;align-items:center;gap:26px;flex:1;">
            <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}" style="color:var(--ak-text-soft);text-decoration:none;">Dashboard</a>
            <a href="{{ Route::has('market-overview') ? route('market-overview') : url('/market-overview') }}" style="color:var(--ak-text-soft);text-decoration:none;">Market Overview</a>
            <a href="{{ Route::has('predictions.index') ? route('predictions.index') : url('/predictions') }}" style="color:var(--ak-text-soft);text-decoration:none;">Predictions</a>
            <a href="{{ Route::has('stocks.index') ? route('stocks.index') : url('/stocks') }}" style="color:var(--ak-text-soft);text-decoration:none;">Stocks</a>
            <a href="{{ Route::has('ai-status') ? route('ai-status') : url('/ai-status') }}" style="color:var(--ak-text-soft);text-decoration:none;">AI Status</a>
        </nav>

        <x-theme-switcher />

        <span class="ak-avatar">
            {{ strtoupper(substr(auth()->user()->name ?? 'AK', 0, 2)) }}
        </span>
    </div>
</header>

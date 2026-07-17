<header class="welcome-nav">
    <div class="welcome-container welcome-nav__inner">
        <a href="{{ url('/') }}" class="welcome-brand" aria-label="AktienKI Startseite">
            <span class="welcome-brand__text">AKTIEN</span>
            <span class="welcome-brand__ki">KI</span>
            <span class="welcome-brand__domain">.com</span>
        </a>

        <nav class="welcome-nav__links" aria-label="Hauptnavigation">
            <a href="#technology">Technologie</a>
            <a href="#features">Features</a>
            <a href="#pricing">Preise</a>
            <a href="#resources">Ressourcen</a>
        </nav>

        <div class="welcome-nav__actions">
            <div class="welcome-engine-status">
                <span class="welcome-engine-status__dot"></span>
                <span>
                    <strong>AI Engine Online</strong>
                    <small>Letzte Aktualisierung: vor 12 Sek.</small>
                </span>
            </div>

            @auth
                <a href="{{ url('/dashboard') }}" class="welcome-button welcome-button--primary">
                    Intelligence Center
                </a>
            @else
                <a href="{{ route('login') }}" class="welcome-button welcome-button--ghost">
                    Login
                </a>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="welcome-button welcome-button--primary">
                        Kostenlos starten
                    </a>
                @endif
            @endauth
        </div>

        <button
            type="button"
            class="welcome-menu-button"
            @click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen.toString()"
            aria-label="Navigation öffnen"
        >
            <svg x-show="!mobileOpen" viewBox="0 0 24 24" fill="none">
                <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <svg x-cloak x-show="mobileOpen" viewBox="0 0 24 24" fill="none">
                <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div
        x-cloak
        x-show="mobileOpen"
        x-transition.opacity.duration.180ms
        class="welcome-mobile-menu"
    >
        <div class="welcome-container welcome-mobile-menu__inner">
            <a href="#technology" @click="mobileOpen = false">Technologie</a>
            <a href="#features" @click="mobileOpen = false">Features</a>
            <a href="#pricing" @click="mobileOpen = false">Preise</a>
            <a href="#resources" @click="mobileOpen = false">Ressourcen</a>

            <div class="welcome-mobile-menu__actions">
                @auth
                    <a href="{{ url('/dashboard') }}" class="welcome-button welcome-button--primary">
                        Intelligence Center
                    </a>
                @else
                    <a href="{{ route('login') }}" class="welcome-button welcome-button--ghost">
                        Login
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="welcome-button welcome-button--primary">
                            Kostenlos starten
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</header>

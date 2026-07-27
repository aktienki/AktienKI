<header
    class="ak-app-topbar sticky top-0 z-40 h-[73px] border-b border-[var(--ak-border)] bg-[var(--ak-card-strong)] backdrop-blur-xl"
    x-data="{
        navOverflow: false,
        canScrollLeft: false,
        canScrollRight: false,
        updateNavigation() {
            const nav = this.$refs.navigation;
            if (!nav) return;
            this.navOverflow = nav.scrollWidth > nav.clientWidth + 2;
            this.canScrollLeft = nav.scrollLeft > 2;
            this.canScrollRight = nav.scrollLeft + nav.clientWidth < nav.scrollWidth - 2;
        },
        scrollNavigation(direction) {
            this.$refs.navigation?.scrollBy({ left: direction * 260, behavior: 'smooth' });
        }
    }"
    x-init="$nextTick(() => {
        updateNavigation();
        new ResizeObserver(() => updateNavigation()).observe($refs.navigation);
        window.addEventListener('resize', () => updateNavigation());
    })"
>
    <div class="ak-container flex h-full items-center gap-2 sm:gap-4 lg:gap-5">
        <a href="{{ route('dashboard') }}" class="flex w-14 shrink-0 items-center overflow-hidden sm:w-auto">
            <img src="{{ asset('brand/logo.svg') }}?v={{ filemtime(public_path('brand/logo.svg')) }}" alt="aktienKI.com" class="ak-brand-logo ak-brand-logo-dark h-11 max-w-none transition duration-300 hover:scale-105">
            <img src="{{ asset('brand/logo-light.svg') }}?v={{ filemtime(public_path('brand/logo-light.svg')) }}" alt="aktienKI.com" class="ak-brand-logo ak-brand-logo-light hidden h-11 max-w-none transition duration-300 hover:scale-105">
        </a>

        <button
            x-cloak
            x-show="navOverflow"
            type="button"
            @click="scrollNavigation(-1)"
            :disabled="!canScrollLeft"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-violet-300/20 bg-white/[.07] text-violet-200 shadow-lg transition hover:border-violet-300/40 hover:bg-violet-500/20 disabled:cursor-default disabled:opacity-25"
            aria-label="{{ __('Vorherige Menüpunkte anzeigen') }}"
            title="{{ __('Vorherige Menüpunkte anzeigen') }}"
        >
            <x-heroicon-o-chevron-left class="h-4 w-4" />
        </button>

        <nav
            x-ref="navigation"
            @scroll.debounce.50ms="updateNavigation()"
            class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto whitespace-nowrap scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            @guest
                <a href="{{ route('welcome') }}" class="ak-top-link"><x-heroicon-o-home /><span>{{ __('Startseite') }}</span></a>
                <a href="{{ route('features') }}" class="{{ request()->routeIs('features') ? 'ak-top-link-active' : 'ak-top-link' }}"><x-heroicon-o-sparkles /><span>{{ __('Features') }}</span></a>
                <a href="{{ route('roadmap') }}" class="{{ request()->routeIs('roadmap') ? 'ak-top-link-active' : 'ak-top-link' }}"><x-heroicon-o-map /><span>{{ __('Roadmap') }}</span></a>
            @endguest
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'ak-top-link-active' : 'ak-top-link' }}">
                <x-heroicon-o-squares-2x2 /><span>Dashboard</span>
            </a>
            @if(Route::has('stocks.index'))
                <a href="{{ route('stocks.index') }}" class="{{ request()->routeIs('stocks.index') ? 'ak-top-link-active' : 'ak-top-link' }}">
                    <x-heroicon-o-chart-bar-square /><span>Aktien</span>
                </a>
            @endif
            <a href="{{ route('predictions.index') }}" class="{{ request()->routeIs('predictions.*') ? 'ak-top-link-active' : 'ak-top-link' }}">
                <x-heroicon-o-chart-bar /><span>{{ __('Prognosen') }}</span>
            </a>
            @auth
                <a href="{{ route('watchlists.index') }}" class="{{ request()->routeIs('watchlists.*') ? 'ak-top-link-active' : 'ak-top-link' }}">
                    <x-heroicon-o-star /><span>Watchlist</span>
                </a>
                <a href="{{ route('depots.index') }}" class="{{ request()->routeIs('depots.*') ? 'ak-top-link-active' : 'ak-top-link' }}">
                    <x-heroicon-o-briefcase /><span>{{ __('Depots') }}</span>
                </a>
                <a href="{{ route('paper-depots.index') }}" class="{{ request()->routeIs('paper-depots.*') ? 'ak-top-link-active' : 'ak-top-link' }}">
                    <x-heroicon-o-beaker /><span>{{ __('Musterdepots') }}</span>
                </a>
            @endauth
            <a href="{{ route('sectors.index') }}" class="{{ request()->routeIs('sectors.index') ? 'ak-top-link-active' : 'ak-top-link' }}">
                <x-heroicon-o-building-office-2 /><span>{{ __('Sektoren') }}</span>
            </a>
            <a href="{{ route('stocks.apple') }}" class="{{ request()->routeIs('stocks.apple') ? 'ak-top-link-active' : 'ak-top-link' }}">
                <x-heroicon-o-presentation-chart-line /><span>Apple</span>
            </a>
            <a href="#" class="ak-top-link"><x-heroicon-o-globe-alt /><span>Märkte</span></a>
            <a href="{{ route('recommendations.index') }}" class="{{ request()->routeIs('recommendations.*') ? 'ak-top-link-active' : 'ak-top-link' }}"><x-heroicon-o-sparkles /><span>{{ __('Empfehlungen') }}</span></a>
            <a href="#" class="ak-top-link"><x-heroicon-o-cpu-chip /><span>KI Status</span></a>
            <a href="#" class="ak-top-link"><x-heroicon-o-newspaper /><span>News</span></a>
            @guest
                <a href="{{ route('pricing') }}" class="{{ request()->routeIs('pricing') ? 'ak-top-link-active' : 'ak-top-link' }}"><x-heroicon-o-banknotes /><span>{{ __('Preise') }}</span></a>
            @endguest
            <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'ak-top-link-active' : 'ak-top-link' }}"><x-heroicon-o-envelope /><span>{{ __('Kontakt') }}</span></a>
        </nav>

        <button
            x-cloak
            x-show="navOverflow"
            type="button"
            @click="scrollNavigation(1)"
            :disabled="!canScrollRight"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-violet-300/20 bg-white/[.07] text-violet-200 shadow-lg transition hover:border-violet-300/40 hover:bg-violet-500/20 disabled:cursor-default disabled:opacity-25"
            aria-label="{{ __('Weitere Menüpunkte anzeigen') }}"
            title="{{ __('Weitere Menüpunkte anzeigen') }}"
        >
            <x-heroicon-o-chevron-right class="h-4 w-4" />
        </button>

        <div class="ml-auto flex shrink-0 items-center justify-end gap-3">
            <x-preference-controls class="hidden sm:flex" />
            <x-risk-profile-badge class="hidden lg:flex" />
            <div class="hidden items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-medium text-emerald-300 sm:flex">
                <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-lg shadow-emerald-400/70 animate-pulse"></span> Engine
            </div>
            @auth
                <div x-data="{ open:false }" class="relative">
                    <button type="button" @click="open=!open" @click.outside="open=false" class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white transition hover:bg-violet-500/15">
                        <x-heroicon-o-user-circle class="h-6 w-6 text-violet-300" />
                        <span class="hidden max-w-24 truncate md:inline">{{ auth()->user()->name }}</span>
                        <x-heroicon-o-chevron-down class="h-4 w-4 text-slate-400 transition" x-bind:class="{ 'rotate-180': open }" />
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-3 w-60 overflow-hidden rounded-3xl border border-white/10 bg-[#171325]/95 shadow-2xl shadow-black/40 backdrop-blur-xl">
                        <div class="border-b border-white/10 px-5 py-4">
                            <p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                            <p class="mt-1 text-xs text-slate-400">Benutzerkonto</p>
                        </div>
                        <div class="p-2">
                            @if(Route::has('profile.edit'))
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm text-slate-300 transition hover:bg-violet-500/15 hover:text-white"><x-heroicon-o-user class="h-5 w-5 text-violet-300" />Profil</a>
                            @endif
                            <a href="{{ route('profile.edit') }}#darstellung" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm text-slate-300 transition hover:bg-violet-500/15 hover:text-white"><x-heroicon-o-cog-6-tooth class="h-5 w-5 text-violet-300" />{{ __('Einstellungen') }}</a>
                            <a href="#" class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm text-slate-300 transition hover:bg-violet-500/15 hover:text-white"><x-heroicon-o-credit-card class="h-5 w-5 text-violet-300" />Mein Abo</a>
                        </div>
                        <div class="border-t border-white/10 p-2">
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm text-rose-300 transition hover:bg-rose-500/15"><x-heroicon-o-arrow-left-on-rectangle class="h-5 w-5" />Abmelden</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</header>

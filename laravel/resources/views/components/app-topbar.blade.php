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
            <div
                x-data="{
                    open: false,
                    left: 0,
                    top: 0,
                    toggle() {
                        const bounds = this.$refs.trigger.getBoundingClientRect();
                        this.left = bounds.left;
                        this.top = bounds.bottom + 8;
                        this.open = !this.open;
                    }
                }"
                @click.outside="open = false"
                class="relative shrink-0"
            >
                <button
                    x-ref="trigger"
                    type="button"
                    @click="toggle()"
                    class="{{ request()->routeIs('markets.*', 'daily-market-analysis', 'sectors.*') ? 'ak-top-link-active' : 'ak-top-link' }}"
                    :aria-expanded="open"
                >
                    <x-heroicon-o-globe-alt />
                    <span>{{ __('Märkte') }}</span>
                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="{ 'rotate-180': open }" />
                </button>
                <template x-teleport="body">
                    <div
                        x-cloak
                        x-show="open"
                        @keydown.escape.window="open = false"
                        x-transition.origin.top.left
                        class="fixed z-[100] w-64 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-2 shadow-2xl shadow-black/35 backdrop-blur-xl"
                        :style="`left: ${left}px; top: ${top}px`"
                    >
                        <a href="{{ route('markets.situation') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-globe-europe-africa class="h-5 w-5 text-teal-500" />
                            {{ __('Marktlage') }}
                        </a>
                        <a href="{{ route('markets.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-building-library class="h-5 w-5 text-teal-500" />
                            {{ __('Märkte') }}
                        </a>
                        <a href="{{ route('sectors.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-building-office-2 class="h-5 w-5 text-teal-500" />
                            {{ __('Sektoren') }}
                        </a>
                        <a href="{{ route('daily-market-analysis') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-scale class="h-5 w-5 text-amber-500" />
                            {{ __('Chancen & Risiken') }}
                        </a>
                    </div>
                </template>
            </div>
            <div
                x-data="{
                    open: false,
                    left: 0,
                    top: 0,
                    toggle() {
                        const bounds = this.$refs.trigger.getBoundingClientRect();
                        this.left = bounds.left;
                        this.top = bounds.bottom + 8;
                        this.open = !this.open;
                    }
                }"
                @click.outside="open = false"
                class="relative shrink-0"
            >
                <button
                    x-ref="trigger"
                    type="button"
                    @click="toggle()"
                    class="{{ request()->routeIs('predictions.*', 'recommendations.*', 'signal-changes.*') ? 'ak-top-link-active' : 'ak-top-link' }}"
                    :aria-expanded="open"
                >
                    <x-heroicon-o-chart-bar />
                    <span>{{ __('Prognosen') }}</span>
                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="{ 'rotate-180': open }" />
                </button>
                <template x-teleport="body">
                    <div
                        x-cloak
                        x-show="open"
                        @keydown.escape.window="open = false"
                        x-transition.origin.top.left
                        class="fixed z-[100] w-64 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-2 shadow-2xl shadow-black/35 backdrop-blur-xl"
                        :style="`left: ${left}px; top: ${top}px`"
                    >
                        <a href="{{ route('recommendations.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-sparkles class="h-5 w-5 text-amber-500" />
                            {{ __('Empfehlung Top 3') }}
                        </a>
                        <a href="{{ route('predictions.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-chart-bar-square class="h-5 w-5 text-teal-500" />
                            {{ __('Prognosetabelle') }}
                        </a>
                        <a href="{{ route('signal-changes.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                            <x-heroicon-o-arrows-right-left class="h-5 w-5 text-amber-500" />
                            {{ __('Signaländerungen') }}
                        </a>
                    </div>
                </template>
            </div>
            @auth
                @php
                    $strategyDepotAccess = app(\App\Services\PlanAccessService::class)
                        ->allows(auth()->user(), \App\Enums\PlanLevel::Pro);
                    $activeStrategyDepot = $strategyDepotAccess
                        ? \App\Models\Portfolio::query()
                            ->where('user_id', auth()->id())
                            ->where('type', 'paper')
                            ->where('active', true)
                            ->get(['id', 'meta'])
                            ->first(fn (\App\Models\Portfolio $portfolio): bool => (bool) data_get($portfolio->meta, 'automation.live_enabled', false))
                        : null;
                    $strategyDepotUrl = $activeStrategyDepot
                        ? route('depots.show', ['portfolio' => $activeStrategyDepot, 'return_to' => 'paper'])
                        : route('paper-depots.index');
                @endphp
                <div
                    x-data="{
                        open: false,
                        left: 0,
                        top: 0,
                        toggle() {
                            const bounds = this.$refs.trigger.getBoundingClientRect();
                            this.left = bounds.left;
                            this.top = bounds.bottom + 8;
                            this.open = !this.open;
                        }
                    }"
                    @click.outside="open = false"
                    class="relative shrink-0"
                >
                    <button
                        x-ref="trigger"
                        type="button"
                        @click="toggle()"
                        class="{{ request()->routeIs('depots.*', 'paper-depots.*', 'watchlists.*') ? 'ak-top-link-active' : 'ak-top-link' }}"
                        :aria-expanded="open"
                    >
                        <x-heroicon-o-briefcase />
                        <span>{{ __('Depots & Watchlist') }}</span>
                        <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="{ 'rotate-180': open }" />
                    </button>
                    <template x-teleport="body">
                        <div
                            x-cloak
                            x-show="open"
                            @keydown.escape.window="open = false"
                            x-transition.origin.top.left
                            class="fixed z-[100] w-64 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-2 shadow-2xl shadow-black/35 backdrop-blur-xl"
                            :style="`left: ${left}px; top: ${top}px`"
                        >
                            <a href="{{ route('depots.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                                <x-heroicon-o-briefcase class="h-5 w-5 text-teal-500" />
                                {{ __('aKI Depot') }}
                            </a>
                            <a href="{{ route('paper-depots.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                                <x-heroicon-o-beaker class="h-5 w-5 text-amber-500" />
                                {{ __('Musterdepot') }}
                            </a>
                            @if($strategyDepotAccess)
                                <a href="{{ $strategyDepotUrl }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-cyan-500/10 hover:text-cyan-400">
                                    <x-heroicon-o-bolt class="h-5 w-5 text-cyan-400" />
                                    <span>{{ __('Strategiedepot') }}</span>
                                    <span class="ml-auto rounded border border-cyan-300/35 bg-cyan-400/[.12] px-1.5 py-0.5 text-[8px] font-black leading-none tracking-wide text-cyan-300">PRO</span>
                                </a>
                            @else
                                <span class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-muted)] opacity-40" title="{{ __('Ab Pro verfügbar') }}" aria-disabled="true">
                                    <x-heroicon-o-bolt class="h-5 w-5 text-cyan-400" />
                                    <span>{{ __('Strategiedepot') }}</span>
                                    <span class="ml-auto rounded border border-cyan-300/25 bg-cyan-400/[.08] px-1.5 py-0.5 text-[8px] font-black leading-none tracking-wide text-cyan-300">PRO</span>
                                </span>
                            @endif
                            <a href="{{ route('watchlists.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                                <x-heroicon-o-star class="h-5 w-5 text-amber-500" />
                                Watchlist
                            </a>
                        </div>
                    </template>
                </div>
            @endauth
            @auth
                <div
                    x-data="{
                        open: false,
                        left: 0,
                        top: 0,
                        toggle() {
                            const bounds = this.$refs.trigger.getBoundingClientRect();
                            this.left = bounds.left;
                            this.top = bounds.bottom + 8;
                            this.open = !this.open;
                        }
                    }"
                    @click.outside="open = false"
                    class="relative shrink-0"
                >
                    <button
                        x-ref="trigger"
                        type="button"
                        @click="toggle()"
                        class="{{ request()->routeIs('setup.*') ? 'ak-top-link-active' : 'ak-top-link' }}"
                        :aria-expanded="open"
                    >
                        <x-heroicon-o-adjustments-horizontal />
                        <span>{{ __('Setup') }}</span>
                        <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="{ 'rotate-180': open }" />
                    </button>
                    <template x-teleport="body">
                        <div
                            x-cloak
                            x-show="open"
                            @keydown.escape.window="open = false"
                            x-transition.origin.top.left
                            class="fixed z-[100] w-64 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-2 shadow-2xl shadow-black/35 backdrop-blur-xl"
                            :style="`left: ${left}px; top: ${top}px`"
                        >
                            @php
                                $planAccess = app(\App\Services\PlanAccessService::class);
                                $canUseStrategies = $planAccess->allows(auth()->user(), \App\Enums\PlanLevel::Pro);
                                $canUseSmartSelection = $planAccess->allows(auth()->user(), \App\Enums\PlanLevel::Plus);
                                $lockedMenuClass = 'flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-slate-500 opacity-55';
                            @endphp
                            @if($canUseStrategies)
                            <a href="{{ route('setup.filter') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                                <x-heroicon-o-funnel class="h-5 w-5 text-teal-500" />
                                {{ __('Strategie') }}
                                <span class="ml-auto rounded-md border border-cyan-400/25 bg-cyan-400/10 px-1.5 py-0.5 text-[8px] font-black uppercase text-cyan-300">PRO</span>
                            </a>
                            <a href="{{ route('setup.short') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-rose-500/10 hover:text-rose-300">
                                <x-heroicon-o-arrow-trending-down class="h-5 w-5 text-rose-400" />
                                {{ __('Short-Strategie') }}
                                <span class="ml-auto rounded-md border border-cyan-400/25 bg-cyan-400/10 px-1.5 py-0.5 text-[8px] font-black uppercase text-cyan-300">PRO</span>
                            </a>
                            @else
                            <span class="{{ $lockedMenuClass }}" title="{{ __('Verfügbar ab Pro') }}" aria-disabled="true"><x-heroicon-o-funnel class="h-5 w-5" />{{ __('Strategie') }}<span class="ml-auto text-[8px] font-black uppercase">PRO</span></span>
                            <span class="{{ $lockedMenuClass }}" title="{{ __('Verfügbar ab Pro') }}" aria-disabled="true"><x-heroicon-o-arrow-trending-down class="h-5 w-5" />{{ __('Short-Strategie') }}<span class="ml-auto text-[8px] font-black uppercase">PRO</span></span>
                            @endif
                            @if($canUseSmartSelection)
                            <a href="{{ route('setup.quality') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-amber-500/10 hover:text-amber-300">
                                <x-heroicon-o-shield-check class="h-5 w-5 text-amber-400" />
                                {{ __('Smart Selection') }}
                                <span class="ml-auto rounded-md border border-amber-400/25 bg-amber-400/10 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-300">PLUS</span>
                            </a>
                            @else
                            <span class="{{ $lockedMenuClass }}" title="{{ __('Verfügbar ab Plus') }}" aria-disabled="true"><x-heroicon-o-shield-check class="h-5 w-5" />{{ __('Smart Selection') }}<span class="ml-auto text-[8px] font-black uppercase">PLUS</span></span>
                            @endif
                            @if($canUseStrategies)
                            <a href="{{ route('setup.saved-filters.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10 hover:text-teal-500">
                                <x-heroicon-o-bookmark-square class="h-5 w-5 text-amber-400" />
                                {{ __('Strategie Manager') }}
                                <span class="ml-auto rounded-md border border-cyan-400/25 bg-cyan-400/10 px-1.5 py-0.5 text-[8px] font-black uppercase text-cyan-300">PRO</span>
                            </a>
                            @else
                            <span class="{{ $lockedMenuClass }}" title="{{ __('Verfügbar ab Pro') }}" aria-disabled="true"><x-heroicon-o-bookmark-square class="h-5 w-5" />{{ __('Strategie Manager') }}<span class="ml-auto text-[8px] font-black uppercase">PRO</span></span>
                            @endif
                        </div>
                    </template>
                </div>
            @endauth
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

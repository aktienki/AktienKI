<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('AktienKI macht komplexe Marktdaten mit künstlicher Intelligenz verständlich.') }}">
    <title>{{ __('AktienKI – Märkte klarer sehen') }}</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .welcome-background {
            background-color: #070b22;
            background-image:
                radial-gradient(circle at 72% 20%, rgba(139, 92, 246, .19), transparent 30%),
                radial-gradient(circle at 18% 72%, rgba(251,146,60, .10), transparent 27%),
                linear-gradient(rgba(43, 29, 93, .30) 1px, transparent 1px),
                linear-gradient(90deg, rgba(43, 29, 93, .30) 1px, transparent 1px);
            background-size: auto, auto, 60px 60px, 60px 60px;
        }
        .welcome-topbar {
            background: rgba(7, 11, 34, .82);
            border-bottom: 1px solid rgba(139, 92, 246, .24);
            box-shadow: 0 12px 45px rgba(0, 0, 0, .24), 0 1px 0 rgba(251,146,60, .04) inset;
            backdrop-filter: blur(22px);
        }
        .welcome-scene { opacity: 0; filter:saturate(.74) brightness(.95) contrast(.97); transform: translateX(1.25rem) scale(1.025); transition: opacity .65s ease, transform .65s ease; pointer-events: none; }
        .welcome-scene.is-active { opacity: 1; transform: translateX(0) scale(1); pointer-events: auto; }
        @media (prefers-reduced-motion: reduce) { .welcome-scene { transition: none; } }
    </style>
</head>
<body class="welcome-background min-h-screen text-white antialiased lg:h-full lg:min-h-0 lg:overflow-hidden">
    <div class="relative min-h-screen overflow-hidden lg:h-full lg:min-h-0">
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_bottom,transparent_55%,#070b22_92%)]" aria-hidden="true"></div>

        <header class="ak-public-topbar welcome-topbar sticky top-0 z-30 h-[73px]">
          <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>

            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl border border-violet-300/15 bg-[linear-gradient(135deg,rgba(96,70,155,.28),rgba(56,91,150,.22))] px-3 py-2 text-sm font-bold text-violet-100 sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/[.05] hover:text-white sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <x-risk-profile-badge class="hidden xl:flex" />
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-xl border border-violet-300/20 bg-[linear-gradient(135deg,rgba(96,70,155,.74),rgba(56,91,150,.68))] px-3 py-2.5 text-sm font-semibold text-white shadow-[0_8px_22px_rgba(24,38,88,.18)] transition hover:border-violet-200/30 hover:brightness-110 sm:inline-flex">{{ __('Zum Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-xl border border-violet-300/20 bg-[linear-gradient(135deg,rgba(96,70,155,.78),rgba(56,91,150,.72))] px-3 py-2.5 text-sm font-bold text-white shadow-[0_8px_24px_rgba(24,38,88,.20)] transition hover:-translate-y-0.5 hover:border-violet-200/30 hover:brightness-110 lg:inline-flex">{{ __('Kostenlos starten') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
          </div>
        </header>

        <main class="relative z-10 lg:grid lg:h-[calc(100svh-73px)] lg:min-h-0 lg:grid-rows-[minmax(0,1fr)_auto] lg:overflow-hidden">
            <section class="grid items-center gap-8 px-5 py-8 sm:px-8 md:grid-cols-2 md:gap-6 lg:min-h-0 lg:overflow-hidden lg:grid-cols-[42%_58%] lg:gap-0 lg:px-0 lg:py-0">
                <div class="mx-auto max-w-xl lg:-mt-2 lg:mx-0 lg:max-w-none lg:self-start lg:px-[clamp(3rem,6vw,7rem)] lg:pt-0">
                    @if ($showBetaNotice ?? false)
                        @php
                            $betaTesterLimit = $betaTesterLimit ?? 50;
                            $betaTesterCount = min($betaTesterLimit, $betaTesterCount ?? 0);
                            $betaOfferAvailable = $betaTesterCount < $betaTesterLimit;
                            $betaProgress = $betaTesterLimit > 0 ? ($betaTesterCount / $betaTesterLimit) * 100 : 100;
                        @endphp
                        <aside class="mb-8 max-w-lg rounded-2xl border border-amber-300/30 bg-[linear-gradient(135deg,rgba(245,158,11,.16),rgba(139,92,246,.12))] p-4 shadow-[0_16px_45px_rgba(0,0,0,.22)] backdrop-blur-xl lg:mt-10 xl:mt-14">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-amber-300/30 bg-amber-300/10 text-amber-300">
                                    <x-heroicon-o-beaker class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-amber-300/15 px-2.5 py-1 text-[9px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Beta-Phase') }}</span>
                                        @if ($betaOfferAvailable)
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-violet-200">{{ __('Tester gesucht') }}</span>
                                        @endif
                                    </div>
                                    @if ($betaOfferAvailable)
                                        <h2 class="mt-2 text-sm font-black text-white">{{ __('Unterstütze uns – werde Tester.') }}</h2>
                                        <p class="mt-1 text-xs leading-5 text-slate-300">{{ __('AktienKI wird laufend weiterentwickelt.') }}</p>
                                        <p class="mt-1 text-xs font-semibold leading-5 text-amber-100">{{ __('Die ersten 50 registrierten Tester erhalten dauerhaft kostenlosen Zugang zum Pro-Modell.') }}</p>
                                        <div class="mt-3">
                                            <div class="mb-1.5 flex items-center justify-between gap-3 text-[10px] font-bold">
                                                <span class="text-slate-300">{{ __('Registrierte Tester') }}</span>
                                                <span class="text-amber-200">{{ $betaTesterCount }} / {{ $betaTesterLimit }}</span>
                                            </div>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-amber-300 transition-all duration-500" style="width: {{ $betaProgress }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <h2 class="mt-2 text-sm font-black text-white">{{ __('AktienKI befindet sich in der Testphase.') }}</h2>
                                        <p class="mt-1 text-xs leading-5 text-slate-300">{{ __('Das Projekt wird laufend weiterentwickelt und gemeinsam mit unseren Testern verbessert.') }}</p>
                                    @endif
                                </div>
                            </div>
                        </aside>
                    @endif
                    <p class="mb-5 flex items-baseline whitespace-nowrap leading-none" aria-label="aktienKI.com">
                        <span class="font-bold tracking-[.02em] text-white" style="font-size: 24px">aktien</span>
                        <span class="ml-0.5 font-black tracking-[-.04em] text-violet-400" style="font-size: 36px; color: #8b5cf6">KI</span>
                        <span class="font-bold tracking-[-.02em] text-white" style="font-size: 24px">.com</span>
                    </p>
                    <h1 class="whitespace-nowrap text-[2rem] font-bold leading-[1.06] tracking-[-0.04em] text-white min-[380px]:text-4xl md:text-[2.35rem] xl:text-[3.05rem] 2xl:text-[3.5rem]">
                        <span class="block text-slate-100">{{ __('Aktienanalyse.') }}</span>
                        <span class="mt-2 inline-flex items-center gap-2.5 rounded-xl border border-violet-300/15 bg-[linear-gradient(135deg,rgba(96,70,155,.18),rgba(56,91,150,.13))] px-3.5 py-2 text-[.62em] font-semibold tracking-[-0.025em] text-violet-100 shadow-[0_10px_28px_rgba(12,18,48,.14)]">
                            <i class="h-6 w-1 rounded-full bg-amber-300/70 shadow-[0_0_8px_rgba(214,168,79,.18)]"></i>
                            {{ __('Gestützt durch KI.') }}
                        </span>
                    </h1>
                    <p class="mt-6 text-[15px] leading-7 text-slate-300 sm:text-[17px]">
                        {{ __('AktienKI unterstützt dich bei der Analyse von Aktien. Dafür werden historische Kursdaten, Fundamentaldaten und weitere Marktsignale zusammengeführt und mithilfe künstlicher Intelligenz ausgewertet.') }}
                    </p>
                    <p class="mt-4 text-[15px] leading-7 text-slate-400 sm:text-[17px]">
                        {{ __('Das Ergebnis sind strukturierte, verständliche Einschätzungen zu Trends, Chancen und Risiken – als fundierte Grundlage für deine eigene Recherche.') }}
                    </p>
                </div>

                <div id="produkt" class="relative mx-auto aspect-[980/620] h-full max-h-full w-full overflow-hidden rounded-3xl border border-violet-300/15 bg-[#070b22] shadow-[-18px_0_64px_rgba(76,29,149,.13)] lg:mx-0 lg:aspect-auto lg:rounded-none lg:border-y-0 lg:border-r-0" data-scene-player>
                    <img src="{{ route('scenes.localized', ['scene' => 'traders', 'locale' => app()->getLocale(), 'v' => filemtime(public_path('assets/scene-traders.svg'))]) }}" alt="{{ __('Von Tradern für Trader') }}" class="welcome-scene is-active absolute inset-0 h-full w-full object-contain" data-scene>
                    <div
                        class="welcome-scene absolute inset-0 flex h-full w-full flex-col justify-center px-[8%] pb-[9%] pt-[7%]"
                        data-scene
                        x-data="welcomeStockMap(@js($welcomeCountries ?? []))"
                    >
                        <div class="mb-3 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-[clamp(.65rem,1vw,.9rem)] font-black uppercase tracking-[.2em] text-violet-300">{{ __('Globales Aktienuniversum') }}</p>
                                <h2 class="mt-1 text-[clamp(1.2rem,2.2vw,2rem)] font-black tracking-tight text-white">{{ __('Aktienmärkte weltweit') }}</h2>
                                <p class="mt-1 text-[clamp(.65rem,1vw,.85rem)] text-slate-400">{{ __('Länder mit verfügbaren Aktien im AktienKI-Universum') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border border-violet-400/25 bg-violet-500/10 px-3 py-1.5 text-[clamp(.6rem,.9vw,.75rem)] font-bold text-violet-200">
                                {{ count($welcomeCountries ?? []) }} {{ __('Länder') }}
                            </span>
                        </div>
                        <div class="relative min-h-0 flex-1 overflow-hidden rounded-2xl border border-violet-300/15 bg-violet-950/10 p-2">
                            <svg
                                x-ref="map"
                                class="h-full w-full text-slate-500"
                                viewBox="0 0 1000 500"
                                preserveAspectRatio="xMidYMid meet"
                                role="img"
                                aria-label="{{ __('Weltkarte der Länder mit verfügbaren Aktien') }}"
                            >
                                <rect x="0.5" y="0.5" width="999" height="499" rx="10" fill="none" stroke="currentColor" stroke-opacity=".15" />
                            </svg>
                            <p x-show="error" x-cloak class="absolute inset-0 grid place-items-center text-sm text-rose-300">{{ __('Kartendaten konnten nicht geladen werden.') }}</p>
                        </div>
                        <div class="mt-2 flex items-center justify-end gap-2 text-[10px] text-slate-500">
                            <i class="h-2.5 w-8 rounded-full border border-amber-300/35 bg-gradient-to-r from-amber-200/5 to-amber-300/20 shadow-[0_0_7px_rgba(214,168,79,.12)]"></i>
                            <span>{{ __('Aktien enthalten') }}</span>
                            <i class="h-2.5 w-8 rounded-full border border-amber-300/70 bg-gradient-to-r from-amber-200/10 to-amber-300/35 shadow-[0_0_9px_rgba(214,168,79,.20)]"></i>
                        </div>
                    </div>
                    <img src="{{ route('scenes.localized', ['scene' => 'machine-learning', 'locale' => app()->getLocale(), 'v' => filemtime(public_path('assets/scene-machine-learning.svg'))]) }}" alt="{{ __('Machine-Learning-Analyse') }}" class="welcome-scene absolute inset-0 h-full w-full object-contain" data-scene>
                    <img src="{{ route('scenes.localized', ['scene' => 'stock-chat', 'locale' => app()->getLocale(), 'v' => filemtime(public_path('assets/scene-stock-chat.svg'))]) }}" alt="{{ __('Aktie auswählen und mit KI chatten') }}" class="welcome-scene absolute inset-0 h-full w-full object-contain" data-scene data-restart-scene>

                    <button type="button" data-scene-previous aria-label="{{ __('Vorherige Animation') }}" class="absolute left-4 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/10 bg-slate-950/60 text-2xl text-white/70 backdrop-blur transition hover:border-violet-300/25 hover:bg-[linear-gradient(135deg,rgba(96,70,155,.62),rgba(56,91,150,.58))] hover:text-white lg:left-6">‹</button>
                    <button type="button" data-scene-next aria-label="{{ __('Nächste Animation') }}" class="absolute right-4 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/10 bg-slate-950/60 text-2xl text-white/70 backdrop-blur transition hover:border-violet-300/25 hover:bg-[linear-gradient(135deg,rgba(96,70,155,.62),rgba(56,91,150,.58))] hover:text-white lg:right-6">›</button>

                    <div class="absolute bottom-5 left-1/2 z-10 flex -translate-x-1/2 gap-2 rounded-full border border-white/10 bg-slate-950/50 px-4 py-3 backdrop-blur" role="tablist" aria-label="{{ __('Animationsschritte') }}">
                        <button type="button" data-scene-dot="0" aria-label="{{ __('Von Tradern für Trader') }}" aria-selected="true" class="h-1.5 w-8 rounded-full bg-violet-400 transition-all duration-300"></button>
                        <button type="button" data-scene-dot="1" aria-label="{{ __('Aktienmärkte weltweit') }}" aria-selected="false" class="h-1.5 w-2 rounded-full bg-slate-600 transition-all duration-300 hover:bg-slate-400"></button>
                        <button type="button" data-scene-dot="2" aria-label="Machine Learning" aria-selected="false" class="h-1.5 w-2 rounded-full bg-slate-600 transition-all duration-300 hover:bg-slate-400"></button>
                        <button type="button" data-scene-dot="3" aria-label="{{ __('Aktie auswählen und mit KI chatten') }}" aria-selected="false" class="h-1.5 w-2 rounded-full bg-slate-600 transition-all duration-300 hover:bg-slate-400"></button>
                    </div>
                </div>
            </section>

            <aside class="border-t border-violet-300/20 bg-[#090f2a]/90 px-5 py-3 backdrop-blur-xl sm:px-8 lg:px-12" aria-label="AktienKI Statistiken">
                <div class="mx-auto grid max-w-screen-2xl grid-cols-1 gap-2.5 min-[420px]:grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:gap-3">
                    @foreach ([
                        ['stocks', __('Aktien'), __('Analysierte Unternehmen')],
                        ['indices', __('Indizes'), __('Abgedeckte Märkte')],
                        ['sectors', __('Sektoren'), __('Beobachtete Branchen')],
                        ['forecasts', __('Prognosen'), __('Erstellte KI-Signale')],
                        ['data-points', __('Datenpunkte'), __('Verarbeitete Marktdaten')],
                    ] as [$key, $title, $description])
                        <article class="group flex min-w-0 items-center gap-3 rounded-xl border border-white/[.07] bg-white/[.035] px-3.5 py-3 transition hover:border-amber-300/20 hover:bg-amber-400/[.04]">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-amber-300/20 bg-amber-300/[.07] text-amber-300 shadow-[0_0_18px_rgba(245,158,11,.07)]">
                                @if ($key === 'stocks')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 19V9M10 19V5M16 19v-7M3 19h18"/><path d="m5 8 4-4 5 5 6-6"/></svg>
                                @elseif ($key === 'indices')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 18h16M6 15V9M12 15V5M18 15v-3"/><path d="m5 7 6-4 7 6"/></svg>
                                @elseif ($key === 'sectors')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                                @elseif ($key === 'forecasts')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 18V6M4 18h16"/><path d="m7 14 3-4 3 2 5-7"/><path d="M15 5h3v3"/></svg>
                                @else
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><ellipse cx="12" cy="5" rx="7.5" ry="3"/><path d="M4.5 5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3V5M4.5 11v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-baseline gap-2">
                                    <strong class="text-lg font-black leading-none text-white" data-stat="{{ $key }}">
                                        @if (isset($welcomeStats[$key]))
                                            {{ number_format((int) $welcomeStats[$key], 0, ',', app()->getLocale() === 'de' ? '.' : ',') }}
                                        @else
                                            —
                                        @endif
                                    </strong>
                                    <h2 class="truncate text-xs font-bold text-slate-300">{{ $title }}</h2>
                                </div>
                                <p class="mt-1 truncate text-[10px] text-slate-500">{{ $description }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </aside>
        </main>
    </div>
    <script>
        document.querySelectorAll('[data-scene-player]').forEach((player) => {
            const scenes = [...player.querySelectorAll('[data-scene]')];
            const dots = [...player.querySelectorAll('[data-scene-dot]')];
            let active = 0;
            let timer;

            const show = (index) => {
                active = (index + scenes.length) % scenes.length;
                scenes.forEach((scene, i) => scene.classList.toggle('is-active', i === active));
                const activeScene = scenes[active];
                if (activeScene?.hasAttribute('data-restart-scene')) {
                    const source = new URL(activeScene.src, window.location.href);
                    source.searchParams.set('play', Date.now().toString());
                    activeScene.src = source.toString();
                }
                dots.forEach((dot, i) => {
                    const selected = i === active;
                    dot.setAttribute('aria-selected', selected ? 'true' : 'false');
                    dot.classList.toggle('w-8', selected);
                    dot.classList.toggle('bg-violet-400', selected);
                    dot.classList.toggle('w-2', !selected);
                    dot.classList.toggle('bg-slate-600', !selected);
                });
            };
            const stop = () => window.clearInterval(timer);
            const play = () => {
                stop();
                if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    timer = window.setInterval(() => show(active + 1), 6500);
                }
            };

            player.querySelector('[data-scene-previous]').addEventListener('click', () => { show(active - 1); play(); });
            player.querySelector('[data-scene-next]').addEventListener('click', () => { show(active + 1); play(); });
            dots.forEach((dot, index) => dot.addEventListener('click', () => { show(index); play(); }));
            player.addEventListener('mouseenter', stop);
            player.addEventListener('mouseleave', play);
            play();
        });
    </script>
</body>
</html>

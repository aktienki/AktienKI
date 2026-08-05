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
            background-color: #07101e;
            background-image:
                radial-gradient(circle at 76% 20%, rgba(251,146,60, .085), transparent 31%),
                radial-gradient(circle at 18% 86%, rgba(251,146,60, .10), transparent 32%),
                radial-gradient(circle at 9% 14%, rgba(251, 191, 36, .025), transparent 20%),
                linear-gradient(135deg, #07101e 0%, #0b1628 50%, #101a30 100%);
        }
        .welcome-topbar {
            background: rgba(11, 20, 36, .96);
            border-bottom: 1px solid rgba(251,146,60, .14);
            box-shadow: 0 10px 30px rgba(2, 6, 23, .24), inset 0 -1px 0 rgba(251,146,60, .035);
            backdrop-filter: blur(18px) saturate(115%);
        }
        .welcome-dashboard-card {
            background-color: rgba(52, 65, 95, .60);
            border-color: rgba(251,146,60, .32);
            box-shadow: 0 12px 30px rgba(2, 132, 199, .10), inset 0 1px 0 rgba(251,146,60, .035);
            backdrop-filter: blur(8px);
        }
        .welcome-top-link { color:#cbd5e1; border:1px solid transparent; background:transparent; }
        .welcome-top-link:hover { color:#fb923c; border-color:rgba(251,146,60,.16); background:rgba(251,146,60,.075); }
        .welcome-top-link-active { position:relative; color:#fb923c; border:1px solid rgba(251,146,60,.22); background:rgba(251,146,60,.10); }
        .welcome-top-link-active::after { content:""; position:absolute; right:.7rem; bottom:-.38rem; left:.7rem; height:2px; border-radius:999px; background:#fb923c; box-shadow:0 0 12px rgba(251,146,60,.4); }
        .welcome-scene { opacity: 0; filter:saturate(.74) brightness(.95) contrast(.97); transform: translateX(1.25rem) scale(1.025); transition: opacity .65s ease, transform .65s ease; pointer-events: none; }
        .welcome-scene.is-active { opacity: 1; transform: translateX(0) scale(1); pointer-events: auto; }
        @media (prefers-reduced-motion: reduce) { .welcome-scene { transition: none; } }
    </style>
</head>
<body class="welcome-background min-h-screen text-white antialiased lg:h-full lg:min-h-0 lg:overflow-hidden">
    <div class="relative min-h-screen overflow-hidden lg:h-full lg:min-h-0">
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_bottom,transparent_58%,rgba(9,13,34,.72)_94%)]" aria-hidden="true"></div>

        <header class="ak-public-topbar welcome-topbar sticky top-0 z-30 h-[73px]">
          <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>

            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="welcome-top-link-active hidden w-24 justify-center rounded-lg px-3 py-2 text-sm font-bold sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="welcome-top-link hidden w-24 justify-center rounded-lg px-3 py-2 text-sm font-semibold transition lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="welcome-top-link hidden w-24 justify-center rounded-lg px-3 py-2 text-sm font-semibold transition lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="welcome-top-link hidden w-20 justify-center rounded-lg px-3 py-2 text-sm font-semibold transition sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition hover:bg-white/[.05] hover:text-white lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <x-risk-profile-badge class="hidden xl:flex" />
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-lg border border-orange-400/25 bg-orange-400/15 px-3 py-2.5 text-sm font-semibold text-orange-400 shadow-[0_8px_22px_rgba(251,146,60,.12)] transition hover:border-orange-400/40 hover:bg-orange-400/25 sm:inline-flex">{{ __('Zum Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:text-white sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-lg border border-orange-400/30 bg-orange-400/20 px-3 py-2.5 text-sm font-bold text-orange-400 shadow-[0_8px_24px_rgba(251,146,60,.14)] transition hover:border-orange-400/50 hover:bg-orange-400/30 lg:inline-flex">{{ __('Kostenlos starten') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
          </div>
        </header>

        <main class="relative z-10 lg:grid lg:h-[calc(100svh-73px)] lg:min-h-0 lg:grid-rows-[minmax(0,1fr)_auto] lg:overflow-hidden">
            <section class="grid items-stretch gap-4 px-5 py-5 sm:px-8 md:grid-cols-2 lg:min-h-0 lg:overflow-hidden lg:grid-cols-[41%_59%] lg:px-8 lg:py-4 xl:px-10">
                <div class="welcome-dashboard-card mx-auto flex max-w-xl flex-col justify-start rounded-2xl border p-5 lg:mx-0 lg:min-h-0 lg:max-w-none lg:p-6">
                    @if ($showBetaNotice ?? false)
                        @php
                            $betaTesterLimit = $betaTesterLimit ?? 50;
                            $betaTesterCount = min($betaTesterLimit, $betaTesterCount ?? 0);
                            $betaOfferAvailable = $betaTesterCount < $betaTesterLimit;
                            $betaProgress = $betaTesterLimit > 0 ? ($betaTesterCount / $betaTesterLimit) * 100 : 100;
                        @endphp
                        <aside class="mb-6 w-full rounded-xl border border-amber-300/30 bg-amber-300/[.07] p-3 shadow-[0_12px_30px_rgba(2,6,23,.14)]">
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-amber-300/30 bg-amber-300/10 text-amber-300">
                                    <x-heroicon-o-beaker class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-amber-300/15 px-2 py-0.5 text-[8px] font-black uppercase tracking-[.14em] text-amber-300">{{ __('Beta-Phase') }}</span>
                                        @if ($betaOfferAvailable)
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-orange-400">{{ __('Tester gesucht') }}</span>
                                        @endif
                                    </div>
                                    @if ($betaOfferAvailable)
                                        <h2 class="mt-1.5 text-[13px] font-black text-white">{{ __('Unterstütze uns – werde Tester.') }}</h2>
                                        <p class="mt-0.5 text-[11px] leading-4 text-slate-300">{{ __('AktienKI wird laufend weiterentwickelt.') }}</p>
                                        <p class="mt-0.5 text-[11px] font-semibold leading-4 text-amber-100">{{ __('Die ersten 50 registrierten Tester erhalten dauerhaft kostenlosen Zugang zum Pro-Modell.') }}</p>
                                        <div class="mt-2">
                                            <div class="mb-1 flex items-center justify-between gap-3 text-[9px] font-bold">
                                                <span class="text-slate-300">{{ __('Registrierte Tester') }}</span>
                                                <span class="text-amber-200">{{ $betaTesterCount }} / {{ $betaTesterLimit }}</span>
                                            </div>
                                            <div class="h-1 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full rounded-full bg-gradient-to-r from-orange-400 to-amber-300 transition-all duration-500" style="width: {{ $betaProgress }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <h2 class="mt-1.5 text-[13px] font-black text-white">{{ __('AktienKI befindet sich in der Testphase.') }}</h2>
                                        <p class="mt-0.5 text-[11px] leading-4 text-slate-300">{{ __('Das Projekt wird laufend weiterentwickelt und gemeinsam mit unseren Testern verbessert.') }}</p>
                                    @endif
                                </div>
                            </div>
                        </aside>
                    @endif
                    <div class="mb-5">
                        <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[.2em] text-orange-400">
                            <i class="h-px w-8 bg-orange-400/70"></i>
                            {{ __('aKI Market Intelligence') }}
                        </p>
                        <h1 class="mt-3 max-w-[12ch] text-[2.15rem] font-black leading-[1.04] tracking-[-0.045em] text-slate-100 min-[420px]:text-[2.55rem] xl:text-[3rem] 2xl:text-[3.35rem]">
                            {{ __('Aktienanalyse mit KI.') }}
                        </h1>
                        <div class="mt-4 flex items-center gap-2.5 text-xs font-bold uppercase tracking-[.12em] text-slate-400">
                            <i class="h-5 w-1 rounded-full bg-amber-300/75"></i>
                            {{ __('Research · Intelligence · Decisions') }}
                        </div>
                    </div>
                    <p class="mt-6 text-[15px] leading-7 text-slate-300 sm:text-[17px]">
                        {{ __('AktienKI unterstützt dich bei der Analyse von Aktien. Dafür werden historische Kursdaten, Fundamentaldaten und weitere Marktsignale zusammengeführt und mithilfe künstlicher Intelligenz ausgewertet.') }}
                    </p>
                    <p class="mb-5 mt-4 text-[15px] leading-7 text-slate-400 sm:text-[17px] lg:mb-7">
                        {{ __('Das Ergebnis sind strukturierte, verständliche Einschätzungen zu Trends, Chancen und Risiken – als fundierte Grundlage für deine eigene Recherche.') }}
                    </p>
                </div>

                <div id="produkt" class="welcome-dashboard-card relative mx-auto aspect-[980/620] h-full max-h-full w-full overflow-hidden rounded-2xl border bg-[#0b1424]/55 lg:mx-0 lg:aspect-auto" data-scene-player>
                    <img src="{{ route('scenes.localized', ['scene' => 'traders', 'locale' => app()->getLocale(), 'v' => filemtime(public_path('assets/scene-traders.svg'))]) }}" alt="{{ __('Von Tradern für Trader') }}" class="welcome-scene is-active absolute inset-0 h-full w-full object-contain" data-scene>
                    <div
                        class="welcome-scene absolute inset-0 flex h-full w-full flex-col justify-center px-[8%] pb-[9%] pt-[7%]"
                        data-scene
                        x-data="welcomeStockMap(@js($welcomeCountries ?? []))"
                    >
                        <div class="mb-3 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-[clamp(.65rem,1vw,.9rem)] font-black uppercase tracking-[.2em] text-orange-400">{{ __('Globales Aktienuniversum') }}</p>
                                <h2 class="mt-1 text-[clamp(1.2rem,2.2vw,2rem)] font-black tracking-tight text-white">{{ __('Aktienmärkte weltweit') }}</h2>
                                <p class="mt-1 text-[clamp(.65rem,1vw,.85rem)] text-slate-400">{{ __('Länder mit verfügbaren Aktien im AktienKI-Universum') }}</p>
                            </div>
                            <span class="shrink-0 rounded-lg border border-orange-400/25 bg-orange-400/10 px-3 py-1.5 text-[clamp(.6rem,.9vw,.75rem)] font-bold text-orange-400">
                                {{ count($welcomeCountries ?? []) }} {{ __('Länder') }}
                            </span>
                        </div>
                        <div class="relative min-h-0 flex-1 overflow-hidden rounded-xl border border-orange-400/15 bg-orange-400/10 p-2">
                            <svg
                                x-ref="map"
                                class="h-full w-full text-slate-500"
                                viewBox="0 0 1000 500"
                                preserveAspectRatio="xMidYMid meet"
                                role="img"
                                aria-label="{{ __('Weltkarte der Länder mit verfügbaren Aktien') }}"
                            >
                                <rect x="0.5" y="0.5" width="999" height="499" rx="10" fill="none" stroke="currentColor" stroke-opacity=".24" shape-rendering="geometricPrecision" />
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

                    <button type="button" data-scene-previous aria-label="{{ __('Vorherige Animation') }}" class="absolute left-4 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-lg border border-orange-400/15 bg-[#0b1424]/75 text-2xl text-slate-300 backdrop-blur transition hover:border-orange-400/35 hover:bg-orange-400/15 hover:text-orange-400 lg:left-6">‹</button>
                    <button type="button" data-scene-next aria-label="{{ __('Nächste Animation') }}" class="absolute right-4 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-lg border border-orange-400/15 bg-[#0b1424]/75 text-2xl text-slate-300 backdrop-blur transition hover:border-orange-400/35 hover:bg-orange-400/15 hover:text-orange-400 lg:right-6">›</button>

                    <div class="absolute bottom-5 left-1/2 z-10 flex -translate-x-1/2 gap-2 rounded-lg border border-orange-400/15 bg-[#0b1424]/75 px-4 py-3 backdrop-blur" role="tablist" aria-label="{{ __('Animationsschritte') }}">
                        <button type="button" data-scene-dot="0" aria-label="{{ __('Von Tradern für Trader') }}" aria-selected="true" class="h-1.5 w-8 rounded-full bg-orange-400 transition-all duration-300"></button>
                        <button type="button" data-scene-dot="1" aria-label="{{ __('Aktienmärkte weltweit') }}" aria-selected="false" class="h-1.5 w-2 rounded-full bg-slate-600 transition-all duration-300 hover:bg-slate-400"></button>
                        <button type="button" data-scene-dot="2" aria-label="Machine Learning" aria-selected="false" class="h-1.5 w-2 rounded-full bg-slate-600 transition-all duration-300 hover:bg-slate-400"></button>
                        <button type="button" data-scene-dot="3" aria-label="{{ __('Aktie auswählen und mit KI chatten') }}" aria-selected="false" class="h-1.5 w-2 rounded-full bg-slate-600 transition-all duration-300 hover:bg-slate-400"></button>
                    </div>
                </div>
            </section>

            <aside class="border-t border-orange-400/15 bg-[#0b1424]/82 px-5 py-3 backdrop-blur-xl sm:px-8 lg:px-10" aria-label="AktienKI Statistiken">
                <div class="mx-auto grid max-w-screen-2xl grid-cols-1 gap-2.5 min-[420px]:grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:gap-3">
                    @foreach ([
                        ['stocks', __('Aktien'), __('Analysierte Unternehmen')],
                        ['indices', __('Indizes'), __('Abgedeckte Märkte')],
                        ['sectors', __('Sektoren'), __('Beobachtete Branchen')],
                        ['forecasts', __('Prognosen'), __('Erstellte KI-Signale')],
                        ['data-points', __('Datenpunkte'), __('Verarbeitete Marktdaten')],
                    ] as [$key, $title, $description])
                        <article class="welcome-dashboard-card group flex min-w-0 items-center gap-3 rounded-xl border px-3.5 py-3">
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
                    dot.classList.toggle('bg-orange-400', selected);
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

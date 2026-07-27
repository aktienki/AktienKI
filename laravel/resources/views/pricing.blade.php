<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AktienKI Tarife und Funktionsumfang im Vergleich.">
    <title>{{ __('Preise') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('assets/logo.svg') }}" type="image/svg+xml">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .pricing-bg { background-color:#070b22; background-image:radial-gradient(circle at 72% 20%,rgba(139,92,246,.19),transparent 30%),radial-gradient(circle at 18% 72%,rgba(34,211,238,.1),transparent 27%),linear-gradient(rgba(43,29,93,.3) 1px,transparent 1px),linear-gradient(90deg,rgba(43,29,93,.3) 1px,transparent 1px);background-size:auto,auto,60px 60px,60px 60px; }
        .pricing-topbar { background:rgba(7,11,34,.82);border-bottom:1px solid rgba(139,92,246,.24);box-shadow:0 12px 45px rgba(0,0,0,.24),0 1px 0 rgba(34,211,238,.04) inset;backdrop-filter:blur(22px); }
        .pricing-featured { border-width:2px;border-color:rgba(251,191,36,.75);background:linear-gradient(145deg,rgba(251,191,36,.30),rgba(245,158,11,.11));box-shadow:0 26px 64px rgba(180,83,9,.32),0 0 38px rgba(251,191,36,.16),inset 0 1px 0 rgba(254,243,199,.22);transform:translateY(-.35rem); }
        .pricing-featured:hover { border-color:rgba(253,230,138,.95);transform:translateY(-.55rem); }
        .pricing-popular-badge { border:1px solid rgba(251,191,36,.48);background:rgba(251,191,36,.21);color:#fef3c7; }
        .pricing-featured-button { background:linear-gradient(90deg,#f59e0b,#fbbf24);color:#1c1917;box-shadow:0 10px 28px rgba(180,83,9,.3),inset 0 1px 0 rgba(255,255,255,.3); }
        .pricing-featured-button:hover { filter:brightness(1.08);box-shadow:0 12px 34px rgba(245,158,11,.4); }
        .pricing-planned { border-style:dashed;border-color:rgba(248,113,113,.45);background:linear-gradient(145deg,rgba(153,27,27,.25),rgba(190,24,93,.10) 55%,rgba(15,23,42,.22));box-shadow:0 20px 48px rgba(127,29,29,.18),inset 0 1px 0 rgba(254,202,202,.07); }
        .pricing-planned-badge { border:1px solid rgba(252,165,165,.82);background:linear-gradient(90deg,#991b1b,#dc2626 52%,#f87171);color:#fff;box-shadow:0 0 24px rgba(239,68,68,.38),0 6px 18px rgba(127,29,29,.3); }
        .pricing-planned-button { border:1px dashed rgba(148,163,184,.28);background:rgba(148,163,184,.06);color:#94a3b8;cursor:not-allowed; }
        [data-theme="light"] .pricing-bg { background-color:#f8fafc; background-image:radial-gradient(circle at 75% 15%,rgba(139,92,246,.13),transparent 30%),radial-gradient(circle at 15% 80%,rgba(34,211,238,.1),transparent 28%),linear-gradient(rgba(124,58,237,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.07) 1px,transparent 1px); }
        [data-theme="light"] .pricing-topbar { background:rgba(255,255,255,.82);border-color:rgba(124,58,237,.16); }
        [data-theme="light"] .pricing-featured { border-color:rgba(217,119,6,.48);background:linear-gradient(145deg,rgba(251,191,36,.28),rgba(255,255,255,.78)); }
        [data-theme="light"] .pricing-popular-badge { color:#92400e; }
        [data-theme="light"] .pricing-planned { border-color:rgba(220,38,38,.34);background:linear-gradient(145deg,rgba(254,226,226,.9),rgba(255,241,242,.82) 58%,rgba(255,255,255,.9)); }
        [data-theme="light"] .pricing-planned-badge { border-color:#b91c1c;background:linear-gradient(90deg,#991b1b,#dc2626 52%,#f87171);color:#fff; }
    </style>
</head>
<body class="pricing-bg min-h-screen text-[var(--ak-text)] antialiased lg:h-full lg:min-h-0 lg:overflow-hidden">
    <header class="ak-public-topbar pricing-topbar sticky top-0 z-30 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <span class="flex h-10 w-[4.5rem] items-center justify-center">
                    <img src="{{ asset('assets/logo.svg') }}" alt="" class="h-9 w-16">
                </span>
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl bg-[var(--ak-accent-soft)] px-3 py-2 text-sm font-bold text-[var(--ak-accent)] sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 px-3 py-2.5 text-sm font-bold text-white sm:inline-flex">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center px-3 py-2.5 text-sm font-semibold text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-xl border border-cyan-300/25 bg-gradient-to-r from-violet-600 to-cyan-500 px-3 py-2.5 text-sm font-bold text-white shadow-lg shadow-violet-950/40 transition hover:-translate-y-0.5 hover:brightness-110 lg:inline-flex">{{ __('Kostenlos starten') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="mx-auto flex max-w-7xl flex-col px-5 py-10 sm:px-8 lg:h-[calc(100svh-73px)] lg:min-h-0 lg:px-10 lg:py-5">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[.22em] text-[var(--ak-accent)]">{{ __('Tarife') }}</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Der passende Zugang zu AktienKI.') }}</h1>
            <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-[var(--ak-muted)]">{{ __('Beginne kostenlos und erweitere deinen Analyseumfang, wenn deine Anforderungen wachsen.') }}</p>
        </div>

        @php
            $plans = [
                [
                    'name' => __('Free'), 'price' => '0 €', 'suffix' => __('dauerhaft'), 'featured' => false, 'planned' => false,
                    'description' => __('Für den Einstieg in datenbasierte Aktienanalysen.'),
                    'features' => [__('Begrenzte Aktienauswahl'), __('Basis-KI-Scores'), __('Eine persönliche Watchlist'), __('Tägliche Marktübersicht')],
                ],
                [
                    'name' => __('Pro'), 'price' => '19 €', 'suffix' => __('pro Monat'), 'featured' => true, 'planned' => false,
                    'description' => __('Für Anleger mit regelmäßigem Analysebedarf.'),
                    'features' => [__('Erweiterte Aktien- und Indexabdeckung'), __('Detaillierte KI-Analysen'), __('Mehrere Watchlists'), __('Risiko- und Trendsignale'), __('Priorisierte Datenaktualisierung')],
                ],
                [
                    'name' => __('Expert'), 'price' => '49 €', 'suffix' => __('pro Monat'), 'featured' => false, 'planned' => true,
                    'description' => __('Für intensive Recherche und umfassende Marktbeobachtung.'),
                    'features' => [__('Vollständige Marktabdeckung'), __('Alle KI-Modelle und Scores'), __('Unbegrenzte Watchlists'), __('Historische Modellvergleiche'), __('Erweiterte Exporte und Auswertungen')],
                ],
            ];
        @endphp

        <div class="mt-7 grid items-stretch gap-5 lg:min-h-0 lg:flex-1 lg:grid-cols-[minmax(0,3fr)_minmax(230px,.9fr)]">
            <div class="grid items-stretch gap-5 md:grid-cols-3 md:gap-3 lg:min-h-0 lg:gap-4">
                @foreach ($plans as $plan)
                    <article class="relative flex flex-col rounded-[1.5rem] border p-5 shadow-[var(--ak-shadow)] backdrop-blur-xl transition lg:min-h-0 {{ $plan['featured'] ? 'pricing-featured' : ($plan['planned'] ? 'pricing-planned' : 'border-[var(--ak-border)] bg-[var(--ak-card-strong)]') }}">
                        @if ($plan['featured'])
                            <span class="pricing-popular-badge absolute right-5 top-5 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider">{{ __('Beliebt') }}</span>
                        @elseif ($plan['planned'])
                            <span class="pricing-planned-badge absolute right-5 top-5 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider" style="background: linear-gradient(90deg, #7f1d1d 0%, #dc2626 52%, #fb7185 100%); color: #fff; border-color: rgba(254, 202, 202, .9);">{{ __('In Planung') }}</span>
                        @endif
                        <h2 class="text-xl font-black">{{ $plan['name'] }}</h2>
                        <p class="mt-2 min-h-10 text-xs leading-5 text-[var(--ak-muted)]">{{ $plan['description'] }}</p>
                        <div class="mt-3 flex items-end gap-2"><strong class="text-3xl font-black tracking-tight">{{ $plan['price'] }}</strong><span class="pb-1 text-[10px] text-[var(--ak-muted)]">{{ $plan['suffix'] }}</span></div>
                        <div class="my-3 h-px bg-[var(--ak-border)]"></div>
                        <ul class="flex-1 space-y-2">
                            @foreach ($plan['features'] as $feature)
                                <li class="flex gap-2.5 text-xs leading-5"><span class="text-cyan-400">✓</span><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>
                        @if ($plan['planned'])
                            <span class="pricing-planned-button mt-4 flex h-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold">{{ __('In Planung') }}</span>
                        @else
                            <a href="{{ route('register') }}" class="mt-4 flex h-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition hover:-translate-y-0.5 {{ $plan['featured'] ? 'pricing-featured-button' : 'border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] hover:bg-[var(--ak-accent-soft)]' }}">{{ __('Tarif auswählen') }}</a>
                        @endif
                    </article>
                @endforeach
            </div>

            <aside class="flex flex-col rounded-[1.5rem] border border-cyan-300/20 bg-[var(--ak-card-strong)] p-4 shadow-[var(--ak-shadow)] backdrop-blur-xl">
                <div class="flex items-center gap-3 border-b border-[var(--ak-border)] pb-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-cyan-500 text-xs font-black text-white">KI</span>
                    <div><h2 class="text-sm font-black">{{ __('Aki Chat-Assistent') }}</h2><p class="mt-0.5 text-[10px] text-[var(--ak-muted)]">{{ __('Flexibel zubuchbar') }}</p></div>
                </div>
                <div class="mt-3 grid flex-1 gap-2.5 sm:grid-cols-3 lg:grid-cols-1">
                    @foreach ([
                        [__('Free'), 3, false],
                        [__('Basispaket'), 25, false],
                        [__('Premium'), 100, true],
                    ] as [$name, $questions, $featured])
                        <article class="flex flex-col justify-center rounded-2xl border px-3.5 py-3 {{ $featured ? 'border-violet-400/60 bg-violet-500/[.12]' : 'border-[var(--ak-border)] bg-white/[.03]' }}">
                            <div class="flex items-center justify-between gap-2"><h3 class="text-xs font-black">{{ $name }}</h3>@if($featured)<span class="rounded-full bg-violet-500/20 px-2 py-0.5 text-[8px] font-black uppercase text-violet-300">{{ __('Maximal') }}</span>@endif</div>
                            <p class="mt-1.5"><strong class="text-2xl font-black text-[var(--ak-text)]">{{ $questions }}</strong> <span class="text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Fragen pro Monat') }}</span></p>
                        </article>
                    @endforeach
                </div>
                <a href="{{ route('register') }}" class="mt-3 flex h-10 shrink-0 items-center justify-center rounded-xl border border-violet-400/30 bg-violet-500/10 text-xs font-bold text-violet-300 transition hover:bg-violet-500/20">{{ __('Chat-Tarif wählen') }}</a>
            </aside>
        </div>

        <p class="mx-auto mt-4 max-w-3xl shrink-0 text-center text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Alle Preise verstehen sich inklusive gesetzlicher Umsatzsteuer. AktienKI ist ein Analysewerkzeug und keine Anlageberatung. Tarife und Funktionsumfang können sich vor dem offiziellen Start noch ändern.') }}</p>
    </main>
</body>
</html>

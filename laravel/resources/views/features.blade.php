<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lg:h-full lg:overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('So funktioniert die KI-gestützte Aktienanalyse von AktienKI.') }}">
    <title>{{ __('Features') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .features-bg{--ak-accent:#fb923c;--ak-accent-soft:rgba(251,146,60,.10);--ak-card-strong:rgba(52,65,95,.60);--ak-border:rgba(251,146,60,.24);background-color:#090d22;background-image:radial-gradient(circle at 73% 34%,rgba(124,58,237,.16),transparent 34%),radial-gradient(circle at 28% 92%,rgba(251,146,60,.13),transparent 34%),radial-gradient(circle at 8% 16%,rgba(251,191,36,.04),transparent 22%),linear-gradient(135deg,#090d22 0%,#10162f 48%,#171033 100%)}
        .features-topbar{background:rgba(11,20,36,.96);border-bottom:1px solid rgba(251,146,60,.14);box-shadow:0 10px 30px rgba(2,6,23,.24),inset 0 -1px 0 rgba(251,146,60,.035);backdrop-filter:blur(18px) saturate(115%)}
        .feature-dashboard-card{background-color:rgba(52,65,95,.60);border-color:rgba(251,146,60,.30);box-shadow:0 12px 30px rgba(2,132,199,.10),inset 0 1px 0 rgba(251,146,60,.035);backdrop-filter:blur(8px)}
        @media (min-width:1024px){.feature-card-grid{height:auto;min-height:27.5rem}.feature-dashboard-card{min-height:27.5rem;overflow:visible}}
        .feature-icon{color:#fbbf24;border-color:rgba(251,191,36,.34);background:linear-gradient(145deg,rgba(251,191,36,.16),rgba(245,158,11,.05));box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 0 24px rgba(245,158,11,.09)}
        .feature-icon svg{width:1.35rem;height:1.35rem}
        .feature-stat-icon{color:#fff}
        [data-theme="light"] .features-bg{background-color:#f8fafc;background-image:radial-gradient(circle at 75% 15%,rgba(139,92,246,.13),transparent 30%),radial-gradient(circle at 15% 80%,rgba(251,146,60,.1),transparent 28%),linear-gradient(rgba(124,58,237,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.07) 1px,transparent 1px)}
        [data-theme="light"] .features-topbar{background:rgba(255,255,255,.82);border-color:rgba(124,58,237,.16)}
        [data-theme="light"] .feature-icon{color:#b45309;border-color:rgba(217,119,6,.3);background:rgba(245,158,11,.11)}
    </style>
</head>
<body class="features-bg min-h-screen text-[var(--ak-text)] antialiased">
    <header class="ak-public-topbar features-topbar sticky top-0 z-30 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl bg-[var(--ak-accent-soft)] px-3 py-2 text-sm font-bold leading-5 text-[var(--ak-accent)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold leading-5 text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Preise') }}</a>
                @auth
                <a href="{{ route('contact') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Kontakt') }}" aria-label="{{ __('Kontakt') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a>
                @endauth
                <a href="{{ route('reviews.index') }}" class="hidden h-10 w-10 items-center justify-center rounded-xl text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:flex" title="{{ __('Bewertungen') }}" aria-label="{{ __('Bewertungen') }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z" stroke-linejoin="round"/></svg></a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-36 justify-center rounded-lg border border-orange-400/25 bg-orange-400/15 px-3 py-2.5 text-sm font-semibold leading-5 text-orange-400 sm:inline-flex">{{ __('Zum Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2.5 text-sm font-semibold leading-5 text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-40 whitespace-nowrap justify-center rounded-lg border border-orange-400/30 bg-orange-400/20 px-3 py-2.5 text-sm font-bold leading-5 text-orange-400 shadow-lg shadow-orange-400/20 transition hover:bg-orange-400/30 lg:inline-flex">{{ __('Als Tester registrieren') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main class="mx-auto flex max-w-7xl flex-col px-5 py-5 sm:px-8 lg:px-10 lg:py-3">
        <section class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[.22em] text-orange-400">{{ __('So funktioniert AktienKI') }}</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">{{ __('Von Marktdaten zu verständlichen Erkenntnissen.') }}</h1>
            <p class="mx-auto mt-1 max-w-2xl text-xs leading-5 text-[var(--ak-muted)]">{{ __('AktienKI verbindet strukturierte Finanzdaten mit künstlicher Intelligenz und bereitet komplexe Zusammenhänge nachvollziehbar auf.') }}</p>
        </section>

        <section class="feature-card-grid relative mt-3 grid gap-4 md:grid-cols-2 lg:flex-none lg:grid-cols-4" aria-label="{{ __('Analyseprozess') }}">
            @foreach ([
                ['01', __('Daten zusammenführen'), __('Historische Kurse, Fundamentaldaten, Volumen und weitere Marktsignale werden gesammelt, geprüft und für die Analyse vorbereitet.'), 'database'],
                ['02', __('Machine Learning'), __('Mehrere KI-Modelle analysieren Trends, Zusammenhänge und Abweichungen parallel. Ihre Prognosen konkurrieren miteinander, werden laufend verglichen und nach ihrer Qualität gewichtet.'), 'brain'],
                ['03', __('Research neu gedacht'), __('Scores, Signale und Risikohinweise verdichten die Auswertung zu einer klaren Grundlage für deine eigene Recherche.'), 'insights'],
                ['04', __('Trading-Erfolg im Fokus'), __('Auch KI schafft keine Gewissheit. Sie erkennt Muster und unterstützt strukturierte Entscheidungen, doch Prognosen können falsch sein. Eigene Recherche und konsequentes Risikomanagement bleiben unverzichtbar.'), 'success'],
            ] as [$number, $title, $copy, $icon])
                <article class="feature-dashboard-card relative rounded-2xl border p-4">
                    <div class="flex items-center justify-between">
                        <span class="feature-icon flex h-10 w-10 items-center justify-center rounded-xl border" aria-hidden="true">
                            @if ($icon === 'database')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="7.5" ry="3"/><path d="M4.5 5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3V5M4.5 11v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6"/></svg>
                            @elseif ($icon === 'brain')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="5" cy="6" r="1.7"/><circle cx="5" cy="18" r="1.7"/><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/><circle cx="19" cy="8" r="1.7"/><circle cx="19" cy="16" r="1.7"/><path d="m6.6 6 3.7-.8M6.5 6.8l4 4M6.5 17.2l4-4M6.6 18l3.7.8M13.6 5.7l3.8 1.8M13.7 11.5l3.7-2.7M13.7 12.5l3.7 2.7M13.6 18.3l3.8-1.8"/></svg>
                            @elseif ($icon === 'insights')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.2 15.2 5 5M7 13l2.3-2.4 2 1.6 3-4"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 20V4"/><path d="M5 5h10l-2 3 2 3H5"/><path d="m8 17 3-3 2.5 2.5L20 10"/><path d="M16 10h4v4"/></svg>
                            @endif
                        </span>
                        <span class="text-xs font-black tracking-[.2em] text-amber-400">{{ $number }}</span>
                    </div>
                    <h2 class="mt-2 whitespace-nowrap text-base font-black xl:text-lg">{{ $title }}</h2>
                    @if ($number === '01')
                        <span class="mt-2 inline-flex w-fit items-center rounded-full border border-amber-300/30 bg-amber-400/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-[.08em] text-amber-300">{{ __('Ständig kommen neue Aktien dazu') }}</span>
                        <p class="mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Unsere Datenbasis wächst kontinuierlich und verbindet Märkte aus aller Welt:') }}</p>
                        <ul class="mt-2 grid gap-1.5 text-xs" aria-label="{{ __('Aktueller Datenbestand') }}">
                            @foreach ([
                                [__('Länder'), $featureStats['countries'], 'countries'],
                                [__('Sektoren'), $featureStats['sectors'], 'sectors'],
                                [__('Aktien'), $featureStats['stocks'], 'stocks'],
                                [__('Indizes'), $featureStats['indices'], 'indices'],
                            ] as [$label, $value, $statIcon])
                                <li class="flex items-center gap-2 rounded-lg bg-amber-400/[.035] px-3 py-1">
                                    <span class="flex w-32 shrink-0 items-center gap-2.5 text-white">
                                        <span class="feature-icon feature-stat-icon flex h-7 w-7 shrink-0 items-center justify-center rounded-md border-0">
                                            @if ($statIcon === 'countries')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9S14.4 18.5 12 21M12 3C9.6 5.5 8.4 8.5 8.4 12s1.2 6.5 3.6 9"/></svg>
                                            @elseif ($statIcon === 'sectors')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                                            @elseif ($statIcon === 'stocks')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V9M10 19V5M16 19v-7M3 19h18"/><path d="m5 8 4-4 5 5 6-6"/></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 18h16M6 15V9M12 15V5M18 15v-3"/><path d="m5 7 6-4 7 6"/></svg>
                                            @endif
                                        </span>
                                        {{ $label }}
                                    </span>
                                    <strong class="text-sm font-black tabular-nums text-white">{{ number_format($value, 0, ',', '.') }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($number === '02')
                        <p class="mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Training und Validierung laufen kontinuierlich auf einer breiten Datenbasis:') }}</p>
                        <ul class="mt-3 grid gap-2 text-[11px] font-semibold leading-5 text-white" aria-label="{{ __('Trainings- und Validierungsumfang') }}">
                            @foreach ([
                                __('Training auf 30 Jahre Kurshistorie'),
                                __('Umfangreiche Integration von Indikatoren und Makrodaten'),
                                __('Modellvalidierung'),
                                __('Backtest'),
                                __('Walk-Forward-Test'),
                            ] as $trainingFeature)
                                <li class="flex items-start gap-2 rounded-lg bg-amber-400/[.035] px-3 py-1.5">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                                    <span>{{ $trainingFeature }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <span class="mt-3 inline-flex w-fit items-center rounded-full border border-amber-300/30 bg-amber-400/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-[.08em] text-amber-300">{{ __('Strenges Bewertungssystem und Kategorisierung') }}</span>
                        @if (false)
                        <div class="flex flex-col">
                        <p class="order-1 mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Aktive Modelle analysieren Indikatoren und Makrodaten parallel und konkurrieren anhand ihrer Prognosequalität:') }}</p>
                        <div class="order-3 mt-2.5 grid grid-cols-2 gap-3 text-[11px] text-white">
                            @foreach ([
                                [__('Horizon KI'), $featureStats['horizon_models']],
                                [__('Pulse KI'), $featureStats['pulse_models']],
                            ] as [$groupTitle, $models])
                                <div class="min-w-0">
                                    <h3 class="mb-1 font-black uppercase tracking-[.12em] text-amber-400">{{ $groupTitle }}</h3>
                                    <ul class="grid gap-1" aria-label="{{ $groupTitle }}">
                                        @foreach ($models as $model)
                                            <li class="flex min-w-0 items-center gap-1.5">
                                                @switch(\Illuminate\Support\Str::afterLast($model, ' '))
                                                    @case('Aegis')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M8 2.2 9.7 6l4.1.4-3.1 2.8.9 4-3.6-2.1-3.6 2.1.9-4-3.1-2.8L6.3 6 8 2.2Z"/></svg>
                                                        @break
                                                    @case('Atlas')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="8" cy="8" r="5.5"/><circle cx="8" cy="8" r="2.2"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2"/></svg>
                                                        @break
                                                    @case('Helios')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M8 1.8 13 4v3.6c0 3-2.1 5.4-5 6.6-2.9-1.2-5-3.6-5-6.6V4l5-2.2Z"/><path d="m5.5 8 1.6 1.6 3.5-3.5"/></svg>
                                                        @break
                                                    @case('Lumina')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="8" cy="8" r="2.5"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.1 3.1l1.4 1.4M11.5 11.5l1.4 1.4M12.9 3.1l-1.4 1.4M4.5 11.5l-1.4 1.4"/></svg>
                                                        @break
                                                    @case('Nova')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="8" cy="8" r="1.5"/><ellipse cx="8" cy="8" rx="6" ry="2.8" transform="rotate(28 8 8)"/><ellipse cx="8" cy="8" rx="6" ry="2.8" transform="rotate(-28 8 8)"/></svg>
                                                        @break
                                                    @case('Orion')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="m10.8 5.2-1.5 4.1-4.1 1.5 1.5-4.1 4.1-1.5Z"/></svg>
                                                        @break
                                                    @case('Prisma')
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="m8 1.8 5.2 3.1v6.2L8 14.2l-5.2-3.1V4.9L8 1.8Z"/><path d="m2.8 4.9 5.2 3 5.2-3M8 7.9v6.3"/></svg>
                                                        @break
                                                    @default
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 8h3l1.2-3.5L8 12l1.8-5 1.2 1h3.5"/></svg>
                                                @endswitch
                                                <span class="truncate" title="{{ $model }}">{{ $model }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <div class="order-2 mt-2 border-b border-amber-400/10 pb-1.5 text-[9px] font-black uppercase tracking-[.08em] text-white">
                            <div class="flex items-center gap-1.5 text-amber-400">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M17 17l1.5 1.5M5.5 5.5 7 7"/></svg>
                                <span>{{ __('Champion-Challenger-System') }}</span>
                            </div>
                            <div class="mt-1 grid gap-1 pl-1">
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 4h8v3a4 4 0 0 1-8 0V4Z"/><path d="M8 6H4v1a4 4 0 0 0 4 4M16 6h4v1a4 4 0 0 1-4 4M12 11v5M8 20h8M9 16h6"/></svg>
                                    <span>{{ __('Champion') }}</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 4 12 16M18 4 6 20"/><path d="m4 6 2-2 2 2M16 18l2 2 2-2M16 6l2-2 2 2M4 18l2 2 2-2"/></svg>
                                    <span>{{ __('Challenger') }}</span>
                                </span>
                            </div>
                        </div>
                        </div>
                        @endif
                    @elseif ($number === '03')
                        <p class="mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ $copy }}</p>
                        <ul class="mt-2.5 grid gap-2 text-xs font-semibold text-white" aria-label="{{ __('Research-Funktionen') }}">
                            @foreach ([
                                [__('KI-Score'), 'score'],
                                [__('Kursprognose'), 'forecast'],
                                [__('Watchlist'), 'watchlist'],
                                [__('Fundamentaldaten'), 'fundamentals'],
                                [__('KI-Historie'), 'history'],
                                [__('Aktien Labeling'), 'labeling'],
                                [__('Strategietester'), 'strategy_tester'],
                                [__('Frage unsere KI (je nach Abo)'), 'ai_chat'],
                            ] as [$researchFeature, $researchIcon])
                                <li class="flex items-center gap-2.5">
                                    @if ($researchIcon === 'score')
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2.5 12a5.5 5.5 0 1 1 11 0"/><path d="m8 12 3-4M4.5 10h.01M6 7.5h.01M10 7.5h.01M11.5 10h.01"/></svg>
                                    @elseif ($researchIcon === 'forecast')
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 13V3M2 13h12"/><path d="m4 10 3-3 2 1.5L13 4"/><path d="M10.5 4H13v2.5"/></svg>
                                    @elseif ($researchIcon === 'watchlist')
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="m8 1.8 1.8 3.7 4.1.6-3 2.9.7 4.1L8 11.2l-3.6 1.9.7-4.1-3-2.9 4.1-.6L8 1.8Z"/></svg>
                                    @elseif ($researchIcon === 'fundamentals')
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M3 14V7M7 14V3M11 14V9M15 14H1"/><path d="M2 5h2M6 1h2M10 7h2"/></svg>
                                    @elseif ($researchIcon === 'history')
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5M3.5 3.5 2 3.7l.2 1.5"/></svg>
                                    @elseif ($researchIcon === 'comparison')
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M3 13V7M7 13V3M11 13V9M15 13H1"/><path d="m2 5 3-2 3 3 5-4"/><path d="M11 2h2v2"/></svg>
                                    @else
                                        <svg class="h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 3.5A1.5 1.5 0 0 1 3.5 2h9A1.5 1.5 0 0 1 14 3.5v6a1.5 1.5 0 0 1-1.5 1.5H7l-3.5 2.5V11A1.5 1.5 0 0 1 2 9.5v-6Z"/><path d="M5 6.5h.01M8 6.5h.01M11 6.5h.01"/></svg>
                                    @endif
                                    <span>{{ $researchFeature }}</span>
                                    @if ($researchIcon === 'labeling')
                                        <span class="ml-auto inline-flex w-10 justify-center rounded-md border border-cyan-300/30 bg-cyan-400/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-cyan-300">{{ __('Plus') }}</span>
                                    @elseif ($researchIcon === 'strategy_tester')
                                        <span class="ml-auto inline-flex w-10 justify-center rounded-md border border-amber-300/30 bg-amber-400/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-amber-300">{{ __('Pro') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($number === '04')
                        <p class="mt-2 text-[11px] font-semibold leading-4 text-white"><strong class="text-amber-300">71 %</strong> {{ __('der aktiv gemanagten, auf Euro lautenden Global-Equity-Fonds blieben im Gesamtjahr 2025 hinter dem S&P World zurück.') }} <a href="https://www.spglobal.com/spdji/en/documents/spiva/spiva-europe-year-end-2025.pdf" target="_blank" rel="noopener noreferrer" class="whitespace-nowrap text-amber-300/80 hover:text-amber-200">{{ __('Quelle: S&P') }}</a></p>
                        <p class="mt-2 text-xs leading-4 text-[var(--ak-muted)]">{{ $copy }}</p>
                        <ul class="mt-2 rounded-xl bg-amber-400/[.06] px-3 py-0.5 text-[10px] font-semibold leading-[14px] text-white">
                            <li class="flex items-start gap-2 border-b border-amber-400/10 py-1">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M8 1.5 13 3.7v3.7c0 3.1-2.1 5.6-5 7.1-2.9-1.5-5-4-5-7.1V3.7L8 1.5Z"/><path d="m5.5 8 1.6 1.6 3.6-3.7"/></svg>
                                <span>{{ __('Ein strenger Qualitätsfilter entscheidet, ob eine Aktie für eine Prognose zugelassen wird.') }}</span>
                            </li>
                            <li class="flex items-start gap-2 border-b border-amber-400/10 py-1">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="7" cy="7" r="4.5"/><path d="m10.4 10.4 3.2 3.2M4.8 7h4.4M7 4.8v4.4"/></svg>
                                <span>{{ __('Alle Modelle werden streng auf sogenanntes Overfitting geprüft.') }}</span>
                            </li>
                            <li class="flex items-start gap-2 border-b border-amber-400/10 py-1">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M8.8 2H3.5A1.5 1.5 0 0 0 2 3.5v5.3l5.2 5.2 6.8-6.8L8.8 2Z"/><circle cx="5.2" cy="5.2" r=".8"/><path d="M10.5 5.5v3M10.5 10.8h.01"/></svg>
                                <span>{{ __('Aktien, die den Qualitätsfilter nicht erfüllen, werden eindeutig gekennzeichnet.') }}</span>
                            </li>
                            <li class="flex items-start gap-2 py-1">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-400" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M8 1.5 13 3.7v3.7c0 3.1-2.1 5.6-5 7.1-2.9-1.5-5-4-5-7.1V3.7L8 1.5Z"/><path d="M5 8h5.5M8.5 5.8 10.7 8l-2.2 2.2"/></svg>
                                <span>{{ __('Wir entwickeln eine komplexe Exit-Strategie, die Verlustrisiken begrenzen und dein Kapital bestmöglich schützen soll.') }}</span>
                            </li>
                        </ul>
                    @else
                        <p class="mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ $copy }}</p>
                    @endif
                </article>
            @endforeach
        </section>

        <p class="mx-auto mt-3 max-w-3xl shrink-0 text-center text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('AktienKI unterstützt die Recherche, ersetzt jedoch keine individuelle Anlageberatung. Prognosen können falsch sein und Kapitalanlagen sind mit Risiken verbunden.') }}</p>
    </main>
<x-cookie-consent />
</body>
</html>

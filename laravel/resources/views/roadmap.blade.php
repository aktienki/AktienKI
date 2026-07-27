<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Die öffentliche AktienKI-Roadmap mit geplanten Funktionen und Meilensteinen.') }}">
    <title>{{ __('Roadmap') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('assets/logo.svg') }}" type="image/svg+xml">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .roadmap-bg {
            background-color:#070b22;
            background-image:
                radial-gradient(circle at 74% 13%,rgba(139,92,246,.20),transparent 29%),
                radial-gradient(circle at 16% 72%,rgba(34,211,238,.10),transparent 28%),
                linear-gradient(rgba(43,29,93,.25) 1px,transparent 1px),
                linear-gradient(90deg,rgba(43,29,93,.25) 1px,transparent 1px);
            background-size:auto,auto,60px 60px,60px 60px;
        }
        .roadmap-topbar { background:rgba(7,11,34,.84);border-bottom:1px solid rgba(139,92,246,.24);box-shadow:0 12px 45px rgba(0,0,0,.24);backdrop-filter:blur(22px); }
        .roadmap-event-amber { border-width:2px;border-color:rgba(251,191,36,.75);background:linear-gradient(145deg,rgba(251,191,36,.30),rgba(245,158,11,.11));box-shadow:0 26px 64px rgba(180,83,9,.32),0 0 38px rgba(251,191,36,.16),inset 0 1px 0 rgba(254,243,199,.22);transform:translateY(-.35rem); }
        .roadmap-event-amber:hover { border-color:rgba(253,230,138,.95);transform:translateY(-.55rem); }
        .roadmap-status-amber { border-color:rgba(251,191,36,.48);background:rgba(251,191,36,.21);color:#fef3c7; }
        .roadmap-node-mask { z-index:50!important;box-shadow:0 0 0 8px #070b22,0 12px 28px rgba(76,29,149,.38); }
        .roadmap-dev-node { background:#241a09!important; }
        .roadmap-beta-node { background:#17102f!important; }
        .roadmap-scroll { overflow-x:hidden;scrollbar-width:none; }
        .roadmap-scroll::-webkit-scrollbar { display:none; }
        .roadmap-scroll::-webkit-scrollbar-track { border-radius:999px;background:rgba(15,23,42,.72);box-shadow:inset 0 0 0 1px rgba(148,163,184,.1); }
        .roadmap-scroll::-webkit-scrollbar-thumb { border:2px solid rgba(15,23,42,.72);border-radius:999px;background:linear-gradient(90deg,#8b5cf6,#f59e0b); }
        .roadmap-scroll::-webkit-scrollbar-thumb:hover { background:linear-gradient(90deg,#a78bfa,#fbbf24); }
        .roadmap-range { width:calc(100% - 1rem);height:12px;margin:0 .5rem;appearance:none;-webkit-appearance:none;background:transparent;cursor:pointer; }
        .roadmap-range::-webkit-slider-runnable-track { height:3px;border-radius:999px;background:rgba(148,163,184,.16); }
        .roadmap-range::-webkit-slider-thumb { width:44px;height:7px;margin-top:-2px;border:0;border-radius:999px;-webkit-appearance:none;background:rgba(167,139,250,.58);box-shadow:none; }
        .roadmap-range:hover::-webkit-slider-thumb { background:rgba(167,139,250,.78); }
        .roadmap-range::-moz-range-track { height:3px;border:0;border-radius:999px;background:rgba(148,163,184,.16); }
        .roadmap-range::-moz-range-thumb { width:44px;height:7px;border:0;border-radius:999px;background:rgba(167,139,250,.58); }
        .roadmap-timeline { min-width:0; }
        .roadmap-card-grid { display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem; }
        .roadmap-card-grid > article { min-width:0; }
        [data-theme="light"] .roadmap-bg {
            background-color:#f8fafc;
            background-image:
                radial-gradient(circle at 74% 13%,rgba(139,92,246,.13),transparent 29%),
                radial-gradient(circle at 16% 72%,rgba(34,211,238,.09),transparent 28%),
                linear-gradient(rgba(124,58,237,.07) 1px,transparent 1px),
                linear-gradient(90deg,rgba(124,58,237,.07) 1px,transparent 1px);
        }
        [data-theme="light"] .roadmap-topbar { background:rgba(255,255,255,.84);border-color:rgba(124,58,237,.16); }
        [data-theme="light"] .roadmap-event-amber { border-color:rgba(217,119,6,.48);background:linear-gradient(145deg,rgba(251,191,36,.28),rgba(255,255,255,.78)); }
        [data-theme="light"] .roadmap-status-amber { color:#92400e; }
        [data-theme="light"] .roadmap-node-mask { box-shadow:0 0 0 8px #f8fafc,0 12px 28px rgba(76,29,149,.18); }
        [data-theme="light"] .roadmap-dev-node { background:#fff7df!important; }
        [data-theme="light"] .roadmap-beta-node { background:#f3efff!important; }
        [data-theme="light"] .roadmap-scroll { scrollbar-color:rgba(124,58,237,.65) rgba(226,232,240,.9); }
        [data-theme="light"] .roadmap-scroll::-webkit-scrollbar-track { background:rgba(226,232,240,.9); }
        [data-theme="light"] .roadmap-scroll::-webkit-scrollbar-thumb { border-color:rgba(226,232,240,.9); }
        [data-theme="light"] .roadmap-range::-webkit-slider-runnable-track { background:rgba(100,116,139,.18); }
        [data-theme="light"] .roadmap-range::-moz-range-track { background:rgba(100,116,139,.18); }
    </style>
</head>
<body class="roadmap-bg min-h-screen text-[var(--ak-text)] antialiased">
    <header class="ak-public-topbar roadmap-topbar sticky top-0 z-40 h-[73px]">
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
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl bg-[var(--ak-accent-soft)] px-3 py-2 text-sm font-bold text-[var(--ak-accent)] sm:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Preise') }}</a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-32 justify-center rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 px-3 py-2.5 text-sm font-bold text-white sm:inline-flex">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center px-3 py-2.5 text-sm font-semibold text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-36 whitespace-nowrap justify-center rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 px-3 py-2.5 text-sm font-bold text-white lg:inline-flex">{{ __('Registrieren') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    @php
        $events = [
            [
                'period' => __('Aktueller Stand'),
                'status' => __('Development'),
                'title' => __('Produktentwicklung'),
                'description' => __('Aufbau der technischen Basis für eine skalierbare, sichere und nachvollziehbare KI-gestützte Aktienanalyse.'),
                'items' => [__('Datenbank und Marktanbindung'), __('Machine-Learning-Infrastruktur'), __('Analyse-, Risiko- und Qualitätssysteme')],
                'color' => 'amber',
                'icon' => 'DEV',
            ],
            [
                'period' => __('September 2026'),
                'status' => __('Geplant'),
                'title' => __('Öffentliche Beta-Version'),
                'description' => __('AktienKI wird unter realen Bedingungen getestet und gemeinsam mit den ersten registrierten Nutzern weiter verbessert.'),
                'items' => [__('Tester-Feedback direkt in die Entwicklung'), __('Dauerhafter Pro-Zugang für die ersten 50 Tester'), __('Laufende Optimierung von Daten, Modellen und Bedienung')],
                'color' => 'violet',
                'icon' => 'β',
            ],
            [
                'period' => 'Q3 2026',
                'status' => __('In Entwicklung'),
                'title' => __('Analyse-Fundament'),
                'description' => __('Ausbau des Aktienscreeners, stabilere KI-Scores und verbesserte Risiko- und Prognosemodelle.'),
                'items' => [__('Erweiterter Aktienvergleich'), __('Personalisierte Risikosignale'), __('Watchlist-Performance und Einstiegskurse')],
                'color' => 'violet',
                'icon' => '01',
            ],
            [
                'period' => 'Q4 2026',
                'status' => __('Als Nächstes'),
                'title' => __('Portfolios und Benachrichtigungen'),
                'description' => __('Von der Beobachtung zur laufenden Begleitung ausgewählter Aktien und Märkte.'),
                'items' => [__('Portfolio-Ansicht'), __('E-Mail- und Signal-Benachrichtigungen'), __('Neue Märkte und internationale Indizes')],
                'color' => 'cyan',
                'icon' => '02',
            ],
            [
                'period' => 'Q1 2027',
                'status' => __('Geplant'),
                'title' => __('Aki Analyse-Assistent'),
                'description' => __('Interaktive Erklärungen zu Prognosen, Kennzahlen, Risiken und Marktbewegungen.'),
                'items' => [__('Aktienbezogene Dialoge'), __('Erklärbare KI-Signale'), __('Mehrsprachige Analyseantworten')],
                'color' => 'violet',
                'icon' => '03',
            ],
        ];
    @endphp

    <main class="mx-auto max-w-screen-2xl px-4 py-10 sm:px-6 lg:px-8 lg:py-5">
        <section class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[.22em] text-[var(--ak-accent)]">{{ __('Produktentwicklung') }}</p>
            <h1 class="mt-2 text-4xl font-black tracking-tight sm:text-5xl">{{ __('Die AktienKI-Roadmap') }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-[var(--ak-muted)]">{{ __('Ein transparenter Ausblick auf geplante Funktionen, neue Analysewerkzeuge und die nächsten Entwicklungsschritte.') }}</p>
            <p class="mt-3 text-xs text-[var(--ak-muted)]">{{ __('Zeiträume und Inhalte sind Planungsstände und können sich ändern.') }}</p>
        </section>

        <section class="mx-auto mt-12 w-full pb-8">
            <div id="roadmapScroller" class="roadmap-scroll pb-5">
                <div class="roadmap-timeline relative pt-16">
                    <div class="absolute left-8 right-8 top-6 z-0 h-px bg-gradient-to-r from-violet-400 via-cyan-400/70 to-violet-400/25"></div>
                    <div class="roadmap-card-grid">
                        @foreach ($events as $event)
                            @php
                                $eventPalette = match ($event['color']) {
                                    'amber' => [
                                        'node' => 'border-amber-300/40 bg-[#241a09] text-amber-300 shadow-amber-950/30',
                                        'line' => 'bg-amber-400/70',
                                        'text' => 'text-amber-300',
                                        'dot' => 'bg-amber-400',
                                    ],
                                    'cyan' => [
                                        'node' => 'border-cyan-300/35 bg-[#0b1830] text-cyan-300 shadow-cyan-950/30',
                                        'line' => 'bg-cyan-400/60',
                                        'text' => 'text-cyan-300',
                                        'dot' => 'bg-cyan-400',
                                    ],
                                    default => [
                                        'node' => 'border-violet-300/35 bg-[#17102f] text-violet-300 shadow-violet-950/30',
                                        'line' => 'bg-violet-400/60',
                                        'text' => 'text-violet-300',
                                        'dot' => 'bg-violet-400',
                                    ],
                                };
                            @endphp
                            <article class="relative">
                                <span class="absolute left-1/2 top-[-4rem] z-10 flex h-12 w-12 -translate-x-1/2 items-center justify-center rounded-2xl border font-black shadow-xl {{ $eventPalette['node'] }} {{ in_array($event['icon'], ['DEV', 'β'], true) ? 'roadmap-node-mask' : '' }} {{ $event['icon'] === 'DEV' ? 'roadmap-dev-node' : ($event['icon'] === 'β' ? 'roadmap-beta-node' : '') }}">{{ $event['icon'] }}</span>
                                <span class="absolute left-1/2 top-[-1rem] h-4 w-px -translate-x-1/2 {{ $eventPalette['line'] }}"></span>
                                <div class="flex h-full min-h-[330px] flex-col rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-5 shadow-[var(--ak-shadow)] backdrop-blur-xl transition hover:-translate-y-1 hover:border-violet-400/35 {{ $event['color'] === 'amber' ? 'roadmap-event-amber' : '' }}">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <span class="text-xs font-black uppercase tracking-[.16em] {{ $eventPalette['text'] }}">{{ $event['period'] }}</span>
                                        <span class="rounded-full border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] {{ $event['color'] === 'amber' ? 'roadmap-status-amber' : '' }}">{{ $event['status'] }}</span>
                                    </div>
                                    <h2 class="mt-4 text-xl font-black">{{ $event['title'] }}</h2>
                                    <p class="mt-2 text-sm leading-6 text-[var(--ak-muted)]">{{ $event['description'] }}</p>
                                    <ul class="mt-4 flex-1 space-y-2 border-t border-[var(--ak-border)] pt-4">
                                        @foreach ($event['items'] as $item)
                                            <li class="flex items-start gap-2.5 text-xs leading-5 text-[var(--ak-text)]">
                                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $eventPalette['dot'] }}"></span>{{ $item }}
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($event['period'] === __('Aktueller Stand'))
                                        <a href="{{ route('project-status') }}" class="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-amber-300/30 bg-[linear-gradient(135deg,rgba(96,70,155,.38),rgba(56,91,150,.30))] px-4 text-xs font-black text-[var(--ak-text)] transition hover:border-amber-300/50 hover:brightness-110">
                                            <x-heroicon-o-arrow-right-circle class="h-4 w-4 text-amber-300" />{{ __('Details zum aktuellen Projektstatus') }}
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>

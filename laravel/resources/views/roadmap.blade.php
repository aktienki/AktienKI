<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Die öffentliche AktienKI-Roadmap mit geplanten Funktionen und Meilensteinen.') }}">
    <title>{{ __('Roadmap') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('brand/generated/bull-icon.png') }}" type="image/png">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .roadmap-bg {
            --ak-accent:#fb923c;
            --ak-accent-soft:rgba(251,146,60,.10);
            --ak-card-strong:rgba(52,65,95,.60);
            --ak-border:rgba(251,146,60,.24);
            background-color:#090d22;
            background-image:
                radial-gradient(circle at 73% 34%,rgba(124,58,237,.16),transparent 34%),
                radial-gradient(circle at 28% 92%,rgba(251,146,60,.13),transparent 34%),
                radial-gradient(circle at 8% 16%,rgba(251,191,36,.04),transparent 22%),
                linear-gradient(135deg,#090d22 0%,#10162f 48%,#171033 100%);
        }
        .roadmap-topbar { background:rgba(11,20,36,.96);border-bottom:1px solid rgba(251,146,60,.14);box-shadow:0 10px 30px rgba(2,6,23,.24),inset 0 -1px 0 rgba(251,146,60,.035);backdrop-filter:blur(18px) saturate(115%); }
        .roadmap-dashboard-card { background-color:rgba(52,65,95,.60);border-color:rgba(251,146,60,.30);box-shadow:0 12px 30px rgba(2,132,199,.10),inset 0 1px 0 rgba(251,146,60,.035);backdrop-filter:blur(8px); }
        .roadmap-event-amber { border-width:2px;border-color:rgba(251,191,36,.75);background:linear-gradient(145deg,rgba(251,191,36,.30),rgba(245,158,11,.11));box-shadow:0 26px 64px rgba(180,83,9,.32),0 0 38px rgba(251,191,36,.16),inset 0 1px 0 rgba(254,243,199,.22);transform:translateY(-.35rem); }
        .roadmap-event-amber:hover { border-color:rgba(253,230,138,.95);transform:translateY(-.55rem); }
        .roadmap-event-complete { opacity:.58;background:rgba(16,185,129,.055)!important;border-color:rgba(52,211,153,.22)!important;box-shadow:none!important; }
        .roadmap-status-amber { border-color:rgba(251,191,36,.48);background:rgba(251,191,36,.21);color:#fef3c7; }
        .roadmap-status-green { border-color:rgba(52,211,153,.42);background:rgba(16,185,129,.16);color:#a7f3d0; }
        .roadmap-done-node { background:rgba(16,185,129,.16)!important; }
        .roadmap-node-mask { z-index:50!important;box-shadow:0 0 0 8px #10162f,0 12px 28px rgba(251,146,60,.20); }
        .roadmap-dev-node { background:#241a09!important; }
        .roadmap-beta-node { background:#0b1830!important; }
        .roadmap-scroll { overflow-x:hidden;scrollbar-width:none; }
        .roadmap-scroll::-webkit-scrollbar { display:none; }
        .roadmap-scroll::-webkit-scrollbar-track { border-radius:999px;background:rgba(15,23,42,.72);box-shadow:inset 0 0 0 1px rgba(148,163,184,.1); }
        .roadmap-scroll::-webkit-scrollbar-thumb { border:2px solid rgba(15,23,42,.72);border-radius:999px;background:linear-gradient(90deg,#8b5cf6,#f59e0b); }
        .roadmap-scroll::-webkit-scrollbar-thumb:hover { background:linear-gradient(90deg,#a78bfa,#fbbf24); }
        .roadmap-range { width:calc(100% - 1rem);height:12px;margin:0 .5rem;appearance:none;-webkit-appearance:none;background:transparent;cursor:pointer; }
        .roadmap-range::-webkit-slider-runnable-track { height:3px;border-radius:999px;background:rgba(148,163,184,.16); }
        .roadmap-range::-webkit-slider-thumb { width:44px;height:7px;margin-top:-2px;border:0;border-radius:999px;-webkit-appearance:none;background:rgba(251,146,60,.58);box-shadow:none; }
        .roadmap-range:hover::-webkit-slider-thumb { background:rgba(251,146,60,.78); }
        .roadmap-range::-moz-range-track { height:3px;border:0;border-radius:999px;background:rgba(148,163,184,.16); }
        .roadmap-range::-moz-range-thumb { width:44px;height:7px;border:0;border-radius:999px;background:rgba(251,146,60,.58); }
        .roadmap-timeline { min-width:0; }
        .roadmap-card-grid { display:grid;grid-template-columns:repeat(5,minmax(0,1fr));grid-auto-rows:430px;gap:.75rem; }
        .roadmap-card-grid > article { min-width:0; }
        [data-theme="light"] .roadmap-bg {
            background-color:#f8fafc;
            background-image:
                radial-gradient(circle at 74% 13%,rgba(139,92,246,.13),transparent 29%),
                radial-gradient(circle at 16% 72%,rgba(251,146,60,.09),transparent 28%),
                linear-gradient(rgba(124,58,237,.07) 1px,transparent 1px),
                linear-gradient(90deg,rgba(124,58,237,.07) 1px,transparent 1px);
        }
        [data-theme="light"] .roadmap-topbar { background:rgba(255,255,255,.84);border-color:rgba(124,58,237,.16); }
        [data-theme="light"] .roadmap-event-amber { border-color:rgba(217,119,6,.48);background:linear-gradient(145deg,rgba(251,191,36,.28),rgba(255,255,255,.78)); }
        [data-theme="light"] .roadmap-event-complete { background:rgba(16,185,129,.045)!important;border-color:rgba(5,150,105,.20)!important; }
        [data-theme="light"] .roadmap-status-amber { color:#92400e; }
        [data-theme="light"] .roadmap-status-green { color:#047857; }
        [data-theme="light"] .roadmap-done-node { background:rgba(16,185,129,.11)!important; }
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
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl bg-[var(--ak-accent-soft)] px-3 py-2 text-sm font-bold text-[var(--ak-accent)] sm:inline-flex">{{ __('Roadmap') }}</a>
                <a href="{{ route('pricing') }}" class="hidden w-20 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Preise') }}</a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-32 justify-center rounded-lg border border-orange-400/25 bg-orange-400/15 px-3 py-2.5 text-sm font-bold text-orange-400 sm:inline-flex">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center px-3 py-2.5 text-sm font-semibold text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-36 whitespace-nowrap justify-center rounded-lg border border-orange-400/30 bg-orange-400/20 px-3 py-2.5 text-sm font-bold text-orange-400 lg:inline-flex">{{ __('Registrieren') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    @php
        $events = [
            [
                'period' => __('Abgeschlossen'),
                'status' => __('Erledigt'),
                'title' => __('Produktentwicklung'),
                'description' => __('Aufbau der technischen Basis für eine skalierbare, sichere und nachvollziehbare KI-gestützte Aktienanalyse.'),
                'items' => [__('Datenbank und Marktanbindung'), __('Machine-Learning-Infrastruktur'), __('Analyse-, Risiko- und Qualitätssysteme')],
                'color' => 'green',
                'icon' => 'DEV',
            ],
            [
                'period' => __('Aktuelle Phase'),
                'status' => __('Aktuell'),
                'title' => __('Geschlossener Betatest'),
                'description' => __('Die erste Entwicklungsphase wurde mit einem kleinen, gezielt ausgewählten Tester-Kreis abgeschlossen.'),
                'items' => [__('10 Tester'), __('Stresstests der Funktionalität'), __('Debugging und Fehlerbehebung')],
                'color' => 'amber',
                'icon' => '✓',
            ],
            [
                'period' => __('September 2026'),
                'status' => __('Geplant'),
                'title' => __('Öffentliche Beta-Version'),
                'description' => __('AktienKI wird unter realen Bedingungen getestet und gemeinsam mit den ersten registrierten Nutzern weiter verbessert.'),
                'items' => [__('Tester-Feedback direkt in die Entwicklung'), __('Dauerhafter Pro-Zugang für die ersten 25 Tester'), __('Laufende Optimierung von Daten, Modellen und Bedienung')],
                'color' => 'violet',
                'icon' => 'β',
            ],
            [
                'period' => __('Deployment'),
                'status' => __('Öffentlich'),
                'title' => __('AktienKI für Alle'),
                'description' => __('Nach dem Betatest startet AktienKI öffentlich – mit Einführungspreisen für die ersten 250 Nutzer.'),
                'items' => [__('Plus: 9,90 € statt 14,90 €'), __('Pro: 19,90 € statt 29,90 €'), __('Einführungspreise für die ersten 250 Nutzer')],
                'color' => 'cyan',
                'icon' => '01',
            ],
            [
                'period' => 'Q4 2026',
                'status' => __('Als Nächstes'),
                'title' => __('Ausblick 2027'),
                'description' => '',
                'items' => [__('Erweiterung Infrastruktur'), __('Brokeranbindung'), __('Erweiterung Datengrundlage')],
                'color' => 'cyan',
                'icon' => '02',
                'separate' => true,
            ],
        ];
    @endphp

    <main class="mx-auto max-w-screen-2xl px-4 py-5 sm:px-6 lg:px-8 lg:py-3">
        <section class="relative z-10 mx-auto flex h-[120px] max-w-3xl flex-col items-center text-center">
            <p class="text-xs font-black uppercase tracking-[.22em] text-orange-400">{{ __('Produktentwicklung') }}</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Die AktienKI-Roadmap') }}</h1>
            <p class="mx-auto mt-2 max-w-2xl text-xs leading-6 text-[var(--ak-muted)]">{{ __('Ein transparenter Ausblick auf geplante Funktionen, neue Analysewerkzeuge und die nächsten Entwicklungsschritte.') }}</p>
            <p class="mt-2 text-[11px] text-[var(--ak-muted)]">{{ __('Zeiträume und Inhalte sind Planungsstände und können sich ändern.') }}</p>
        </section>

        <section class="mx-auto mt-3 w-full pb-8">
            <div id="roadmapScroller" class="roadmap-scroll pb-5">
                <div class="roadmap-timeline relative pt-16">
                    <div class="absolute left-8 right-8 top-6 z-0 h-px bg-gradient-to-r from-orange-400/25 via-orange-400/80 to-orange-400/20"></div>
                    <div class="roadmap-card-grid">
                        @foreach ($events as $event)
                            @php
                                $eventPalette = match ($event['color']) {
                                    'green' => [
                                        'node' => 'border-emerald-300/40 bg-[#08251b] text-emerald-300 shadow-emerald-950/30',
                                        'line' => 'bg-emerald-400/70',
                                        'text' => 'text-emerald-300',
                                        'dot' => 'bg-emerald-400',
                                    ],
                                    'amber' => [
                                        'node' => 'border-amber-300/40 bg-[#241a09] text-amber-300 shadow-amber-950/30',
                                        'line' => 'bg-amber-400/70',
                                        'text' => 'text-amber-300',
                                        'dot' => 'bg-amber-400',
                                    ],
                                    'cyan' => [
                                        'node' => 'border-orange-400/35 bg-[#0b1830] text-orange-400 shadow-orange-400/30',
                                        'line' => 'bg-orange-400/60',
                                        'text' => 'text-orange-400',
                                        'dot' => 'bg-orange-400',
                                    ],
                                    default => [
                                        'node' => 'border-orange-400/35 bg-[#0b1830] text-orange-400 shadow-orange-400/30',
                                        'line' => 'bg-orange-400/60',
                                        'text' => 'text-orange-400',
                                        'dot' => 'bg-orange-400',
                                    ],
                                };
                            @endphp
                            <article class="relative">
                                @if (!($event['separate'] ?? false) && $event['icon'] !== 'DEV')
                                    <span class="absolute left-1/2 top-[-4rem] z-10 flex h-12 w-12 -translate-x-1/2 items-center justify-center rounded-2xl border font-black shadow-xl {{ $eventPalette['node'] }} {{ $event['icon'] === 'β' ? 'roadmap-node-mask roadmap-beta-node' : '' }}">{{ $event['icon'] }}</span>
                                @endif
                                @if (!($event['separate'] ?? false))
                                    <span class="absolute left-1/2 top-[-1rem] h-4 w-px -translate-x-1/2 {{ $eventPalette['line'] }}"></span>
                                @endif
                                <div class="roadmap-dashboard-card flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border p-5 {{ ($event['separate'] ?? false) ? 'border-dashed border-cyan-300/35 bg-cyan-400/[.045] shadow-none' : '' }} {{ $event['color'] === 'amber' ? 'roadmap-event-amber' : ($event['color'] === 'green' ? 'roadmap-event-complete' : '') }}">
                                    <div class="flex min-h-[3.25rem] shrink-0 flex-wrap items-start justify-between gap-3 py-1">
                                        <span class="text-xs font-black uppercase tracking-[.16em] {{ $eventPalette['text'] }}">{{ $event['period'] }}</span>
                                        <span class="rounded-md border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] {{ $event['color'] === 'amber' ? 'roadmap-status-amber' : ($event['color'] === 'green' ? 'roadmap-status-green' : '') }}">{{ $event['status'] }}</span>
                                    </div>
                                    <h2 class="mt-7 flex h-12 shrink-0 items-start overflow-hidden text-xl font-black leading-6">{{ $event['title'] }}</h2>
                                    <p class="mt-1.5 h-[72px] shrink-0 overflow-hidden text-sm leading-6 text-[var(--ak-muted)]">{{ $event['description'] }}</p>
                                    <ul class="mt-3 flex-1 space-y-2 border-t border-[var(--ak-border)] pt-3">
                                        @foreach ($event['items'] as $item)
                                            <li class="flex items-start gap-2.5 text-xs leading-5 text-[var(--ak-text)]">
                                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $eventPalette['dot'] }}"></span>{{ $item }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto mt-1 w-full max-w-5xl" aria-label="{{ __('Laufende Modellentwicklung') }}">
            <div class="roadmap-dashboard-card relative flex items-center gap-4 overflow-hidden rounded-2xl border px-5 py-4 sm:gap-6 sm:px-7 sm:py-5">
                <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-orange-300 via-orange-400 to-cyan-400"></div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-orange-400/35 bg-orange-400/10 text-orange-300 shadow-[0_0_24px_rgba(251,146,60,.16)] sm:h-12 sm:w-12">
                    <x-heroicon-o-arrow-right class="h-6 w-6" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-orange-300">{{ __('Laufende Entwicklung') }}</p>
                    <p class="mt-1 text-sm font-semibold leading-6 text-[var(--ak-text)] sm:text-base">{{ __('Ständig werden neue Modelle für weitere Aktien entwickelt und getestet.') }}</p>
                </div>
                <x-heroicon-o-arrow-right class="ml-auto hidden h-7 w-7 shrink-0 text-cyan-300 sm:block" aria-hidden="true" />
            </div>
        </section>
    </main>
<x-cookie-consent />
</body>
</html>

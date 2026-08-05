<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Aktueller Entwicklungs- und Betriebsstatus von AktienKI.') }}">
    <title>{{ __('Projektstatus') }} – AktienKI</title>
    <link rel="icon" href="{{ asset('assets/logo.svg') }}" type="image/svg+xml">
    <x-preference-head :force-dark="true" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .status-bg {
            background-color:#070b22;
            background-image:
                radial-gradient(circle at 76% 14%,rgba(92,75,160,.16),transparent 30%),
                radial-gradient(circle at 15% 78%,rgba(56,91,150,.10),transparent 28%),
                linear-gradient(rgba(43,29,93,.22) 1px,transparent 1px),
                linear-gradient(90deg,rgba(43,29,93,.22) 1px,transparent 1px);
            background-size:auto,auto,60px 60px,60px 60px;
        }
        .status-topbar { background:rgba(7,11,34,.86);border-bottom:1px solid rgba(139,92,246,.20);box-shadow:0 12px 45px rgba(0,0,0,.22);backdrop-filter:blur(22px); }
        [data-theme="light"] .status-bg { background-color:#f8fafc;background-image:linear-gradient(rgba(124,58,237,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.06) 1px,transparent 1px); }
        [data-theme="light"] .status-topbar { background:rgba(255,255,255,.88);border-color:rgba(124,58,237,.15); }
        [data-theme="light"] .status-page-nav { background:rgba(255,255,255,.92);box-shadow:0 18px 45px rgba(76,29,149,.14); }
    </style>
</head>
<body class="status-bg min-h-screen text-[var(--ak-text)] antialiased">
    <header class="ak-public-topbar status-topbar sticky top-0 z-40 h-[73px]">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center justify-between px-3 sm:px-8 lg:px-12 xl:px-16">
            <a href="{{ route('welcome') }}" class="flex items-center gap-3" aria-label="{{ __('AktienKI Startseite') }}">
                <x-brand-wordmark />
            </a>
            <div class="flex items-center gap-1.5 sm:gap-3">
                <a href="{{ route('welcome') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Startseite') }}</a>
                <a href="{{ route('features') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Features') }}</a>
                <a href="{{ route('roadmap') }}" class="hidden w-24 justify-center rounded-xl px-3 py-2 text-sm font-semibold text-[var(--ak-muted)] transition hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)] lg:inline-flex">{{ __('Roadmap') }}</a>
                <x-preference-controls />
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden w-32 justify-center rounded-xl border border-violet-300/20 bg-[linear-gradient(135deg,rgba(96,70,155,.74),rgba(56,91,150,.68))] px-3 py-2.5 text-sm font-bold text-white sm:inline-flex">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden w-24 justify-center px-3 py-2.5 text-sm font-semibold text-[var(--ak-muted)] hover:text-[var(--ak-text)] sm:inline-flex">{{ __('Anmelden') }}</a>
                    <a href="{{ route('register') }}" class="hidden w-36 whitespace-nowrap justify-center rounded-xl border border-violet-300/20 bg-[linear-gradient(135deg,rgba(96,70,155,.78),rgba(56,91,150,.72))] px-3 py-2.5 text-sm font-bold text-white lg:inline-flex">{{ __('Registrieren') }}</a>
                @endauth
                <x-public-mobile-menu />
            </div>
        </div>
    </header>

    <main
        x-data="{ page: 1, total: 3, go(number) { this.$refs.statusContent.scrollTop = 0; this.page = number }, next() { if (this.page < this.total) this.go(this.page + 1) }, previous() { if (this.page > 1) this.go(this.page - 1) } }"
        @keydown.right.window="next()"
        @keydown.left.window="previous()"
        class="mx-auto flex h-[calc(100dvh-73px)] min-h-0 w-full max-w-screen-2xl flex-col overflow-hidden px-4 py-6 sm:px-6 lg:px-8"
    >
        <div x-ref="statusContent" class="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-2 pr-1">
        <div x-show="page === 1">
        <section class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
            <article class="rounded-3xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-6 shadow-[var(--ak-shadow)] sm:p-8">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-300/25 bg-amber-300/[.08] px-3 py-1.5 text-[10px] font-black uppercase tracking-[.14em] text-amber-300">
                        <i class="h-2 w-2 rounded-full bg-amber-300 shadow-[0_0_8px_rgba(251,191,36,.5)]"></i>{{ __('Aktive Beta-Phase') }}
                    </span>
                    <span class="rounded-full border px-3 py-1.5 text-[10px] font-black uppercase tracking-wide {{ $databaseAvailable ? 'border-emerald-400/20 bg-emerald-400/[.08] text-emerald-400' : 'border-rose-400/20 bg-rose-400/[.08] text-rose-400' }}">
                        {{ $databaseAvailable ? __('Systemdaten verfügbar') : __('Systemdaten vorübergehend nicht verfügbar') }}
                    </span>
                </div>
                <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-5xl">{{ __('Aktueller Projektstatus') }}</h1>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-[var(--ak-muted)] sm:text-base">{{ __('AktienKI befindet sich in einer aktiven Entwicklungs- und Testphase. Kernfunktionen für Datenanalyse, Prognosen, Risikoprofile und Watchlists sind bereits nutzbar und werden laufend validiert und verbessert.') }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('roadmap') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-violet-300/20 bg-[linear-gradient(135deg,rgba(96,70,155,.55),rgba(56,91,150,.48))] px-4 text-xs font-bold text-white transition hover:brightness-110"><x-heroicon-o-map class="h-4 w-4" />{{ __('Zur Roadmap') }}</a>
                    @guest
                        <a href="{{ route('register') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-bold transition hover:border-violet-400/30"><x-heroicon-o-user-plus class="h-4 w-4" />{{ __('Tester werden') }}</a>
                    @endguest
                </div>
            </article>

            <article class="rounded-3xl border border-amber-300/25 bg-[linear-gradient(145deg,rgba(245,158,11,.11),rgba(96,70,155,.08),var(--ak-card))] p-6 shadow-[var(--ak-shadow)]">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-300">{{ __('Beta-Programm') }}</p>
                        <h2 class="mt-2 text-xl font-black">{{ __('Die ersten 50 Tester') }}</h2>
                    </div>
                    <span class="text-2xl font-black tabular-nums text-amber-200">{{ $stats['testers'] }} / {{ $betaLimit }}</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-[var(--ak-muted)]">{{ __('Frühe Tester unterstützen die Produktentwicklung und erhalten dauerhaft kostenlosen Zugang zum Pro-Modell, solange Plätze verfügbar sind.') }}</p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/[.07]">
                    <div class="h-full rounded-full bg-[linear-gradient(90deg,rgba(96,70,155,.85),rgba(214,168,79,.85))]" style="width: {{ $betaProgress }}%"></div>
                </div>
                <p class="mt-3 text-right text-[10px] font-bold text-[var(--ak-muted)]">{{ number_format($betaProgress, 0, ',', '.') }} % {{ __('belegt') }}</p>
            </article>
        </section>

        <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                [__('Aktien'), $stats['stocks'], 'chart-bar'],
                [__('Indizes'), $stats['indices'], 'globe-alt'],
                [__('Prognosen'), $stats['predictions'], 'sparkles'],
                [__('Validiert'), $stats['validated'], 'check-badge'],
                [__('Aktive Modelle'), $stats['models'], 'cpu-chip'],
                [__('Letzte Prognose'), $stats['last_prediction_at'] ? \Illuminate\Support\Carbon::parse($stats['last_prediction_at'])->format('d.m. H:i') : null, 'clock'],
            ] as [$label, $value, $icon])
                <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                    <p class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ $label }}</p>
                    <p class="mt-2 truncate text-xl font-black tabular-nums">{{ is_numeric($value) ? number_format($value, 0, ',', '.') : ($value ?? '—') }}</p>
                </article>
            @endforeach
        </section>
        </div>

        <div x-cloak x-show="page === 2">
        @php
            $areas = [
                [__('Markt- und Fundamentaldaten'), __('Aktiv'), __('Aktien, Indizes, Sektoren, Kursreihen und Fundamentalkennzahlen werden strukturiert zusammengeführt.'), 'emerald'],
                [__('Machine-Learning-Modelle'), __('Aktiv'), __('Mehrere Modelle analysieren parallel und konkurrieren innerhalb des Champion-Challenger-Systems.'), 'emerald'],
                [__('Prognosen und Validierung'), __('Beta'), __('Historische Prognosen, Trefferquoten und Renditen werden nachvollziehbar gespeichert und ausgewertet.'), 'amber'],
                [__('Risikoprofile und Signale'), __('Beta'), __('BUY, WATCH, HOLD und SELL werden abhängig vom gewählten Risikoprofil personalisiert.'), 'amber'],
                [__('Watchlists und Performance'), __('Beta'), __('Einstiegskurs und zugehörige Prognose werden gespeichert; Aktien lassen sich zwischen Listen verschieben.'), 'amber'],
                [__('Aki Analyse-Assistent'), __('In Planung'), __('Interaktive, aktienbezogene Erklärungen und Rückfragen werden schrittweise vorbereitet.'), 'violet'],
            ];
        @endphp
        <section class="mt-6">
            <div class="flex items-end justify-between gap-4">
                <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Komponenten') }}</p><h2 class="mt-1 text-2xl font-black">{{ __('Status der Produktbereiche') }}</h2></div>
                <span class="hidden text-xs text-[var(--ak-muted)] sm:block">{{ __('Stand: :date', ['date' => now()->format('d.m.Y')]) }}</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($areas as [$title, $status, $description, $color])
                    <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-black">{{ $title }}</h3>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wide {{ $color === 'emerald' ? 'border-emerald-400/20 bg-emerald-400/[.08] text-emerald-400' : ($color === 'amber' ? 'border-amber-300/20 bg-amber-300/[.08] text-amber-300' : 'border-violet-400/20 bg-violet-400/[.08] text-violet-300') }}">{{ $status }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-[var(--ak-muted)]">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </section>
        </div>

        <div x-cloak x-show="page === 3">
        <section class="mt-6 grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <h2 class="text-lg font-black">{{ __('Zuletzt umgesetzt') }}</h2>
                <ul class="mt-4 space-y-3 text-sm text-[var(--ak-muted)]">
                    @foreach ([__('Historische Erfolgs-Heatmap nach KI-Score und Konfidenz'), __('Prognosegebundene Watchlist-Einträge mit Einstiegskurs'), __('Historische Chartansicht rund um den Prognosezeitpunkt'), __('Responsive Ansichten für Desktop, Tablet und Mobilgeräte')] as $item)
                        <li class="flex items-start gap-2.5"><x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" /><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </article>
            <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <h2 class="text-lg font-black">{{ __('Aktuelle Einschränkungen') }}</h2>
                <ul class="mt-4 space-y-3 text-sm text-[var(--ak-muted)]">
                    @foreach ([__('Beta-Funktionen und Datenstrukturen können sich noch verändern.'), __('Historische Erfolgswerte benötigen ausreichend validierte Prognosen.'), __('Der Aki Analyse-Assistent befindet sich noch in Planung.'), __('Alle Inhalte dienen der Information und stellen keine Anlageberatung dar.')] as $item)
                        <li class="flex items-start gap-2.5"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-amber-300" /><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </article>
        </section>

        @if ($modelAliases->isNotEmpty())
            <section class="mt-6 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <h2 class="text-lg font-black">{{ __('Aktive öffentliche Modell-Aliase') }}</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($modelAliases as $model)
                        <span class="rounded-lg border border-violet-400/15 bg-violet-500/[.07] px-3 py-2 text-xs font-bold text-violet-200">{{ $model->public_alias }} <small class="ml-1 uppercase text-[var(--ak-muted)]">{{ $model->ai_type }}</small></span>
                    @endforeach
                </div>
            </section>
        @endif
        </div>
        </div>

        <nav class="status-page-nav z-30 mx-auto mt-3 flex w-full max-w-md shrink-0 items-center justify-between gap-4 rounded-2xl border border-violet-300/15 bg-[#100c24]/90 p-2.5 shadow-2xl shadow-black/35 backdrop-blur-xl" aria-label="{{ __('Projektstatus Seitensteuerung') }}">
            <button type="button" @click="previous()" :disabled="page === 1" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[.04] text-violet-200 transition hover:border-violet-300/25 hover:bg-violet-500/10 disabled:cursor-default disabled:opacity-25" aria-label="{{ __('Vorherige Statusseite') }}">
                <x-heroicon-o-arrow-left class="h-5 w-5" />
            </button>

            <div class="flex min-w-0 flex-1 flex-col items-center">
                <p class="text-[9px] font-black uppercase tracking-[.14em] text-slate-500"><span x-text="page"></span> / <span x-text="total"></span></p>
                <div class="mt-1.5 flex items-center gap-2">
                    <template x-for="number in total" :key="number">
                        <button type="button" @click="go(number)" class="h-1.5 rounded-full transition-all duration-200" :class="page === number ? 'w-8 bg-violet-400' : 'w-2 bg-slate-600 hover:bg-slate-400'" :aria-label="`${@js(__('Statusseite'))} ${number}`" :aria-current="page === number ? 'page' : null"></button>
                    </template>
                </div>
            </div>

            <button type="button" @click="next()" :disabled="page === total" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-[linear-gradient(135deg,rgba(96,70,155,.45),rgba(56,91,150,.38))] text-violet-100 transition hover:border-violet-300/25 hover:brightness-110 disabled:cursor-default disabled:opacity-25" aria-label="{{ __('Nächste Statusseite') }}">
                <x-heroicon-o-arrow-right class="h-5 w-5" />
            </button>
        </nav>
    </main>
</body>
</html>

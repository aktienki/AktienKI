<x-app-layout>
    @php
        $riskLabels = [
            'cautious' => __('Defensiv'),
            'normal' => __('Normal'),
            'dynamic' => __('Dynamisch'),
            'opportunity_oriented' => __('Chancenorientiert'),
            'opportunity' => __('Chancenorientiert'),
            'aggressive' => __('Offensiv'),
        ];
        $isOpportunityProfile = in_array($riskProfile, ['opportunity_oriented', 'opportunity', 'aggressive'], true);
    @endphp

    <main id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)] xl:h-[calc(100dvh-89px)] xl:min-h-0 xl:overflow-hidden">
        <div class="ak-container flex min-h-[calc(100dvh-73px)] flex-col py-4 lg:py-5 xl:h-full xl:min-h-0">
            <header class="mb-4 flex shrink-0 flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-400">{{ __('Persönliche Übersicht') }}</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">{{ __('Mein Dashboard') }}</h1>
                </div>
                <div class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold text-[var(--ak-muted)] {{ $isOpportunityProfile ? 'border-rose-400/35 bg-rose-400/[.10] shadow-[0_8px_24px_rgba(251,113,133,.08)]' : 'border-cyan-400/35 bg-cyan-400/[.10] shadow-[0_8px_24px_rgba(34,211,238,.08)]' }}">
                    <x-heroicon-o-shield-check class="h-4 w-4 {{ $isOpportunityProfile ? 'text-rose-300' : 'text-cyan-400' }}" />
                    {{ __('Risikoprofil') }}
                    <span class="{{ $isOpportunityProfile ? 'text-rose-300' : 'text-cyan-300' }}">{{ $riskLabels[$riskProfile] ?? ucfirst($riskProfile) }}</span>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 xl:min-h-0 xl:flex-1 xl:grid-cols-6">
                @if ($strategyPortfolio)
                    @php
                        $strategyPerformance = (float) $strategyPortfolio->dashboard_performance;
                        $strategyPortfolioActive = (bool) data_get($strategyPortfolio->meta, 'automation.live_enabled', false);
                        $strategyEmailActive = $strategyPortfolioActive
                            && (bool) data_get($strategyPortfolio->meta, 'automation.transaction_email_enabled', false);
                        $strategyDemoTransactions = [
                            ['SAP.DE', __('Kauf'), 8, 184.60, 'cyan', now()->subHours(3)],
                            ['ASML.AS', __('Kauf'), 2, 694.20, 'cyan', now()->subHours(19)],
                            ['MSFT', __('Verkauf'), 3, 421.35, 'rose', now()->subDay()],
                        ];
                    @endphp
                    <div class="flex flex-col gap-3 self-start sm:col-span-2 xl:h-full xl:min-h-0 xl:col-span-2">
                    <a href="{{ route('depots.show', ['portfolio' => $strategyPortfolio, 'return_to' => 'paper']) }}" class="ak-card ak-dashboard-card group relative flex min-h-[94px] overflow-hidden p-3 transition {{ $strategyPortfolioActive ? 'border-cyan-200/90 ring-1 ring-inset ring-cyan-200/30 shadow-[0_0_35px_rgba(34,211,238,.24)] hover:border-cyan-100' : 'border-cyan-200/45 hover:border-cyan-100/70' }}">
                        <span class="pointer-events-none absolute -left-8 -top-10 h-28 w-28 rounded-full {{ $strategyPortfolioActive ? 'bg-cyan-300/35' : 'bg-cyan-300/15' }} blur-2xl"></span>
                        <div class="relative flex w-full min-w-0 flex-col justify-between">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-cyan-200/45 bg-cyan-300/20 text-cyan-200"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2"><b class="truncate text-base text-[var(--ak-text)]">{{ $strategyPortfolio->name }}</b><small class="rounded bg-cyan-400/20 px-1.5 py-0.5 text-[8px] font-black text-cyan-200">{{ __('STRATEGIEDEPOT') }}</small></span>
                                        <span class="mt-0.5 flex min-w-0 gap-1 overflow-hidden">
                                            @foreach ($strategyPortfolio->strategies as $strategy)
                                                <small class="truncate rounded border px-1.5 py-0.5 text-[8px] font-black {{ $strategyPortfolioActive ? 'border-cyan-200/60 bg-cyan-300 text-slate-950' : 'border-cyan-300/20 bg-cyan-400/10 text-cyan-300' }}">{{ $strategy->name }}</small>
                                            @endforeach
                                        </span>
                                    </span>
                                </div>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    <span class="flex items-center gap-1 rounded-md border px-2 py-1 text-[8px] font-black uppercase {{ $strategyEmailActive ? 'border-amber-300/60 bg-amber-300/20 text-amber-200' : 'border-slate-500/25 bg-slate-500/10 text-slate-400' }}" title="{{ __('E-Mail pro Transaktion') }}">
                                        <x-heroicon-o-envelope class="h-3 w-3" />{{ $strategyEmailActive ? __('E-Mail aktiv') : __('E-Mail aus') }}
                                    </span>
                                    @if ($strategyPortfolioActive)
                                        <span class="flex items-center gap-1.5 rounded-md border border-cyan-200 bg-cyan-300 px-2 py-1 text-[9px] font-black uppercase text-slate-950 shadow-[0_0_15px_rgba(103,232,249,.35)]"><i class="h-1.5 w-1.5 rounded-full bg-slate-950"></i>{{ __('Aktiv') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="mt-2 grid grid-cols-4 gap-1 border-t border-cyan-300/15 pt-2 text-center">
                                @foreach ([
                                    [__('Depotwert'), $strategyPortfolio->dashboard_positions_value, $strategyPortfolio->currency, 'text-[var(--ak-text)]'],
                                    [__('Kapital'), $strategyPortfolio->dashboard_cash, $strategyPortfolio->currency, 'text-[var(--ak-text)]'],
                                    [__('Gesamtwert'), $strategyPortfolio->dashboard_total_value, $strategyPortfolio->currency, 'text-cyan-300'],
                                    [__('Performance'), $strategyPerformance, '%', $strategyPerformance >= 0 ? 'text-cyan-300' : 'text-rose-300'],
                                ] as [$label, $value, $suffix, $valueClass])
                                    <span class="min-w-0"><small class="block truncate text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $label }}</small><b class="block truncate text-xs tabular-nums {{ $valueClass }}">{{ $label === __('Performance') && $value >= 0 ? '+' : '' }}{{ number_format((float) $value, $suffix === '%' ? 1 : 0, ',', '.') }} {{ $suffix }}</b></span>
                                @endforeach
                            </div>
                        </div>
                    </a>
                    <article class="ak-card ak-dashboard-card min-h-[94px] overflow-hidden border-cyan-300/35 p-3">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-arrows-right-left class="h-4 w-4 text-cyan-300" />
                                <h2 class="text-sm font-black text-[var(--ak-text)]">{{ __('Letzte Transaktionen') }}</h2>
                            </div>
                            <span class="rounded border border-amber-300/40 bg-amber-300/15 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-300">{{ __('Demo') }}</span>
                        </div>
                        <div class="grid gap-1">
                            @foreach ($strategyDemoTransactions as [$symbol, $action, $quantity, $price, $tone, $time])
                                <div class="grid grid-cols-[60px_48px_1fr_auto] items-center gap-2 rounded-md border border-cyan-300/10 bg-cyan-400/[.04] px-2 py-1 text-[10px]">
                                    <b class="truncate text-cyan-200">{{ $symbol }}</b>
                                    <span class="font-black {{ $tone === 'cyan' ? 'text-cyan-300' : 'text-rose-300' }}">{{ $action }}</span>
                                    <span class="truncate text-[var(--ak-muted)]">{{ $quantity }} × {{ number_format($price, 2, ',', '.') }} {{ $strategyPortfolio->currency }}</span>
                                    <time class="tabular-nums text-[var(--ak-muted)]">{{ $time->format('d.m. H:i') }}</time>
                                </div>
                            @endforeach
                        </div>
                    </article>
                    <article class="ak-card ak-dashboard-card mt-3 overflow-hidden p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Persönlicher Bereich') }}</p>
                                <h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Überblick') }}</h2>
                            </div>
                            <x-heroicon-o-squares-2x2 class="h-5 w-5 text-cyan-300" />
                        </div>
                        <div class="grid grid-cols-2 overflow-hidden rounded-xl border border-cyan-300/15 sm:grid-cols-4">
                            @foreach ([
                                [__('Musterdepots'), $overview['paper_depots'], 'heroicon-o-beaker', route('paper-depots.index')],
                                [__('Watchlists'), $overview['watchlists'], 'heroicon-o-star', route('watchlists.index')],
                                [__('Strategien'), $overview['strategies'], 'heroicon-o-adjustments-horizontal', route('setup.saved-filters.index')],
                                [__('Labels'), $overview['labels'], 'heroicon-o-tag', route('setup.quality')],
                            ] as [$label, $count, $icon, $url])
                                <a href="{{ $url }}" class="group min-w-0 border-cyan-300/15 bg-cyan-400/[.035] px-3 py-3 transition hover:bg-cyan-400/[.10] [&:not(:last-child)]:border-r">
                                    <small class="mb-3 block truncate text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-cyan-300/25 bg-cyan-400/10 text-cyan-300 group-hover:border-cyan-200/45">
                                            <x-dynamic-component :component="$icon" class="h-4 w-4" />
                                        </span>
                                        <b class="text-lg font-black tabular-nums text-[var(--ak-text)]">{{ number_format($count, 0, ',', '.') }}</b>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </article>
                    <article class="ak-card ak-dashboard-card overflow-hidden border-cyan-300/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-9 w-9 place-items-center rounded-lg border border-cyan-300/25 bg-cyan-400/10 text-cyan-300">
                                    <x-heroicon-o-user-group class="h-4.5 w-4.5" />
                                </span>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Community') }}</p>
                                    <h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Seit deinem letzten Login') }}</h2>
                                </div>
                            </div>
                            <span class="rounded border border-amber-300/40 bg-amber-300/15 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-300">{{ __('Demo') }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ([
                                [__('Neue Beiträge'), 12, 'heroicon-o-document-text'],
                                [__('Diskussionen'), 4, 'heroicon-o-chat-bubble-left-right'],
                                [__('Neue Kommentare'), 28, 'heroicon-o-chat-bubble-bottom-center-text'],
                            ] as [$label, $count, $icon])
                                <div class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.04] px-2.5 py-2">
                                    <span class="flex items-center justify-between gap-2">
                                        <x-dynamic-component :component="$icon" class="h-3.5 w-3.5 text-cyan-300" />
                                        <b class="text-sm font-black tabular-nums text-[var(--ak-text)]">{{ $count }}</b>
                                    </span>
                                    <small class="mt-1.5 block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </div>
                            @endforeach
                        </div>
                    </article>
                    </div>
                @endif

                <article class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-cyan-300/40 p-4 sm:col-span-2 xl:h-full xl:col-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-300/30 bg-cyan-400/10 text-cyan-300">
                                <x-heroicon-o-globe-europe-africa class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Aktuelle Marktlage') }}</p>
                                <h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ $marketSituation?->headline ?: __('Marktüberblick') }}</h2>
                            </div>
                        </div>
                        @if ($marketSituation?->analysis_date)
                            <time class="shrink-0 text-[8px] font-bold tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($marketSituation->analysis_date)->format('d.m.Y') }}</time>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach ([
                            [__('Ausblick'), $marketSituation?->market_outlook ? __($marketSituation->market_outlook) : '—', 'text-cyan-300'],
                            [__('Konfidenz'), is_numeric($marketSituation?->confidence) ? number_format((float) $marketSituation->confidence, 0, ',', '.').' %' : '—', 'text-[var(--ak-text)]'],
                            [__('Risiko'), $marketSituation?->risk_level ? __($marketSituation->risk_level) : '—', $marketSituation?->risk_level === 'high' ? 'text-rose-300' : 'text-amber-300'],
                        ] as [$label, $value, $class])
                            <div class="rounded-lg border border-cyan-300/15 bg-cyan-400/[.04] px-2.5 py-2">
                                <small class="block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                <b class="mt-1 block truncate text-xs {{ $class }}">{{ $value }}</b>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">
                        {{ $marketSituation?->executive_summary ?: __('Noch keine aktuelle Marktanalyse verfügbar.') }}
                    </p>
                    <a href="{{ route('daily-market-analysis') }}" class="mt-auto inline-flex items-center gap-1 pt-3 text-[10px] font-black text-cyan-300 hover:text-cyan-200">
                        {{ __('Vollständiger Bericht') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                    </a>
                </article>

                <div class="flex flex-col gap-3 self-start sm:col-span-2 xl:col-span-2">
                <article class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-cyan-300/40 p-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-300/30 bg-cyan-400/10 text-cyan-300"><x-heroicon-o-chart-bar-square class="h-5 w-5" /></span>
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Globale Modellläufe') }}</p>
                                <h2 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Letzte Prognosen') }}</h2>
                            </div>
                        </div>
                        <a href="{{ route('predictions.index') }}" class="text-xs font-black text-cyan-300 hover:text-cyan-200">{{ __('Alle') }} →</a>
                    </div>

                    <div class="grid min-h-0 flex-1 gap-1.5">
                        @foreach ($continentPredictions as $continent)
                            <div class="grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-2 rounded-lg border border-cyan-300/15 bg-cyan-400/[.04] px-2.5 py-2">
                                <span class="grid h-7 w-7 place-items-center rounded-md border border-cyan-300/15 bg-cyan-400/[.06] text-cyan-300">
                                    <x-continent-icon :continent="$continent['key']" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2"><b class="truncate text-base text-[var(--ak-text)]">{{ $continent['label'] }}</b><small class="text-xs font-black tabular-nums text-cyan-300">{{ number_format($continent['count'], 0, ',', '.') }}</small></span>
                                    <time class="block text-[11px] tabular-nums text-[var(--ak-muted)]">{{ $continent['latest_at'] ? \Illuminate\Support\Carbon::parse($continent['latest_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') : '—' }}</time>
                                </span>
                                <span class="flex items-center gap-1 text-[10px] font-black tabular-nums">
                                    <i class="rounded bg-cyan-400/18 px-1.5 py-1 not-italic text-cyan-200">B {{ $continent['buy'] }}</i>
                                    <i class="rounded bg-amber-400/14 px-1.5 py-1 not-italic text-amber-300">W {{ $continent['watch'] }}</i>
                                    <i class="rounded bg-slate-400/10 px-1.5 py-1 not-italic text-slate-300">H {{ $continent['hold'] }}</i>
                                    <i class="rounded bg-rose-400/12 px-1.5 py-1 not-italic text-rose-300">S {{ $continent['sell'] }}</i>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </article>
                <article class="ak-card ak-dashboard-card overflow-hidden border-cyan-300/35 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Letzte 48 Stunden') }}</p>
                            <h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Empfehlungen & Signalübergänge') }}</h2>
                        </div>
                        <x-heroicon-o-arrow-path-rounded-square class="h-5 w-5 text-cyan-300" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('predictions.index', ['signal' => 'BUY']) }}" class="rounded-lg border border-cyan-300/20 bg-cyan-400/[.06] p-2.5 transition hover:bg-cyan-400/[.11]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-cyan-300">{{ __('BUY') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['buy_count'] }}</b></span>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ implode(' · ', $recentSignalOverview['buy_symbols']) ?: __('Keine neuen Empfehlungen') }}</small>
                        </a>
                        <a href="{{ route('predictions.index', ['signal' => 'WATCH']) }}" class="rounded-lg border border-amber-300/20 bg-amber-400/[.05] p-2.5 transition hover:bg-amber-400/[.10]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-amber-300">{{ __('WATCH') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['watch_count'] }}</b></span>
                            <small class="mt-1 block text-[8px] text-[var(--ak-muted)]">{{ __('Aktuelle Beobachtungen') }}</small>
                        </a>
                    </div>
                    <a href="{{ route('signal-changes.index', ['days' => 2]) }}" class="mt-2.5 block rounded-lg border border-cyan-300/15 bg-cyan-400/[.035] p-2.5 transition hover:bg-cyan-400/[.08]">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Signalübergänge') }}</span>
                            <b class="text-sm tabular-nums text-cyan-300">{{ $recentSignalOverview['transition_count'] }}</b>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($recentSignalOverview['transitions'] as $transition)
                                <span class="rounded border border-cyan-300/15 bg-cyan-400/[.06] px-2 py-1 text-[8px] font-black text-[var(--ak-muted)]">{{ $transition['from'] }} → {{ $transition['to'] }} <b class="ml-1 text-cyan-300">{{ $transition['count'] }}</b></span>
                            @empty
                                <small class="text-[8px] text-[var(--ak-muted)]">{{ __('Keine Signalübergänge') }}</small>
                            @endforelse
                        </div>
                    </a>
                </article>
                </div>
            </section>

        </div>
    </main>
    <style>
        @media (min-width: 1280px) {
            html:has(#personal-dashboard),
            body:has(#personal-dashboard) {
                height: 100dvh;
                overflow: hidden;
                overscroll-behavior: none;
            }
        }

        .ak-dashboard-card {
            background-color: color-mix(in srgb, var(--ak-card) 60%, transparent);
            border-color: color-mix(in srgb, #22d3ee 32%, transparent);
            box-shadow: 0 12px 30px rgba(2, 132, 199, .10), inset 0 1px 0 rgba(165, 243, 252, .035);
            backdrop-filter: blur(8px);
        }
    </style>
</x-app-layout>

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
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-orange-400">{{ __('Persönliche Übersicht') }}</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">{{ __('Mein Dashboard') }}</h1>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" data-dashboard-aki-open class="inline-flex items-center gap-2 rounded-xl border border-orange-400/45 bg-orange-400/[.12] px-3 py-2 text-xs font-black text-orange-300 shadow-[0_8px_24px_rgba(251,146,60,.10)] transition hover:border-orange-300 hover:bg-orange-400/[.2]">
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                        {{ __('AKI fragen') }}
                    </button>
                    <div class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold text-[var(--ak-muted)] {{ $isOpportunityProfile ? 'border-rose-400/35 bg-rose-400/[.10] shadow-[0_8px_24px_rgba(251,113,133,.08)]' : 'border-orange-400/35 bg-orange-400/[.10] shadow-[0_8px_24px_rgba(251,146,60,.08)]' }}">
                        <x-heroicon-o-shield-check class="h-4 w-4 {{ $isOpportunityProfile ? 'text-rose-300' : 'text-orange-400' }}" />
                        {{ __('Risikoprofil') }}
                        <span class="{{ $isOpportunityProfile ? 'text-rose-300' : 'text-orange-400' }}">{{ $riskLabels[$riskProfile] ?? ucfirst($riskProfile) }}</span>
                    </div>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 xl:min-h-0 xl:flex-1 xl:grid-cols-6">
                <div class="flex flex-col gap-3 self-start sm:col-span-2 xl:h-full xl:min-h-0 xl:col-span-2">
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
                    <a href="{{ route('depots.show', ['portfolio' => $strategyPortfolio, 'return_to' => 'paper']) }}" class="ak-card ak-dashboard-card group relative flex min-h-[94px] overflow-hidden p-3 transition {{ $strategyPortfolioActive ? 'border-orange-200/90 ring-1 ring-inset ring-orange-200/30 shadow-[0_0_35px_rgba(251,146,60,.24)] hover:border-orange-100' : 'border-orange-200/45 hover:border-orange-100/70' }}">
                        <span class="pointer-events-none absolute -left-8 -top-10 h-28 w-28 rounded-full {{ $strategyPortfolioActive ? 'bg-orange-400/35' : 'bg-orange-400/15' }} blur-2xl"></span>
                        <div class="relative flex w-full min-w-0 flex-col justify-between">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-orange-200/45 bg-orange-400/20 text-orange-200"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2"><b class="truncate text-base text-[var(--ak-text)]">{{ $strategyPortfolio->name }}</b><small class="rounded bg-orange-400/20 px-1.5 py-0.5 text-[8px] font-black text-orange-200">{{ __('STRATEGIEDEPOT') }}</small></span>
                                        <span class="mt-0.5 flex min-w-0 gap-1 overflow-hidden">
                                            @foreach ($strategyPortfolio->strategies as $strategy)
                                                <small class="truncate rounded border px-1.5 py-0.5 text-[8px] font-black {{ $strategyPortfolioActive ? 'border-orange-200/60 bg-orange-400 text-slate-950' : 'border-orange-400/20 bg-orange-400/10 text-orange-400' }}">{{ $strategy->name }}</small>
                                            @endforeach
                                        </span>
                                    </span>
                                </div>
                                <span class="flex shrink-0 items-center gap-1.5">
                                    <span class="flex items-center gap-1 rounded-md border px-2 py-1 text-[8px] font-black uppercase {{ $strategyEmailActive ? 'border-amber-300/60 bg-amber-300/20 text-amber-200' : 'border-slate-500/25 bg-slate-500/10 text-slate-400' }}" title="{{ __('E-Mail pro Transaktion') }}">
                                        <x-heroicon-o-envelope class="h-3 w-3" />{{ $strategyEmailActive ? __('E-Mail aktiv') : __('E-Mail aus') }}
                                    </span>
                                    @if ($strategyPortfolioActive)
                                        <span class="flex items-center gap-1.5 rounded-md border border-orange-200 bg-orange-400 px-2 py-1 text-[9px] font-black uppercase text-slate-950 shadow-[0_0_15px_rgba(251,146,60,.35)]"><i class="h-1.5 w-1.5 rounded-full bg-slate-950"></i>{{ __('Aktiv') }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="mt-2 grid grid-cols-4 gap-1 border-t border-orange-400/15 pt-2 text-center">
                                @foreach ([
                                    [__('Depotwert'), $strategyPortfolio->dashboard_positions_value, $strategyPortfolio->currency, 'text-[var(--ak-text)]'],
                                    [__('Kapital'), $strategyPortfolio->dashboard_cash, $strategyPortfolio->currency, 'text-[var(--ak-text)]'],
                                    [__('Gesamtwert'), $strategyPortfolio->dashboard_total_value, $strategyPortfolio->currency, 'text-orange-400'],
                                    [__('Performance'), $strategyPerformance, '%', $strategyPerformance >= 0 ? 'text-orange-400' : 'text-rose-300'],
                                ] as [$label, $value, $suffix, $valueClass])
                                    <span class="min-w-0"><small class="block truncate text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $label }}</small><b class="block truncate text-xs tabular-nums {{ $valueClass }}">{{ $label === __('Performance') && $value >= 0 ? '+' : '' }}{{ number_format((float) $value, $suffix === '%' ? 1 : 0, ',', '.') }} {{ $suffix }}</b></span>
                                @endforeach
                            </div>
                        </div>
                    </a>
                    <article class="ak-card ak-dashboard-card min-h-[94px] overflow-hidden border-orange-400/35 p-3">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-arrows-right-left class="h-4 w-4 text-orange-400" />
                                <h2 class="text-sm font-black text-[var(--ak-text)]">{{ __('Letzte Transaktionen') }}</h2>
                            </div>
                            <span class="rounded border border-amber-300/40 bg-amber-300/15 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-300">{{ __('Demo') }}</span>
                        </div>
                        <div class="grid gap-1">
                            @foreach ($strategyDemoTransactions as [$symbol, $action, $quantity, $price, $tone, $time])
                                <div class="grid grid-cols-[60px_48px_1fr_auto] items-center gap-2 rounded-md border border-orange-400/10 bg-orange-400/[.04] px-2 py-1 text-[10px]">
                                    <b class="truncate text-orange-200">{{ $symbol }}</b>
                                    <span class="font-black {{ $tone === 'cyan' ? 'text-orange-400' : 'text-rose-300' }}">{{ $action }}</span>
                                    <span class="truncate text-[var(--ak-muted)]">{{ $quantity }} × {{ number_format($price, 2, ',', '.') }} {{ $strategyPortfolio->currency }}</span>
                                    <time class="tabular-nums text-[var(--ak-muted)]">{{ $time->format('d.m. H:i') }}</time>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @else
                    <article class="ak-card ak-dashboard-card flex min-h-[94px] flex-col justify-between overflow-hidden border-orange-400/35 p-4">
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Strategiedepot') }}</p>
                                <h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Noch kein Strategiedepot vorhanden') }}</h2>
                            </div>
                        </div>
                        <a href="{{ route('paper-depots.index') }}" class="mt-3 text-[10px] font-black text-orange-400 hover:text-orange-200">{{ __('Depot einrichten') }} →</a>
                    </article>
                    <article class="ak-card ak-dashboard-card min-h-[94px] overflow-hidden border-orange-400/35 p-3">
                        <div class="mb-2 flex items-center gap-2">
                            <x-heroicon-o-arrows-right-left class="h-4 w-4 text-orange-400" />
                            <h2 class="text-sm font-black text-[var(--ak-text)]">{{ __('Letzte Transaktionen') }}</h2>
                        </div>
                        <p class="rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-3 py-3 text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Noch keine Transaktionen vorhanden.') }}</p>
                    </article>
                @endif
                    <article class="ak-card ak-dashboard-card mt-3 overflow-hidden p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Persönlicher Bereich') }}</p>
                                <h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Überblick') }}</h2>
                            </div>
                            <x-heroicon-o-squares-2x2 class="h-5 w-5 text-orange-400" />
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ([
                                [__('Musterdepots'), $overview['paper_depots'], 'heroicon-o-beaker', route('paper-depots.index')],
                                [__('Watchlists'), $overview['watchlists'], 'heroicon-o-star', route('watchlists.index')],
                                [__('Strategien'), $overview['strategies'], 'heroicon-o-adjustments-horizontal', route('setup.saved-filters.index')],
                                [__('Labels'), $overview['labels'], 'heroicon-o-tag', route('setup.quality')],
                            ] as [$label, $count, $icon, $url])
                                <a href="{{ $url }}" class="group min-w-0 rounded-xl border border-orange-400/20 bg-orange-400/[.045] p-3 transition hover:border-orange-300/45 hover:bg-orange-400/[.10]">
                                    <span class="mb-2 flex items-center justify-between gap-2">
                                        <small class="truncate text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400 group-hover:border-orange-200/45">
                                            <x-dynamic-component :component="$icon" class="h-4 w-4" />
                                        </span>
                                    </span>
                                    <b class="block text-xl font-black tabular-nums text-[var(--ak-text)]">{{ number_format($count, 0, ',', '.') }}</b>
                                    @if ((int) $count > 0)
                                        <small class="mt-1 block truncate text-[8px] font-semibold text-orange-400">{{ __('Aktive Einträge') }}</small>
                                    @else
                                        <small class="mt-1 block truncate text-[8px] font-semibold text-[var(--ak-muted)]">{{ __('Noch keine Daten vorhanden') }}</small>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </article>
                    <a href="{{ route('community.index') }}" class="ak-card ak-dashboard-card block overflow-hidden border-orange-400/35 p-4 transition hover:border-orange-200/60">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-9 w-9 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400">
                                    <x-heroicon-o-user-group class="h-4.5 w-4.5" />
                                </span>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Community') }}</p>
                                    <h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Seit deinem letzten Login') }}</h2>
                                </div>
                            </div>
                            <x-heroicon-o-arrow-up-right class="h-4 w-4 text-orange-400" />
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ([
                                [__('Beiträge'), $communityOverview['posts'], 'heroicon-o-document-text'],
                                [__('Mitglieder'), $communityOverview['members'], 'heroicon-o-user-group'],
                                [__('Letzte 7 Tage'), $communityOverview['recent'], 'heroicon-o-clock'],
                            ] as [$label, $count, $icon])
                                <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-2.5 py-2">
                                    <span class="flex items-center justify-between gap-2">
                                        <x-dynamic-component :component="$icon" class="h-3.5 w-3.5 text-orange-400" />
                                        <b class="text-sm font-black tabular-nums text-[var(--ak-text)]">{{ $count }}</b>
                                    </span>
                                    <small class="mt-1.5 block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                </div>
                            @endforeach
                        </div>
                        @if (($communityOverview['posts'] + $communityOverview['members']) === 0)
                            <p class="mt-2 rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-2.5 py-2 text-[9px] text-[var(--ak-muted)]">{{ __('Noch keine Community-Daten vorhanden.') }}</p>
                        @endif
                    </a>
                </div>

                <article class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/40 p-4 sm:col-span-2 xl:h-full xl:col-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-orange-400/30 bg-orange-400/10 text-orange-400">
                                <x-heroicon-o-globe-europe-africa class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Aktuelle Marktlage') }}</p>
                                <h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ $marketSituation?->headline ?: __('Marktüberblick') }}</h2>
                            </div>
                        </div>
                        @if ($marketSituation?->analysis_date)
                            <time class="shrink-0 text-[8px] font-bold tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($marketSituation->analysis_date)->format('d.m.Y') }}</time>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach ([
                            [__('Ausblick'), $marketSituation?->market_outlook ? __($marketSituation->market_outlook) : '—', 'text-orange-400'],
                            [__('Konfidenz'), is_numeric($marketSituation?->confidence) ? number_format((float) $marketSituation->confidence, 0, ',', '.').' %' : '—', 'text-[var(--ak-text)]'],
                            [__('Risiko'), $marketSituation?->risk_level ? __($marketSituation->risk_level) : '—', $marketSituation?->risk_level === 'high' ? 'text-rose-300' : 'text-amber-300'],
                        ] as [$label, $value, $class])
                            <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-2.5 py-2">
                                <small class="block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small>
                                <b class="mt-1 block truncate text-xs {{ $class }}">{{ $value }}</b>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 min-h-0 flex-1 overflow-hidden text-xs leading-5 text-[var(--ak-muted)]">
                        {{ $marketSituation?->executive_summary ?: __('Noch keine aktuelle Marktanalyse verfügbar.') }}
                    </p>
                    <a href="{{ route('daily-market-analysis') }}" class="mt-auto inline-flex items-center gap-1 pt-3 text-[10px] font-black text-orange-400 hover:text-orange-200">
                        {{ __('Vollständiger Bericht') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                    </a>
                </article>

                <div class="flex flex-col gap-3 self-start sm:col-span-2 xl:col-span-2">
                <article class="ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/40 p-4">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-orange-400/30 bg-orange-400/10 text-orange-400"><x-heroicon-o-chart-bar-square class="h-5 w-5" /></span>
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.16em] text-orange-400">{{ __('Globale Modellläufe') }}</p>
                                <h2 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Letzte Prognosen') }}</h2>
                            </div>
                        </div>
                        <a href="{{ route('predictions.index') }}" class="text-xs font-black text-orange-400 hover:text-orange-200">{{ __('Alle') }} →</a>
                    </div>

                    <div class="grid min-h-0 flex-1 gap-1.5">
                        @foreach ($continentPredictions as $continent)
                            <div class="grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-2 rounded-lg border border-orange-400/15 bg-orange-400/[.04] px-2.5 py-2">
                                <span class="grid h-7 w-7 place-items-center rounded-md border border-orange-400/15 bg-orange-400/[.06] text-orange-400">
                                    <x-continent-icon :continent="$continent['key']" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2"><b class="truncate text-base text-[var(--ak-text)]">{{ $continent['label'] }}</b><small class="text-xs font-black tabular-nums text-orange-400">{{ number_format($continent['count'], 0, ',', '.') }}</small></span>
                                    <time class="block text-[11px] tabular-nums text-[var(--ak-muted)]">{{ $continent['latest_at'] ? \Illuminate\Support\Carbon::parse($continent['latest_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') : '—' }}</time>
                                </span>
                                <span class="flex items-center gap-1 text-[10px] font-black tabular-nums">
                                    <i class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-cyan-400/20 bg-cyan-400/[.10] px-1 py-1 not-italic text-cyan-300">B {{ $continent['buy'] }}</i>
                                    <i class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-amber-400/25 bg-amber-400/[.10] px-1 py-1 not-italic text-amber-300">W {{ $continent['watch'] }}</i>
                                    <i class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-slate-400/25 bg-slate-400/[.10] px-1 py-1 not-italic text-slate-300">H {{ $continent['hold'] }}</i>
                                    <i class="inline-flex h-8 w-12 items-center justify-center rounded-lg border border-rose-400/25 bg-rose-400/[.10] px-1 py-1 not-italic text-rose-300">S {{ $continent['sell'] }}</i>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </article>
                <article class="ak-card ak-dashboard-card overflow-hidden border-orange-400/35 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Letzte 48 Stunden') }}</p>
                            <h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Empfehlungen & Signalübergänge') }}</h2>
                        </div>
                        <x-heroicon-o-arrow-path-rounded-square class="h-5 w-5 text-orange-400" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('predictions.index', ['signal' => 'BUY']) }}" class="rounded-lg border border-orange-400/20 bg-orange-400/[.06] p-2.5 transition hover:bg-orange-400/[.11]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-orange-400">{{ __('BUY') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['buy_count'] }}</b></span>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ __('Neue Kaufempfehlungen') }} · {{ implode(' · ', $recentSignalOverview['buy_symbols']) ?: __('Keine neuen Signale') }}</small>
                        </a>
                        <a href="{{ route('predictions.index', ['signal' => 'SELL']) }}" class="rounded-lg border border-rose-400/20 bg-rose-400/[.06] p-2.5 transition hover:bg-rose-400/[.11]">
                            <span class="flex items-center justify-between gap-2"><small class="font-black uppercase text-rose-300">{{ __('SELL') }}</small><b class="text-lg tabular-nums text-[var(--ak-text)]">{{ $recentSignalOverview['sell_count'] }}</b></span>
                            <small class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ __('Neue Verkaufssignale') }} · {{ implode(' · ', $recentSignalOverview['sell_symbols']) ?: __('Keine neuen Signale') }}</small>
                        </a>
                    </div>
                    <div class="mt-2.5 block rounded-lg border border-orange-400/15 bg-orange-400/[.035] p-2.5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[9px] font-black uppercase text-[var(--ak-muted)]">{{ __('Signalübergänge') }}</span>
                            <b class="text-sm tabular-nums text-orange-400">{{ $recentSignalOverview['transition_count'] }}</b>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($recentSignalOverview['transitions'] as $transition)
                                <span class="rounded border border-orange-400/15 bg-orange-400/[.06] px-2 py-1 text-[8px] font-black text-[var(--ak-muted)]">{{ $transition['from'] }} → {{ $transition['to'] }} <b class="ml-1 text-orange-400">{{ $transition['count'] }}</b></span>
                            @empty
                                <small class="text-[8px] text-[var(--ak-muted)]">{{ __('Keine Signalübergänge') }}</small>
                            @endforelse
                        </div>
                    </div>
                </article>
                </div>
            </section>

        </div>

        <div id="dashboard-aki-modal" class="fixed inset-0 z-[200] hidden place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="dashboard-aki-title">
            <section class="w-full max-w-2xl overflow-hidden rounded-2xl border border-teal-300/45 text-slate-100 shadow-2xl" style="background:linear-gradient(145deg,rgba(14,38,57,.98),rgba(8,25,42,.98)) !important;max-height:calc(100dvh - 2rem);display:flex;flex-direction:column;">
                <header class="flex items-center justify-between border-b border-teal-300/25 px-4 py-3" style="background:linear-gradient(110deg,rgba(20,184,166,.18),rgba(245,158,11,.12)) !important;">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-amber-500">{{ __('Assistent') }}</p><h2 id="dashboard-aki-title" class="text-base font-black text-slate-100">{{ __('AKI fragen') }}</h2></div>
                    <button type="button" data-dashboard-aki-close class="rounded-lg p-2 text-slate-300 hover:bg-slate-700/60" aria-label="{{ __('Chat schließen') }}"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </header>
                <div id="dashboard-aki-messages" class="min-h-0 space-y-2 overscroll-contain p-5" style="background:rgba(9,28,45,.82) !important;height:45vh;max-height:500px;overflow-y:scroll;flex:0 1 auto;"><p class="max-w-[92%] rounded-xl border border-teal-300/20 bg-slate-700/70 px-3 py-2 text-xs leading-5 text-slate-100">{{ __('Du kannst zum Beispiel fragen: „Was kannst du mir heute empfehlen?“') }}</p></div>
                <form id="dashboard-aki-form" class="flex gap-2 border-t border-teal-300/20 p-3" style="background:rgba(10,30,47,.96) !important;">
                    <input id="dashboard-aki-input" type="text" class="min-w-0 flex-1 rounded-lg border border-teal-300/30 bg-slate-950/65 px-3 py-2 text-xs text-slate-100 placeholder:text-slate-400" placeholder="{{ __('Was kannst du mir heute empfehlen?') }}" autocomplete="off">
                    <button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-black text-white hover:bg-teal-500">{{ __('Senden') }}</button>
                </form>
            </section>
        </div>
    </main>
    <script>
        (() => {
            const modal = document.getElementById('dashboard-aki-modal');
            const messages = document.getElementById('dashboard-aki-messages');
            const input = document.getElementById('dashboard-aki-input');
            const form = document.getElementById('dashboard-aki-form');
            if (!modal || !form) return;
            const history = [];
            const open = () => { modal.classList.remove('hidden'); modal.classList.add('grid'); window.setTimeout(() => input?.focus(), 40); };
            const close = () => { modal.classList.add('hidden'); modal.classList.remove('grid'); };
            document.querySelector('[data-dashboard-aki-open]')?.addEventListener('click', open);
            document.querySelector('[data-dashboard-aki-close]')?.addEventListener('click', close);
            modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const question = (input?.value || '').trim();
                if (!question) return;
                const user = document.createElement('p'); user.className = 'ml-auto max-w-[88%] rounded-xl bg-teal-500 px-3 py-2 text-xs text-white'; user.textContent = question; messages.appendChild(user); input.value = '';
                history.push({ role: 'user', content: question });
                const pending = document.createElement('p'); pending.className = 'flex max-w-[92%] items-center gap-2 rounded-xl border border-amber-300/20 bg-slate-700/70 px-3 py-2 text-xs text-amber-200'; pending.innerHTML = '<span>{{ __('AKI denkt') }}</span><span class="dashboard-aki-dots" aria-hidden="true">•••</span>'; messages.appendChild(pending); messages.scrollTop = messages.scrollHeight;
                try {
                    const response = await fetch('{{ route('aki.chat') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ question, messages: history.slice(-8), filters: {} }) });
                    const payload = await response.json(); pending.remove();
                    const text = response.ok ? (payload.answer || '{{ __('Keine Antwort erhalten.') }}') : (payload.message || '{{ __('Die KI ist gerade nicht erreichbar.') }}');
                    const answer = document.createElement('p'); answer.className = 'max-w-[92%] whitespace-pre-line rounded-xl border border-teal-300/15 bg-slate-700/70 px-3 py-2 text-xs text-slate-100'; answer.textContent = text; messages.appendChild(answer); history.push({ role: 'assistant', content: text });
                    const suggestedSymbols = Array.isArray(payload.filter_suggestion?.symbols) ? payload.filter_suggestion.symbols.filter(Boolean) : [];
                    if (response.ok && suggestedSymbols.length) {
                        const params = new URLSearchParams();
                        suggestedSymbols.forEach((symbol) => params.append('symbols[]', symbol));
                        const link = document.createElement('a');
                        link.href = '{{ route('predictions.index') }}?' + params.toString();
                        link.className = 'inline-flex items-center gap-2 rounded-lg border border-teal-300/35 bg-teal-500/15 px-3 py-2 text-[10px] font-black text-teal-200 transition hover:bg-teal-500/25';
                        link.target = '_self';
                        link.innerHTML = '<span>{{ __('Empfohlene Aktien öffnen') }}</span><span aria-hidden="true">→</span>';
                        messages.appendChild(link);
                    }
                } catch (_) { pending.remove(); const error = document.createElement('p'); error.className = 'max-w-[92%] rounded-xl border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-xs text-rose-300'; error.textContent = '{{ __('Die Verbindung zur KI konnte nicht hergestellt werden.') }}'; messages.appendChild(error); }
                messages.scrollTop = messages.scrollHeight;
            });
        })();
    </script>
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
            border-color: color-mix(in srgb, #fb923c 32%, transparent);
            box-shadow: 0 12px 30px rgba(194, 65, 12, .10), inset 0 1px 0 rgba(251, 146, 60, .045);
            backdrop-filter: blur(8px);
        }
        .dashboard-aki-dots { display: inline-block; min-width: 1.6em; letter-spacing: .12em; animation: dashboard-aki-pulse 1.1s steps(4, end) infinite; }
        @keyframes dashboard-aki-pulse { 0%,20% { opacity: .25; } 40% { opacity: .65; } 60%,100% { opacity: 1; } }
        #dashboard-aki-messages { scrollbar-width: thin; scrollbar-color: rgba(45,212,191,.7) rgba(15,23,42,.45); }
        #dashboard-aki-messages::-webkit-scrollbar { width: 8px; }
        #dashboard-aki-messages::-webkit-scrollbar-track { background: rgba(15,23,42,.45); border-radius: 999px; }
        #dashboard-aki-messages::-webkit-scrollbar-thumb { background: rgba(45,212,191,.7); border-radius: 999px; }
    </style>
</x-app-layout>

<x-app-layout>
    <x-detail-page-theme />
    <div id="strategy-depot-page" x-data="{ simulationOpen: {{ request()->boolean('test') && !$liveSimulationEnabled ? 'true' : 'false' }}, simulationSubmitting: false, automationOpen: false, strategyConfirmOpen: false, capitalOpen: false, resetOpen: false, deleteOpen: false }" class="ak-detail-design flex min-h-[calc(100dvh-89px)] flex-col py-4 text-[var(--ak-text)]">
        <div class="ak-depot-detail-hero ak-detail-hero mb-4 flex shrink-0 flex-col gap-3 rounded-2xl border border-[var(--ak-border)] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300">
                    <x-heroicon-o-briefcase class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-700">{{ __('Depot') }}</p>
                    <div class="mt-1 flex items-center gap-2"><h1 class="truncate text-2xl font-black tracking-tight">{{ $portfolio->name }}</h1>@if($liveSimulationEnabled)<span class="inline-flex shrink-0 items-center gap-1 rounded-md border border-orange-400/25 bg-orange-400/[.09] px-2 py-1 text-[9px] font-black uppercase tracking-wide text-orange-400"><span class="h-1.5 w-1.5 rounded-full bg-orange-400"></span>{{ __('Strategie') }}</span>@endif</div>
                    <p class="mt-1 truncate text-sm text-[var(--ak-muted)]">{{ $portfolio->description ?: __('Positionen und Entwicklung deines Depots.') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
            @if($portfolio->type === 'paper')<button type="button" @if($canActivateStrategyAccount) @click="automationOpen=true" @endif @disabled(!$canActivateStrategyAccount) title="{{ $canActivateStrategyAccount ? '' : __('Ab Pro verfügbar') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border px-3 text-xs font-black disabled:cursor-not-allowed disabled:opacity-40 {{ $liveSimulationEnabled ? 'border-orange-400/30 bg-orange-400/[.1] text-orange-400' : 'border-[var(--ak-border)] bg-[var(--ak-card)] text-[var(--ak-muted)]' }}"><x-heroicon-o-bolt class="h-4 w-4" />{{ $liveSimulationEnabled ? __('Mitlaufend aktiv') : __('Konto aktivieren') }}</button>@endif
            @if($portfolio->type === 'paper')<button type="button" @if(!$liveSimulationEnabled) @click="simulationOpen=true" @endif @disabled($liveSimulationEnabled) title="{{ $liveSimulationEnabled ? __('Während das Strategiedepot mitläuft, ist keine historische Simulation möglich.') : __('Simulation starten') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-amber-300/25 bg-amber-300/[.09] px-4 text-xs font-black text-amber-200 shadow-sm shadow-amber-950/15 disabled:cursor-not-allowed disabled:border-slate-500/20 disabled:bg-slate-500/[.06] disabled:text-slate-500 disabled:shadow-none"><x-heroicon-o-play class="h-4 w-4" />{{ __('Simulation') }}</button>@endif
            @if($portfolio->type === 'paper')<button type="button" @click="capitalOpen=true" class="inline-flex h-10 items-center gap-2 rounded-xl border border-teal-300/20 bg-teal-400/[.07] px-3 text-xs font-black text-teal-200"><x-heroicon-o-banknotes class="h-4 w-4" />{{ __('Kapital') }}</button>@endif
            @if($portfolio->type === 'paper')<button type="button" @click="resetOpen=true" class="inline-flex h-10 items-center gap-2 rounded-xl border border-orange-400/20 bg-orange-400/[.07] px-3 text-xs font-black text-orange-400"><x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Zurücksetzen') }}</button>@endif
            @if($portfolio->type === 'paper')<button type="button" @click="deleteOpen=true" class="inline-flex h-10 items-center gap-2 rounded-xl border border-rose-300/20 bg-rose-400/[.07] px-3 text-xs font-black text-rose-300"><x-heroicon-o-trash class="h-4 w-4" />{{ __('Löschen') }}</button>@endif
            <a href="{{ $backUrl }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700">
                <x-heroicon-o-arrow-left class="h-4 w-4" />{{ $backLabel }}
            </a>
            </div>
        </div>

        @if(session('status') || in_array($simulationRun?->status, ['queued', 'running'], true))
            <div class="relative mb-4 overflow-hidden rounded-xl border border-amber-300/20 bg-amber-300/[.055] px-4 pb-4 pt-3 shadow-[var(--ak-shadow)]">
                @if(in_array($simulationRun?->status, ['queued', 'running'], true))
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="ak-depot-sim-spinner" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-[10px] font-black uppercase tracking-[.12em] text-amber-300">{{ session('status', __('Depotsimulation gestartet.')) }}</p>
                                    <span class="ak-depot-sim-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                                </div>
                                <p id="portfolio-simulation-status" class="mt-0.5 truncate text-[10px] font-medium text-[var(--ak-muted)]">{{ __('Strategien, Transaktionen und Depotentwicklung werden berechnet …') }}</p>
                            </div>
                        </div>
                        <span id="portfolio-simulation-progress" class="shrink-0 text-xs font-black tabular-nums text-amber-200">0 %</span>
                    </div>
                    <div id="portfolio-simulation-track" class="ak-depot-sim-progress mt-3" role="progressbar" aria-label="{{ __('Depotsimulation läuft') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="portfolio-simulation-bar"></span></div>
                @else
                    <p class="text-sm font-bold text-teal-200">{{ session('status') }}</p>
                @endif
            </div>
        @endif
        @if($errors->any())<div class="mb-4 rounded-xl border border-rose-300/25 bg-rose-400/[.08] px-4 py-3 text-sm font-bold text-rose-200">{{ $errors->first() }}</div>@endif

        <section class="mb-1 grid items-stretch gap-3 xl:grid-cols-[minmax(380px,.8fr)_minmax(0,1.2fr)]">
            <div class="ak-depot-detail-card ak-detail-panel relative h-full overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-teal-500/30 via-cyan-400 to-amber-400/45"></div>
                <div class="mb-2 flex items-center justify-between"><div><p class="text-[8px] font-black uppercase tracking-[.16em] text-teal-300">{{ __('Depotdaten') }}</p><h2 class="mt-0.5 text-sm font-black">{{ __('Kapital und Bestand') }}</h2></div><x-heroicon-o-banknotes class="h-5 w-5 text-teal-300" /></div>
                <div class="ak-depot-metrics-grid grid grid-cols-2 gap-x-4 gap-y-2 xl:grid-cols-3">
                    @foreach ([
                        [__('Depotwert'), number_format($currentValue, 2, ',', '.').' '.$portfolio->currency, 'text-[var(--ak-text)]', 'portfolio-live-position-value'],
                        [__('Kontostand'), number_format($cashBalance, 2, ',', '.').' '.$portfolio->currency, 'text-[var(--ak-text)]'],
                        [__('Gesamtwert'), number_format($totalValue, 2, ',', '.').' '.$portfolio->currency, 'text-amber-300', 'portfolio-live-total-value'],
                        [__('Ø Kapitalauslastung'), number_format($averageCapitalUtilization, 1, ',', '.').' %', 'text-teal-300'],
                        [__('Performance'), ($performance > 0 ? '+' : '').number_format($performance, 2, ',', '.').' %', $performance > 0 ? 'text-emerald-400' : ($performance < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]')],
                        [__('Positionen'), $portfolio->positions->count(), 'text-teal-700'],
                        [__('Trades'), $simulationRun?->trades_count ?? 0, 'text-[var(--ak-text)]'],
                        [__('Trefferquote'), number_format((float) ($simulationSummary['hit_rate_percent'] ?? 0), 1, ',', '.').' %', 'text-orange-400'],
                        [__('Profitfaktor'), number_format(\App\Support\ProfitFactor::cap($simulationSummary['profit_factor'] ?? 0) ?? 0, 2, ',', '.'), 'text-orange-400'],
                        [__('Max. Drawdown'), number_format((float) ($simulationSummary['max_drawdown_percent'] ?? 0), 1, ',', '.').' %', 'text-rose-300'],
                        [__('Verschiedene Aktien'), $distinctStocksCount, 'text-orange-400'],
                        [__('Höchster Gewinn'), $highestProfit !== null ? '+'.number_format($highestProfit, 2, ',', '.').' '.$portfolio->currency : '—', 'text-teal-300'],
                        [__('Höchster Verlust'), $highestLoss !== null ? number_format($highestLoss, 2, ',', '.').' '.$portfolio->currency : '—', 'text-rose-300'],
                    ] as $metric)
                        @php
                            [$label, $value, $valueClass, $metricId] = array_pad($metric, 4, null);
                        @endphp
                        <div class="ak-depot-metric min-w-0 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2">
                            <p class="text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</p>
                            <p class="mt-0.5 truncate text-sm font-black tabular-nums {{ $valueClass }}" @if($metricId) id="{{ $metricId }}" @endif>{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="hidden" aria-hidden="true">
                    @foreach($livePortfolioPositions as $livePosition)
                        <span data-live-symbol="{{ $livePosition['symbol'] }}" data-live-decimals="4"></span>
                    @endforeach
                </div>
                @if($portfolio->strategies->isNotEmpty())
                    @php
                        $strategyWeightTotal = max(1.0, (float) $portfolio->strategies->sum(
                            fn ($strategy) => max(0.0, (float) ($strategy->pivot->capital_weight ?? 1))
                        ));
                    @endphp
                    <div class="mt-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                        <p class="mb-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Strategieanteile') }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($portfolio->strategies as $strategy)
                                @php
                                    $strategyShare = max(0.0, (float) ($strategy->pivot->capital_weight ?? 1)) / $strategyWeightTotal * 100;
                                @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-teal-500/25 bg-teal-500/[.09] px-2 py-1 text-[9px] font-black text-teal-500"><span class="max-w-28 truncate">{{ $strategy->name }}</span><strong class="tabular-nums text-[var(--ak-text)]">{{ number_format($strategyShare, 0, ',', '.') }} %</strong></span>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($portfolio->type === 'paper')
                    <form id="portfolio-strategy-form" method="POST" action="{{ route('depots.strategies.update', $portfolio) }}" class="mt-3 rounded-xl border border-teal-500/20 bg-teal-500/[.045] p-3">
                        @csrf
                        @method('PUT')
                        <div class="mb-1.5 flex items-center justify-between gap-2"><p class="text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Strategien verwalten') }}</p><button type="button" @click="strategyConfirmOpen=true" class="inline-flex h-7 items-center gap-1 rounded-md bg-gradient-to-r from-teal-600 to-orange-400 px-2.5 text-[9px] font-black text-white"><x-heroicon-o-check class="h-3.5 w-3.5" />{{ __('Speichern') }}</button></div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($availableStrategies as $strategy)
                                <label class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-md border px-2 text-[9px] font-black transition {{ $portfolio->strategies->contains('id', $strategy->id) ? 'border-teal-300/35 bg-teal-400/10 text-teal-200' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]' }}">
                                    <input type="checkbox" name="strategies[]" value="{{ $strategy->id }}" @checked($portfolio->strategies->contains('id', $strategy->id)) class="h-3 w-3 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30"><span>{{ $strategy->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </form>
                @endif
            </div>
            <div class="ak-depot-detail-card ak-detail-panel relative flex h-full min-h-[420px] min-w-0 flex-col overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-400/35 via-cyan-400 to-teal-500/30"></div>
                <div class="mb-1 flex items-center justify-between gap-3"><div><p class="text-[8px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Depotentwicklung') }}</p><h2 class="mt-0.5 text-sm font-black">{{ $simulationRun?->simulation_start_date && $simulationRun?->simulation_end_date ? $simulationRun->simulation_start_date.' – '.$simulationRun->simulation_end_date : __('Noch keine Simulation vorhanden') }}</h2></div>@if($simulationRun?->status === 'completed')<a href="{{ route('depots.simulation.report', [$portfolio, $simulationRun->public_id]) }}" title="{{ __('Bericht laden') }}" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-orange-400/25 bg-orange-400/10 px-2.5 text-[10px] font-black text-orange-400"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />{{ __('Bericht') }}</a>@else<x-heroicon-o-chart-bar-square class="h-5 w-5 text-orange-400" />@endif</div>
                @if($simulationRun?->status === 'completed' && !empty($simulationSummary['equity_curve']))
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl bg-transparent"><div id="portfolio-simulation-chart" class="ak-portfolio-line-chart min-h-[260px] flex-1"></div><div id="portfolio-profit-bars" class="relative mx-10 h-24 shrink-0 border-t border-white/10 bg-transparent"></div></div>
                @else
                    <div class="grid flex-1 place-items-center rounded-xl border border-dashed border-teal-500/25 bg-[linear-gradient(145deg,color-mix(in_srgb,var(--ak-surface-muted)_94%,#06b6d4_6%),var(--ak-surface-muted))] p-8 text-center">
                        <div class="max-w-md">
                            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-teal-500/25 bg-teal-500/10 text-teal-500"><x-heroicon-o-chart-bar-square class="h-7 w-7" /></span>
                            <h3 class="mt-4 text-base font-black text-[var(--ak-text)]">{{ __('Depotentwicklung berechnen') }}</h3>
                            <p class="mt-2 text-xs leading-5 text-[var(--ak-muted)]">{{ __('Starte eine historische Simulation, um Performance, Drawdown, Trefferquote und die Entwicklung des Depotwerts auszuwerten.') }}</p>
                            @if($portfolio->type === 'paper' && !$liveSimulationEnabled)
                                <button type="button" @click="simulationOpen=true" class="mt-5 inline-flex h-10 items-center gap-2 rounded-xl bg-teal-700 px-4 text-xs font-black text-white shadow-lg shadow-teal-950/20 hover:bg-teal-600"><x-heroicon-o-play class="h-4 w-4" />{{ __('Simulation starten') }}</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if($simulationRun && !in_array($simulationRun->status, ['queued', 'running', 'completed'], true))
            <section class="mb-4 rounded-xl border border-rose-300/20 bg-rose-400/[.06] px-4 py-3 text-sm font-bold text-rose-300 shadow-[var(--ak-shadow)]">
                {{ $simulationRun->error_message }}
            </section>
        @endif

        <div x-show="automationOpen" x-cloak class="fixed inset-0 z-[125] grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm" @keydown.escape.window="automationOpen=false">
            <form method="POST" action="{{ route('depots.automation.update', $portfolio) }}" class="w-full max-w-lg rounded-2xl border border-orange-400/25 bg-[#16253a]/90 p-6 shadow-2xl" @click.outside="automationOpen=false">
                @csrf @method('PUT')
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-400/10 text-orange-400"><x-heroicon-o-bolt class="h-7 w-7" /></div>
                <h2 class="mt-4 text-xl font-black text-white">{{ $liveSimulationEnabled ? __('Strategiekonto verwalten') : __('Mitlaufende Simulation aktivieren') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Neue Signale der zugeordneten Strategien können fortlaufend als simulierte Käufe und Verkäufe in diesem Musterdepot verbucht werden.') }}</p>
                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-orange-400/20 bg-orange-400/[.06] p-3 text-sm font-bold text-orange-400"><input type="checkbox" name="transaction_email_enabled" value="1" @checked($transactionEmailsEnabled) class="mt-0.5 h-4 w-4 rounded border-orange-400/40 bg-slate-950 text-orange-4000 focus:ring-orange-4000/30"><span><strong class="block text-white">{{ __('E-Mail pro Transaktion') }}</strong><small class="mt-1 block font-medium text-slate-300">{{ __('Bei jedem simulierten Kauf oder Verkauf eine E-Mail senden.') }}</small></span></label>
                <p class="mt-3 text-[10px] leading-5 text-amber-200"><x-heroicon-o-information-circle class="mr-1 inline h-4 w-4" />{{ __('Im Pro-Tarif kann genau ein Strategiekonto gleichzeitig aktiv sein.') }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="automationOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button>
                    @if($liveSimulationEnabled)<button type="submit" name="enabled" value="0" class="h-10 rounded-lg border border-rose-300/25 bg-rose-400/[.08] px-4 text-xs font-black text-rose-200">{{ __('Deaktivieren') }}</button>@endif
                    <button type="submit" name="enabled" value="1" class="h-10 rounded-lg border border-orange-400/25 bg-orange-400/[.12] px-4 text-xs font-black text-orange-400">{{ $liveSimulationEnabled ? __('Einstellungen speichern') : __('Konto aktivieren') }}</button>
                </div>
            </form>
        </div>

        <section class="mt-4 overflow-hidden rounded-2xl border border-cyan-400/25 bg-cyan-400/[.018] shadow-[0_18px_60px_rgba(6,182,212,.05)]">
            <div class="ak-detail-card-head flex items-center justify-between border-b border-[var(--ak-border)] px-4 py-3">
                <div>
                    <h2 class="font-black">{{ __('Depotpositionen') }}</h2>
                    <p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Aktien, Einstiegskurse und aktuelle Entwicklung.') }}</p>
                </div>
                <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1.5 text-[10px] font-black text-[var(--ak-muted)]">{{ $portfolio->currency }}</span>
            </div>

            @if ($portfolio->positions->isEmpty())
                <div class="grid min-h-64 place-items-center p-8 text-center">
                    <div>
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]"><x-heroicon-o-chart-bar-square class="h-6 w-6" /></span>
                        <h3 class="mt-4 font-black">{{ __('Noch keine Positionen vorhanden') }}</h3>
                        <p class="mt-2 max-w-md text-sm text-[var(--ak-muted)]">{{ __('Im nächsten Schritt können Aktien mit Stückzahl und Einstiegskurs zu diesem Depot hinzugefügt werden.') }}</p>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ak-stocks-table w-full min-w-[1450px] text-left text-xs">
                        <thead><tr>
                            @foreach ([__('Aktie'), __('Positionsgröße'), __('Kaufkurs'), __('Kauf-KI'), __('Aktueller Kurs'), __('KI-Bewertung'), __('Signalwechsel'), __('Wert'), __('Performance'), __('Verlauf'), __('Handel')] as $heading)
                                @if($heading === __('Signalwechsel') && !$canViewSignalChanges) @continue @endif
                                <th class="border-b border-[var(--ak-border)] px-4 py-3 text-[10px] font-black uppercase tracking-wide">{{ $heading }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($portfolio->positions as $position)
                                @php
                                    $entry = (float) $position->average_buy_price;
                                    $current = (float) ($position->current_price ?? $entry);
                                    $value = (float) $position->quantity * $current;
                                    $positionPerformance = $entry > 0 ? (($current - $entry) / $entry) * 100 : 0;
                                    $prediction = $positionPredictions->get($position->instrument_id);
                                    $entryData = $positionEntryData->get($position->instrument_id, []);
                                    $scorePercent = \App\Support\AiScore::toPercent($prediction?->prediction_score);
                                    $toPercent = static fn ($number) => is_numeric($number) ? max(0,min(100,(float)$number*((float)$number<=1?100:1))) : null;
                                    $confidence = $toPercent($prediction?->confidence); $stability = $toPercent($prediction?->horizon_fusion_stability_score);
                                    $risk = \App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor);
                                    $stats = $positionWalkForwardStats->get($position->instrument_id);
                                    $hitRate = is_numeric($stats?->hit_rate) ? (float)$stats->hit_rate : null;
                                    $profitTrade = is_numeric($stats?->average_profit_per_trade_percent) ? (float)$stats->average_profit_per_trade_percent : null;
                                    $profitScale = $profitTrade !== null ? max(0,min(100,50+$profitTrade*25)) : null;
                                    $color = static function($percent) { if(!is_numeric($percent)) return '#64748b'; $percent=max(0,min(100,(float)$percent)); $h=$percent<=50?($percent/50)*48:48+(($percent-50)/50)*94; return sprintf('hsl(%.1f 78%% 52%%)',$h); };
                                    $signal = strtoupper((string)($prediction?->personalized_signal ?: 'HOLD'));
                                    $signalTone = match($signal) {'BUY'=>'border-emerald-300/55 bg-emerald-400/15 text-emerald-300','WAIT'=>'border-emerald-300/45 bg-emerald-400/10 text-emerald-300','WATCH'=>'border-lime-300/40 bg-lime-400/10 text-lime-300','SELL'=>'border-rose-300/45 bg-rose-400/10 text-rose-300',default=>'border-amber-300/40 bg-amber-400/10 text-amber-300'};
                                    $change = $canViewSignalChanges ? $positionSignalChanges->get($position->instrument_id) : null;
                                    $series = collect($positionPerformanceSeries->get($position->instrument_id, collect()))->pluck('value')->map(fn($v)=>(float)$v)->values();
                                    $cw=130;$ch=38;$cp=3;$cmin=$series->isNotEmpty()?min((float)$series->min(),0):0;$cmax=$series->isNotEmpty()?max((float)$series->max(),0):0;$cr=max(.01,$cmax-$cmin);
                                    $poly=$series->map(fn($v,$i)=>number_format($cp+($i/max(1,$series->count()-1))*($cw-2*$cp),1,'.','').','.number_format($cp+(($cmin+$cr-$v)/$cr)*($ch-2*$cp),1,'.',''))->implode(' ');
                                @endphp
                                <tr>
                                    <td class="px-4 py-3"><a href="{{ route('stocks.show', ['symbol' => $position->instrument->symbol, 'prediction' => $latestPredictionIds->get($position->instrument_id), 'return_to' => request()->getRequestUri()]) }}" class="font-black text-teal-700">{{ $position->instrument->symbol }}</a><p class="mt-0.5 text-[10px] text-[var(--ak-muted)]">{{ $position->instrument->name }}</p></td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($position->quantity, 4, ',', '.') }}</td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($entry, 2, ',', '.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3"><strong class="text-amber-300">{{ is_numeric($entryData['ai_score'] ?? null) ? number_format((float)$entryData['ai_score'],0,',','.') : '—' }}</strong><p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ $entryData['signal'] ?? '—' }} @if($entryData['date'] ?? null)· {{ \Illuminate\Support\Carbon::parse($entryData['date'])->format('d.m.Y') }}@endif</p></td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($current, 2, ',', '.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3"><div class="flex min-w-[27rem] items-center gap-2"><span class="inline-flex min-w-16 justify-center rounded-lg border px-2 py-2 text-[10px] font-black {{ $signalTone }}">{{ $signal }}</span>@foreach([['KI-Score',$scorePercent,$scorePercent!==null?number_format($scorePercent/10,1,',','.'):'—',$color($scorePercent),true],['Konf.',$confidence,$confidence!==null?number_format($confidence,0,',','.').'%':'—',$color($confidence),false],['Hit-Rate',$hitRate,$hitRate!==null?number_format($hitRate,0,',','.').'%':'—',$color($hitRate),false],['Ø/Trade',$profitScale,$profitTrade!==null?(($profitTrade>0?'+':'').number_format($profitTrade,2,',','.').'%'):'—',$color($profitScale),false],['Stabilität',$stability,$stability!==null?number_format($stability,0,',','.').'%':'—',$color($stability),false],['Risiko',$risk,$risk!==null?number_format($risk,0,',','.').'%':'—',$risk!==null?$color(100-$risk):'#64748b',false]] as [$label,$dv,$display,$dc,$large])<div class="screener-metric-donut {{ $large?'screener-metric-donut-score':'' }}" style="--donut-value:{{ number_format($dv??0,2,'.','') }}%;--donut-color:{{ $dc }}"><span>{{ $display }}</span><small>{{ $label }}</small></div>@endforeach</div></td>
                                    @if($canViewSignalChanges)<td class="px-4 py-3 text-center">@if($change)<span class="whitespace-nowrap rounded-md border border-cyan-400/25 bg-cyan-400/[.07] px-2 py-1.5 text-[9px] font-black text-cyan-300">{{ $change['from'] }} → {{ $change['to'] }} · {{ \Illuminate\Support\Carbon::parse($change['date'])->format('d.m.') }}</span>@else — @endif</td>@endif
                                    <td class="px-4 py-3 font-black tabular-nums">{{ number_format($value, 2, ',', '.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3 font-black tabular-nums {{ $positionPerformance > 0 ? 'text-emerald-400' : ($positionPerformance < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ $positionPerformance > 0 ? '+' : '' }}{{ number_format($positionPerformance, 2, ',', '.') }} %</td>
                                    <td class="px-4 py-3">@if($series->count()>=2)<svg class="h-10 w-32" viewBox="0 0 {{ $cw }} {{ $ch }}"><polyline points="{{ $poly }}" fill="none" stroke="{{ $positionPerformance>=0?'#34d399':'#fb7185' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>@else — @endif</td>
                                    <td class="px-4 py-3"><form method="POST" action="{{ route('paper-depots.instruments.sell', [$portfolio, $position->instrument_id]) }}" class="flex items-center gap-1.5" onsubmit="return confirm(@js(__('Verkauf im Musterdepot ausführen?'))) ">@csrf<input name="quantity" type="number" min="0.0001" max="{{ $position->quantity }}" step="0.0001" value="{{ rtrim(rtrim(number_format((float)$position->quantity,4,'.',''),'0'),'.') }}" required class="ak-input h-8 w-20 px-2 text-[10px]"><button class="h-8 rounded-lg border border-rose-400/30 bg-rose-400/10 px-2.5 text-[9px] font-black text-rose-300">{{ __('Verkaufen') }}</button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="ak-detail-panel mt-4 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <div class="ak-detail-card-head flex items-center justify-between border-b border-[var(--ak-border)] px-4 py-3">
                <div>
                    <h2 class="font-black">{{ __('Transaktionshistorie') }}</h2>
                    <p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Alle simulierten Käufe, Verkäufe und Gebühren in chronologischer Reihenfolge.') }}</p>
                </div>
                <span class="rounded-lg border border-amber-300/25 bg-amber-300/[.08] px-2.5 py-1.5 text-[10px] font-black text-amber-300">{{ $portfolio->transactions->count() }} {{ __('Buchungen') }}</span>
            </div>
            @if($portfolio->transactions->isEmpty())
                <div class="p-6 text-center text-sm text-[var(--ak-muted)]">{{ __('Noch keine Transaktionen vorhanden.') }}</div>
            @else
                <div class="max-h-80 overflow-auto">
                    <table class="ak-stocks-table w-full min-w-[860px] text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-[var(--ak-card)]"><tr>
                            @foreach([__('Datum'),__('Aktion'),__('Aktie'),__('Stück'),__('Kurs'),__('Gebühr'),__('Kontobewegung'),__('Ergebnis')] as $heading)
                                <th class="border-b border-[var(--ak-border)] px-4 py-3 text-[10px] font-black uppercase tracking-wide">{{ $heading }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach($portfolio->transactions as $transaction)
                                @php
                                    $isSale = strtolower($transaction->type) === 'sell';
                                    $gross = (float)$transaction->quantity * (float)$transaction->price;
                                    $movement = $isSale ? $gross - (float)$transaction->fees : -($gross + (float)$transaction->fees);
                                    $simulated = data_get($transaction->meta, 'source') === 'portfolio_backtest_simulation';
                                    $result = data_get($transaction->meta, 'realized_profit');
                                    $resultPercent = data_get($transaction->meta, 'performance_percent');
                                    $triggerStrategyIds = collect(data_get($transaction->meta, 'strategy_ids', [data_get($transaction->meta, 'strategy_id')]))->filter();
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 tabular-nums text-[var(--ak-muted)]">{{ $transaction->transaction_date?->format('d.m.Y') }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex min-w-20 justify-center rounded-md border px-2.5 py-1 text-[10px] font-black {{ $isSale ? 'border-rose-300/35 bg-rose-400/10 text-rose-300' : 'border-teal-300/35 bg-teal-400/10 text-teal-300' }}">{{ $isSale ? __('VERKAUF') : __('KAUF') }}</span>@if($simulated)<span class="ml-2 text-[9px] font-black uppercase tracking-wide text-amber-300">{{ __('Simulation') }}</span>@endif @if($triggerStrategyIds->isNotEmpty())<p class="mt-1 text-[9px] font-bold text-orange-400">{{ $triggerStrategyIds->map(fn($id) => $strategyNames->get((int)$id))->filter()->join(' · ') }}</p>@endif</td>
                                    <td class="px-4 py-3">
                                        @if($transaction->instrument)
                                            <a href="{{ route('stocks.show', ['symbol' => $transaction->instrument->symbol, 'prediction' => $latestPredictionIds->get($transaction->instrument_id), 'return_to' => request()->getRequestUri()]) }}" class="font-black text-teal-300 hover:text-teal-200">{{ $transaction->instrument->symbol }}</a>
                                        @else
                                            <span class="font-black text-[var(--ak-muted)]">—</span>
                                        @endif
                                        <p class="mt-0.5 text-[10px] text-[var(--ak-muted)]">{{ $transaction->instrument?->name }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format(round($transaction->quantity),0,',','.') }}</td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($transaction->price,2,',','.') }} {{ $transaction->currency }}</td>
                                    <td class="px-4 py-3 tabular-nums text-[var(--ak-muted)]">{{ number_format($transaction->fees,2,',','.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3 font-black tabular-nums {{ $movement >= 0 ? 'text-teal-300' : 'text-rose-300' }}">{{ $movement >= 0 ? '+' : '' }}{{ number_format($movement,2,',','.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3 font-black tabular-nums {{ (float)$result >= 0 ? 'text-teal-300' : 'text-rose-300' }}">@if($result !== null){{ (float)$result >= 0 ? '+' : '' }}{{ number_format((float)$result,2,',','.') }} {{ $portfolio->currency }}<p class="mt-0.5 text-[10px]">{{ (float)$resultPercent >= 0 ? '+' : '' }}{{ number_format((float)$resultPercent,2,',','.') }} %</p>@else—@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div x-show="simulationOpen" x-cloak class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm" @keydown.escape.window="simulationOpen=false">
            <form method="POST" action="{{ route('depots.simulation.start', $portfolio) }}" class="w-full max-w-lg rounded-2xl border border-amber-300/25 bg-[#16253a]/90 p-6 shadow-2xl" @click.outside="if(!simulationSubmitting) simulationOpen=false" @submit="simulationSubmitting=true">
                @csrf
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-400/10 text-amber-300"><x-heroicon-o-exclamation-triangle class="h-7 w-7" /></div>
                <h2 class="mt-4 text-xl font-black">{{ __('Simulation neu starten?') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Dabei werden alle bisherigen Positionen, Transaktionen, Kontobuchungen und Simulationshistorien dieses Musterdepots endgültig gelöscht. Das Verrechnungskonto wird auf das festgelegte Startkapital zurückgesetzt.') }}</p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" :disabled="simulationSubmitting" @click="simulationOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300 disabled:opacity-40">{{ __('Abbrechen') }}</button><button :disabled="simulationSubmitting" class="inline-flex h-10 min-w-40 items-center justify-center gap-2 rounded-lg border border-amber-300/25 bg-amber-300/[.1] px-4 text-xs font-black text-amber-100 shadow-sm shadow-amber-950/20 disabled:cursor-wait disabled:opacity-70"><svg x-show="simulationSubmitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"/></svg><span x-text="simulationSubmitting ? @js(__('Wird gestartet …')) : @js(__('Löschen und simulieren'))"></span></button></div>
            </form>
        </div>

        <div x-show="strategyConfirmOpen" x-cloak class="fixed inset-0 z-[121] grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm" @keydown.escape.window="strategyConfirmOpen=false">
            <div class="w-full max-w-lg rounded-2xl border border-orange-400/25 bg-[#16253a]/90 p-6 shadow-2xl" @click.outside="strategyConfirmOpen=false">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-400/10 text-orange-400"><x-heroicon-o-adjustments-horizontal class="h-7 w-7" /></div>
                <h2 class="mt-4 text-xl font-black">{{ __('Strategiezuordnung ändern?') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Hinzugefügte Strategien steuern dieses Depot künftig parallel. Entfernte Strategien lösen keine neuen Transaktionen mehr aus; bestehende Positionen und Historien bleiben erhalten.') }}</p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="strategyConfirmOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button type="submit" form="portfolio-strategy-form" class="h-10 rounded-lg bg-gradient-to-r from-teal-600 to-orange-400 px-4 text-xs font-black text-white">{{ __('Zuordnung speichern') }}</button></div>
            </div>
        </div>

        <div x-show="resetOpen" x-cloak class="fixed inset-0 z-[122] grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm" @keydown.escape.window="resetOpen=false">
            <form method="POST" action="{{ route('depots.reset', $portfolio) }}" class="w-full max-w-lg rounded-2xl border border-amber-300/25 bg-[#16253a]/90 p-6 shadow-2xl" @click.outside="resetOpen=false">@csrf
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-400/10 text-amber-300"><x-heroicon-o-arrow-path class="h-7 w-7" /></div><h2 class="mt-4 text-xl font-black">{{ __('Musterdepot zurücksetzen?') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Alle Positionen, Transaktionen, Kontobuchungen und Simulationsergebnisse werden endgültig gelöscht. Strategien und das Depot selbst bleiben erhalten; das Konto wird auf das Startkapital zurückgesetzt.') }}</p>
                <label class="mt-4 flex gap-3 rounded-xl border border-amber-300/25 bg-amber-400/[.07] p-3 text-sm font-bold text-amber-100"><input required type="checkbox" name="confirm_reset" value="1" class="mt-0.5 h-4 w-4 rounded border-amber-300/60 bg-slate-950 text-amber-400 accent-amber-400 focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-slate-900"><span>{{ __('Ich bestätige das Löschen der gesamten Depothistorie.') }}</span></label>
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="resetOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button class="h-10 rounded-lg border border-amber-300/50 bg-amber-400 px-4 text-xs font-black text-slate-950 shadow-[0_0_18px_rgba(251,191,36,.18)] transition hover:bg-amber-300">{{ __('Depot zurücksetzen') }}</button></div>
            </form>
        </div>

        <div x-show="capitalOpen" x-cloak class="fixed inset-0 z-[124] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="capitalOpen=false">
            <form method="POST" action="{{ route('depots.capital.update', $portfolio) }}" class="isolate w-full max-w-lg rounded-2xl border border-teal-300/25 p-6 shadow-2xl" style="background-color: rgba(22, 37, 58, 0.90);" @click.outside="capitalOpen=false">@csrf @method('PUT')
                <x-heroicon-o-banknotes class="h-9 w-9 text-teal-300" /><h2 class="mt-4 text-xl font-black text-white">{{ __('Kapital festlegen') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Das Startkapital bestimmt die verfügbare Kapitalbasis für Simulationen und automatische Depottransaktionen. Das Verrechnungskonto wird um die Differenz angepasst.') }}</p>
                <label class="mt-5 grid gap-2 text-[10px] font-black uppercase tracking-wide text-slate-300">{{ __('Startkapital') }}<div class="relative"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" required value="{{ number_format((float) data_get($portfolio->meta, 'automation.initial_capital', 10000), 0, '.', '') }}" class="ak-input h-12 w-full pr-14 text-base font-black tabular-nums"><span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 font-black text-teal-300">{{ $portfolio->currency }}</span></div></label>
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="capitalOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button class="h-10 rounded-lg bg-gradient-to-r from-teal-600 to-orange-400 px-4 text-xs font-black text-white">{{ __('Kapital speichern') }}</button></div>
            </form>
        </div>

        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-[123] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="deleteOpen=false">
            <form method="POST" action="{{ route('depots.destroy', $portfolio) }}" class="w-full max-w-lg rounded-2xl border border-rose-300/30 bg-[#16253a]/90 p-6 shadow-2xl" @click.outside="deleteOpen=false">@csrf @method('DELETE')
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-400/12 text-rose-300"><x-heroicon-o-trash class="h-7 w-7" /></div><h2 class="mt-4 text-xl font-black text-rose-100">{{ __('Musterdepot endgültig löschen?') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Das Depot, seine Strategiezuordnungen, Positionen, Transaktionen, Kontobuchungen und Berichte werden unwiderruflich gelöscht. Die Strategien selbst bleiben im Strategie Manager erhalten.') }}</p>
                <label class="mt-4 flex gap-3 rounded-xl border border-rose-300/25 bg-rose-400/[.08] p-3 text-sm font-bold text-rose-100"><input required type="checkbox" name="confirm_delete" value="1" class="mt-0.5 h-4 w-4 rounded bg-slate-950 text-rose-500"><span>{{ __('Ich bestätige die endgültige Löschung dieses Musterdepots.') }}</span></label>
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="deleteOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button class="h-10 rounded-lg bg-gradient-to-r from-rose-600 to-red-700 px-4 text-xs font-black text-white">{{ __('Endgültig löschen') }}</button></div>
            </form>
        </div>
    </div>

    <style>
      #strategy-depot-page .ak-depot-detail-hero{
        background:
          radial-gradient(circle at 82% 0%,color-mix(in srgb,#06b6d4 10%,transparent),transparent 34%),
          linear-gradient(135deg,color-mix(in srgb,var(--ak-card) 95%,#0891b2 5%),var(--ak-card));
        box-shadow:var(--ak-shadow);
      }
      #strategy-depot-page .ak-depot-detail-card{
        background:transparent !important;
        border-color:rgba(255,255,255,.82) !important;
        box-shadow:0 12px 30px rgba(2,132,199,.12),0 0 0 1px rgba(34,211,238,.10) inset,inset 0 1px 0 rgba(207,250,254,.08) !important;
      }
      #strategy-depot-page .ak-depot-detail-card>.absolute:first-child{
          background:linear-gradient(90deg,rgba(255,255,255,.25),#ffffff,rgba(255,255,255,.45)) !important;
      }
      #strategy-depot-page .ak-depot-metric{
        background:rgba(10,45,65,.82) !important;
        border-color:rgba(34,211,238,.26) !important;
        box-shadow:inset 0 1px 0 rgba(207,250,254,.05);
      }
      #strategy-depot-page .ak-depot-metric:hover{background:rgba(16,61,82,.78) !important;border-color:rgba(34,211,238,.52) !important}
      #strategy-depot-page .ak-depot-metric{
        transition:border-color .18s ease,background-color .18s ease;
      }
      #strategy-depot-page .ak-depot-metric:hover{
        border-color:color-mix(in srgb,#06b6d4 30%,var(--ak-border));
      }
      :root[data-theme="light"] #strategy-depot-page .ak-depot-detail-hero{
        background:linear-gradient(135deg,rgba(255,255,255,.96),rgba(236,254,255,.78)) !important;
        box-shadow:0 14px 34px rgba(14, 116, 144,.12) !important;
      }
      :root[data-theme="light"] #strategy-depot-page .ak-depot-detail-card{
        background:rgba(255,255,255,.78) !important;
        border-color:rgba(14,116,144,.28) !important;
        box-shadow:0 12px 28px rgba(14, 116, 144,.10),inset 0 1px 0 rgba(255,255,255,.9) !important;
      }
      :root[data-theme="light"] #strategy-depot-page .ak-depot-detail-card>.absolute:first-child{
        background:linear-gradient(90deg,rgba(14,116,144,.28),#22d3ee,rgba(14,116,144,.2)) !important;
      }
      :root[data-theme="light"] #strategy-depot-page .ak-depot-metric{
        background:rgba(240,249,250,.94) !important;
        border-color:rgba(14,116,144,.24) !important;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.9) !important;
      }
      :root[data-theme="light"] #strategy-depot-page .ak-depot-metric:hover{
        background:rgba(224,247,250,.98) !important;
        border-color:rgba(14,116,144,.42) !important;
      }
      :root[data-theme="light"] #strategy-depot-page .ak-depot-metric p:first-child,
      :root[data-theme="light"] #strategy-depot-page .ak-depot-detail-card p.text-\[8px\]{color:#64748b !important}
      :root[data-theme="light"] #strategy-depot-page .ak-depot-detail-card .bg-\[var\(--ak-surface-muted\)\],
      :root[data-theme="light"] #strategy-depot-page .ak-depot-detail-card form{background:rgba(248,252,253,.88) !important;border-color:rgba(14,116,144,.2) !important}
      @media(max-width:1279px){
        #strategy-depot-page .ak-depot-detail-hero>div:last-child{justify-content:flex-start}
      }
    </style>

    @if($portfolioValueCurve->count() >= 2)
    <script>
    document.addEventListener('DOMContentLoaded',()=>{
      const node=document.querySelector('#manual-portfolio-value-chart');
      const curve=@json($portfolioValueCurve);
      if(!node||!window.ApexCharts||curve.length<2)return;
      const light=document.documentElement.dataset.theme==='light';
      new ApexCharts(node,{chart:{type:'area',height:224,toolbar:{show:false},zoom:{enabled:false},background:'transparent'},series:[{name:@json(__('Depotwert')),data:curve.map(p=>({x:new Date(p.x).getTime(),y:Number(p.y)}))}],colors:['#22d3ee'],stroke:{curve:'smooth',width:2.3},fill:{type:'gradient',gradient:{opacityFrom:.28,opacityTo:.02,stops:[0,100]}},dataLabels:{enabled:false},markers:{size:0},legend:{show:false},grid:{borderColor:light?'rgba(14,116,144,.12)':'rgba(148,163,184,.10)'},xaxis:{type:'datetime',labels:{style:{colors:'#7f93a8',fontSize:'9px'}},axisBorder:{show:false},axisTicks:{show:false}},yaxis:{labels:{style:{colors:'#7f93a8',fontSize:'9px'},formatter:v=>`${new Intl.NumberFormat(document.documentElement.lang||'de-DE',{maximumFractionDigits:0}).format(v)} {{ $portfolio->currency }}`}},tooltip:{x:{format:'dd.MM.yyyy'},y:{formatter:v=>`${new Intl.NumberFormat(document.documentElement.lang||'de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v)} {{ $portfolio->currency }}`}},theme:{mode:light?'light':'dark'}}).render();
    });
    </script>
    @endif

    @if($simulationRun)
    <style>
      .ak-depot-sim-spinner{width:20px;height:20px;flex:0 0 auto;border:2px solid rgba(251,191,36,.2);border-top-color:#fbbf24;border-right-color:#22d3ee;border-radius:999px;animation:ak-depot-sim-spin .9s linear infinite}
      .ak-depot-sim-dots{display:inline-flex;align-items:center;gap:3px;height:12px}.ak-depot-sim-dots i{width:3px;height:3px;border-radius:999px;background:#fbbf24;animation:ak-depot-sim-dot 1.2s ease-in-out infinite}.ak-depot-sim-dots i:nth-child(2){animation-delay:.16s}.ak-depot-sim-dots i:nth-child(3){animation-delay:.32s}
      .ak-depot-sim-progress{height:6px;overflow:hidden;border:1px solid rgba(251,191,36,.18);border-radius:999px;background:rgba(15,23,42,.62);box-shadow:inset 0 1px 2px rgba(0,0,0,.35)}.ak-depot-sim-progress span{display:block;width:34%;height:100%;border-radius:999px;background:linear-gradient(90deg,transparent,rgba(34, 211, 238,.95),#fbbf24,transparent);box-shadow:0 0 8px rgba(251,191,36,.35);animation:ak-depot-sim-progress 1.8s ease-in-out infinite}.ak-depot-sim-progress span.is-determinate{animation:none;background:linear-gradient(90deg,rgba(34, 211, 238,.82),rgba(251,191,36,.9));transition:width .35s ease}
      .ak-portfolio-line-chart{background:transparent !important}
      .ak-portfolio-line-chart .apexcharts-canvas,.ak-portfolio-line-chart svg{background:transparent !important}
      .ak-portfolio-line-chart path.apexcharts-line{stroke:#22d3ee !important;filter:drop-shadow(0 0 3px rgba(34,211,238,.22))}
      :root[data-theme="light"] .ak-portfolio-line-chart path.apexcharts-line{stroke:#0e7490 !important;filter:drop-shadow(0 0 2px rgba(14,116,144,.16))}
      @keyframes ak-depot-sim-spin{to{transform:rotate(360deg)}}@keyframes ak-depot-sim-dot{0%,65%,100%{opacity:.25;transform:translateY(0)}32%{opacity:1;transform:translateY(-2px)}}@keyframes ak-depot-sim-progress{from{transform:translateX(-110%)}to{transform:translateX(310%)}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',()=>{
      @if(in_array($simulationRun->status,['queued','running'],true))
      const poll=async()=>{try{const r=await fetch(@json(route('depots.simulation.status',[$portfolio,$simulationRun->public_id])),{headers:{Accept:'application/json'},cache:'no-store'});if(r.ok){const d=await r.json();const progress=Math.max(0,Math.min(100,Number(d.progress)||0));const bar=document.querySelector('#portfolio-simulation-bar');const track=document.querySelector('#portfolio-simulation-track');document.querySelector('#portfolio-simulation-progress').textContent=`${progress} %`;if(progress>0){bar.classList.add('is-determinate');bar.style.width=`${progress}%`;track?.setAttribute('aria-valuenow',String(progress))}if(d.finished){location.reload();return}}}catch(_){/* Temporäre Verbindungsfehler unterbrechen die Animation nicht. */}setTimeout(poll,1500)};setTimeout(poll,700);
      @elseif($simulationRun->status === 'completed')
      const curve=@json($simulationSummary['equity_curve']??[]);
      const trades=@json($chartTrades);
      const chartNode=document.querySelector('#portfolio-simulation-chart');
      if(window.ApexCharts&&chartNode&&curve.length){
        const isLightTheme=document.documentElement.dataset.theme==='light';
        const chartHeight=Math.max(260,Math.floor(chartNode.getBoundingClientRect().height));
        const escapeHtml=value=>String(value??'—').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
        const dateLabel=value=>value?new Intl.DateTimeFormat(document.documentElement.lang||'de-DE').format(new Date(value)):'—';
        const baseValue=Number(curve[0]?.equity)||1;
        const moneyLabel=value=>new Intl.NumberFormat(document.documentElement.lang||'de-DE',{notation:'compact',maximumFractionDigits:1}).format(Number(value));
        const depotData=curve.map(point=>({x:new Date(point.date).getTime(),y:Number(point.equity)}));
        const profitBars=trades.filter(trade=>trade.sell_date&&Number.isFinite(Number(trade.realized_profit))).map(trade=>({x:new Date(trade.sell_date).getTime(),y:Number(trade.realized_profit),trade,fillColor:Number(trade.realized_profit)>=0?'#22d3ee':'#fb7185'}));
        const profitSeriesName=@json(__('Gewinn / Verlust'));
        const series=[{name:@json(__('Depot gesamt')),data:depotData,type:'line'}];
        const chartMin=depotData[0].x;
        const chartMax=depotData[depotData.length-1].x;
        const depotValues=depotData.map(point=>Number(point.y)||0);
        const highestDepotValue=Math.max(...depotValues);
        const lowestDepotValue=Math.min(...depotValues);
        const depotValueRange=Math.max(1,highestDepotValue-lowestDepotValue);
        const chartValueMax=highestDepotValue+Math.max(depotValueRange*.05,Math.abs(highestDepotValue)*.002,1);
        const chartValueMin=Math.max(0,lowestDepotValue-Math.max(depotValueRange*.08,Math.abs(lowestDepotValue)*.002,1));
        const rightOffset=Math.max(7*24*60*60*1000,(chartMax-chartMin)*0.035);
        const yearlyData=[...depotData.reduce((years,point)=>{
          const year=new Date(point.x).getFullYear();
          const values=years.get(year)??[];
          values.push(point);
          years.set(year,values);
          return years;
        },new Map()).entries()];
        const yearBoundaries=yearlyData.slice(1).map(([year])=>({
          x:new Date(year,0,1).getTime(),
          borderColor:'rgba(148,163,184,.28)',
          strokeDashArray:5,
          borderWidth:1,
        }));
        const yearBadges=yearlyData.map(([year,points])=>{
          const startValue=Number(points[0]?.y)||0;
          const endValue=Number(points[points.length-1]?.y)||0;
          const change=startValue>0?((endValue-startValue)/startValue)*100:0;
          const positive=change>=0;
          const segmentStart=Math.max(chartMin,points[0].x);
          const segmentEnd=Math.min(chartMax,points[points.length-1].x);
          return {
            x:segmentStart+((segmentEnd-segmentStart)/2),
            borderColor:'transparent',
            borderWidth:0,
            label:{
              text:`${year} · ${positive?@js(__('Gewinn')):@js(__('Verlust'))} ${change>=0?'+':''}${change.toFixed(1)} %`,
              position:'top',
              orientation:'horizontal',
              offsetY:2,
              borderColor:positive?'rgba(34, 211, 238,.38)':'rgba(251,113,133,.38)',
              style:{background:positive?'rgba(8, 145, 178,.22)':'rgba(190,24,93,.2)',color:positive?'#a5f3fc':'#fecdd3',fontSize:'8px',fontWeight:800,padding:{left:5,right:5,top:2,bottom:2}},
            },
          };
        });
        new ApexCharts(chartNode,{chart:{type:'line',height:chartHeight,toolbar:{show:false},animations:{enabled:false},zoom:{enabled:false},background:'transparent'},series,colors:[isLightTheme?'#0e7490':'#22d3ee'],stroke:{show:true,width:1.65,curve:'smooth',lineCap:'round'},fill:{type:'gradient',gradient:{shade:isLightTheme?'light':'dark',type:'vertical',shadeIntensity:.12,gradientToColors:[isLightTheme?'rgba(14,116,144,0)':'rgba(34,211,238,0)'],inverseColors:false,opacityFrom:.12,opacityTo:0,stops:[0,100]}},markers:{size:0,hover:{sizeOffset:0}},dataLabels:{enabled:false},legend:{show:false},annotations:{xaxis:[...yearBoundaries,...yearBadges]},xaxis:{type:'datetime',min:chartMin,max:chartMax+rightOffset,labels:{show:true,datetimeUTC:false,format:'dd.MM.yy',style:{colors:'#7f93a8',fontSize:'8px'},hideOverlappingLabels:true},axisBorder:{show:true,color:isLightTheme?'rgba(14, 116, 144,.25)':'rgba(255,255,255,.18)'},axisTicks:{show:false},tooltip:{enabled:false}},yaxis:{show:true,min:chartValueMin,max:chartValueMax,forceNiceScale:false,decimalsInFloat:0,labels:{show:true,minWidth:34,style:{colors:'#7f93a8',fontSize:'8px'},formatter:value=>`${moneyLabel(value)} {{ $portfolio->currency }}`},axisBorder:{show:false}},grid:{borderColor:isLightTheme?'rgba(14, 116, 144,.12)':'rgba(255,255,255,.12)',padding:{top:20,bottom:0,left:2,right:10}},theme:{mode:isLightTheme?'light':'dark'},tooltip:{shared:false,intersect:true,custom:({seriesIndex,dataPointIndex,w})=>{
          const point=w.config.series[seriesIndex]?.data?.[dataPointIndex];
          if(!point?.trade){const value=Number(point?.y);const change=((value/baseValue)-1)*100;return `<div class="px-3 py-2 text-xs"><b>${escapeHtml(w.config.series[seriesIndex]?.name)}</b><div class="mt-1">${moneyLabel(value)} {{ $portfolio->currency }} · ${change>=0?'+':''}${change.toFixed(2)} %</div></div>`;}
          const trade=point.trade;const performance=Number(trade.performance);
          return `<div class="min-w-56 p-3 text-xs"><div class="font-black text-white">${escapeHtml(trade.name||trade.symbol)} <span class="text-orange-400">${escapeHtml(trade.symbol)}</span></div><div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-slate-300"><span>${escapeHtml(@json(__('Kaufdatum')))}</span><b>${dateLabel(trade.buy_date)}</b><span>${escapeHtml(@json(__('Verkaufsdatum')))}</span><b>${dateLabel(trade.sell_date)}</b><span>${escapeHtml(@json(__('Kaufkurs')))}</span><b>${trade.buy_price==null?'—':Number(trade.buy_price).toFixed(2)} {{ $portfolio->currency }}</b><span>${escapeHtml(@json(__('Verkaufskurs')))}</span><b>${Number(trade.sell_price).toFixed(2)} {{ $portfolio->currency }}</b><span>${escapeHtml(@json(__('Performance')))}</span><b style="color:${performance>=0?'#22d3ee':'#fb7185'}">${performance>=0?'+':''}${Number.isFinite(performance)?performance.toFixed(2):'—'} %</b><span>${escapeHtml(@json(__('Strategie')))}</span><b>${escapeHtml(trade.strategies.join(', ')||'—')}</b></div></div>`;
        }}}).render();
        const profitNode=document.querySelector('#portfolio-profit-bars');
        if(profitNode&&profitBars.length){
          const maxProfit=Math.max(...profitBars.map(point=>Math.abs(point.y)),1);
          profitBars.forEach(point=>{
            const bar=document.createElement('span');
            const position=((point.x-chartMin)/(chartMax+rightOffset-chartMin))*100;
            const height=Math.max(4,(Math.abs(point.y)/maxProfit)*42);
            bar.style.cssText=`position:absolute;left:${position}%;width:4px;height:${height}px;background:${point.y>=0?'#22d3ee':'#fb7185'};opacity:.62;border-radius:1px;${point.y>=0?'bottom:50%':'top:50%'}`;
            bar.title=`${point.trade.symbol} · ${point.y>=0?'+':''}${point.y.toFixed(2)} {{ $portfolio->currency }}`;
            profitNode.appendChild(bar);
          });
          const zero=document.createElement('span');zero.style.cssText='position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(148,163,184,.16)';profitNode.prepend(zero);
        }
      }
      @endif
    });
    </script>
    @endif
</x-app-layout>

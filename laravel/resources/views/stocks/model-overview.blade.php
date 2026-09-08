<x-app-layout>
    @php
        $signal = strtoupper((string) ($currentSignal?->signal ?: $release['recommended_signal']));
        $signalTone = match($signal) {
            'BUY' => 'border-emerald-400/40 bg-emerald-400/[.08] text-emerald-400',
            'SELL' => 'border-rose-400/40 bg-rose-400/[.08] text-rose-400',
            'WATCH', 'WAIT' => 'border-amber-400/40 bg-amber-400/[.08] text-amber-400',
            default => 'border-slate-400/30 bg-slate-400/[.05] text-slate-400',
        };
        $qualityTone = match($release['quality_class']) {
            'quality' => 'border-emerald-400/40 text-emerald-400',
            'solid' => 'border-cyan-400/40 text-cyan-400',
            'basic' => 'border-amber-400/40 text-amber-400',
            default => 'border-slate-400/30 text-slate-400',
        };
        $gateLabel = static fn (string $key): string => match($key) {
            'profit_factor_above_1_05' => 'Profitfaktor > 1,05',
            'hit_rate_at_least_66_7pct' => 'Hit-Rate ≥ 66,7 %',
            'positive_average_net_trade' => 'Ø Netto-Rendite > 0',
            'positive_cumulative_return' => 'Kumulierte Rendite > 0',
            'profit_factor_at_least_2_0' => 'Profitfaktor ≥ 2,0',
            'minimum_5_non_overlapping_oos_trades' => 'Mindestens 5 unabhängige OOS-Trades',
            'minimum_5_tcn_trades' => 'Mindestens 5 TCN-Trades',
            'tcn_drawdown_not_worse' => 'TCN-Drawdown nicht schlechter',
            'tcn_hit_rate_not_worse' => 'TCN-Hit-Rate nicht schlechter',
            'tcn_average_return_better' => 'TCN-Ø-Rendite besser',
            'positive_tcn_average_return' => 'Positive TCN-Ø-Rendite',
            'tcn_profit_factor_at_least_1_25' => 'TCN-Profitfaktor ≥ 1,25',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
        $statusLabel = static fn (string $status): string => match($status) {
            'eligible' => 'Freigegeben',
            'ineligible_performance' => 'Performance nicht ausreichend',
            'not_evaluated' => 'Nicht bewertet',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
        $policyLabel = static fn (string $policy): string => match($policy) {
            'minimum_predicted_return' => 'Mindest-Prognoserendite',
            'tabular_positive_and_tcn_confirmation' => 'Standard positiv + TCN-Bestätigung',
            default => $policy !== '' ? ucfirst(str_replace('_', ' ', $policy)) : '—',
        };
        $exitReasonLabel = static fn (string $reason): string => match($reason) {
            'MAX_HOLDING_DAYS' => 'Horizont erreicht',
            'TCN_SCORE_DROP' => 'TCN-Ausstiegsschwelle',
            default => ucfirst(strtolower(str_replace('_', ' ', $reason))),
        };
        $standardTradeDays = collect($tradeChart['availability'])
            ->filter(fn (array $variants): bool => in_array('standard', $variants, true))
            ->keys()->map(fn ($days): int => (int) $days)->implode(',');
        $tcnTradeDays = collect($tradeChart['availability'])
            ->filter(fn (array $variants): bool => in_array('pure_tcn', $variants, true))
            ->keys()->map(fn ($days): int => (int) $days)->implode(',');
    @endphp

    <main id="model-overview-page" class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container" x-data="{ horizon: {{ (int) $initialHorizon }} }">
            <nav class="mb-4 flex flex-wrap items-center gap-3 text-xs font-bold">
                <a href="{{ route('stocks.show', ['symbol' => $instrument->symbol]) }}" data-back-link class="text-[var(--ak-muted)] transition hover:text-cyan-400">← {{ __('Zurück') }}</a>
                <span class="text-[var(--ak-border)]">/</span>
                <a href="{{ route('screener.index') }}" class="text-[var(--ak-muted)] transition hover:text-cyan-400">{{ __('Aktienscreener') }}</a>
            </nav>

            @if(isset($errors) && $errors->has('model_configuration'))
                <div class="mb-4 rounded-xl border border-rose-400/30 bg-rose-400/[.08] px-4 py-3 text-xs font-bold text-rose-400" role="alert">{{ $errors->first('model_configuration') }}</div>
            @endif

            <header class="ak-card overflow-hidden border-cyan-400/30 p-5 sm:p-6">
                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-md border border-violet-400/40 bg-violet-400/[.08] px-2 py-1 text-[8px] font-black uppercase tracking-[.16em] text-violet-400">PRO</span>
                            <span class="rounded-md border border-cyan-400/35 bg-cyan-400/[.06] px-2 py-1 text-[8px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Service Datenbank') }}</span>
                        </div>
                        <p class="mt-4 text-[10px] font-black uppercase tracking-[.22em] text-cyan-500">{{ __('Modelltransparenz') }}</p>
                        <h1 class="mt-1 text-3xl font-black text-[var(--ak-text)] sm:text-4xl">{{ __('Modellübersicht') }}</h1>
                        <p class="mt-2 text-lg font-black text-[var(--ak-text)]">{{ $instrument->name ?: $instrument->symbol }} <span class="text-cyan-500">{{ $instrument->symbol }}</span></p>
                        <p class="mt-2 text-xs text-[var(--ak-muted)]">{{ $instrument->country_code }} · {{ $instrument->exchange }} · {{ $instrument->currency }}@if($instrument->sector_code) · {{ __($instrument->sector_code) }}@endif</p>
                    </div>
                    <div class="flex flex-wrap gap-2 xl:max-w-md xl:justify-end">
                        <span class="rounded-lg border px-3 py-2 text-xs font-black uppercase {{ $signalTone }}">{{ $signal }}</span>
                        <span class="rounded-lg border px-3 py-2 text-xs font-black uppercase {{ $qualityTone }}">{{ $release['quality_class'] }}</span>
                        @if($currentSignal?->buy_rating)<span class="rounded-lg border border-cyan-400/30 px-3 py-2 text-xs font-black text-cyan-400">Rating {{ $currentSignal->buy_rating }}</span>@endif
                        @if($currentSignal?->has_quality_gate_buy)<span class="rounded-lg border border-emerald-400/35 bg-emerald-400/[.06] px-3 py-2 text-xs font-black text-emerald-400">Quality Gate</span>@endif
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        [__('Horizonte'), $horizons->count(), __('vollständig veröffentlicht'), 'text-cyan-500'],
                        [__('Prediction aktiv'), $predictionEnabledCount, __('von :count Horizonten', ['count' => $horizons->count()]), 'text-emerald-400'],
                        [__('Quality Gate'), $qualityGateCount, __('aktive Varianten bestanden'), 'text-amber-400'],
                        [__('Datenstand'), \Illuminate\Support\Carbon::parse($release['dataset_cutoff'])->format('d.m.Y'), $release['pipeline_version'], 'text-violet-400'],
                    ] as [$summaryLabel, $summaryValue, $summaryNote, $summaryTone])
                        <div class="rounded-xl border border-[var(--ak-border)] bg-cyan-400/[.025] p-3">
                            <small class="text-[8px] font-black uppercase tracking-[.14em] text-[var(--ak-muted)]">{{ $summaryLabel }}</small>
                            <b class="mt-1 block text-xl font-black {{ $summaryTone }}">{{ $summaryValue }}</b>
                            <span class="mt-1 block truncate text-[8px] text-[var(--ak-muted)]">{{ $summaryNote }}</span>
                        </div>
                    @endforeach
                </div>
            </header>

            <div class="mt-4 grid items-stretch gap-4 xl:grid-cols-2">
                <section class="ak-card border-cyan-400/25 p-4 sm:p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Dreijahres-Backtest · OOS · nicht überlappend') }}</p>
                            <h2 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('Kumulierte Rendite nach Modell und Horizont') }}</h2>
                        </div>
                        <div class="flex gap-3 text-[9px] font-bold text-[var(--ak-muted)]"><span><i class="mr-1 inline-block h-2 w-2 rounded-full bg-cyan-400"></i>Standard</span><span><i class="mr-1 inline-block h-2 w-2 rounded-full bg-violet-400"></i>Pure TCN</span></div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <div class="min-w-[560px] space-y-2">
                            <div class="grid grid-cols-[90px_minmax(0,1fr)_90px] items-center gap-3 text-[8px] font-black text-[var(--ak-muted)]">
                                <span>{{ __('Modell') }}</span><span class="grid grid-cols-2"><i class="pr-2 text-right not-italic">−{{ number_format($chart['scale'], 0, ',', '.') }} %</i><i class="pl-2 not-italic">+{{ number_format($chart['scale'], 0, ',', '.') }} %</i></span><span class="text-right">{{ __('Ergebnis · DD') }}</span>
                            </div>
                            @forelse($chart['rows'] as $row)
                                @php
                                    $value = (float) $row['return_percent'];
                                    $positive = $value >= 0;
                                    $barTone = $positive
                                        ? ($row['variant'] === 'pure_tcn' ? 'bg-violet-400' : 'bg-cyan-400')
                                        : 'bg-rose-400';
                                @endphp
                                <div class="grid grid-cols-[90px_minmax(0,1fr)_90px] items-center gap-3">
                                    <span class="truncate text-[10px] font-black text-[var(--ak-text)]">{{ $row['days'] }}T · {{ $row['label'] }}@if($row['selected']) <i class="text-emerald-400 not-italic">●</i>@endif</span>
                                    <div class="grid h-6 grid-cols-2 overflow-hidden rounded-md bg-slate-500/[.06]">
                                        <span class="flex items-center justify-end border-r border-cyan-400/20">@if(!$positive)<i class="block h-3 rounded-l-sm {{ $barTone }}" style="width: {{ number_format($row['bar_percent'], 2, '.', '') }}%"></i>@endif</span>
                                        <span class="flex items-center">@if($positive)<i class="block h-3 rounded-r-sm {{ $barTone }}" style="width: {{ number_format($row['bar_percent'], 2, '.', '') }}%"></i>@endif</span>
                                    </div>
                                    <span class="text-right text-[10px] font-black tabular-nums {{ $positive ? 'text-emerald-400' : 'text-rose-400' }}">{{ sprintf('%+.2f %%', $value) }}<small class="block text-[7px] font-bold text-[var(--ak-muted)]">DD {{ is_numeric($row['drawdown_percent']) ? number_format($row['drawdown_percent'], 1, ',', '.').' %' : '—' }}</small></span>
                                </div>
                            @empty
                                <p class="py-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Keine veröffentlichten Backtest-Kennzahlen vorhanden.') }}</p>
                            @endforelse
                        </div>
                    </div>
                    <p class="mt-4 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Gesamtergebnis der im aktiven Release veröffentlichten, nicht überlappenden OOS-Trades.') }}</p>
                </section>

                <section
                    class="ak-card border-violet-400/25 p-4 sm:p-5"
                    x-data="{ tradeHorizon: {{ (int) $tradeChart['initial']['days'] }}, tradeVariant: '{{ $tradeChart['initial']['variant'] }}' }"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-violet-400">{{ __('Reale Kauf- und Verkaufspunkte') }}</p>
                            <h2 class="mt-1 text-xl font-black text-[var(--ak-text)]">{{ __('OOS-Tradeverlauf') }}</h2>
                            <p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ __('Kumuliertes Backtestkapital · netto nach Kosten') }}</p>
                        </div>
                        <div class="flex gap-3 text-[8px] font-bold text-[var(--ak-muted)]">
                            <span><i class="mr-1 inline-block h-2 w-2 rotate-45 bg-emerald-400"></i>{{ __('Kauf') }}</span>
                            <span><i class="mr-1 inline-block h-2 w-2 rotate-45 bg-rose-400"></i>{{ __('Verkauf') }}</span>
                        </div>
                    </div>

                    @if(empty($tradeChart['series']))
                        <div class="mt-5 rounded-xl border border-[var(--ak-border)] px-4 py-12 text-center text-sm text-[var(--ak-muted)]">{{ __('Für dieses Release sind noch keine geprüften Einzeltrades veröffentlicht.') }}</div>
                    @else
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($horizons as $horizon)
                                    @php
                                        $days = (int) $horizon['days'];
                                        $availableVariants = $tradeChart['availability'][$days] ?? [];
                                        $hasStandard = in_array('standard', $availableVariants, true);
                                        $hasTcn = in_array('pure_tcn', $availableVariants, true);
                                    @endphp
                                    <button
                                        type="button"
                                        @if($availableVariants) @click="tradeHorizon = {{ $days }}; @if(!$hasStandard && $hasTcn) tradeVariant = 'pure_tcn' @elseif($hasStandard && !$hasTcn) tradeVariant = 'standard' @endif" @endif
                                        @disabled(!$availableVariants)
                                        class="rounded-md border px-2.5 py-1.5 text-[9px] font-black transition disabled:cursor-not-allowed disabled:opacity-30"
                                        :class="tradeHorizon === {{ $days }} ? 'border-cyan-400/55 bg-cyan-400/[.1] text-cyan-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)]'"
                                    >{{ $days }}T</button>
                                @endforeach
                            </div>
                            <div class="flex gap-1.5">
                                <button type="button" @click="tradeVariant = 'standard'" :disabled="![{{ $standardTradeDays }}].includes(tradeHorizon)" class="rounded-md border px-2.5 py-1.5 text-[9px] font-black transition disabled:cursor-not-allowed disabled:opacity-30" :class="tradeVariant === 'standard' ? 'border-cyan-400/55 bg-cyan-400/[.1] text-cyan-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)]'">Standard</button>
                                <button type="button" @click="tradeVariant = 'pure_tcn'" :disabled="![{{ $tcnTradeDays }}].includes(tradeHorizon)" class="rounded-md border px-2.5 py-1.5 text-[9px] font-black transition disabled:cursor-not-allowed disabled:opacity-30" :class="tradeVariant === 'pure_tcn' ? 'border-violet-400/55 bg-violet-400/[.1] text-violet-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)]'">Pure TCN</button>
                            </div>
                        </div>

                        @foreach($tradeChart['series'] as $days => $variants)
                            @foreach($variants as $variantKey => $series)
                                @php
                                    $lineTone = $variantKey === 'pure_tcn' ? '#a78bfa' : '#22d3ee';
                                    $summary = $series['summary'];
                                @endphp
                                <div x-cloak x-show="tradeHorizon === {{ (int) $days }} && tradeVariant === '{{ $variantKey }}'" x-transition.opacity class="mt-4">
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach([
                                            [__('Trades'), number_format($summary['trades'], 0, ',', '.')],
                                            [__('Hit-Rate'), number_format($summary['hit_rate_percent'], 1, ',', '.').' %'],
                                            [__('Gesamtrendite'), sprintf('%+.2f %%', $summary['cumulative_return_percent'])],
                                            [__('Max. Drawdown'), number_format($summary['max_drawdown_percent'], 1, ',', '.').' %'],
                                        ] as [$tradeMetricLabel, $tradeMetricValue])
                                            <div class="rounded-lg border border-[var(--ak-border)] bg-slate-500/[.035] p-2"><small class="block text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $tradeMetricLabel }}</small><b class="mt-1 block text-xs tabular-nums text-[var(--ak-text)]">{{ $tradeMetricValue }}</b></div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[#071423]/70 p-2">
                                        <svg viewBox="0 0 800 255" role="img" aria-label="{{ $series['days'] }}-Tage-Backtest {{ $series['label'] }} mit Kauf- und Verkaufspunkten" class="h-auto w-full">
                                            @foreach($series['grid'] as $gridLine)
                                                <line x1="54" x2="782" y1="{{ number_format($gridLine['y'], 2, '.', '') }}" y2="{{ number_format($gridLine['y'], 2, '.', '') }}" stroke="rgba(148,163,184,.13)" stroke-width="1" />
                                                <text x="47" y="{{ number_format($gridLine['y'] + 3, 2, '.', '') }}" text-anchor="end" font-size="8" fill="#94a3b8">{{ sprintf('%+.0f%%', $gridLine['value']) }}</text>
                                            @endforeach
                                            <line x1="54" x2="782" y1="{{ number_format($series['zero_y'], 2, '.', '') }}" y2="{{ number_format($series['zero_y'], 2, '.', '') }}" stroke="rgba(34,211,238,.24)" stroke-width="1" stroke-dasharray="4 5" />
                                            <polyline points="{{ $series['line_points'] }}" fill="none" stroke="{{ $lineTone }}" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />
                                            @foreach($series['points'] as $point)
                                                @php
                                                    $x = number_format($point['x'], 2, '.', '');
                                                    $y = number_format($point['y'], 2, '.', '');
                                                    $tooltip = ($point['action'] === 'buy' ? __('Kauf') : __('Verkauf')).' · '.\Illuminate\Support\Carbon::parse($point['date'])->format('d.m.Y').' · '.number_format($point['price'], 2, ',', '.').' '.$instrument->currency;
                                                @endphp
                                                @if($point['action'] === 'buy')
                                                    <path d="M {{ $x }} {{ number_format($point['y'] - 5, 2, '.', '') }} L {{ number_format($point['x'] - 5, 2, '.', '') }} {{ number_format($point['y'] + 4, 2, '.', '') }} L {{ number_format($point['x'] + 5, 2, '.', '') }} {{ number_format($point['y'] + 4, 2, '.', '') }} Z" fill="#34d399" stroke="#071423" stroke-width="1.5"><title>{{ $tooltip }}</title></path>
                                                @else
                                                    <path d="M {{ $x }} {{ number_format($point['y'] + 5, 2, '.', '') }} L {{ number_format($point['x'] - 5, 2, '.', '') }} {{ number_format($point['y'] - 4, 2, '.', '') }} L {{ number_format($point['x'] + 5, 2, '.', '') }} {{ number_format($point['y'] - 4, 2, '.', '') }} Z" fill="#fb7185" stroke="#071423" stroke-width="1.5"><title>{{ $tooltip }} · {{ sprintf('%+.2f %%', $point['net_return_percent']) }}</title></path>
                                                @endif
                                            @endforeach
                                            @foreach($series['date_labels'] as $dateLabel)
                                                <text x="{{ number_format($dateLabel['x'], 2, '.', '') }}" y="242" text-anchor="{{ $loop->first ? 'start' : ($loop->last ? 'end' : 'middle') }}" font-size="8" fill="#94a3b8">{{ $dateLabel['label'] }}</text>
                                            @endforeach
                                        </svg>
                                    </div>

                                    <div class="mt-3 overflow-x-auto">
                                        <table class="w-full min-w-[560px] table-fixed text-left">
                                            <thead><tr class="text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><th class="pb-2">{{ __('Kauf') }}</th><th class="pb-2">{{ __('Verkauf') }}</th><th class="pb-2 text-right">{{ __('Haltedauer') }}</th><th class="pb-2 text-right">{{ __('Kosten') }}</th><th class="pb-2 text-right">{{ __('Netto') }}</th></tr></thead>
                                            <tbody class="divide-y divide-[var(--ak-border)]">
                                                @foreach($series['latest_trades'] as $trade)
                                                    <tr class="text-[8px] text-[var(--ak-text)]">
                                                        <td class="py-2"><b class="block text-emerald-400">{{ \Illuminate\Support\Carbon::parse($trade['entry_date'])->format('d.m.Y') }}</b><span class="tabular-nums text-[var(--ak-muted)]">{{ number_format($trade['entry_price'], 2, ',', '.') }} {{ $instrument->currency }}</span></td>
                                                        <td class="py-2"><b class="block text-rose-400">{{ \Illuminate\Support\Carbon::parse($trade['exit_date'])->format('d.m.Y') }}</b><span class="tabular-nums text-[var(--ak-muted)]">{{ number_format($trade['exit_price'], 2, ',', '.') }} {{ $instrument->currency }} · {{ $exitReasonLabel($trade['exit_reason']) }}</span></td>
                                                        <td class="py-2 text-right font-bold tabular-nums">{{ $trade['holding_days'] }} T</td>
                                                        <td class="py-2 text-right tabular-nums text-[var(--ak-muted)]">{{ number_format($trade['cost_percent'], 2, ',', '.') }} %</td>
                                                        <td class="py-2 text-right font-black tabular-nums {{ $trade['net_return_percent'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ sprintf('%+.2f %%', $trade['net_return_percent']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    @endif

                    <p class="mt-4 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Gezeigt werden nur Serving-Einzeltrades, deren Trades, Hit-Rate, Rendite und Drawdown mit dem aktiven Release übereinstimmen. Tägliche Kurszeilen werden nicht gespeichert.') }}</p>
                </section>
            </div>

            <section class="ak-card mt-4 overflow-hidden border-violet-400/25">
                <div class="border-b border-[var(--ak-border)] p-4 sm:p-5">
                    <p class="text-[9px] font-black uppercase tracking-[.16em] text-violet-400">{{ __('Horizontdetails') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($horizons as $horizon)
                            <button type="button" @click="horizon = {{ $horizon['days'] }}" class="rounded-lg border px-4 py-2 text-xs font-black transition" :class="horizon === {{ $horizon['days'] }} ? 'border-cyan-400/60 bg-cyan-400/[.12] text-cyan-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)] hover:border-cyan-400/30'">
                                {{ $horizon['days'] }}T
                                <i class="ml-1 inline-block h-1.5 w-1.5 rounded-full {{ $horizon['active_prediction_enabled'] ? 'bg-emerald-400' : 'bg-rose-400' }}"></i>
                            </button>
                        @endforeach
                    </div>
                </div>

                @foreach($horizons as $horizon)
                    <div x-cloak x-show="horizon === {{ $horizon['days'] }}" x-transition.opacity class="p-4 sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><h2 class="text-xl font-black text-[var(--ak-text)]">{{ $horizon['days'] }} {{ __('Handelstage') }}</h2><p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ __('Aktiv') }}: <b class="text-cyan-400">{{ $horizon['active_variant'] === 'pure_tcn' ? 'Pure TCN' : 'Standard' }}</b> · {{ $statusLabel($horizon['active_prediction_status']) }}</p></div>
                            <div class="flex flex-wrap gap-2"><span class="rounded-md border border-violet-400/30 px-2 py-1 text-[9px] font-black text-violet-400">{{ __('Vergleichssieger') }}: {{ $horizon['preferred_variant'] === 'pure_tcn' ? 'Pure TCN' : 'Standard' }}</span><span class="rounded-md border px-2 py-1 text-[9px] font-black {{ $horizon['active_prediction_enabled'] ? 'border-emerald-400/35 text-emerald-400' : 'border-rose-400/35 text-rose-400' }}">{{ $horizon['active_prediction_enabled'] ? __('Prediction aktiv') : __('Prediction gesperrt') }}</span></div>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-2">
                            @foreach($horizon['variants'] as $variant)
                                @php
                                    $metrics = $variant['metrics'];
                                    $variantTone = $variant['key'] === 'pure_tcn' ? 'text-violet-400' : 'text-cyan-400';
                                    $variantBorder = $variant['key'] === 'pure_tcn' ? 'border-violet-400/25' : 'border-cyan-400/25';
                                    $prediction = $variant['prediction'];
                                    $configurationKey = $release['id'].':'.$horizon['days'].':'.$variant['key'];
                                    $configurationSaved = in_array($configurationKey, $personalModelConfigurationKeys ?? [], true);
                                @endphp
                                <article class="rounded-2xl border {{ $variantBorder }} bg-cyan-400/[.018] p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div><p class="text-[9px] font-black uppercase tracking-[.14em] {{ $variantTone }}">{{ $variant['label'] }}</p><h3 class="mt-1 text-lg font-black text-[var(--ak-text)]">{{ $variant['model_name'] }}</h3><p class="mt-1 text-[8px] text-[var(--ak-muted)]">{{ $variant['quality_label'] }} · {{ $statusLabel($variant['prediction_status']) }}</p></div>
                                        <div class="flex flex-col items-end gap-1">@if($variant['selected'])<span class="rounded-md border border-emerald-400/35 bg-emerald-400/[.06] px-2 py-1 text-[8px] font-black text-emerald-400">CHAMPION</span>@else<span class="rounded-md border border-slate-400/25 px-2 py-1 text-[8px] font-black text-slate-400">CHALLENGER</span>@endif<span class="rounded-md border px-2 py-1 text-[8px] font-black {{ $variant['quality_gate_passed'] ? 'border-amber-400/40 text-amber-400' : 'border-slate-400/25 text-slate-400' }}">{{ $variant['quality_gate_passed'] ? 'QUALITY GATE' : 'STANDARD' }}</span></div>
                                    </div>

                                    <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach([
                                            [__('OOS-Trades'), $metrics['trades'] !== null ? number_format($metrics['trades'], 0, ',', '.') : '—', ''],
                                            [__('Hit-Rate'), $metrics['hit_rate'] !== null ? number_format($metrics['hit_rate'] * 100, 1, ',', '.').' %' : '—', ''],
                                            [__('Profitfaktor'), $metrics['profit_factor'] !== null ? number_format($metrics['profit_factor'], 2, ',', '.') : '—', ''],
                                            [__('Ø Netto/Trade'), $metrics['average_net_trade'] !== null ? sprintf('%+.2f %%', $metrics['average_net_trade'] * 100) : '—', ($metrics['average_net_trade'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'],
                                            [__('Median/Trade'), $metrics['median_net_trade'] !== null ? sprintf('%+.2f %%', $metrics['median_net_trade'] * 100) : '—', ($metrics['median_net_trade'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'],
                                            [__('Gesamtrendite'), $metrics['cumulative_return'] !== null ? sprintf('%+.2f %%', $metrics['cumulative_return'] * 100) : '—', ($metrics['cumulative_return'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'],
                                            [__('Max. Drawdown'), $metrics['max_drawdown'] !== null ? number_format(abs($metrics['max_drawdown'] * 100), 2, ',', '.').' %' : '—', 'text-rose-400'],
                                            [__('Streuung/Trade'), $metrics['stddev_net_trade'] !== null ? number_format($metrics['stddev_net_trade'] * 100, 2, ',', '.').' %' : '—', ''],
                                            [__('Ø Haltedauer'), $metrics['average_holding_days'] !== null ? number_format($metrics['average_holding_days'], 1, ',', '.').' T' : '—', ''],
                                        ] as [$metricLabel, $metricValue, $metricTone])
                                            <div class="rounded-lg border border-[var(--ak-border)] p-2"><dt class="text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $metricLabel }}</dt><dd class="mt-1 text-xs font-black tabular-nums {{ $metricTone }}">{{ $metricValue }}</dd></div>
                                        @endforeach
                                    </dl>

                                    <div class="mt-4 rounded-xl border border-[var(--ak-border)] p-3">
                                        <div class="flex items-center justify-between gap-3"><b class="text-[10px] text-[var(--ak-text)]">{{ __('Einstiegslogik') }}</b><span class="text-[9px] font-black {{ $variant['prediction_enabled'] ? 'text-emerald-400' : 'text-rose-400' }}">{{ $variant['prediction_enabled'] ? __('freigegeben') : __('gesperrt') }}</span></div>
                                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[8px]"><dt class="text-[var(--ak-muted)]">{{ __('Verfahren') }}</dt><dd class="text-right font-bold">{{ $policyLabel((string) ($variant['entry_policy']['type'] ?? '')) }}</dd><dt class="text-[var(--ak-muted)]">{{ __('Schwelle') }}</dt><dd class="text-right font-bold">{{ $variant['threshold'] !== null ? number_format($variant['threshold'] * 100, 3, ',', '.').' %' : '—' }}</dd><dt class="text-[var(--ak-muted)]">{{ __('Bei Ablehnung') }}</dt><dd class="text-right font-bold">{{ $variant['entry_policy']['on_reject'] ?? '—' }}</dd><dt class="text-[var(--ak-muted)]">{{ __('Offene Position') }}</dt><dd class="text-right font-bold">{{ ($variant['entry_policy']['preserve_open_position_until_horizon'] ?? false) ? __('bis Horizont halten') : '—' }}</dd></dl>
                                    </div>

                                    @if($prediction)
                                        <div class="mt-3 rounded-xl border border-violet-400/20 bg-violet-400/[.035] p-3"><div class="flex items-center justify-between"><b class="text-[9px] uppercase text-violet-400">{{ __('Letzte Prediction') }}</b><span class="text-[8px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($prediction['as_of'])->format('d.m.Y H:i') }}</span></div><div class="mt-2 grid grid-cols-4 gap-2 text-center"><span><small class="block text-[7px] text-[var(--ak-muted)]">{{ __('Signal') }}</small><b class="text-xs">{{ $prediction['signal'] }}</b></span><span><small class="block text-[7px] text-[var(--ak-muted)]">{{ __('Rendite') }}</small><b class="text-xs {{ ($prediction['expected_return_percent'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $prediction['expected_return_percent'] !== null ? sprintf('%+.2f %%', $prediction['expected_return_percent']) : '—' }}</b></span><span><small class="block text-[7px] text-[var(--ak-muted)]">{{ __('Konf.') }}</small><b class="text-xs">{{ $prediction['confidence_percent'] !== null ? number_format($prediction['confidence_percent'], 1, ',', '.').' %' : '—' }}</b></span><span><small class="block text-[7px] text-[var(--ak-muted)]">{{ __('Ziel') }}</small><b class="text-xs">{{ $prediction['target_price'] !== null ? number_format($prediction['target_price'], 2, ',', '.') : '—' }}</b></span></div></div>
                                    @endif

                                    <div class="mt-3"><p class="text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Prüfkriterien') }}</p><div class="mt-2 flex flex-wrap gap-1.5">@forelse($variant['quality_gates'] as $gate => $passed)<span class="rounded-md border px-2 py-1 text-[7px] font-black {{ $passed ? 'border-emerald-400/30 text-emerald-400' : 'border-rose-400/30 text-rose-400' }}">{{ $passed ? '✓' : '×' }} {{ $gateLabel((string) $gate) }}</span>@empty<span class="text-[8px] text-[var(--ak-muted)]">{{ __('Keine Quality-Gate-Prüfung veröffentlicht.') }}</span>@endforelse</div></div>

                                    <details class="mt-3 rounded-xl border border-[var(--ak-border)] p-3"><summary class="cursor-pointer text-[9px] font-black text-[var(--ak-text)]">{{ __('Modellartefakte') }} ({{ count($variant['artifacts']) }})</summary><div class="mt-2 space-y-2">@forelse($variant['artifacts'] as $artifact)<div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 text-[8px]"><span class="min-w-0"><b class="block truncate text-[var(--ak-text)]">{{ $artifact['name'] }}</b><small class="text-[var(--ak-muted)]">{{ $artifact['kind'] }} · {{ $artifact['format'] ?: '—' }} · SHA {{ $artifact['sha256'] !== '' ? substr($artifact['sha256'], 0, 12) : '—' }}</small></span><b class="text-[var(--ak-muted)]">{{ $artifact['size'] ?? '—' }}</b></div>@empty<p class="text-[8px] text-[var(--ak-muted)]">{{ __('Kein Artefakt im Manifest.') }}</p>@endforelse</div></details>

                                    <div class="mt-3 rounded-xl border border-teal-300/25 bg-teal-400/[.055] p-3">
                                        <p class="text-[9px] font-black text-[var(--ak-text)]">{{ __('Du möchtest diese Konfiguration deiner persönlichen Strategie hinzufügen?') }}</p>
                                        <p class="mt-1 text-[8px] leading-4 text-[var(--ak-muted)]">{{ $horizon['days'] }}T · {{ $variant['label'] }} · {{ $variant['model_name'] }}</p>
                                        @if($configurationSaved)
                                            <a href="{{ route('setup.saved-filters.index') }}" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-400/35 bg-emerald-400/[.08] px-3 py-2.5 text-[9px] font-black text-emerald-400 transition hover:bg-emerald-400/[.13]"><x-heroicon-o-check-circle class="h-4 w-4" />{{ __('In persönlicher Strategie gespeichert') }}</a>
                                        @else
                                            <form method="POST" action="{{ route('stocks.models.strategy.store', ['symbol' => $instrument->symbol]) }}" class="mt-3">
                                                @csrf
                                                <input type="hidden" name="release_id" value="{{ $release['id'] }}">
                                                <input type="hidden" name="horizon" value="{{ $horizon['days'] }}">
                                                <input type="hidden" name="variant" value="{{ $variant['key'] }}">
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-teal-300/35 bg-teal-400/[.11] px-3 py-2.5 text-[9px] font-black text-teal-300 transition hover:border-teal-300/55 hover:bg-teal-400/[.18]"><x-heroicon-o-bookmark-square class="h-4 w-4" />{{ __('Konfiguration hinzufügen') }} <span aria-hidden="true">→</span></button>
                                            </form>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if($horizon['recommendation_gates'])
                            <div class="mt-4 rounded-xl border border-amber-400/20 bg-amber-400/[.025] p-3"><p class="text-[8px] font-black uppercase tracking-wide text-amber-400">{{ __('TCN-vs.-Standard-Entscheidung') }}</p><div class="mt-2 flex flex-wrap gap-1.5">@foreach($horizon['recommendation_gates'] as $gate => $passed)<span class="rounded-md border px-2 py-1 text-[7px] font-black {{ $passed ? 'border-emerald-400/30 text-emerald-400' : 'border-rose-400/30 text-rose-400' }}">{{ $passed ? '✓' : '×' }} {{ $gateLabel((string) $gate) }}</span>@endforeach</div></div>
                        @endif
                    </div>
                @endforeach
            </section>

            <section class="ak-card mt-4 border-cyan-400/20 p-4 sm:p-5">
                <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Release-Nachweis') }}</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div><small class="block text-[8px] uppercase text-[var(--ak-muted)]">Release-ID</small><b class="mt-1 block truncate font-mono text-[9px] text-[var(--ak-text)]" title="{{ $release['id'] }}">{{ $release['id'] }}</b></div>
                    <div><small class="block text-[8px] uppercase text-[var(--ak-muted)]">Source Commit</small><b class="mt-1 block font-mono text-[9px] text-[var(--ak-text)]">{{ substr($release['source_commit'], 0, 12) }}</b></div>
                    <div><small class="block text-[8px] uppercase text-[var(--ak-muted)]">{{ __('Aktiviert') }}</small><b class="mt-1 block text-[9px] text-[var(--ak-text)]">{{ \Illuminate\Support\Carbon::parse($release['activated_at'])->format('d.m.Y H:i') }}</b></div>
                    <div><small class="block text-[8px] uppercase text-[var(--ak-muted)]">{{ __('Evidenz') }}</small><b class="mt-1 block text-[9px] text-[var(--ak-text)]">{{ $release['evidence_level'] }}</b></div>
                </div>
                @if($release['tracking_comment'])<p class="mt-4 border-t border-[var(--ak-border)] pt-3 text-[9px] leading-5 text-[var(--ak-muted)]">{{ $release['tracking_comment'] }}</p>@endif
            </section>

            <p class="mt-4 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Historische Backtests und Modellprognosen sind keine Garantie für zukünftige Ergebnisse und stellen keine Anlageberatung dar.') }}</p>
        </div>
    </main>
</x-app-layout>

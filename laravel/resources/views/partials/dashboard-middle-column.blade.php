                <section id="dashboard-center-combined-card" data-help-card="center-combined" class="dashboard-center-combined-card ak-card min-h-0 overflow-hidden rounded-xl border-cyan-400/35">
                <article id="dashboard-newscenter-card" data-mobile-dashboard-card="champion" class="dashboard-newscenter flex min-h-0 flex-col p-4" aria-labelledby="dashboard-champion-title">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-amber-400/45 bg-amber-400/10 text-amber-400 shadow-[0_0_18px_rgba(251,191,36,.13)]"><x-heroicon-o-trophy class="h-7 w-7" /><span class="absolute -right-1 -top-1 rounded-full border border-amber-300/40 bg-[var(--ak-panel)] px-1 text-[8px] font-black text-amber-400">#1</span></span>
                            <span class="min-w-0"><span class="block text-[10px] font-black uppercase tracking-[.16em] text-amber-500">{{ __('Champion') }}</span><span id="dashboard-champion-title" class="mt-1 block truncate text-lg font-black text-[var(--ak-text)]">{{ __('Drei-Faktoren-Champion') }}</span></span>
                        </span>
                    </div>
                    <div class="grid min-h-0 flex-1 gap-1.5 overflow-hidden">
                        @forelse($topRankedStocks as $rankIndex => $rankedStock)
                            @php
                                $rank = $rankIndex + 1;
                                $rankFlag = $dashboardCountryFlags[strtoupper((string) ($rankedStock->country ?? ''))] ?? '🌐';
                                $rankScore = is_numeric($rankedStock->dashboard_ranking_score ?? null)
                                    ? (float) $rankedStock->dashboard_ranking_score
                                    : \App\Support\AiScore::toPercent(is_numeric($rankedStock->ai_score) ? $rankedStock->ai_score : $rankedStock->prediction_score);
                                $rankReturn = is_numeric($rankedStock->current_price) && (float) $rankedStock->current_price !== 0.0 && is_numeric($rankedStock->predicted_price_20d)
                                    ? (((float) $rankedStock->predicted_price_20d / (float) $rankedStock->current_price) - 1) * 100
                                    : (is_numeric($rankedStock->market_return_20d ?? null) ? (float) $rankedStock->market_return_20d : null);
                                $rankSignal = strtoupper((string) ($rankedStock->personalized_signal ?: 'HOLD'));
                                $rankSignalTone = match($rankSignal) { 'BUY' => 'text-emerald-400 border-emerald-400/30', 'WATCH' => 'text-lime-400 border-lime-400/30', 'SELL' => 'text-rose-400 border-rose-400/30', 'WAIT' => 'text-orange-400 border-orange-400/30', default => 'text-amber-400 border-amber-400/30' };
                                $rankQualityPercent = is_numeric($rankedStock->three_factor_score ?? null) ? (float) $rankedStock->three_factor_score : 0;
                                $rankQualityGrade = number_format($rankQualityPercent, 0, ',', '.');
                                $rankQualityLevel = max(0, min(5, (int) ceil($rankQualityPercent / 20)));
                                $rankCurrency = strtoupper((string) ($rankedStock->currency ?: 'EUR'));
                                $rankCurrencyLabel = match($rankCurrency) { 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'JPY' => '¥', default => $rankCurrency };
                                $rankDailyChange = is_numeric($rankedStock->daily_change_percent ?? null) ? (float) $rankedStock->daily_change_percent : null;
                                $rankReason = __('Gesamtscore :average (wie im Screener), unterstützt durch POSITIV :buy %, extern :external % und Panel :panel % (Dezil :decile)', [
                                    'average' => number_format((float) ($rankedStock->three_factor_score ?? 0), 1, ',', '.'),
                                    'buy' => number_format((float) ($rankedStock->three_factor_buy_score ?? 0), 0, ',', '.'),
                                    'external' => number_format((float) ($rankedStock->three_factor_external_score ?? 0), 0, ',', '.'),
                                    'panel' => number_format((float) ($rankedStock->three_factor_panel_score ?? 0), 1, ',', '.'),
                                    'decile' => (int) ($rankedStock->panel_decile ?? 0),
                                ]);
                                // All three inputs the 81%-average is made of, shown
                                // individually next to the donut instead of just one
                                // of them (panel) - so nothing looks re-counted that
                                // is already folded into the score above.
                                $rankExternalConfirmed = (bool) ($rankedStock->three_factor_external_confirmed ?? false);
                            @endphp
                            @if($rank === 1)
                                <div class="dashboard-champion-entry group grid min-h-0 grid-cols-[minmax(0,1fr)_auto] grid-rows-[auto_auto] content-center items-center gap-3 overflow-y-auto rounded-xl border border-amber-400/35 px-4 py-3 transition hover:border-amber-400/60">
                                    <a href="{{ route('stocks.show', ['symbol' => $rankedStock->symbol, 'return_to' => '/dashboard']) }}" class="col-span-2 grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                                        <span class="min-w-0"><b class="flex min-w-0 items-center gap-2"><span class="truncate text-sm font-black text-[var(--ak-text)]">{{ $rankFlag }} {{ $rankedStock->name ?: $rankedStock->symbol }}</span>@if($rankedStock->external_review_ranking_downgraded ?? false)<span class="shrink-0 rounded-md border border-rose-400/35 bg-rose-400/10 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide text-rose-400" title="{{ __('Externer KI-Widerspruch: Ranking um einen Punkt reduziert') }}">KI −1</span>@endif</b><small class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ $rankedStock->symbol }}</span><span class="{{ $rankSignalTone }}">{{ signal_label($rankSignal) }}</span><span>{{ ($rankedStock->display_price_live ?? false) ? __('Livekurs') : __('Letzter Kurs') }} {{ is_numeric($rankedStock->display_price ?? null) ? number_format((float) $rankedStock->display_price, 2, ',', '.').' '.$rankCurrencyLabel : '—' }}</span><span class="{{ $rankDailyChange === null ? '' : ($rankDailyChange >= 0 ? 'text-emerald-400' : 'text-rose-400') }}">{{ __('Tag') }} {{ $rankDailyChange !== null ? sprintf('%+.2f%%', $rankDailyChange) : '—' }}</span></small></span>
                                        <span class="flex items-center gap-2"><span class="dashboard-champion-donut"><x-segmented-score-donut :score="$rankQualityPercent" :display="$rankQualityGrade" :level="$rankQualityLevel" type="chance" :label="__('Drei-Faktoren-Mittel')" /></span><span class="dashboard-champion-factors grid h-[66px] min-w-[76px] grid-rows-3 items-center gap-0.5 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-1" title="{{ $rankReason }}"><span class="flex items-center justify-between gap-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Positiv') }}<b class="text-emerald-400">{{ number_format((float) ($rankedStock->three_factor_buy_score ?? 0), 0, ',', '.') }}%</b></span><span class="flex items-center justify-between gap-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Extern') }}<b class="text-cyan-400">{{ $rankExternalConfirmed ? number_format((float) ($rankedStock->three_factor_external_score ?? 0), 0, ',', '.').'%' : '—' }}</b></span><span class="flex items-center justify-between gap-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Panel') }}<b class="text-amber-400">D{{ (int) ($rankedStock->panel_decile ?? 0) }}</b></span></span></span>
                                    </a>
                                    <details class="dashboard-champion-reason group col-span-2 min-w-0 border-t border-amber-400/15 pt-2">
                                        <summary class="flex cursor-pointer list-none items-center gap-2 text-[9px] font-bold leading-4 text-[var(--ak-muted)] [&::-webkit-details-marker]:hidden">
                                            <span class="min-w-0 flex-1 truncate"><b class="text-amber-500">{{ __('Warum Champion?') }}</b> {{ ucfirst($rankReason) }}.</span>
                                            <x-heroicon-o-chevron-down class="h-3.5 w-3.5 shrink-0 text-amber-500 transition-transform duration-200 group-open:rotate-180" />
                                        </summary>
                                        <p class="mt-2 text-[9px] font-bold leading-4 text-[var(--ak-muted)]">{{ ucfirst($rankReason) }}.</p>
                                    </details>
                                </div>
                            @else
                                <a href="{{ route('stocks.show', ['symbol' => $rankedStock->symbol, 'return_to' => '/dashboard']) }}" class="group grid min-h-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 overflow-hidden rounded-xl border border-cyan-400/25 bg-white/[.018] px-4 py-3 transition hover:border-cyan-400/45">
                                    <span class="min-w-0"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $rankFlag }} {{ $rankedStock->name ?: $rankedStock->symbol }}</b><small class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ $rankedStock->symbol }}</span><span class="{{ $rankSignalTone }}">{{ signal_label($rankSignal) }}</span><span>{{ ($rankedStock->display_price_live ?? false) ? __('Livekurs') : __('Letzter Kurs') }} {{ is_numeric($rankedStock->display_price ?? null) ? number_format((float) $rankedStock->display_price, 2, ',', '.').' '.$rankCurrencyLabel : '—' }}</span><span class="{{ $rankDailyChange === null ? '' : ($rankDailyChange >= 0 ? 'text-emerald-400' : 'text-rose-400') }}">{{ __('Tag') }} {{ $rankDailyChange !== null ? sprintf('%+.2f%%', $rankDailyChange) : '—' }}</span></small></span>
                                    <span class="flex items-center gap-1.5"><span class="rounded-md border border-cyan-400/20 px-1.5 py-1 text-[9px] font-black text-cyan-400">KI {{ is_numeric($rankScore) ? number_format($rankScore, 1, ',', '.') : '—' }}</span><span class="rounded-md border px-1.5 py-1 text-[9px] font-black {{ is_numeric($rankReturn) && $rankReturn < 0 ? 'border-rose-400/25 text-rose-400' : 'border-emerald-400/25 text-emerald-400' }}">20T {{ is_numeric($rankReturn) ? sprintf('%+.1f%%', $rankReturn) : '—' }}</span></span>
                                </a>
                            @endif
                        @empty
                            <div class="grid flex-1 place-items-center rounded-lg border border-dashed border-amber-400/20 px-3 text-center text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Aktuell sind keine bewerteten Aktien verfügbar.') }}</div>
                        @endforelse
                    </div>
                </article>

                @if(false)
                <article x-data="{ cockpitOpen: true }" data-dashboard-card="signal-cockpit" data-dashboard-width="1" data-dashboard-height="6" data-dashboard-size="6" style="--dashboard-card-order:{{ $dashboardCardOrder('signal-cockpit') }}" class="dashboard-bento-signal-cockpit flex min-h-0 flex-col overflow-hidden p-4 {{ $dashboardCardVisible('signal-cockpit') ? '' : 'hidden' }}">
                    <div class="dashboard-collapsible-header flex items-center justify-between gap-3 mb-3">
                        <div class="flex min-w-0 flex-1 items-center gap-2.5 text-left sm:gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-300 sm:h-10 sm:w-10"><x-heroicon-o-signal class="h-5 w-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block whitespace-nowrap text-[9px] font-black uppercase tracking-[.16em] text-cyan-600">PRO · 5 {{ __('Handelstage') }}</span>
                                <span class="mt-1 flex min-w-0 items-center gap-2">
                                    <span class="block min-w-0 truncate text-sm font-black text-[var(--ak-text)] sm:text-base">{{ __('Signal-Cockpit') }}</span>
                                    <span class="hidden shrink-0 rounded-md border border-cyan-400/25 px-1.5 py-0.5 text-[7px] font-black uppercase tracking-wide sm:inline-flex"><span class="text-emerald-500">Top 3 POSITIV</span><span class="mx-1 text-[var(--ak-muted)]">·</span><span class="text-rose-500">1 SELL</span></span>
                                </span>
                                <span class="mt-1 flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[8px] font-black">
                                    <span class="inline-flex shrink-0 rounded border border-cyan-400/20 px-1 py-0.5 sm:hidden"><span class="text-emerald-500">Top 3 POSITIV</span><span class="mx-1 text-[var(--ak-muted)]">·</span><span class="text-rose-500">1 SELL</span></span>
                                    <span class="text-cyan-600" title="{{ __('Rohwert') }}: {{ is_numeric($cockpitAverageScore) ? number_format((float) $cockpitAverageScore, 1, ',', '.').'/10' : '—' }}">Ø KI {{ $cockpitAverageScoreGrade }}</span>
                                    <span class="text-amber-600" title="{{ __('Rohwert') }}: {{ is_numeric($cockpitAverageRisk) ? number_format((float) $cockpitAverageRisk, 0, ',', '.').' %' : '—' }}">Ø {{ __('Risiko') }} {{ $cockpitAverageRiskLevel }}</span>
                                </span>
                            </span>
                        </div>
                        <span class="flex shrink-0 items-center gap-2"><a href="{{ route('predictions.index') }}" class="hidden text-[9px] font-black text-cyan-600 sm:inline">{{ __('Alle') }} →</a></span>
                    </div>
                    <div x-show="cockpitOpen" x-cloak x-transition.opacity class="grid min-h-0 flex-1 gap-2" style="grid-template-rows:{{ $panelTopStocks->isNotEmpty() ? 'auto minmax(0,1fr)' : 'minmax(0,1fr)' }}">
                        @if(false)
                        @php
                            $profileUniverseLabel = match($profileUniverseStats['level'] ?? 'balanced') {
                                'defensive' => __('Defensiv'),
                                'opportunity' => __('Chancenorientiert'),
                                'risk' => __('Risk'),
                                default => __('Ausgewogen'),
                            };
                        @endphp
                        <style>
                            .aki-profile-universe-grid { grid-template-columns: 92px 82px minmax(0, 1fr); }
                            @media (max-width: 640px) {
                                .aki-profile-universe-grid { grid-template-columns: minmax(0, 1fr) 92px; }
                                .aki-profile-universe-score { grid-column: 1 / -1; }
                            }
                        </style>
                        <section class="rounded-lg border border-cyan-400/15 px-2 py-1" style="background:transparent" aria-labelledby="profile-universe-title">
                            <div class="aki-profile-universe-grid grid items-center gap-x-2 gap-y-1">
                                <div class="min-w-0">
                                    <p id="profile-universe-title" class="truncate text-[9px] font-black uppercase tracking-[.08em] text-cyan-300">{{ __('Aktives Portfolio') }}</p>
                                    <div class="mt-0.5 flex items-end gap-1"><strong class="text-xl font-black leading-none tabular-nums text-[var(--ak-text)]">{{ number_format((int) ($profileUniverseStats['active_count'] ?? 0), 0, ',', '.') }}</strong><span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Aktien') }}</span></div>
                                    <p class="mt-1 truncate text-[8px] font-black uppercase tracking-wide text-cyan-400">{{ $profileUniverseLabel }} · Ø {{ is_numeric($profileUniverseStats['average_score'] ?? null) ? number_format($profileUniverseStats['average_score'], 1, ',', '.') : '—' }}</p>
                                </div>
                                <div class="rounded-md border border-amber-400/25 bg-amber-400/[.05] px-1.5 py-0.5 text-center" title="{{ __('Mindestens drei Prognosehorizonte und der KI-Score bewegen sich gemeinsam in Richtung eines neuen Signals.') }}">
                                    <p class="truncate text-[8px] font-black uppercase tracking-wide text-amber-500">{{ __('Wechsel nah') }}</p>
                                    <div class="flex items-center justify-center gap-1.5"><strong class="text-base font-black leading-5 tabular-nums text-[var(--ak-text)]">{{ $profileUniverseStats['transition_candidates'] ?? 0 }}</strong><span class="text-[8px] font-black tabular-nums text-emerald-500">↑ {{ $profileUniverseStats['transition_to_buy'] ?? 0 }}</span><span class="text-[8px] font-black tabular-nums text-rose-500">↓ {{ $profileUniverseStats['transition_to_sell'] ?? 0 }}</span></div>
                                </div>
                                <div class="aki-profile-universe-score min-w-0">
                                    <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ __('KI-Rating') }}</span><span>5− bis 1+</span></div>
                                    <div class="relative mx-1 h-8 pt-3">
                                        <div class="h-1.5 rounded-full border border-white/10 bg-gradient-to-r from-rose-400 via-amber-300 to-emerald-400 shadow-inner"></div>
                                        @foreach(($profileUniverseStats['bins'] ?? []) as $index => $bin)
                                            @php
                                                $position = 10 + ($index * 20);
                                                $markerTone = ['border-rose-500 bg-rose-100 text-rose-700','border-orange-500 bg-orange-100 text-orange-700','border-amber-500 bg-amber-100 text-amber-800','border-lime-500 bg-lime-100 text-lime-800','border-emerald-500 bg-emerald-100 text-emerald-800'][$index] ?? 'border-cyan-500 bg-cyan-100 text-cyan-800';
                                            @endphp
                                            <span class="absolute top-1 -translate-x-1/2" style="left:{{ $position }}%" title="{{ $bin['label'] }}: {{ $bin['count'] }} {{ __('Aktien') }}">
                                                <span class="grid min-w-[22px] place-items-center rounded-full border px-1 py-0.5 text-[8px] font-black leading-none tabular-nums shadow-sm {{ $markerTone }}">{{ $bin['count'] }}</span>
                                                <span class="mx-auto block h-2 w-px bg-current opacity-50"></span>
                                            </span>
                                        @endforeach
                                        <div class="absolute inset-x-0 bottom-0 flex justify-between text-[8px] font-bold tabular-nums text-[var(--ak-muted)]"><span>0</span><span>2</span><span>4</span><span>6</span><span>8</span><span>10</span></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        @endif
                        <section class="flex min-h-0 flex-col overflow-hidden bg-transparent">
                            <div class="dashboard-signal-list grid min-h-0 flex-1 content-start gap-1.5 overflow-y-auto pr-0.5">
                                @forelse($displaySignalChanges as $changeIndex => $change)
                                    @php $changeFlag = $dashboardCountryFlags[strtoupper((string) ($change['country'] ?? ''))] ?? '🌐'; @endphp
                                    @php
                                        $isSellCard = ($change['_cockpit_group'] ?? null) === 'sell';
                                        $cardTone = $isSellCard ? 'border-rose-400/25 hover:border-rose-400/45' : 'border-cyan-400/20 hover:border-cyan-400/45';
                                        $changeScore = is_numeric($change['score'] ?? null) ? (float) $change['score'] : null;
                                        $changeRisk = is_numeric($change['risk'] ?? null) ? (float) $change['risk'] : null;
                                        $changeScoreGrade = \App\Support\QualityGrade::fromPercent(\App\Support\AiScore::toPercent($changeScore)) ?? '—';
                                        $changeRiskLevel = \App\Support\QualityGrade::riskLevel($changeRisk) ?? '—';
                                    @endphp
                                    <a href="{{ route('stocks.show', ['symbol' => $change['symbol'], 'prediction' => $change['prediction_id'], 'return_to' => '/dashboard']) }}" title="{{ $change['name'] ?: $change['symbol'] }}" class="aki-signal-compact-card group flex min-w-0 items-center gap-2 rounded-lg border bg-white/[.018] px-2 py-1.5 transition {{ $isSellCard ? 'border-rose-400/30 hover:border-rose-400/55' : 'border-amber-400/30 hover:border-amber-400/55' }}">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border {{ $isSellCard ? 'border-rose-400/35 bg-rose-400/10 text-rose-400' : 'border-amber-400/35 bg-amber-400/10 text-amber-500' }}" aria-hidden="true">
                                            @if($isSellCard)<x-heroicon-o-arrow-trending-down class="h-5 w-5" />@else<x-heroicon-o-arrow-trending-up class="h-5 w-5" />@endif
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex min-w-0 items-center justify-between gap-2"><b class="truncate text-sm font-black text-[var(--ak-text)]">{{ $change['name'] ?: $change['symbol'] }}</b><time class="shrink-0 rounded-md border border-cyan-400/20 px-1.5 py-0.5 text-[8px] font-black tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($change['at'])->format(app()->getLocale() === 'en' ? 'm/d' : 'd.m.') }}</time></span>
                                            <small class="mt-0.5 block truncate text-[9px] font-black {{ $isSellCard ? 'text-rose-400' : 'text-amber-500' }}">{{ $change['from'] }} → {{ $change['to'] }}</small>
                                            <small class="mt-1 flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ $changeFlag }} {{ $change['symbol'] }}</span><span class="text-cyan-500" title="{{ __('Rohwert') }}: {{ $changeScore !== null ? number_format($changeScore, 1, ',', '.').'/10' : '—' }}">KI {{ $changeScoreGrade }}</span><span class="text-amber-500" title="{{ __('Rohwert') }}: {{ $changeRisk !== null ? number_format($changeRisk, 0, ',', '.').' %' : '—' }}">{{ __('Risiko') }} {{ $changeRiskLevel }}</span>
                                        @foreach([5, 10, 15, 20] as $days)
                                            @php
                                                $forecast = $change['horizons'][$days] ?? null;
                                                $forecastDirection = $forecast === null ? 'empty' : (abs((float) $forecast) < .5 ? 'neutral' : ($forecast > 0 ? 'positive' : 'negative'));
                                                $forecastTitle = $forecast === null ? __('Keine Prognose verfügbar') : (($forecast >= 0 ? '+' : '').number_format($forecast, 1, ',', '.').'%');
                                                $forecastDisplay = $forecast === null ? '—' : (($forecast >= 0 ? '+' : '').number_format($forecast, 1, ',', '.').'%');
                                                $forecastBadgeClass = match($forecastDirection) {
                                                    'positive' => 'text-emerald-400',
                                                    'negative' => 'text-rose-400',
                                                    'neutral' => 'text-amber-400',
                                                    default => 'text-[var(--ak-muted)]',
                                                };
                                            @endphp
                                            <span title="{{ $days }}T: {{ $forecastTitle }}" class="whitespace-nowrap tabular-nums {{ $forecastBadgeClass }}">{{ $days }}T {{ $forecastDisplay }}</span>
                                        @endforeach
                                            </small>
                                        </span>
                                    </a>
                                @empty <small class="text-[10px] text-[var(--ak-muted)]">{{ __('Keine Wechsel in den letzten 30 Handelstagen.') }}</small> @endforelse
                            </div>
                        </section>
                        @if($panelTopStocks->isNotEmpty())
                        <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-[var(--ak-border)] border-l-4 border-l-cyan-400 bg-transparent">
                            <div class="flex items-center justify-between gap-2 border-b border-amber-400/35 bg-amber-400/10 px-3 py-1.5 text-amber-400">
                                <span class="flex min-w-0 items-center gap-1.5 text-[9px] font-black uppercase tracking-[.16em]"><x-heroicon-o-arrow-trending-up class="h-3.5 w-3.5 shrink-0" />{{ __('Panel-Modell · 10 Stärkste') }}</span>
                                <span class="shrink-0 text-[8px] font-black uppercase tracking-wide tabular-nums text-amber-500/80">{{ __('Stand') }} {{ \Illuminate\Support\Carbon::parse($panelTopAsOf)->format(app()->getLocale() === 'en' ? 'm/d' : 'd.m.') }}</span>
                            </div>
                            <div class="grid min-h-0 flex-1 content-start gap-1.5 overflow-y-auto p-1.5">
                                @foreach($panelTopStocks as $panelIndex => $panelRow)
                                    @php
                                        $panelName = $panelRow->name ?: $panelRow->symbol;
                                        $panelPctile = is_numeric($panelRow->xsec_pctile) ? (int) round($panelRow->xsec_pctile * 100) : null;
                                        $panelBeta = is_numeric($panelRow->beta_60) ? (float) $panelRow->beta_60 : null;
                                        $panelRet20 = is_numeric($panelRow->ret_20) ? (float) $panelRow->ret_20 : null;
                                        $panelRet120 = is_numeric($panelRow->ret_120) ? (float) $panelRow->ret_120 : null;
                                        $panelPct = fn (?float $v): string => $v === null ? '—' : (($v >= 0 ? '+' : '').number_format($v * 100, 0, ',', '.').'%');
                                        $panelPctTone = fn (?float $v): string => $v === null ? 'text-[var(--ak-muted)]' : ($v >= 0 ? 'text-emerald-400' : 'text-rose-400');
                                    @endphp
                                    <a href="{{ route('stocks.show', ['symbol' => $panelRow->symbol, 'return_to' => '/dashboard']) }}" title="{{ $panelName }}" class="group flex min-w-0 items-center gap-2 rounded-lg border border-cyan-400/20 bg-white/[.018] px-2 py-1.5 transition hover:border-cyan-400/55">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-cyan-400/35 bg-cyan-400/10 text-xs font-black tabular-nums text-cyan-300" aria-hidden="true">{{ $panelIndex + 1 }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex min-w-0 items-center justify-between gap-2">
                                                <b class="truncate text-sm font-black text-[var(--ak-text)]">{{ $panelName }}</b>
                                                <span class="shrink-0 rounded-md border border-cyan-400/20 px-1.5 py-0.5 text-[8px] font-black tabular-nums text-cyan-500" title="{{ __('Querschnitts-Perzentil') }}">P{{ $panelPctile ?? '—' }}</span>
                                            </span>
                                            <small class="mt-1 flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                                <span>{{ $panelFlag($panelRow->country) }} {{ $panelRow->symbol }}</span>
                                                <span class="text-cyan-500" title="{{ __('Dezil') }} 1–10">{{ __('Dezil') }} {{ $panelRow->decile ?? '—' }}</span>
                                                <span class="text-amber-500" title="60-{{ __('Tage-Beta') }}">β {{ $panelBeta === null ? '—' : number_format($panelBeta, 2, ',', '.') }}</span>
                                                <span class="whitespace-nowrap tabular-nums {{ $panelPctTone($panelRet20) }}" title="{{ __('Kursänderung 20 Handelstage') }}">20T {{ $panelPct($panelRet20) }}</span>
                                                <span class="whitespace-nowrap tabular-nums {{ $panelPctTone($panelRet120) }}" title="{{ __('Kursänderung 120 Handelstage') }}">120T {{ $panelPct($panelRet120) }}</span>
                                            </small>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                        @endif
                    </div>
                </article>
                @endif
                <article data-dashboard-card="signal-cockpit" data-dashboard-width="1" data-dashboard-height="6" data-dashboard-size="6" style="--dashboard-card-order:{{ $dashboardCardOrder('signal-cockpit') }}" class="dashboard-bento-signal-cockpit flex min-h-0 flex-col overflow-hidden p-4 {{ $dashboardCardVisible('signal-cockpit') ? '' : 'hidden' }}">
                    <div class="dashboard-collapsible-header mb-3 flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-arrows-right-left class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[9px] font-black uppercase tracking-[.16em] text-cyan-600">{{ __('Drei-Faktoren-Auswahl') }}</span>
                            <span class="mt-1 flex items-center gap-2"><b class="truncate text-base font-black text-[var(--ak-text)]">{{ __('Beste Alternativen') }}</b><small class="rounded-md border border-cyan-400/25 px-1.5 py-0.5 text-[7px] font-black uppercase text-cyan-500">{{ $threeFactorAlternatives->count() }}</small></span>
                        </span>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div id="dashboard-ranking-stocks-row" class="grid content-start gap-2">
                            @forelse($threeFactorAlternatives as $alternative)
                                @php
                                    $alternativeFlag = $dashboardCountryFlags[strtoupper((string) ($alternative->country ?? ''))] ?? '🌐';
                                    $alternativeRating = (string) ($alternative->serving_buy_rating_raw ?? $alternative->serving_buy_rating ?? '—');
                                    $alternativeExternal = ($alternative->three_factor_external_confirmed ?? false) && is_numeric($alternative->three_factor_external_score ?? null) ? (float) $alternative->three_factor_external_score : null;
                                    $alternativeDecile = is_numeric($alternative->panel_decile ?? null) ? (int) $alternative->panel_decile : null;
                                    $alternativePanelTone = $alternativeDecile === null ? 'border-slate-400/25 text-[var(--ak-muted)]' : ($alternativeDecile >= 6 ? 'border-emerald-400/35 bg-emerald-400/[.08] text-emerald-500' : ($alternativeDecile >= 4 ? 'border-amber-400/35 bg-amber-400/[.08] text-amber-500' : 'border-rose-400/35 bg-rose-400/[.08] text-rose-500'));
                                    $alternativeCategory = (string) ($alternative->alternative_category ?? 'score');
                                    $alternativeCategoryLabel = match($alternativeCategory) {
                                        'panel' => __('Panel-Bewertung'),
                                        'external' => __('Externe Bewertung'),
                                        'rank' => __('Ranking · Platz :rank', ['rank' => (int) ($alternative->alternative_rank ?? 0)]),
                                        default => __('KI-Bewertung'),
                                    };
                                    $alternativeReturn20 = is_numeric($alternative->expected_return_20d ?? null) ? (float) $alternative->expected_return_20d : null;
                                    $alternativeScore = is_numeric($alternative->three_factor_score ?? null) ? (float) $alternative->three_factor_score : 0.0;
                                    $alternativeBuyScore = is_numeric($alternative->three_factor_buy_score ?? null) ? (float) $alternative->three_factor_buy_score : 0.0;
                                    $alternativeScoreGrade = number_format($alternativeScore, 0, ',', '.');
                                    $alternativeScoreLevel = max(0, min(5, (int) ceil($alternativeScore / 20)));
                                    $alternativeSignal = strtoupper((string) ($alternative->personalized_signal ?: 'BUY'));
                                    $alternativeSignalTone = match($alternativeSignal) { 'BUY' => 'text-emerald-500', 'WATCH' => 'text-lime-500', 'SELL' => 'text-rose-400', 'WAIT' => 'text-orange-500', default => 'text-amber-500' };
                                    $alternativeCurrency = strtoupper((string) ($alternative->currency ?: 'EUR'));
                                    $alternativeCurrencyLabel = match($alternativeCurrency) { 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'JPY' => '¥', default => $alternativeCurrency };
                                    $alternativeDaily = is_numeric($alternative->daily_change_percent ?? null) ? (float) $alternative->daily_change_percent : null;
                                    $alternativeRankBadge = '#'.((int) ($alternative->alternative_rank ?? 0));
                                @endphp
                                <a href="{{ route('stocks.show', ['symbol' => $alternative->symbol, 'return_to' => '/dashboard']) }}" @class(['dashboard-alternative-entry group', 'dashboard-alternative-entry--silver' => (int) ($alternative->alternative_rank ?? 0) === 2, 'dashboard-alternative-entry--bronze' => (int) ($alternative->alternative_rank ?? 0) === 3]) title="{{ $alternative->name }}">
                                    <span class="dashboard-alternative-rank" aria-hidden="true">{{ $alternativeRankBadge }}</span>
                                    <span class="min-w-0 flex-1">
                                        <small class="block text-[8px] font-black uppercase tracking-[.14em] text-cyan-600">{{ $alternativeCategoryLabel }}</small>
                                        <b class="flex min-w-0 items-center gap-1.5"><span class="truncate text-[13px] font-black text-[var(--ak-text)]">{{ $alternativeFlag }} {{ $alternative->name ?: $alternative->symbol }}</span></b>
                                        <small class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                            <span>{{ $alternative->symbol }}</span>
                                            <span class="{{ $alternativeSignalTone }}">{{ signal_label($alternativeSignal) }}</span>
                                            <span>{{ ($alternative->display_price_live ?? false) ? __('Livekurs') : __('Letzter Kurs') }} {{ is_numeric($alternative->display_price ?? null) ? number_format((float) $alternative->display_price, 2, ',', '.').' '.$alternativeCurrencyLabel : '—' }}</span>
                                            <span class="{{ $alternativeDaily === null ? '' : ($alternativeDaily >= 0 ? 'text-emerald-500' : 'text-rose-400') }}">{{ __('Tag') }} {{ $alternativeDaily !== null ? sprintf('%+.2f%%', $alternativeDaily) : '—' }}</span>
                                        </small>
                                        <small class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                            <span class="text-emerald-500">KI {{ $alternativeRating }}</span>
                                            <span class="text-cyan-500">{{ __('Extern') }} {{ $alternativeExternal !== null ? number_format($alternativeExternal, 0, ',', '.').'%' : '—' }}</span>
                                            <span class="{{ $alternativeReturn20 !== null && $alternativeReturn20 < 0 ? 'text-rose-400' : 'text-emerald-500' }}">20T {{ $alternativeReturn20 !== null ? sprintf('%+.1f%%', $alternativeReturn20) : '—' }}</span>
                                        </small>
                                    </span>
                                    <span class="dashboard-alternative-scores">
                                        <span class="dashboard-champion-donut"><x-segmented-score-donut :score="$alternativeScore" :display="$alternativeScoreGrade" :level="$alternativeScoreLevel" type="chance" :label="__('Drei-Faktoren-Mittel')" /></span>
                                        <span class="dashboard-champion-factors grid grid-rows-3 items-center gap-0.5 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-1" title="{{ __('Gesamtscore :average (wie im Screener), unterstützt durch POSITIV :buy %, extern :external % und Panel :panel (Dezil :decile)', ['average' => number_format($alternativeScore, 1, ',', '.'), 'buy' => number_format($alternativeBuyScore, 0, ',', '.'), 'external' => $alternativeExternal !== null ? number_format($alternativeExternal, 0, ',', '.') : '—', 'panel' => $alternativeDecile !== null ? 'D'.$alternativeDecile : '—', 'decile' => $alternativeDecile ?? 0]) }}"><span class="flex items-center justify-between gap-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Positiv') }}<b class="text-emerald-400">{{ number_format($alternativeBuyScore, 0, ',', '.') }}%</b></span><span class="flex items-center justify-between gap-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Extern') }}<b class="text-cyan-400">{{ $alternativeExternal !== null ? number_format($alternativeExternal, 0, ',', '.').'%' : '—' }}</b></span><span class="flex items-center justify-between gap-1.5 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Panel') }}<b class="text-amber-400">{{ $alternativeDecile !== null ? 'D'.$alternativeDecile : '—' }}</b></span></span>
                                    </span>
                                </a>
                            @empty
                                <div class="grid min-h-28 place-items-center px-4 text-center text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Aktuell gibt es neben dem Champion keine weitere extern bestätigte POSITIV-Alternative mit Panel-Wert.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </article>
                </section>

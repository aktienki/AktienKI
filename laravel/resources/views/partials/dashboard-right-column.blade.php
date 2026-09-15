                <div class="dashboard-right-column min-h-0">
                <article data-dashboard-card="signals" data-dashboard-size="{{ $dashboardCardSize('signals') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('signals') }}" class="dashboard-bento-signals ak-card ak-dashboard-card flex min-h-[250px] flex-1 flex-col overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('signals') ? '' : 'hidden' }}">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-emerald-400">{{ __('Extern geprüft') }}</p>
                            <h2 class="mt-1 text-base font-black text-[var(--ak-text)]">{{ __('Bestätigte POSITIV-Signale') }}</h2>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-md border border-emerald-400/25 bg-emerald-400/[.08] px-2 py-1 text-[9px] font-black text-emerald-400"><x-heroicon-o-check-badge class="h-4 w-4" />{{ $externalConfirmedBuys->count() }}</span>
                    </div>
                    <div class="grid min-h-0 flex-1 content-start gap-2 overflow-y-auto pr-0.5">
                        @forelse($externalConfirmedBuys as $stock)
                            @php
                                $confirmedFlag = $dashboardCountryFlags[strtoupper((string) ($stock->country ?? ''))] ?? '🌐';
                                $confirmedRating = (string) ($stock->serving_buy_rating_raw ?? $stock->serving_buy_rating ?? '—');
                                $confirmedConfidence = $stock->external_confirmation_confidence ?? null;
                            @endphp
                            <a href="{{ route('stocks.show', ['symbol' => $stock->symbol, 'return_to' => '/dashboard']) }}" class="group grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-lg border border-emerald-400/25 bg-emerald-400/[.045] px-3 py-2 transition hover:border-emerald-400/55 hover:bg-emerald-400/[.08]">
                                <span class="min-w-0">
                                    <b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $confirmedFlag }} {{ $stock->name ?: $stock->symbol }}</b>
                                    <small class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[8px] font-black uppercase tracking-wide"><span class="text-[var(--ak-muted)]">{{ $stock->symbol }}</span><span class="text-emerald-400">POSITIV {{ $confirmedRating }}</span><span class="text-cyan-500">{{ (int) ($stock->serving_buy_confirmations ?? 0) }} {{ __('Modellbestätigungen') }}</span></small>
                                </span>
                                <span class="text-right"><b class="block text-base font-black tabular-nums text-emerald-400">{{ is_numeric($confirmedConfidence) ? number_format((int) $confirmedConfidence, 0, ',', '.').' %' : '—' }}</b><small class="block text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Extern') }}</small></span>
                            </a>
                        @empty
                            <div class="grid min-h-24 place-items-center rounded-lg border border-dashed border-emerald-400/25 px-4 text-center text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Aktuell ist kein POSITIV-Signal für denselben Serving-Batch extern bestätigt.') }}</div>
                        @endforelse
                        @if($additionalAlternative)
                            @php
                                $additionalFlag = $dashboardCountryFlags[strtoupper((string) ($additionalAlternative->country ?? ''))] ?? '🌐';
                                $additionalRating = (string) ($additionalAlternative->serving_buy_rating_raw ?? $additionalAlternative->serving_buy_rating ?? '—');
                                $additionalSignal = strtoupper((string) ($additionalAlternative->personalized_signal ?: 'WATCH'));
                            @endphp
                            <a href="{{ route('stocks.show', ['symbol' => $additionalAlternative->symbol, 'return_to' => '/dashboard']) }}" class="group grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-lg border border-amber-400/25 bg-amber-400/[.045] px-3 py-2 transition hover:border-amber-400/55 hover:bg-amber-400/[.08]">
                                <span class="min-w-0">
                                    <b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $additionalFlag }} {{ $additionalAlternative->name ?: $additionalAlternative->symbol }}</b>
                                    <small class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[8px] font-black uppercase tracking-wide"><span class="text-[var(--ak-muted)]">{{ $additionalAlternative->symbol }}</span><span class="text-amber-400">{{ signal_label($additionalSignal) }} {{ $additionalRating }}</span></small>
                                </span>
                                <span class="text-right"><b class="block text-[9px] font-black uppercase tracking-wide text-amber-400">{{ __('Evtl. bald interessant') }}</b></span>
                            </a>
                        @endif
                    </div>
                </article>
                <article data-dashboard-card="earnings" data-dashboard-width="1" data-dashboard-height="6" data-dashboard-size="6" style="--dashboard-card-order:{{ $dashboardCardOrder('earnings') }}" class="dashboard-bento-earnings ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-emerald-400/30 p-4 {{ $dashboardCardVisible('earnings') ? '' : 'hidden' }}">
                    @php
                        $earningsAbove = $recentEarnings->filter(fn ($earning) => is_numeric($earning->surprise_percent) && (float) $earning->surprise_percent >= 0)->count();
                        $earningsBelow = $recentEarnings->filter(fn ($earning) => is_numeric($earning->surprise_percent) && (float) $earning->surprise_percent < 0)->count();
                        $earningsAverage = $recentEarnings->filter(fn ($earning) => is_numeric($earning->surprise_percent))->avg(fn ($earning) => (float) $earning->surprise_percent);
                    @endphp
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-emerald-400/25 bg-emerald-400/10 text-emerald-300"><x-heroicon-o-presentation-chart-line class="h-4.5 w-4.5" /></span><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.16em] text-emerald-300">{{ __('Fundamentaldaten') }}</p><h2 class="mt-1 truncate text-base font-black text-[var(--ak-text)]">{{ __('Aktuelle Quartalszahlen') }}</h2></div></div>
                    </div>
                    <div class="mb-2 grid grid-cols-3 gap-1.5">
                        <div class="rounded-lg border border-emerald-400/20 bg-emerald-400/[.06] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Über Erwartung') }}</small><b class="text-sm tabular-nums text-emerald-300">{{ $earningsAbove }}</b></div>
                        <div class="rounded-lg border border-rose-400/20 bg-rose-400/[.05] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Unter Erwartung') }}</small><b class="text-sm tabular-nums text-rose-300">{{ $earningsBelow }}</b></div>
                        <div class="rounded-lg border border-cyan-400/20 bg-cyan-400/[.05] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">Ø {{ __('Überraschung') }}</small><b class="text-sm tabular-nums {{ $earningsAverage === null ? 'text-[var(--ak-muted)]' : ($earningsAverage >= 0 ? 'text-emerald-300' : 'text-rose-300') }}">{{ $earningsAverage === null ? '—' : (($earningsAverage >= 0 ? '+' : '').number_format($earningsAverage, 1, ',', '.').'%') }}</b></div>
                    </div>
                    <div class="grid min-h-0 flex-1 grid-rows-2 gap-1.5">
                        @forelse($recentEarnings as $earning)
                            @php
                                $surprise = is_numeric($earning->surprise_percent) ? (float) $earning->surprise_percent : null;
                                $difference = is_numeric($earning->eps_actual) && is_numeric($earning->eps_estimate) ? (float) $earning->eps_actual - (float) $earning->eps_estimate : null;
                            @endphp
                            <a href="{{ route('stocks.show', ['symbol' => $earning->symbol, 'return_to' => '/dashboard']) }}" class="flex min-h-0 flex-col justify-center rounded-lg border border-emerald-400/12 bg-emerald-400/[.035] px-2.5 py-2 transition hover:border-emerald-300/35 hover:bg-emerald-400/[.07]">
                                <span class="flex items-start justify-between gap-2"><span class="min-w-0"><b class="block truncate text-[10px] text-[var(--ak-text)]">{{ $earning->name ?: $earning->symbol }}</b><small class="block text-[8px] text-[var(--ak-muted)]">{{ $earning->symbol }} · {{ date('d.m.Y', strtotime((string) $earning->earnings_date)) }}</small></span><span class="shrink-0 rounded-md border px-2 py-1 text-[8px] font-black {{ $surprise === null ? 'border-slate-400/20 text-[var(--ak-muted)]' : ($surprise >= 0 ? 'border-emerald-400/25 bg-emerald-400/[.08] text-emerald-300' : 'border-rose-400/25 bg-rose-400/[.08] text-rose-300') }}">{{ $surprise === null ? __('Ohne Vergleich') : ($surprise >= 0 ? __('ÜBER ERWARTUNG') : __('UNTER ERWARTUNG')) }}</span></span>
                                <span class="mt-2 grid grid-cols-3 gap-1.5 border-t border-emerald-400/10 pt-1.5"><span><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">EPS {{ __('Ist') }}</small><b class="text-[10px] tabular-nums text-[var(--ak-text)]">{{ number_format((float) $earning->eps_actual, 2, ',', '.') }}</b></span><span><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Erwartet') }}</small><b class="text-[10px] tabular-nums text-[var(--ak-muted)]">{{ is_numeric($earning->eps_estimate) ? number_format((float) $earning->eps_estimate, 2, ',', '.') : '—' }}</b></span><span class="text-right"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Abweichung') }}</small><b class="text-[10px] tabular-nums {{ $surprise === null ? 'text-[var(--ak-muted)]' : ($surprise >= 0 ? 'text-emerald-300' : 'text-rose-300') }}">{{ $surprise === null ? '—' : (($surprise >= 0 ? '+' : '').number_format($surprise, 1, ',', '.').'%') }}@if($difference !== null) <small>({{ $difference >= 0 ? '+' : '' }}{{ number_format($difference, 2, ',', '.') }})</small>@endif</b></span></span>
                            </a>
                        @empty
                            <div class="rounded-lg border border-dashed border-emerald-400/20 p-4 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Noch keine aktuellen Quartalszahlen gespeichert.') }}</div>
                        @endforelse
                    </div>
                </article>
                <article data-dashboard-card="market-summary" data-dashboard-width="1" data-dashboard-height="1" data-dashboard-size="1" style="--dashboard-card-order:{{ $dashboardCardOrder('market-summary') }}" class="dashboard-bento-market-summary ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden border-orange-400/35 p-4 {{ $dashboardCardVisible('market-summary') ? '' : 'hidden' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-orange-400/25 bg-orange-400/10 text-orange-400"><x-heroicon-o-globe-europe-africa class="h-4.5 w-4.5" /></span><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Aktuelle Marktlage') }}</p><h2 class="mt-1 truncate text-sm font-black text-[var(--ak-text)]">{{ $marketSituation?->headline ?: __('Noch kein Marktbericht verfügbar') }}</h2></div></div>
                        <time class="shrink-0 text-[8px] font-black tabular-nums text-[var(--ak-muted)]">{{ $marketSituation?->analysis_date ? date('d.m.Y', strtotime((string) $marketSituation->analysis_date)) : '—' }}</time>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-1.5">
                        <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.045] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ausblick') }}</small><b class="text-[10px] uppercase {{ $marketOutlookIsNeutral ? 'text-amber-300' : 'text-cyan-300' }}">{{ $marketSituation?->market_outlook ?: '—' }}</b></div>
                        <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.045] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Konfidenz') }}</small><b class="text-[10px] tabular-nums text-[var(--ak-text)]">{{ is_numeric($marketSituation?->confidence) ? number_format((float) $marketSituation->confidence, 0, ',', '.').' %' : '—' }}</b></div>
                        <div class="rounded-lg border border-orange-400/15 bg-orange-400/[.045] px-2 py-1.5"><small class="block text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ __('Risiko') }}</small><b class="text-[10px] uppercase {{ $marketRiskIsHigh ? 'text-rose-400' : 'text-amber-300' }}">{{ $marketSituation?->risk_level ?: '—' }}</b></div>
                    </div>
                    <a href="{{ route('daily-market-analysis') }}" class="mt-3 inline-flex items-center gap-1 text-[9px] font-black text-orange-400 hover:text-orange-200">{{ __('Marktbericht öffnen') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" /></a>
                </article>
                </div>

                    @php
                        $compactBuyScore = $topStockToday ? \App\Support\AiScore::toPercent(is_numeric($topStockToday->ai_score) ? $topStockToday->ai_score : $topStockToday->prediction_score) : null;
                        $compactWatchScore = $topWatchStock ? \App\Support\AiScore::toPercent(is_numeric($topWatchStock->ai_score) ? $topWatchStock->ai_score : $topWatchStock->prediction_score) : null;
                        $dashboardCountryFlags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'AT' => '🇦🇹', 'CH' => '🇨🇭', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'NL' => '🇳🇱', 'DK' => '🇩🇰', 'SE' => '🇸🇪', 'NO' => '🇳🇴', 'FI' => '🇫🇮', 'IT' => '🇮🇹', 'ES' => '🇪🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'HK' => '🇭🇰', 'CA' => '🇨🇦', 'AU' => '🇦🇺'];
                        $compactBuyFlag = $dashboardCountryFlags[strtoupper((string) ($topStockToday->country ?? ''))] ?? '🌐';
                        $compactWatchFlag = $dashboardCountryFlags[strtoupper((string) ($topWatchStock->country ?? ''))] ?? '🌐';
                    @endphp
                    <article id="dashboard-daily-tips-card" data-help-card="daily-tips" x-data="{ opportunitiesOpen: true }" class="dashboard-daily-tips ak-card ak-dashboard-card flex min-h-0 flex-col overflow-hidden rounded-xl border-cyan-400/30 p-4" aria-labelledby="dashboard-daily-tips-title">
                        <div class="dashboard-collapsible-header flex items-center justify-between gap-3" :class="opportunitiesOpen ? 'mb-3' : ''">
                            <div class="flex min-w-0 flex-1 items-center gap-3 text-left"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-400/30 bg-cyan-400/10 text-cyan-600"><x-heroicon-o-bolt class="h-5 w-5" /></span><span class="min-w-0"><span class="block whitespace-nowrap text-[9px] font-black uppercase tracking-[.16em] text-cyan-600">{{ __('Zusätzliche Kandidaten') }}</span><span class="mt-1 flex min-w-0 items-center gap-2"><span id="dashboard-daily-tips-title" class="truncate text-base font-black text-[var(--ak-text)]">{{ __('Aufsteiger, Technik & Neu') }}</span></span><span class="mt-1 flex flex-wrap gap-1.5 text-[8px] font-black"><span class="text-cyan-600">{{ __('Score-Anstieg (5 Tage)') }}</span><span class="text-[var(--ak-muted)]">· {{ __('ChartView') }} · {{ __('neues POSITIV') }}</span></span></span></div>
                        </div>
                        <div id="dashboard-best-stocks-row" x-show="opportunitiesOpen" x-cloak x-transition.opacity class="grid min-h-0 flex-1 grid-cols-1 content-start gap-1.5 overflow-y-auto pr-1" aria-label="{{ __('Aufsteiger und technische Auswahl') }}">
                            @if(! $canUsePro)
                                <div class="rounded-xl border border-cyan-400/15 p-3 text-center text-[10px] font-bold text-[var(--ak-muted)]">{{ __('Diese Auswahl ist im Pro-Tarif verfügbar.') }}</div>
                            @else
                                @if($bestNewStock)
                                    @php
                                        $newStockFlag = $dashboardCountryFlags[strtoupper((string) ($bestNewStock->country ?? ''))] ?? '🌐';
                                        $newStockDate = $bestNewStock->new_buy_at ? \Illuminate\Support\Carbon::parse($bestNewStock->new_buy_at)->timezone('Europe/Berlin')->format('d.m.Y') : null;
                                    @endphp
                                    <a href="{{ route('stocks.show', ['symbol' => $bestNewStock->symbol, 'return_to' => '/dashboard']) }}" class="dashboard-opportunity-card group flex min-w-0 items-center gap-2.5 rounded-xl border border-orange-400/20 bg-orange-400/[.045] px-3 py-2 transition hover:border-orange-300/45 hover:bg-orange-400/[.10]" title="{{ $bestNewStock->name }}">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-amber-400/35 bg-amber-400/[.12] text-amber-500"><x-heroicon-o-sparkles class="h-4 w-4" /></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between gap-2"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $newStockFlag }} {{ $bestNewStock->name ?: $bestNewStock->symbol }}</b><em class="shrink-0 rounded-md border border-amber-400/35 bg-amber-400/[.12] px-1.5 py-0.5 text-[9px] font-black not-italic tabular-nums text-amber-500">KI {{ number_format((float) ($bestNewStock->score_10 ?? 0), 1, ',', '.') }}</em></span>
                                            <small class="mt-0.5 block truncate text-[9px] font-black text-amber-500">{{ $bestNewStock->is_fresh_buy ? __('Beste neue POSITIV-Aktie') : __('Neueste Aktie') }}@if($newStockDate) · {{ $newStockDate }}@endif</small>
                                            <small class="mt-0.5 flex flex-wrap gap-x-1.5 text-[8px] font-bold uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ $bestNewStock->symbol }}</span><span class="text-emerald-500">{{ signal_label('BUY') }}</span>@if(is_numeric($bestNewStock->panel_decile ?? null))<span class="text-cyan-500">Panel D{{ (int) $bestNewStock->panel_decile }}</span>@endif@if(is_numeric($bestNewStock->expected_return_20d ?? null))<span class="{{ (float) $bestNewStock->expected_return_20d >= 0 ? 'text-emerald-500' : 'text-rose-400' }}">20T {{ sprintf('%+.1f %%', (float) $bestNewStock->expected_return_20d) }}</span>@endif</small>
                                        </span>
                                    </a>
                                @else
                                    <div class="rounded-xl border border-dashed border-orange-400/20 p-3 text-center text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Keine weitere neue POSITIV-Aktie verfügbar.') }}</div>
                                @endif

                                @if($scoreRiser)
                                    @php
                                        $riserFlag = $dashboardCountryFlags[strtoupper((string) ($scoreRiser->country ?? ''))] ?? '🌐';
                                        $riserSignal = strtoupper((string) ($scoreRiser->personalized_signal ?: $scoreRiser->model_signal ?: 'HOLD'));
                                        $riserSignalTone = match($riserSignal) { 'WATCH' => 'text-lime-500', 'SELL' => 'text-rose-400', 'WAIT' => 'text-orange-500', default => 'text-amber-500' };
                                    @endphp
                                    <a href="{{ route('stocks.show', ['symbol' => $scoreRiser->symbol, 'return_to' => '/dashboard']) }}" class="dashboard-opportunity-card group flex min-w-0 items-center gap-2.5 rounded-xl border border-orange-400/20 bg-orange-400/[.045] px-3 py-2 transition hover:border-orange-300/45 hover:bg-orange-400/[.10]" title="{{ $scoreRiser->name }}">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-emerald-400/35 bg-emerald-400/[.12] text-emerald-500"><x-heroicon-o-arrow-trending-up class="h-4 w-4" /></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between gap-2"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $riserFlag }} {{ $scoreRiser->name ?: $scoreRiser->symbol }}</b><em class="shrink-0 rounded-md border border-emerald-400/35 bg-emerald-400/[.12] px-1.5 py-0.5 text-[9px] font-black not-italic tabular-nums text-emerald-500">+{{ number_format($scoreRiser->score_rise_delta, 1, ',', '.') }}</em></span>
                                            <small class="mt-0.5 block truncate text-[9px] font-black text-emerald-500">{{ __('Score-Aufsteiger · :days Tage · noch kein POSITIV', ['days' => $scoreRiser->score_rise_days]) }}</small>
                                            <small class="mt-0.5 flex flex-wrap gap-x-1.5 text-[8px] font-bold uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ $scoreRiser->symbol }}</span><span class="{{ $riserSignalTone }}">{{ signal_label($riserSignal) }}</span><span class="text-emerald-500">KI {{ number_format($scoreRiser->score_rise_prev, 1, ',', '.') }} → {{ number_format($scoreRiser->score_rise_now, 1, ',', '.') }}</span></small>
                                        </span>
                                    </a>
                                @else
                                    <div class="rounded-xl border border-dashed border-orange-400/20 p-3 text-center text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Kein Score-Aufsteiger der letzten Tage ohne POSITIV.') }}</div>
                                @endif

                                @if($topIndicatorStock)
                                    @php
                                        $indicatorFlag = $dashboardCountryFlags[strtoupper((string) ($topIndicatorStock->country ?? ''))] ?? '🌐';
                                        $indicatorSignal = strtoupper((string) ($topIndicatorStock->personalized_signal ?: $topIndicatorStock->model_signal ?: 'HOLD'));
                                    @endphp
                                    <a href="{{ route('stocks.show', ['symbol' => $topIndicatorStock->symbol, 'return_to' => '/dashboard']) }}" class="dashboard-opportunity-card group flex min-w-0 items-center gap-2.5 rounded-xl border border-orange-400/20 bg-orange-400/[.045] px-3 py-2 transition hover:border-orange-300/45 hover:bg-orange-400/[.10]" title="{{ $topIndicatorStock->name }}">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-cyan-400/35 bg-cyan-400/[.12] text-cyan-500"><x-heroicon-o-chart-bar class="h-4 w-4" /></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center justify-between gap-2"><b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $indicatorFlag }} {{ $topIndicatorStock->name ?: $topIndicatorStock->symbol }}</b><em class="shrink-0 rounded-md border border-cyan-400/35 bg-cyan-400/[.12] px-1.5 py-0.5 text-[9px] font-black not-italic tabular-nums text-cyan-500">{{ number_format($topIndicatorStock->indicator_score, 0, ',', '.') }}%</em></span>
                                            <small class="mt-0.5 block truncate text-[9px] font-black text-cyan-500">{{ __('Bester Indikatorscore · :events Signale, :n Fälle', ['events' => $topIndicatorStock->indicator_events, 'n' => $topIndicatorStock->indicator_samples]) }}</small>
                                            <small class="mt-0.5 flex flex-wrap gap-x-1.5 text-[8px] font-bold uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ $topIndicatorStock->symbol }}</span><span>{{ signal_label($indicatorSignal) }}</span>@if($topIndicatorStock->indicator_top_label)<span class="truncate text-cyan-500" title="{{ $topIndicatorStock->indicator_top_label }}">{{ $topIndicatorStock->indicator_top_label }}{{ $topIndicatorStock->indicator_top_prob !== null ? ' · '.number_format($topIndicatorStock->indicator_top_prob, 0, ',', '.').'%' : '' }}</span>@endif</small>
                                        </span>
                                    </a>
                                @else
                                    <div class="rounded-xl border border-dashed border-orange-400/20 p-3 text-center text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Keine technische ChartView-Statistik verfügbar.') }}</div>
                                @endif
                            @endif
                        </div>
                    </article>

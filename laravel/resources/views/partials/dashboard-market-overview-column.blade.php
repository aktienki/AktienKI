                <div id="dashboard-middle-column" data-dashboard-card="market" data-dashboard-size="{{ $dashboardCardSize('market') }}" style="--dashboard-card-order:{{ $dashboardCardOrder('market') }}" class="dashboard-bento-market dashboard-market-overview-grid min-h-0 sm:col-span-2 {{ $dashboardMarketVisible ? '' : 'hidden' }}">
                    <article id="dashboard-market-overview-card" data-dashboard-top-row-card data-help-card="market" class="ak-card min-h-[150px] shrink-0 rounded-xl border-cyan-400/30 p-4" aria-labelledby="profile-universe-title">
                        <div class="aki-profile-universe-grid grid gap-3">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p id="profile-universe-title" class="text-[10px] font-black uppercase tracking-[.12em] text-cyan-400">{{ __('Aktuelle Remote-Aktien') }}</p>
                                    <div class="mt-1 flex items-end gap-1.5"><strong class="text-2xl font-black leading-none tabular-nums text-[var(--ak-text)]">{{ number_format((int) ($profileUniverseStats['active_count'] ?? 0), 0, ',', '.') }}</strong><span class="pb-0.5 text-[10px] font-bold text-[var(--ak-muted)]">{{ __('Aktien') }}</span></div>
                                    <p class="mt-1 text-[8px] font-black uppercase tracking-wide text-cyan-400">{{ __('Remote-Serving') }} · {{ number_format((int) ($profileUniverseStats['current_signal_count'] ?? 0), 0, ',', '.') }} {{ __('mit aktueller Prediction') }} · {{ number_format((int) ($profileUniverseStats['open_count'] ?? 0), 0, ',', '.') }} {{ __('ausstehend') }}</p>
                                </div>
                            </div>
                            <div class="aki-profile-universe-score min-w-0">
                                <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]"><span>{{ __('Aktuelle Signale') }}</span><span>{{ __('Serving') }}</span></div>
                                <div class="relative mx-1" style="height:64px">
                                    <div class="absolute inset-x-0 h-2 rounded-full border border-white/10 bg-gradient-to-r from-slate-400 via-amber-300 to-emerald-400 shadow-inner" style="top:42px"></div>
                                    @foreach(($profileUniverseStats['bins'] ?? []) as $index => $bin)
                                        @php $position = 12.5 + ($index * 25); $markerTone = ['border-rose-500 bg-rose-100 text-rose-700','border-amber-500 bg-amber-100 text-amber-800','border-lime-500 bg-lime-100 text-lime-800','border-emerald-500 bg-emerald-100 text-emerald-800'][$index] ?? 'border-cyan-500 bg-cyan-100 text-cyan-800'; @endphp
                                        <span class="absolute top-0 -translate-x-1/2" style="left:{{ $position }}%" title="{{ $bin['range'] ?? $bin['label'] }}: {{ $bin['count'] }} {{ __('Aktien') }}"><span class="grid h-8 w-12 place-items-center rounded-md border px-1 text-[10px] font-black leading-none tabular-nums shadow-md {{ $markerTone }}">{{ $bin['count'] }}</span><span class="mx-auto block h-2.5 w-px bg-current opacity-60"></span></span>
                                    @endforeach
                                    <div class="absolute inset-x-0 bottom-0 grid grid-cols-4 text-center text-[7px] font-black uppercase tracking-wide">
                                        @foreach ([[__('SELL'),'text-rose-500'],[__('HOLD'),'text-amber-500'],[__('WATCH'),'text-lime-600'],[__('POSITIV'),'text-emerald-500']] as [$signalLabel,$signalTone])
                                            <span class="truncate {{ $signalTone }}">{{ $signalLabel }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
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
                </div>

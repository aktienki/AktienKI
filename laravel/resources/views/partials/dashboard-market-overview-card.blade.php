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

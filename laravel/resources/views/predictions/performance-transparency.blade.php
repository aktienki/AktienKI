<x-app-layout>
    <main class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container">
            <header class="mb-5 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div><p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-500">{{ __('Transparenz') }}</p>
                <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">{{ __('Performance & Einordnung') }}</h1>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ak-muted)]">{{ __('Aktienspezifische Out-of-Sample-Ergebnisse nach deinem Profil. Die Werte zeigen abgeschlossene Signalwechsel-Trades und keine bloßen Tage innerhalb eines BUY-Signals.') }}</p></div>
                <div class="self-start rounded-xl border border-cyan-400/20 px-3 py-2 lg:min-w-[390px]">
                    <div class="flex items-center justify-between gap-5 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]"><span>{{ __('Qualitätsstufen') }}</span><span>{{ array_sum($qualitySummary) }} {{ __('Aktien') }}</span></div>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        @foreach([['quality',__('Quality'),'border-emerald-400/45 text-emerald-400'],['solid',__('Solid'),'border-cyan-400/45 text-cyan-400'],['basic',__('Basic'),'border-amber-400/45 text-amber-400'],['observation',__('Beobachtung'),'border-rose-400/45 text-rose-400']] as [$key,$label,$tone])
                            <span class="text-center"><b class="grid h-7 min-w-12 place-items-center rounded-md border bg-transparent text-sm font-black tabular-nums {{ $tone }}">{{ $qualitySummary[$key] ?? 0 }}</b><small class="mt-1 block truncate text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</small></span>
                        @endforeach
                    </div>
                </div>
            </header>

            <section x-data="{ filtersOpen: {{ request()->except('page') ? 'true' : 'false' }} }" class="ak-card overflow-hidden border-cyan-400/30">
                <div class="border-b border-[var(--ak-border)] p-3 sm:p-4">
                    <button type="button" @click="filtersOpen = !filtersOpen" class="flex w-full items-center justify-between gap-3 text-left">
                        <span class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/10 text-cyan-500"><x-heroicon-o-adjustments-horizontal class="h-5 w-5" /></span><span><b class="block text-sm font-black text-[var(--ak-text)]">{{ __('Tabelle filtern') }}</b><small class="text-[9px] font-bold text-[var(--ak-muted)]">{{ $rows->total() }} {{ __('Treffer') }}</small></span></span>
                        <x-heroicon-o-chevron-down class="h-5 w-5 text-cyan-500 transition" x-bind:class="filtersOpen && 'rotate-180'" />
                    </button>
                    <form x-cloak x-show="filtersOpen" x-transition class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-7" method="GET">
                        <label class="xl:col-span-2"><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Aktie') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('Name oder Symbol') }}" class="ak-input w-full" /></label>
                        <label><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Signal') }}</span><select name="signal" class="ak-input w-full"><option value="">{{ __('Alle') }}</option>@foreach(['BUY','WATCH','HOLD','WAIT','SELL'] as $value)<option value="{{ $value }}" @selected(request('signal')===$value)>{{ $value }}</option>@endforeach</select></label>
                        <label><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Score') }}</span><select name="score" class="ak-input w-full"><option value="">{{ __('Alle') }}</option>@foreach(['1+','1','1−','2+','2','2−','3+','3','3−','4+','4','4−','5+','5','5−'] as $value)<option value="{{ $value }}" @selected(request('score')===$value)>{{ $value }}</option>@endforeach</select></label>
                        <label><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Risiko') }}</span><select name="risk" class="ak-input w-full"><option value="">{{ __('Alle') }}</option>@foreach([2,3,4,5] as $value)<option value="{{ $value }}" @selected((string)request('risk')===(string)$value)>{{ $value }}</option>@endforeach</select></label>
                        <label><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Klasse') }}</span><select name="quality" class="ak-input w-full"><option value="">{{ __('Alle') }}</option>@foreach(['quality','solid','basic','observation'] as $value)<option value="{{ $value }}" @selected(request('quality')===$value)>{{ __(ucfirst($value)) }}</option>@endforeach</select></label>
                        <span class="flex items-end gap-2"><button class="ak-button-primary flex-1" type="submit">{{ __('Anwenden') }}</button><a class="ak-button-secondary" href="{{ route('predictions.performance-transparency') }}">{{ __('Reset') }}</a></span>
                        <label><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Hitrate ab') }}</span><input name="hit_rate" type="number" min="0" max="100" step="1" value="{{ request('hit_rate') }}" placeholder="0 %" class="ak-input w-full" /></label>
                        <label><span class="mb-1 block text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('Profitfaktor ab') }}</span><input name="profit_factor" type="number" min="0" step="0.1" value="{{ request('profit_factor') }}" placeholder="0,0" class="ak-input w-full" /></label>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table x-data="{ open: {} }" class="w-full min-w-[1050px] border-collapse text-left">
                        <thead class="bg-cyan-400/[.055] text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                            <tr><th class="px-4 py-3">{{ __('Aktie') }}</th><th>{{ __('Signal') }}</th><th>{{ __('Score') }}</th><th>{{ __('Risiko') }}</th><th>{{ __('Klasse') }}</th><th>{{ __('Hitrate') }}</th><th>{{ __('Ø/Trade') }}</th><th>{{ __('Profitfaktor') }}</th><th>{{ __('OOS-Trades') }}</th><th class="pr-4">{{ __('Details') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                        @forelse($rows as $row)
                            @php
                                $after = $row->after_metrics;
                                $signalTone = match(strtoupper((string)$row->personalized_signal)) { 'BUY' => 'text-emerald-500', 'SELL' => 'text-rose-500', 'WATCH' => 'text-lime-500', default => 'text-amber-500' };
                                $classTone = match($row->quality_class) { 'quality' => 'border-emerald-400/35 text-emerald-500', 'solid' => 'border-cyan-400/35 text-cyan-500', 'basic' => 'border-amber-400/35 text-amber-500', default => 'border-slate-400/25 text-[var(--ak-muted)]' };
                            @endphp
                            <tr class="text-xs text-[var(--ak-text)]">
                                <td class="px-4 py-3"><a href="{{ route('stocks.show', $row->symbol) }}" class="block max-w-64 truncate font-black hover:text-cyan-500">{{ $row->name ?: $row->symbol }}</a><small class="text-[9px] text-[var(--ak-muted)]">{{ $row->country }} · {{ $row->symbol }}</small></td>
                                <td><b class="{{ $signalTone }}">{{ strtoupper((string)$row->personalized_signal) }}</b></td>
                                <td><b class="text-base">{{ $row->score_grade ?: '—' }}</b></td>
                                <td><b class="text-base">{{ $row->risk_level ?: '—' }}</b>@if(is_numeric($row->risk_percent))<small class="ml-1 text-[9px] text-[var(--ak-muted)]">{{ number_format($row->risk_percent,0,',','.') }}%</small>@endif</td>
                                <td><span class="rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ $classTone }}">{{ __($row->quality_class) }}</span></td>
                                <td class="font-black tabular-nums">{{ is_numeric($after['hit_rate'] ?? null) ? number_format($after['hit_rate'],1,',','.').' %' : '—' }}</td>
                                <td class="font-black tabular-nums {{ (float)($after['average_return_percent'] ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ is_numeric($after['average_return_percent'] ?? null) ? sprintf('%+.2f %%',$after['average_return_percent']) : '—' }}</td>
                                <td class="font-black tabular-nums">{{ is_numeric($after['profit_factor'] ?? null) ? number_format(min(9.99,(float)$after['profit_factor']),2,',','.') : '—' }}</td>
                                <td class="font-black tabular-nums">{{ number_format((int)($after['trades'] ?? $row->validation_event_count),0,',','.') }}</td>
                                <td class="pr-4"><button type="button" @click="open[{{ (int)$row->instrument_id }}]=!open[{{ (int)$row->instrument_id }}]" class="grid h-8 w-8 place-items-center rounded-lg border border-cyan-400/25 text-cyan-500"><x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="open[{{ (int)$row->instrument_id }}] && 'rotate-180'" /></button></td>
                            </tr>
                            <tr x-cloak x-show="open[{{ (int)$row->instrument_id }}]" class="bg-cyan-400/[.025]"><td colspan="10" class="p-0"><div class="px-4 py-3 text-[10px] text-[var(--ak-muted)]"><p>{{ $row->reason }}</p><div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4"><span>{{ __('Vor Filter') }}: {{ (int)($row->before_metrics['trades'] ?? 0) }} {{ __('Trades') }}</span><span>{{ __('Hitrate') }} {{ is_numeric($row->before_metrics['hit_rate'] ?? null) ? number_format($row->before_metrics['hit_rate'],1,',','.').' %' : '—' }}</span><span>{{ __('Ø/Trade') }} {{ is_numeric($row->before_metrics['average_return_percent'] ?? null) ? sprintf('%+.2f %%',$row->before_metrics['average_return_percent']) : '—' }}</span><span>PF {{ is_numeric($row->before_metrics['profit_factor'] ?? null) ? number_format(min(9.99,(float)$row->before_metrics['profit_factor']),2,',','.') : '—' }}</span></div></div></td></tr>
                        @empty
                            <tr><td colspan="10" class="px-5 py-12 text-center text-sm text-[var(--ak-muted)]">{{ __('Noch keine aktienspezifischen Kalibrierungen vorhanden.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="mt-5">{{ $rows->links() }}</div>
            <p class="mt-4 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Historische Ergebnisse und Modellprognosen sind keine Garantie für zukünftige Entwicklungen und stellen keine Anlageberatung dar.') }}</p>
        </div>
    </main>
</x-app-layout>

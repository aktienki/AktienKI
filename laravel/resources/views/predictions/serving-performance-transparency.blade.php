<x-app-layout>
    <main class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container">
            <header class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.22em] text-cyan-500">Serving · regulärer OOS-Backtest</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">Performance nach Aktie und Horizont</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ak-muted)]">Standard und TCN werden getrennt verglichen. Der ausgewählte Champion und die Prediction-Freigabe gelten jeweils nur für 10T, 20T oder 40T.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                    @foreach([
                        ['Konfigurationen',$summary->configurations,'text-cyan-400'],
                        ['Freigegeben',$summary->eligible,'text-emerald-400'],
                        ['Quality Gate',$summary->quality_gate,'text-amber-400'],
                        ['Standard Champion',$summary->standard_champions,'text-cyan-400'],
                        ['TCN Champion',$summary->tcn_champions,'text-violet-400'],
                        ['Mit Prediction',$summary->with_prediction,'text-amber-400'],
                    ] as [$label,$value,$tone])
                        <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-right"><b class="block text-xl font-black tabular-nums {{ $tone }}">{{ $value }}</b><small class="block text-[7px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ $label }}</small></div>
                    @endforeach
                </div>
            </header>

            <section class="ak-card mb-4 p-3 sm:p-4">
                <form method="GET" class="grid gap-2 md:grid-cols-3 xl:grid-cols-8">
                    <input name="q" value="{{ request('q') }}" placeholder="Name oder Symbol" class="ak-input xl:col-span-2" />
                    <select name="horizon" class="ak-input"><option value="">Alle Horizonte</option>@foreach([10,20,40] as $value)<option value="{{ $value }}" @selected((int)request('horizon')===$value)>{{ $value }}T</option>@endforeach</select>
                    <select name="variant" class="ak-input"><option value="">Beide Champions</option><option value="standard" @selected(request('variant')==='standard')>Standard</option><option value="pure_tcn" @selected(request('variant')==='pure_tcn')>TCN</option></select>
                    <select name="quality" class="ak-input"><option value="">Alle Qualitäten</option>@foreach(['quality'=>'Quality','solid'=>'Solid','basic'=>'Basic','underperform'=>'Nicht qualifiziert','open'=>'Offen'] as $key=>$label)<option value="{{ $key }}" @selected(request('quality')===$key)>{{ $label }}</option>@endforeach</select>
                    <select name="status" class="ak-input"><option value="">Jeder Status</option><option value="eligible" @selected(request('status')==='eligible')>Freigegeben</option><option value="quality_gate" @selected(request('status')==='quality_gate')>Quality Gate</option><option value="blocked" @selected(request('status')==='blocked')>Gesperrt</option></select>
                    <input name="hit_rate" type="number" min="0" max="100" step="1" value="{{ request('hit_rate') }}" placeholder="Hitrate ab %" class="ak-input" />
                    <input name="profit_factor" type="number" min="0" step="0.1" value="{{ request('profit_factor') }}" placeholder="Profitfaktor ab" class="ak-input" />
                    <div class="flex gap-2 xl:col-span-8 xl:justify-end"><button class="ak-button-primary">Anwenden</button><a class="ak-button-secondary" href="{{ route('predictions.performance-transparency') }}">Zurücksetzen</a></div>
                </form>
            </section>

            <section class="ak-card overflow-hidden border-cyan-400/25">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1280px] border-collapse text-left">
                        <thead class="bg-cyan-400/[.055] text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                            <tr><th class="px-4 py-3">Aktie</th><th>Horizont</th><th>Aktiver Champion</th><th>Prediction-Status</th><th>Standard OOS</th><th>TCN OOS</th><th>Aktiver Backtest</th><th>Schwelle</th><th class="pr-4">Letzte Prediction</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                        @forelse($rows as $row)
                            @php
                                $active = $row->metrics;
                                $standard = $row->standard_metrics;
                                $tcn = $row->tcn_metrics;
                                $reasons = collect($row->skip_reason)->map(fn($reason) => match($reason) {
                                    'hit_rate_at_least_66_7pct' => 'Hitrate unter 66,7 %',
                                    'profit_factor_above_1_0' => 'Profitfaktor nicht über 1,0',
                                    'positive_average_net_trade' => 'Ø-Trade nicht positiv',
                                    'positive_cumulative_return' => 'Gesamtrendite nicht positiv',
                                    'minimum_10_non_overlapping_oos_trades' => 'Weniger als 10 überlappungsfreie OOS-Trades',
                                    default => str_replace('_',' ',$reason),
                                })->implode(' · ');
                            @endphp
                            <tr class="align-top text-xs text-[var(--ak-text)] hover:bg-cyan-400/[.025]">
                                <td class="px-4 py-3"><a href="{{ route('stocks.show',['symbol'=>$row->symbol]) }}" class="block max-w-56 truncate font-black hover:text-cyan-400">{{ $row->name ?: $row->symbol }}</a><small class="text-[9px] text-[var(--ak-muted)]">{{ $row->country }} · {{ $row->symbol }}</small></td>
                                <td><b class="text-base text-cyan-400">{{ $row->horizon }}T</b><small class="block text-[8px] uppercase text-[var(--ak-muted)]">{{ $row->horizon_quality }}</small></td>
                                <td><span class="inline-flex rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ $row->variant==='pure_tcn' ? 'border-violet-400/40 text-violet-400' : 'border-cyan-400/40 text-cyan-400' }}">{{ $row->variant_label }}</span>@if($row->champion)<small class="mt-1 block max-w-44 truncate text-[8px] text-[var(--ak-muted)]">{{ $row->champion }}</small>@endif</td>
                                <td><span title="{{ $reasons }}" class="inline-flex rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ $row->prediction_enabled ? 'border-emerald-400/40 text-emerald-400' : 'border-rose-400/35 text-rose-400' }}">{{ $row->prediction_enabled ? 'freigegeben' : 'gesperrt' }}</span>@if($row->quality_gate_passed)<span class="ml-1 inline-flex rounded-md border border-amber-400/45 bg-amber-400/[.06] px-2 py-1 text-[9px] font-black uppercase text-amber-400">Quality Gate</span>@endif<small class="mt-1 block max-w-52 text-[8px] leading-4 text-[var(--ak-muted)]">{{ $reasons ?: $row->prediction_status }}</small></td>
                                <td><b>HR {{ is_numeric($standard->hit_rate) ? number_format($standard->hit_rate,1,',','.').' %' : '—' }}</b><small class="block text-[8px] text-[var(--ak-muted)]">PF {{ is_numeric($standard->profit_factor) ? number_format($standard->profit_factor,2,',','.') : '—' }} · {{ $standard->trades }} Trades</small><small class="block text-[8px] {{ (float)($standard->average_return??0)>=0?'text-emerald-400':'text-rose-400' }}">Ø {{ is_numeric($standard->average_return) ? sprintf('%+.2f %%',$standard->average_return) : '—' }}</small></td>
                                <td><b>HR {{ is_numeric($tcn->hit_rate) ? number_format($tcn->hit_rate,1,',','.').' %' : '—' }}</b><small class="block text-[8px] text-[var(--ak-muted)]">PF {{ is_numeric($tcn->profit_factor) ? number_format($tcn->profit_factor,2,',','.') : '—' }} · {{ $tcn->trades }} Trades</small><small class="block text-[8px] {{ (float)($tcn->average_return??0)>=0?'text-emerald-400':'text-rose-400' }}">Ø {{ is_numeric($tcn->average_return) ? sprintf('%+.2f %%',$tcn->average_return) : '—' }}</small></td>
                                <td><b>HR {{ is_numeric($active->hit_rate) ? number_format($active->hit_rate,1,',','.').' %' : '—' }}</b><small class="block text-[8px] text-[var(--ak-muted)]">PF {{ is_numeric($active->profit_factor) ? number_format($active->profit_factor,2,',','.') : '—' }} · DD {{ is_numeric($active->max_drawdown) ? number_format($active->max_drawdown,1,',','.').' %' : '—' }}</small><small class="block text-[8px] text-[var(--ak-muted)]">{{ $active->trades }} nicht überlappende Trades</small></td>
                                <td><b class="tabular-nums">{{ is_numeric($row->entry_threshold) ? number_format($row->entry_threshold*100,2,',','.').' %' : '—' }}</b><small class="block text-[8px] text-[var(--ak-muted)]">TCN {{ is_numeric($row->pure_tcn_entry_threshold) ? number_format($row->pure_tcn_entry_threshold*100,2,',','.').' %' : '—' }}</small></td>
                                <td class="pr-4">@if($row->prediction)<b class="text-emerald-400">{{ $row->prediction->signal }}</b><small class="block text-[8px] text-[var(--ak-muted)]">{{ sprintf('%+.2f %%',$row->prediction->expected_return_percent) }} · {{ \Illuminate\Support\Carbon::parse($row->prediction->as_of)->format('d.m.Y') }}</small>@else<span class="text-[var(--ak-muted)]">Keine gespeichert</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-6 py-14 text-center text-sm text-[var(--ak-muted)]">Keine Modell-/Horizont-Konfiguration mit diesen Filtern.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="mt-5">{{ $rows->links() }}</div>
            <p class="mt-3 text-[9px] text-[var(--ak-muted)]">Quelle: serving_releases.compact_metrics · feste Horizonte · keine überlappenden Positionen.</p>
        </div>
    </main>
</x-app-layout>

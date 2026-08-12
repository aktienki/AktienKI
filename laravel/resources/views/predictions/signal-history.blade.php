<x-app-layout>
    <style>
        .signal-history-donut{position:relative;display:grid;width:42px;height:42px;place-items:center;border-radius:999px;background:conic-gradient(var(--donut-color) var(--donut-value),rgba(148,163,184,.22) 0);box-shadow:0 3px 10px color-mix(in srgb,var(--donut-color) 22%,transparent)}
        .signal-history-donut:after{content:"";position:absolute;inset:5px;border-radius:999px;background:var(--ak-card)}
        .signal-history-donut span{position:relative;z-index:1;font-size:10px;font-weight:900;color:var(--ak-text);font-variant-numeric:tabular-nums}
        .signal-history-donut small{font-size:7px;margin-left:1px;color:var(--ak-muted)}
        .signal-history-chart{height:94px;width:100%}.signal-history-chart-grid{stroke:rgba(148,163,184,.2);stroke-dasharray:4 5}.signal-history-chart-line{fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
    </style>
    <div class="mx-auto max-w-[1800px] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[.28em] text-amber-500">Forecast · Historie</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-[var(--ak-text)]">Signalwechsel zu BUY und SELL</h1>
                <p class="mt-2 text-sm text-[var(--ak-muted)]">Abgeschlossene Trades zeigen die tatsächlich realisierte Performance nach dem Schließen.</p>
                @if($demo)<p class="mt-2 inline-flex rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-600">Simulationsvorschau – nicht mit echten Ergebnissen vermischt</p>@endif
            </div>
            <form method="get" class="flex flex-wrap items-center gap-2">
                <select name="signal" class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-sm font-bold text-[var(--ak-text)]">
                    <option value="">BUY + SELL</option><option value="BUY" @selected(request('signal') === 'BUY')>BUY</option><option value="SELL" @selected(request('signal') === 'SELL')>SELL</option>
                </select>
                <select name="days" class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-sm font-bold text-[var(--ak-text)]">
                    @foreach([30,90,180,365] as $option)<option value="{{ $option }}" @selected($days === $option)>{{ $option }} Tage</option>@endforeach
                </select>
                <button class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-black text-white">Anwenden</button>
            </form>
        </div>
        @php
            $chartPoints = function (array $values): string {
                if (count($values) < 2) return '';
                $min = min($values); $max = max($values); $range = max(0.0001, $max - $min); $last = count($values) - 1;
                return collect($values)->map(fn ($value, $index) => round(4 + ($index / $last) * 292, 1).','.round(84 - (($value - $min) / $range) * 70, 1))->implode(' ');
            };
            $chartCards = [
                ['key' => 'performance', 'title' => 'Performanceverlauf', 'subtitle' => 'Kumulierte Trade-Performance'.($demo ? ' · Simulation' : ''), 'color' => '#06b6d4', 'suffix' => ' %'],
                ['key' => 'profit_factor', 'title' => 'Profitfaktorverlauf', 'subtitle' => 'Kumulatives Gewinn-/Verlustverhältnis'.($demo ? ' · Simulation' : ''), 'color' => '#f59e0b', 'suffix' => ''],
                ['key' => 'drawdown', 'title' => 'Drawdownverlauf', 'subtitle' => 'Abstand zum bisherigen Equity-Hoch'.($demo ? ' · Simulation' : ''), 'color' => '#ef526b', 'suffix' => ' %'],
            ];
        @endphp
        <div class="mb-5 grid gap-4 lg:grid-cols-3">
            @foreach($chartCards as $chart)
                @php $values = $chartData[$chart['key']] ?? []; $lastValue = $values !== [] ? end($values) : null; @endphp
                <section class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[.16em]" style="color:{{ $chart['color'] }}">{{ $chart['title'] }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ $chart['subtitle'] }}</p></div><p class="text-lg font-black" style="color:{{ $chart['color'] }}">{{ $lastValue !== null ? number_format($lastValue,2,',','.').$chart['suffix'] : '—' }}</p></div>
                    @if(count($values) > 1)<svg class="signal-history-chart mt-3" viewBox="0 0 300 94" preserveAspectRatio="none" role="img" aria-label="{{ $chart['title'] }}"><line x1="4" x2="296" y1="22" y2="22" class="signal-history-chart-grid"/><line x1="4" x2="296" y1="53" y2="53" class="signal-history-chart-grid"/><line x1="4" x2="296" y1="84" y2="84" class="signal-history-chart-grid"/><polyline points="{{ $chartPoints($values) }}" class="signal-history-chart-line" stroke="{{ $chart['color'] }}"/></svg>@else<div class="mt-3 grid h-[94px] place-items-center rounded-xl bg-slate-500/5 text-xs text-[var(--ak-muted)]">Noch keine abgeschlossenen Trades</div>@endif
                </section>
            @endforeach
        </div>
        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach([['Signalwechsel',$stats->transitions,'text-cyan-500'],['Geschlossen',$stats->closed,'text-teal-500'],['Gewinner',$stats->wins,'text-emerald-500'],['Ø Performance',number_format($stats->average,2,',','.').' %','text-amber-500'],['Summe',number_format($stats->total,2,',','.').' %','text-violet-500']] as [$label,$value,$color])
                <div class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-sm"><p class="text-[11px] font-black uppercase tracking-widest text-[var(--ak-muted)]">{{ $label }}</p><p class="mt-2 text-2xl font-black {{ $color }}">{{ $value }}</p></div>
            @endforeach
        </div>
        <div class="overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-teal-500/10 text-[11px] font-black uppercase tracking-wider text-[var(--ak-muted)]"><tr><th class="px-4 py-3">Datum</th><th class="px-4 py-3">Aktie</th><th class="px-4 py-3">Übergang</th><th class="px-4 py-3">Einstieg</th><th class="px-4 py-3">KI-Score</th><th class="px-4 py-3">Konfidenz</th><th class="px-4 py-3">Risiko</th><th class="px-4 py-3">Schluss</th><th class="px-4 py-3">Performance</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Bericht</th></tr></thead><tbody class="divide-y divide-[var(--ak-border)]">
                @forelse($rows as $row)<tr class="hover:bg-teal-500/5"><td class="whitespace-nowrap px-4 py-3 text-[var(--ak-muted)]">{{ \Carbon\Carbon::parse($row->prediction_time)->format('d.m.Y H:i') }}</td><td class="px-4 py-3"><a class="font-black text-teal-600 hover:underline" href="{{ route('stocks.show', ['symbol' => $row->symbol, 'return_to' => '/predictions/signal-history']) }}">{{ $row->symbol }}</a><div class="text-xs text-[var(--ak-muted)]">{{ $row->name }}</div></td><td class="px-4 py-3"><span class="rounded-full border px-3 py-1 text-xs font-black {{ strtoupper($row->signal)==='BUY' ? 'border-emerald-400/50 text-emerald-600' : 'border-rose-400/50 text-rose-600' }}">{{ strtoupper($row->previous_signal) }} → {{ strtoupper($row->signal) }}</span></td><td class="px-4 py-3 font-bold text-[var(--ak-text)]">{{ number_format((float)$row->current_price,2,',','.') }} {{ $row->currency }}</td><td class="px-3 py-2"><div class="flex justify-center">@if($row->score_at_signal !== null)<div class="signal-history-donut" style="--donut-value:{{ max(0,min(100,$row->score_at_signal * 10)) }}%;--donut-color:#f28a45"><span>{{ number_format($row->score_at_signal,1,',','.') }}<small>/10</small></span></div>@else<span>—</span>@endif</div></td><td class="px-3 py-2"><div class="flex justify-center">@if($row->confidence_at_signal !== null)<div class="signal-history-donut" style="--donut-value:{{ max(0,min(100,$row->confidence_at_signal)) }}%;--donut-color:#06b6d4"><span>{{ number_format($row->confidence_at_signal,0,',','.') }}<small>%</small></span></div>@else<span>—</span>@endif</div></td><td class="px-3 py-2"><div class="flex justify-center">@if($row->risk_at_signal !== null)<div class="signal-history-donut" style="--donut-value:{{ max(0,min(100,$row->risk_at_signal)) }}%;--donut-color:#ef526b"><span>{{ number_format($row->risk_at_signal,0,',','.') }}<small>%</small></span></div>@else<span>—</span>@endif</div></td><td class="px-4 py-3 font-bold text-[var(--ak-text)]">{{ $row->closed && $row->validated_at ? \Carbon\Carbon::parse($row->validated_at)->format('d.m.Y') : '—' }}</td><td class="px-4 py-3 font-black {{ ($row->performance_percent ?? 0) >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $row->performance_percent !== null ? sprintf('%+.2f %%',$row->performance_percent) : '—' }}</td><td class="px-4 py-3 text-xs font-black uppercase tracking-wide {{ $row->closed ? 'text-teal-600' : 'text-amber-600' }}">{{ $row->closed ? 'geschlossen' : 'offen' }}</td><td class="px-4 py-3">@php $report = \App\Models\AnalysisReport::query()->where('prediction_id',$row->id)->latest('id')->first(); @endphp @if($report)<a target="_blank" rel="noopener" href="{{ route('analysis-reports.show',$report) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-amber-500/40 text-amber-500 hover:bg-amber-500/15" title="Bericht öffnen"><x-heroicon-o-document-text class="h-4 w-4" /></a>@else<span class="text-[var(--ak-muted)]">—</span>@endif</td></tr>@empty<tr><td colspan="11" class="px-4 py-14 text-center text-[var(--ak-muted)]">Keine BUY-/SELL-Signalwechsel im gewählten Zeitraum.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </div>
</x-app-layout>

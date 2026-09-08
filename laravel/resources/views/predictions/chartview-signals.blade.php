<x-app-layout>
    <main id="chartview-signals-page" class="ak-body min-h-[calc(100dvh-73px)] py-5">
        <div class="ak-container">
            <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-[10px] font-black uppercase tracking-[.2em] text-cyan-400">PRO · ChartView</p><h1 class="mt-1 text-3xl font-black text-[var(--ak-text)]">{{ __('ChartView-Signale') }}</h1><p class="mt-2 text-xs text-[var(--ak-muted)]">{{ __('Technische Signale und Chartmuster der letzten drei Handelstage mit globaler und aktienspezifischer 3-Jahres-Statistik.') }}</p></div>
                <div class="rounded-xl border border-cyan-400/20 bg-cyan-400/[.06] px-4 py-2 text-xs text-[var(--ak-muted)]">{{ __('Handelstage') }}: <b class="text-cyan-300">{{ $tradingDays->reverse()->map(fn($day) => \Illuminate\Support\Carbon::parse($day)->format('d.m.Y'))->implode(' · ') }}</b></div>
            </header>
            <details class="chartview-filter group mb-4 rounded-xl border border-cyan-400/20 bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-xs font-black text-cyan-300 marker:content-none">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />{{ __('Filter anzeigen') }}</span>
                    <x-heroicon-o-chevron-down class="h-4 w-4 transition group-open:rotate-180" />
                </summary>
            <form class="grid gap-2 border-t border-cyan-400/15 p-3 sm:grid-cols-2 lg:grid-cols-7">
                <input name="q" value="{{ request('q') }}" placeholder="{{ __('Aktie oder Symbol') }}" class="ak-input h-10 text-sm">
                <select name="event" class="ak-input h-10 text-sm"><option value="">{{ __('Alle ChartView-Signale') }}</option>@foreach($eventTypes as $type)<option value="{{ $type->event_key }}" @selected(request('event')===$type->event_key)>{{ app()->getLocale()==='en' ? $type->label_en : $type->label_de }}</option>@endforeach</select>
                <select name="scope" class="ak-input h-10 text-sm"><option value="">{{ __('Alle Datenbasen') }}</option><option value="instrument" @selected(request('scope')==='instrument')>{{ __('Aktienspezifisch') }}</option><option value="blended" @selected(request('scope')==='blended')>{{ __('Gewichtet') }}</option><option value="global" @selected(request('scope')==='global')>{{ __('Global') }}</option></select>
                <select name="tone" class="ak-input h-10 text-sm"><option value="">{{ __('Alle Richtungen') }}</option><option value="positive" @selected(request('tone')==='positive')>{{ __('Positiv') }}</option><option value="negative" @selected(request('tone')==='negative')>{{ __('Negativ') }}</option></select>
                <label class="relative"><span class="sr-only">{{ __('Zeitraum') }}</span><select name="days" class="ak-input h-10 w-full text-sm">@foreach([1,2,3] as $option)<option value="{{ $option }}" @selected($days===$option)>{{ trans_choice('Letzter Handelstag|Letzte :count Handelstage', $option, ['count'=>$option]) }}</option>@endforeach</select></label>
                <label class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-1"><span class="flex justify-between text-[8px] font-black uppercase text-[var(--ak-muted)]"><span>{{ __('Wahrscheinlichkeit ab') }}</span><output data-probability-output class="text-cyan-300">{{ number_format($minimumProbability,0,',','.') }} %</output></span><input type="range" name="min_probability" value="{{ $minimumProbability }}" min="0" max="100" step="1" oninput="this.closest('label').querySelector('[data-probability-output]').textContent=this.value+' %'" class="mt-1 h-2 w-full accent-cyan-400"></label>
                <button class="chartview-filter-submit rounded-lg border border-cyan-400/30 bg-transparent px-4 text-xs font-black text-cyan-300">{{ __('Filter anwenden') }}</button>
            </form>
            </details>
            <section class="chartview-table-shell overflow-hidden rounded-2xl border border-cyan-400/25 bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                <div class="max-h-[calc(100dvh-245px)] overflow-auto"><table class="chartview-table w-full min-w-[1120px] text-left text-xs" data-chartview-sortable><thead class="chartview-table-head sticky top-0 z-20 border-b text-[9px] font-black uppercase tracking-wide"><tr>
                    @foreach ([
                        ['date', __('Handelstag'), 'text-left'],
                        ['age', __('Signalalter'), 'text-left'],
                        ['stock', __('Aktie'), 'text-left'],
                        ['signal', 'ChartView', 'text-left'],
                        ['global', __('Global'), 'text-right chartview-col-global'],
                        ['instrument', __('Aktie'), 'text-right chartview-col-stock'],
                        ['weighted', __('Gewichtet'), 'text-right chartview-col-weighted'],
                        ['samples', __('Fälle Aktie'), 'text-right'],
                        ['scope', __('Datenbasis'), 'text-left'],
                    ] as [$sortKey, $heading, $alignment])
                        <th class="px-4 py-3 {{ $alignment }}" aria-sort="none"><button type="button" class="inline-flex w-full items-center gap-1.5 {{ str_contains($alignment, 'text-right') ? 'justify-end' : 'justify-start' }}" data-chartview-sort="{{ $sortKey }}"><span>{{ $heading }}</span><span class="text-[10px] opacity-45" data-sort-indicator>↕</span></button></th>
                    @endforeach
                    <th class="px-4 py-3 text-right">{{ __('Details') }}</th>
                </tr></thead><tbody class="divide-y divide-cyan-400/10" data-chartview-body>
                    @forelse($signals as $signal)
                        @php
                            $signalDate = \Illuminate\Support\Carbon::parse($signal->bar_time)->toDateString();
                            $signalAgeIndex = $tradingDays
                                ->map(fn ($day) => \Illuminate\Support\Carbon::parse($day)->toDateString())
                                ->search($signalDate);
                            $signalAge = $signalAgeIndex === false ? null : (int) $signalAgeIndex;
                        @endphp
                        <tr class="chartview-table-row transition" data-chartview-row data-sort-date="{{ \Illuminate\Support\Carbon::parse($signal->bar_time)->timestamp }}" data-sort-age="{{ $signalAge ?? 999 }}" data-sort-stock="{{ mb_strtolower($signal->symbol.' '.$signal->name) }}" data-sort-signal="{{ mb_strtolower(app()->getLocale()==='en' ? $signal->label_en : $signal->label_de) }}" data-sort-global="{{ is_numeric($signal->global_probability) ? $signal->global_probability : -1 }}" data-sort-instrument="{{ is_numeric($signal->instrument_probability) ? $signal->instrument_probability : -1 }}" data-sort-weighted="{{ is_numeric($signal->rise_probability) ? $signal->rise_probability : -1 }}" data-sort-samples="{{ (int) $signal->sample_size }}" data-sort-scope="{{ $signal->probability_scope }}"><td class="px-4 py-3 tabular-nums text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($signal->bar_time)->format('d.m.Y') }}</td><td class="px-4 py-3 font-bold text-[var(--ak-muted)]">{{ $signalAge === null ? '—' : ($signalAge === 0 ? __('Letzter Handelstag') : trans_choice(':count Handelstag|:count Handelstage', $signalAge, ['count'=>$signalAge])) }}</td><td class="px-4 py-3"><b class="font-black text-[var(--ak-text)]">{{ $signal->symbol }}</b><small class="block max-w-48 truncate text-[9px] text-[var(--ak-muted)]">{{ $signal->name }}</small></td><td class="chartview-signal px-4 py-3 font-bold {{ $signal->tone==='positive' ? 'chartview-signal--positive' : 'chartview-signal--negative' }}">{{ app()->getLocale()==='en' ? $signal->label_en : $signal->label_de }}</td><td class="chartview-col-global px-4 py-3 text-right tabular-nums">{{ is_numeric($signal->global_probability) ? number_format($signal->global_probability,1,',','.').' %' : '—' }}<small class="block text-[8px] text-[var(--ak-muted)]">n={{ number_format($signal->global_sample_size,0,',','.') }}</small></td><td class="chartview-col-stock px-4 py-3 text-right font-bold tabular-nums">{{ is_numeric($signal->instrument_probability) ? number_format($signal->instrument_probability,1,',','.').' %' : '—' }}</td><td class="chartview-col-weighted chartview-probability px-4 py-3 text-right text-sm font-black tabular-nums">{{ is_numeric($signal->rise_probability) ? number_format($signal->rise_probability,1,',','.').' %' : '—' }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($signal->sample_size,0,',','.') }}</td><td class="px-4 py-3"><span class="chartview-scope chartview-scope--{{ $signal->probability_scope }} rounded-md border px-2 py-1 text-[8px] font-black uppercase">{{ $signal->probability_scope==='instrument' ? __('Aktie') : ($signal->probability_scope==='blended' ? __('Gewichtet') : __('Global')) }}</span></td><td class="px-4 py-3 text-right"><a href="{{ route('stocks.show', ['symbol'=>$signal->symbol, 'prediction'=>$signal->prediction_id, 'analysis'=>1, 'return_to'=>request()->getRequestUri()]) }}" class="chartview-detail inline-flex items-center gap-1 rounded-lg border px-2.5 py-2 text-[9px] font-black">{{ __('Aktiendetails') }} <span>→</span></a></td></tr>
                    @empty<tr><td colspan="10" class="px-4 py-12 text-center text-[var(--ak-muted)]">{{ __('Keine ChartView-Signale für diese Auswahl gefunden.') }}</td></tr>@endforelse
                </tbody></table></div>
                @if($signals->hasPages())<div class="border-t border-cyan-400/15 px-4 py-3">{{ $signals->links() }}</div>@endif
            </section>
        </div>
    </main>
    <style>
        #chartview-signals-page .chartview-scope {
            display: inline-flex;
            width: 5.25rem;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-filter,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-filter form {
            border-color: rgba(251, 146, 60, .26) !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-filter summary {
            color: #fb923c !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-filter :is(.ak-input, label) {
            border-color: rgba(251, 191, 36, .20) !important;
            background-color: transparent !important;
            box-shadow: none !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-filter-submit {
            border-color: rgba(251, 146, 60, .45) !important;
            background: transparent !important;
            color: #fb923c !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-shell {
            border-color: rgba(251, 146, 60, .34) !important;
            background: transparent !important;
            box-shadow: inset 3px 0 0 rgba(251, 146, 60, .70), 0 16px 36px rgba(0, 0, 0, .22) !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-head {
            border-color: rgba(251, 191, 36, .20) !important;
            background: #26324d !important;
            color: #f8fafc !important;
            box-shadow: 0 1px 0 rgba(251, 146, 60, .20), 0 8px 18px rgba(0, 0, 0, .22);
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table tbody,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-row,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-row td {
            background: transparent !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-row td {
            border-bottom: 1px solid rgba(251, 191, 36, .14) !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-row:hover td {
            background: rgba(66, 78, 108, .46) !important;
            border-bottom-color: rgba(251, 146, 60, .46) !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-col-stock,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-col-weighted,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-probability {
            color: #fb923c !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-detail {
            border-color: rgba(251, 146, 60, .34) !important;
            background: transparent !important;
            color: #fb923c !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-detail:hover {
            border-color: rgba(251, 146, 60, .62) !important;
            background: rgba(251, 146, 60, .08) !important;
        }
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-shell > div:last-child,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-shell nav,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-shell nav a,
        :root:not([data-theme="light"]) #chartview-signals-page .chartview-table-shell nav span > span {
            background: transparent !important;
            box-shadow: none !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-chartview-sortable]').forEach((table) => {
                const body = table.querySelector('[data-chartview-body]');
                if (!body) return;
                let activeKey = null;
                let direction = 1;
                table.querySelectorAll('[data-chartview-sort]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const key = button.dataset.chartviewSort;
                        direction = activeKey === key ? direction * -1 : 1;
                        activeKey = key;
                        const rows = [...body.querySelectorAll('[data-chartview-row]')];
                        rows.sort((left, right) => {
                            const a = left.dataset[`sort${key.charAt(0).toUpperCase()}${key.slice(1)}`] ?? '';
                            const b = right.dataset[`sort${key.charAt(0).toUpperCase()}${key.slice(1)}`] ?? '';
                            const numeric = !Number.isNaN(Number(a)) && !Number.isNaN(Number(b));
                            return direction * (numeric ? Number(a) - Number(b) : a.localeCompare(b, document.documentElement.lang || 'de', { sensitivity: 'base' }));
                        });
                        rows.forEach((row) => body.appendChild(row));
                        table.querySelectorAll('th[aria-sort]').forEach((header) => header.setAttribute('aria-sort', 'none'));
                        table.querySelectorAll('[data-sort-indicator]').forEach((indicator) => indicator.textContent = '↕');
                        button.closest('th').setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');
                        button.querySelector('[data-sort-indicator]').textContent = direction === 1 ? '↑' : '↓';
                    });
                });
            });
        });
    </script>
</x-app-layout>

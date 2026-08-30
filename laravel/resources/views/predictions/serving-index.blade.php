<x-app-layout>
    <main class="ak-body min-h-[calc(100dvh-73px)] py-5 sm:py-8">
        <div class="ak-container">
            <header class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.22em] text-cyan-500">Serving · neue Remote-Datenbank</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)] sm:text-3xl">Aktien, Modelle und Predictions</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-[var(--ak-muted)]">
                        Angezeigt werden ausschließlich aktive Releases und freigegebene Predictions der neuen Datenbank. Nicht geeignete Modell-/Horizont-Kombinationen erzeugen bewusst keine Prediction.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ([
                        ['label' => 'Aktive Aktien', 'value' => $summary->active_stocks, 'tone' => 'text-cyan-400'],
                        ['label' => 'Freigegebene Horizonte', 'value' => $summary->eligible_horizons, 'tone' => 'text-emerald-400'],
                        ['label' => 'Prediction-Zeilen', 'value' => $summary->published_predictions, 'tone' => 'text-violet-400'],
                        ['label' => 'Aktien mit Prediction', 'value' => $summary->stocks_with_predictions, 'tone' => 'text-amber-400'],
                    ] as $metric)
                        <div class="min-w-32 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-right">
                            <b class="block text-xl font-black tabular-nums {{ $metric['tone'] }}">{{ number_format($metric['value'], 0, ',', '.') }}</b>
                            <small class="block text-[8px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ $metric['label'] }}</small>
                        </div>
                    @endforeach
                </div>
            </header>

            <section class="ak-card mb-4 p-3 sm:p-4">
                <form method="GET" class="grid gap-2 md:grid-cols-3 xl:grid-cols-7">
                    <input name="q" value="{{ request('q') }}" placeholder="Name, Symbol oder ISIN" class="ak-input xl:col-span-2" />
                    <select name="country" class="ak-input"><option value="">Alle Länder</option>@foreach($countries as $item)<option value="{{ $item }}" @selected(request('country') === $item)>{{ $item }}</option>@endforeach</select>
                    <select name="exchange" class="ak-input"><option value="">Alle Börsen</option>@foreach($exchanges as $item)<option value="{{ $item }}" @selected(request('exchange') === $item)>{{ $item }}</option>@endforeach</select>
                    <select name="sector" class="ak-input"><option value="">Alle Sektoren</option>@foreach($sectors as $item)<option value="{{ $item }}" @selected(request('sector') === $item)>{{ __($item) }}</option>@endforeach</select>
                    <select name="quality" class="ak-input">
                        <option value="">Alle Qualitätsklassen</option>
                        @foreach(['quality' => 'Quality', 'solid' => 'Solid', 'basic' => 'Basic', 'underperform' => 'Nicht qualifiziert', 'open' => 'Offen'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('quality') === $key)>{{ $label }} ({{ $summary->quality_counts[$key] ?? 0 }})</option>
                        @endforeach
                    </select>
                    <select name="status" class="ak-input">
                        <option value="">Alle Zustände</option>
                        <option value="eligible" @selected(request('status') === 'eligible')>Prediction freigegeben</option>
                        <option value="blocked" @selected(request('status') === 'blocked')>Prediction gesperrt</option>
                        <option value="published" @selected(request('status') === 'published')>Prediction vorhanden</option>
                        <option value="waiting" @selected(request('status') === 'waiting')>Noch keine Prediction</option>
                    </select>
                    <div class="flex gap-2 xl:col-span-7 xl:justify-end"><button class="ak-button-primary" type="submit">Anwenden</button><a class="ak-button-secondary" href="{{ route('predictions.index') }}">Zurücksetzen</a></div>
                </form>
            </section>

            <section class="ak-card overflow-hidden border-cyan-400/25">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1320px] border-collapse text-left">
                        <thead class="bg-cyan-400/[.055] text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                            @php
                                $sortUrl = fn(string $column) => route('predictions.index', array_merge(request()->except('page'), [
                                    'sort' => $column,
                                    'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                                ]));
                            @endphp
                            <tr>
                                <th class="px-4 py-3"><a href="{{ $sortUrl('stock') }}">Aktie {{ $sort === 'stock' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                <th class="px-3 py-3"><a href="{{ $sortUrl('quality') }}">Qualität {{ $sort === 'quality' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                @foreach([10,20,40] as $horizon)<th class="px-3 py-3">{{ $horizon }}T · Champion / Backtest</th>@endforeach
                                <th class="px-3 py-3"><a href="{{ $sortUrl('predictions') }}">Letzte Prediction {{ $sort === 'predictions' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                                <th class="px-3 py-3"><a href="{{ $sortUrl('released') }}">Release {{ $sort === 'released' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                        @forelse($stocks as $stock)
                            @php
                                $qualityTone = match($stock->quality_class) {
                                    'quality' => 'border-emerald-400/40 text-emerald-400',
                                    'solid' => 'border-cyan-400/40 text-cyan-400',
                                    'basic' => 'border-amber-400/40 text-amber-400',
                                    'underperform' => 'border-rose-400/40 text-rose-400',
                                    default => 'border-slate-400/30 text-slate-400',
                                };
                            @endphp
                            <tr class="align-top text-xs text-[var(--ak-text)] hover:bg-cyan-400/[.025]">
                                <td class="px-4 py-3">
                                    <a href="{{ route('stocks.show', ['symbol' => $stock->symbol]) }}" class="block max-w-64 truncate font-black hover:text-cyan-400">{{ $stock->name ?: $stock->symbol }}</a>
                                    <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">{{ $stock->country_code }} · {{ $stock->exchange }} · {{ $stock->symbol }}</small>
                                    <small class="block text-[8px] text-[var(--ak-muted)]">{{ $stock->sector_code ?: '—' }}</small>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-md border px-2 py-1 text-[9px] font-black uppercase {{ $qualityTone }}">{{ $stock->quality_class === 'underperform' ? 'Nicht qual.' : $stock->quality_class }}</span>
                                    <small class="mt-2 block text-[8px] text-[var(--ak-muted)]">{{ $stock->eligible_horizon_count }}/{{ count($stock->completed_horizons) }} Horizonte freigegeben</small>
                                </td>
                                @foreach([10,20,40] as $horizon)
                                    @php $scope = $stock->horizons->get($horizon); @endphp
                                    <td class="px-3 py-3">
                                        @if($scope)
                                            <div class="flex items-center gap-2">
                                                <b class="{{ $scope->variant === 'pure_tcn' ? 'text-violet-400' : 'text-cyan-400' }}">{{ $scope->variant_label }}</b>
                                                <span class="rounded border px-1.5 py-0.5 text-[8px] font-black uppercase {{ $scope->prediction_enabled ? 'border-emerald-400/35 text-emerald-400' : 'border-rose-400/30 text-rose-400' }}">{{ $scope->prediction_enabled ? 'aktiv' : 'gesperrt' }}</span>
                                            </div>
                                            <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">HR {{ is_numeric($scope->metrics->hit_rate) ? number_format($scope->metrics->hit_rate, 1, ',', '.').' %' : '—' }} · PF {{ is_numeric($scope->metrics->profit_factor) ? number_format($scope->metrics->profit_factor, 2, ',', '.') : '—' }}</small>
                                            <small class="block text-[8px] text-[var(--ak-muted)]">{{ $scope->metrics->trades }} Trades · Ø {{ is_numeric($scope->metrics->average_return) ? sprintf('%+.2f %%', $scope->metrics->average_return) : '—' }}</small>
                                        @else
                                            <span class="text-[var(--ak-muted)]">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-3">
                                    @if($stock->latest_prediction)
                                        @php $prediction = $stock->latest_prediction; @endphp
                                        <b class="{{ $prediction->signal === 'BUY' ? 'text-emerald-400' : ($prediction->signal === 'SELL' ? 'text-rose-400' : 'text-amber-400') }}">{{ $prediction->signal }} · {{ $prediction->horizon }}T</b>
                                        <small class="mt-1 block text-[9px] text-[var(--ak-muted)]">{{ is_numeric($prediction->expected_return_percent) ? sprintf('%+.2f %%', $prediction->expected_return_percent) : '—' }} · {{ \Illuminate\Support\Carbon::parse($prediction->as_of)->format('d.m.Y H:i') }}</small>
                                    @else
                                        <b class="text-[var(--ak-muted)]">Keine Prediction</b>
                                        <small class="mt-1 block max-w-48 text-[8px] leading-4 text-[var(--ak-muted)]">Kein freigegebener Horizont oder noch kein neuer Prediction-Lauf.</small>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <b class="block text-[10px]">{{ $stock->pipeline_version }}</b>
                                    <small class="mt-1 block text-[8px] text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($stock->released_at)->format('d.m.Y H:i') }}</small>
                                    <small class="block text-[8px] text-[var(--ak-muted)]">Cutoff {{ \Illuminate\Support\Carbon::parse($stock->dataset_cutoff)->format('d.m.Y') }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-14 text-center text-sm text-[var(--ak-muted)]">Keine aktiven Releases mit diesen Filtern vorhanden.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <div class="mt-5">{{ $stocks->links() }}</div>
            <p class="mt-3 text-[9px] text-[var(--ak-muted)]">Quelle: aktienki_serving_next · kein Fallback auf die alte Predictions-Tabelle.</p>
        </div>
    </main>
</x-app-layout>

<x-app-layout>
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300">
                    <x-heroicon-o-list-bullet class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-400">{{ __('Backtest-Trades') }}</p>
                    <h1 class="text-2xl font-black">
                        {{ __('Modellscore :from–:to · Konfidenz :confidenceFrom–:confidenceTo %', [
                            'from' => $scoreBucket,
                            'to' => $scoreBucket + 1,
                            'confidenceFrom' => $confidenceBucket * 10,
                            'confidenceTo' => ($confidenceBucket + 1) * 10,
                        ]) }}
                    </h1>
                    <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ number_format($trades->count(), 0, ',', '.') }} {{ __('abgeschlossene Trades aus dem aktuellen Backtest-Lauf') }}</p>
                </div>
            </div>
            <a href="{{ route('predictions.heatmap', request()->except(['score_bucket', 'confidence_bucket'])) }}" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] hover:border-teal-500/35 hover:text-teal-400">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('Zurück zur Heatmap') }}
            </a>
        </header>

        <section class="mb-3 grid shrink-0 grid-cols-2 gap-3">
            @php
                $profitPositive = $tradeStats->absolute_profit >= 0;
                $performancePositive = $tradeStats->average_performance >= 0;
            @endphp
            <article class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3 shadow-[var(--ak-shadow)]">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Absoluter Profit') }}</p>
                        <p class="mt-1 text-xl font-black tabular-nums {{ $profitPositive ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $profitPositive ? '+' : '' }}{{ number_format($tradeStats->absolute_profit, 2, ',', '.') }} €
                        </p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $profitPositive ? 'bg-emerald-400/10 text-emerald-400' : 'bg-rose-400/10 text-rose-400' }}">
                        <x-heroicon-o-banknotes class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ __('Normiert auf :amount € Einsatz je Trade', ['amount' => number_format($tradeStats->normalized_position, 0, ',', '.')]) }}</p>
            </article>

            <article class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3 shadow-[var(--ak-shadow)]">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Ø Performance je Trade') }}</p>
                        <p class="mt-1 text-xl font-black tabular-nums {{ $performancePositive ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $performancePositive ? '+' : '' }}{{ number_format($tradeStats->average_performance, 2, ',', '.') }} %
                        </p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $performancePositive ? 'bg-emerald-400/10 text-emerald-400' : 'bg-rose-400/10 text-rose-400' }}">
                        <x-heroicon-o-arrow-trending-up class="h-5 w-5" />
                    </div>
                </div>
                <p class="mt-1 text-[9px] text-[var(--ak-muted)]">{{ $tradeStats->winning_trades }} / {{ $trades->count() }} {{ __('Trades positiv') }}</p>
            </article>
        </section>

        <section class="min-h-0 flex-1 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <div class="h-full overflow-y-auto">
                @php
                    $sortUrl = fn (string $column): string => route('predictions.heatmap.trades', array_merge(
                        request()->query(),
                        [
                            'sort' => $column,
                            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                        ],
                    ));
                    $sortIndicator = fn (string $column): string => $sort === $column
                        ? ($direction === 'asc' ? '↑' : '↓')
                        : '↕';
                    $columns = [
                        ['entry', __('Einstieg'), 'text-left'],
                        ['stock', __('Aktie'), 'text-left'],
                        ['exchange', __('Börse'), 'text-left'],
                        ['entry_price', __('Einstiegskurs'), 'text-right'],
                        ['exit_price', __('Exit-Kurs'), 'text-right'],
                        ['return', __('Rendite'), 'text-right'],
                        ['drawdown', __('Drawdown'), 'text-center'],
                        ['score', __('Modellscore'), 'text-center'],
                        ['confidence', __('Konfidenz'), 'text-center'],
                        ['model', __('Modell'), 'text-left'],
                    ];
                @endphp
                <table class="w-full table-fixed border-separate border-spacing-0 text-left text-xs">
                    <colgroup>
                        <col class="w-[11%]"><col class="w-[15%]"><col class="w-[10%]">
                        <col class="w-[10%]"><col class="w-[10%]"><col class="w-[10%]">
                        <col class="w-[9%]"><col class="w-[9%]"><col class="w-[8%]"><col class="w-[8%]">
                    </colgroup>
                    <thead class="sticky top-0 z-10 bg-[#182238] text-[10px] font-black uppercase tracking-[.1em] text-slate-300 shadow-[0_1px_0_var(--ak-border)]">
                        <tr>
                            @foreach ($columns as [$column, $label, $alignment])
                                <th class="px-3 py-3 {{ $alignment }}">
                                    <a href="{{ $sortUrl($column) }}" class="inline-flex max-w-full items-center gap-1 whitespace-nowrap hover:text-teal-300 {{ $sort === $column ? 'text-teal-300' : '' }}">
                                        <span class="truncate">{{ $label }}</span>
                                        <span class="inline-block w-3 shrink-0 text-center text-[10px] {{ $sort === $column ? 'text-teal-300' : 'text-slate-500' }}">{{ $sortIndicator($column) }}</span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trades as $trade)
                            @php $positive = (float) $trade->net_return >= 0; @endphp
                            <tr class="odd:bg-white/[.018] even:bg-teal-500/[.025]">
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 tabular-nums">{{ \Illuminate\Support\Carbon::parse($trade->entry_date)->format('d.m.Y') }}</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5">
                                    <strong class="block truncate text-sm">{{ $trade->symbol }}</strong>
                                    <span class="block truncate text-[10px] text-[var(--ak-muted)]">{{ $trade->name }}</span>
                                </td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5">{{ $trade->exchange_code ?: '—' }}</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 text-right tabular-nums">{{ number_format((float) $trade->entry_price, 2, ',', '.') }}</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 text-right tabular-nums">{{ number_format((float) $trade->exit_price, 2, ',', '.') }}</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 text-right font-black tabular-nums {{ $positive ? 'text-emerald-400' : 'text-rose-400' }}">{{ $positive ? '+' : '' }}{{ number_format((float) $trade->net_return * 100, 2, ',', '.') }} %</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 text-center tabular-nums">{{ number_format((float) $trade->max_drawdown * 100, 1, ',', '.') }} %</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 text-center font-black tabular-nums">{{ number_format((float) $trade->ki_score, 1, ',', '.') }}</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5 text-center font-black tabular-nums">{{ number_format((float) $trade->confidence, 0, ',', '.') }} %</td>
                                <td class="border-b border-[var(--ak-border)] px-3 py-2.5"><span class="block truncate">{{ $trade->model_alias ?: '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-6 py-16 text-center text-[var(--ak-muted)]">{{ __('Für dieses Heatmap-Feld wurden keine Trades gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>

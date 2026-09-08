<x-app-layout>
    <style>
        :root[data-theme="light"] .news-screener-table thead { background:#e7f1f3 !important; color:#334155 !important; }
        :root[data-theme="light"] .news-screener-row { background:rgba(255,255,255,.82); }
        :root[data-theme="light"] .news-screener-row:nth-child(even) { background:rgba(238,247,248,.92); }
        :root[data-theme="light"] .news-screener-row:hover { background:#e2f4f6 !important; }
        :root[data-theme="light"] .news-screener-row[data-news-sentiment="positive"] { box-shadow:inset 4px 0 #10b981; }
        :root[data-theme="light"] .news-screener-row[data-news-sentiment="negative"] { box-shadow:inset 4px 0 #f43f5e; }
        :root[data-theme="light"] .news-screener-row[data-news-sentiment="neutral"] { box-shadow:inset 4px 0 #f59e0b; }
        :root[data-theme="light"] .news-sentiment--positive { background:#d1fae5 !important; border-color:#6ee7b7 !important; color:#047857 !important; }
        :root[data-theme="light"] .news-sentiment--negative { background:#ffe4e6 !important; border-color:#fda4af !important; color:#be123c !important; }
        :root[data-theme="light"] .news-sentiment--neutral { background:#fef3c7 !important; border-color:#fcd34d !important; color:#a16207 !important; }
        :root[data-theme="light"] .news-sentiment--unrated { background:#e2e8f0 !important; border-color:#cbd5e1 !important; color:#475569 !important; }
        :root[data-theme="light"] .news-relevance { background:#cffafe; border-color:#67e8f9; color:#0e7490; }
        :root:not([data-theme="light"]) .news-screener-row:nth-child(even) { background:rgba(34,211,238,.025); }
        :root:not([data-theme="light"]) .news-screener-row[data-news-sentiment="positive"] { box-shadow:inset 4px 0 #34d399, inset 10px 0 18px -18px rgba(52,211,153,.9); }
        :root:not([data-theme="light"]) .news-screener-row[data-news-sentiment="negative"] { box-shadow:inset 4px 0 #fb7185, inset 10px 0 18px -18px rgba(251,113,133,.9); }
        :root:not([data-theme="light"]) .news-screener-row[data-news-sentiment="neutral"] { box-shadow:inset 4px 0 #fbbf24, inset 10px 0 18px -18px rgba(251,191,36,.9); }
        :root:not([data-theme="light"]) .news-screener-row[data-news-sentiment="unrated"] { box-shadow:inset 4px 0 #64748b; }
        .news-screener-row[data-news-personal="true"] { box-shadow:inset 6px 0 #f59e0b, inset 18px 0 28px -30px rgba(245,158,11,.95) !important; }
        :root[data-theme="light"] .news-screener-row[data-news-personal="true"] { background:linear-gradient(90deg,rgba(254,243,199,.78),rgba(255,255,255,.86) 20%) !important; }
        :root:not([data-theme="light"]) .news-screener-row[data-news-personal="true"] { background:linear-gradient(90deg,rgba(245,158,11,.11),rgba(245,158,11,.015) 22%) !important; }
        @media (max-width: 767px) {
            .news-screener { height:auto !important; min-height:0; overflow:visible !important; }
            .news-filter-form { flex-direction:column; align-items:stretch; overflow:visible; }
            .news-filter-form > * { width:100%; min-width:0 !important; }
            .news-table-shell { overflow:visible; border:0; }
            .news-screener-table { display:block; min-width:0 !important; }
            .news-screener-table thead { display:none; }
            .news-screener-table tbody { display:grid; gap:.75rem; }
            .news-screener-row {
                display:grid;
                grid-template-columns:minmax(0,1fr) auto;
                gap:.65rem .75rem;
                overflow:hidden;
                border:1px solid var(--ak-border);
                border-radius:1rem;
                padding:1rem 1rem 1rem 1.2rem;
            }
            .news-screener-row > td { min-width:0; padding:0; }
            .news-cell-time { grid-column:1; grid-row:1; align-self:center; }
            .news-cell-sentiment { grid-column:2; grid-row:1; text-align:right; }
            .news-cell-stock { grid-column:1 / -1; grid-row:2; border-top:1px solid var(--ak-border); padding-top:.7rem !important; }
            .news-cell-message { grid-column:1 / -1; grid-row:3; }
            .news-cell-message p { white-space:normal; }
            .news-cell-relevance { grid-column:1; grid-row:4; display:flex; align-items:center; gap:.5rem; }
            .news-cell-relevance::before { content:"{{ __('Relevanz') }}"; font-size:.55rem; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ak-muted); }
            .news-cell-details { grid-column:2; grid-row:4; align-self:center; text-align:right; }
        }
    </style>
    <div class="news-screener flex h-[calc(100dvh-89px)] min-h-0 flex-col overflow-hidden py-2 text-[var(--ak-text)]">
        <header class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl border border-cyan-400/25 text-cyan-400"><x-heroicon-o-newspaper class="h-5 w-5" /></span>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Unternehmensmeldungen') }}</p>
                    <h1 class="text-xl font-black">{{ __('News-Screener') }}</h1>
                </div>
            </div>
            <div class="grid grid-cols-4 gap-1.5">
                @foreach ([
                    [__('Meldungen'), (int) ($summary->total ?? 0), 'text-cyan-400'],
                    [__('Aktien'), (int) ($summary->stocks ?? 0), 'text-[var(--ak-text)]'],
                    [__('Positiv'), (int) ($summary->positive ?? 0), 'text-emerald-400'],
                    [__('Negativ'), (int) ($summary->negative ?? 0), 'text-rose-400'],
                ] as [$label, $value, $class])
                    <div class="min-w-20 rounded-lg border border-[var(--ak-border)] px-2.5 py-1.5">
                        <p class="text-[7px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">{{ $label }}</p>
                        <p class="text-sm font-black tabular-nums {{ $class }}">{{ number_format($value, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </header>

        <section x-data="{ filtersOpen: false }" class="flex min-h-0 flex-1 flex-col gap-2">
            <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" class="flex h-10 shrink-0 items-center justify-between rounded-xl border border-cyan-400/25 px-4 text-xs font-black text-cyan-400">
                <span class="inline-flex items-center gap-2"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" />{{ __('Filter anzeigen') }}</span>
                <x-heroicon-o-chevron-down class="h-4 w-4 transition" x-bind:class="filtersOpen && 'rotate-180'" />
            </button>
            <form method="GET" action="{{ route('news.index') }}" x-cloak x-show="filtersOpen" class="news-filter-form flex shrink-0 flex-nowrap items-center gap-2 overflow-x-auto rounded-xl border border-[var(--ak-border)] p-2">
                <label class="relative min-w-[260px] flex-1"><span class="sr-only">{{ __('Aktie oder Meldung suchen') }}</span><x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--ak-muted)]" /><input name="q" value="{{ $search }}" placeholder="{{ __('Aktie oder Meldung suchen') }}" class="h-10 w-full rounded-lg border border-[var(--ak-border)] bg-transparent pl-9 pr-3 text-xs" /></label>
                <select name="days" class="h-10 min-w-[145px] shrink-0 rounded-lg border border-[var(--ak-border)] bg-transparent px-3 text-xs font-bold">
                    @foreach ([7, 14, 30, 90, 365] as $option)<option value="{{ $option }}" @selected($days === $option)>{{ __('Letzte :days Tage', ['days' => $option]) }}</option>@endforeach
                </select>
                <select name="sentiment" class="h-10 min-w-[155px] shrink-0 rounded-lg border border-[var(--ak-border)] bg-transparent px-3 text-xs font-bold">
                    <option value="">{{ __('Alle Stimmungen') }}</option>
                    <option value="positive" @selected($sentiment === 'positive')>{{ __('Positiv') }}</option><option value="neutral" @selected($sentiment === 'neutral')>{{ __('Neutral') }}</option><option value="negative" @selected($sentiment === 'negative')>{{ __('Negativ') }}</option><option value="unrated" @selected($sentiment === 'unrated')>{{ __('Nicht bewertet') }}</option>
                </select>
                <label class="flex h-10 min-w-[220px] shrink-0 items-center gap-2 rounded-lg border border-[var(--ak-border)] px-3 text-[10px] font-black"><span class="whitespace-nowrap">{{ __('Relevanz ab') }}</span><input type="range" name="relevance_min" min="0" max="100" step="10" value="{{ $minimumRelevance }}" oninput="this.nextElementSibling.textContent=this.value" class="min-w-0 flex-1 accent-cyan-400" /><output class="w-6 text-right text-cyan-400">{{ $minimumRelevance }}</output></label>
                <button class="h-10 shrink-0 rounded-lg bg-cyan-400 px-5 text-xs font-black text-slate-950">{{ __('Anwenden') }}</button>
                <a href="{{ route('news.index', ['sort' => $sort, 'direction' => $direction]) }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-[var(--ak-border)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-rose-400/40 hover:bg-rose-400/[.07] hover:text-rose-400">
                    <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Zurücksetzen') }}
                </a>
                <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="direction" value="{{ $direction }}">
            </form>

            <div class="news-table-shell min-h-0 flex-1 overflow-auto rounded-xl border border-[var(--ak-border)]">
                <table class="news-screener-table w-full min-w-[1050px] border-collapse text-left">
                    <thead class="sticky top-0 z-10 bg-[var(--ak-card-strong)] text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                        <tr>
                            @php
                                $sortLink = fn (string $column) => route('news.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $sort === $column && $direction === 'desc' ? 'asc' : 'desc']));
                            @endphp
                            @foreach ([
                                'published_at' => __('Zeitpunkt'),
                                'symbol' => __('Aktie'),
                                'headline' => __('Meldung'),
                                'sentiment_score' => __('Stimmung'),
                                'relevance_score' => __('Relevanz'),
                            ] as $sortColumn => $sortLabel)
                                <th class="px-3 py-2" aria-sort="{{ $sort === $sortColumn ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a href="{{ $sortLink($sortColumn) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md px-2 transition hover:bg-cyan-400/10 hover:text-cyan-500 {{ $sort === $sortColumn ? 'bg-cyan-400/10 text-cyan-500' : '' }}">
                                        <span>{{ $sortLabel }}</span>
                                        @if ($sort === $sortColumn)
                                            @if ($direction === 'asc')<x-heroicon-o-arrow-up class="h-3.5 w-3.5" />@else<x-heroicon-o-arrow-down class="h-3.5 w-3.5" />@endif
                                        @else
                                            <x-heroicon-o-chevron-up-down class="h-3.5 w-3.5 opacity-45" />
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                            <th class="px-3 py-3">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ak-border)]">
                        @forelse ($newsItems as $item)
                            @php
                                $score = is_numeric($item->sentiment_score) ? (float) $item->sentiment_score : null;
                                [$sentimentKey, $sentimentLabel, $sentimentClass] = match (true) {
                                    $score === null => ['unrated', __('Offen'), 'news-sentiment--unrated border-slate-400/25 text-[var(--ak-muted)]'],
                                    $score >= .35 => ['positive', __('Positiv'), 'news-sentiment--positive border-emerald-400/30 bg-emerald-400/10 text-emerald-400'],
                                    $score <= -.35 => ['negative', __('Negativ'), 'news-sentiment--negative border-rose-400/30 bg-rose-400/10 text-rose-400'],
                                    default => ['neutral', __('Neutral'), 'news-sentiment--neutral border-amber-400/30 bg-amber-400/10 text-amber-400'],
                                };
                                $summaryText = app()->getLocale() === 'en' ? ($item->ai_summary_en ?: $item->ai_summary_de) : ($item->ai_summary_de ?: $item->ai_summary_en);
                            @endphp
                            <tr class="news-screener-row transition-colors" data-news-sentiment="{{ $sentimentKey }}" data-news-personal="{{ $item->is_personal ? 'true' : 'false' }}">
                                <td class="news-cell-time whitespace-nowrap px-3 py-2.5 text-[10px] font-bold text-[var(--ak-muted)]">{{ $item->published_at ? \Illuminate\Support\Carbon::parse($item->published_at)->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}</td>
                                <td class="news-cell-stock px-3 py-2.5">
                                    @php
                                        $currentSignal = strtoupper((string) ($item->current_signal ?: 'HOLD'));
                                        $signalClass = match ($currentSignal) {
                                            'BUY' => 'border-emerald-400/35 bg-emerald-400/10 text-emerald-400',
                                            'SELL' => 'border-rose-400/35 bg-rose-400/10 text-rose-400',
                                            'WATCH', 'WAIT' => 'border-amber-400/35 bg-amber-400/10 text-amber-400',
                                            default => 'border-slate-400/30 bg-slate-400/10 text-[var(--ak-muted)]',
                                        };
                                    @endphp
                                    <a href="{{ route('stocks.show', ['symbol' => $item->symbol, 'return_to' => request()->getRequestUri()]) }}" class="block text-xs font-black leading-4 text-cyan-400 hover:underline">{{ $item->name }}</a>
                                    <div class="mt-1.5 flex flex-nowrap items-center gap-1 text-[8px] font-black">
                                        <span class="whitespace-nowrap rounded border border-cyan-400/25 bg-cyan-400/[.07] px-1.5 py-0.5 text-cyan-400">{{ __('Score') }} {{ is_numeric($item->current_ai_score) ? number_format((float) $item->current_ai_score, 1, ',', '.') : '—' }}</span>
                                        <span class="whitespace-nowrap rounded border border-violet-400/25 bg-violet-400/[.07] px-1.5 py-0.5 text-violet-400">{{ __('Global') }} #{{ is_numeric($item->global_rank) ? number_format((int) $item->global_rank, 0, ',', '.') : '—' }}</span>
                                        <span class="whitespace-nowrap rounded border px-1.5 py-0.5 {{ $signalClass }}">{{ $currentSignal }}</span>
                                    </div>
                                    @if ($item->is_personal)
                                        <div class="mt-1 flex items-center gap-1 text-[8px] font-black text-amber-400" title="{{ implode(' · ', $item->personal_reasons) }}">
                                            <x-heroicon-o-star class="h-3 w-3 fill-current" /><span class="max-w-44 truncate">{{ implode(' · ', $item->personal_reasons) }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="news-cell-message max-w-xl px-3 py-2.5"><p class="truncate text-xs font-black">{{ $item->headline }}</p>@if($summaryText)<p class="mt-1 line-clamp-2 text-[10px] leading-4 text-[var(--ak-muted)]">{{ $summaryText }}</p>@endif</td>
                                <td class="news-cell-sentiment px-3 py-2.5"><span class="rounded-md border px-2.5 py-1 text-[9px] font-black shadow-sm {{ $sentimentClass }}">{{ $sentimentLabel }}</span></td>
                                <td class="news-cell-relevance px-3 py-2.5"><span class="news-relevance inline-flex min-w-16 justify-center rounded-md border border-cyan-400/25 bg-cyan-400/[.08] px-2 py-1 text-[10px] font-black tabular-nums text-cyan-400">{{ is_numeric($item->relevance_score) ? $item->relevance_score.'/100' : '—' }}</span></td>
                                <td class="news-cell-details px-3 py-2.5"><a href="{{ route('stocks.show', ['symbol' => $item->symbol, 'return_to' => request()->getRequestUri()]) }}#stock-section-news" class="inline-flex items-center gap-1 whitespace-nowrap text-[10px] font-black text-cyan-400">{{ __('Aktiendetails') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" /></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-[var(--ak-muted)]">{{ __('Für diese Filter wurden keine Meldungen gefunden.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($newsItems->hasPages())<div class="shrink-0">{{ $newsItems->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>

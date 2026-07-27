<x-app-layout>
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 flex-col gap-4 border-b border-[var(--ak-border)] pb-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-violet-400/25 bg-violet-500/10 text-violet-300">
                    <x-heroicon-o-building-office-2 class="h-6 w-6" />
                </span>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-violet-300">{{ __('Marktstruktur') }}</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight">{{ __('Sektoren') }}</h1>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Aggregierte KI-Auswertung der aktuell analysierten Aktien.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 py-2.5 text-xs text-[var(--ak-muted)]">
                <strong class="text-[var(--ak-text)]">{{ $sectors->count() }}</strong> {{ __('Sektoren') }}
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto pr-1">
            <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,25rem),25rem))] gap-4 pb-2">
                @forelse ($sectors as $sector)
                @php
                    $scorePercent = \App\Support\AiScore::toPercent($sector->average_score);
                    $scoreChange = is_numeric($sector->five_day_score_change)
                        ? \App\Support\AiScore::toTen($sector->average_score) - \App\Support\AiScore::toTen($sector->five_day_baseline_score)
                        : null;
                    $scoreDirection = $scoreChange === null || abs($scoreChange) < 0.05 ? 'stable' : ($scoreChange > 0 ? 'up' : 'down');
                    $pe = is_numeric($sector->average_pe) ? (float) $sector->average_pe : null;
                    $profitMargin = is_numeric($sector->average_profit_margin) ? (float) $sector->average_profit_margin * 100 : null;
                    $revenueGrowth = is_numeric($sector->average_revenue_growth) ? (float) $sector->average_revenue_growth * 100 : null;
                    $dividendYield = is_numeric($sector->average_dividend_yield) ? (float) $sector->average_dividend_yield : null;
                @endphp

                <article class="group relative cursor-pointer rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)] transition duration-200 hover:-translate-y-0.5 hover:border-violet-400/30 hover:bg-[var(--ak-card-hover)] hover:shadow-[var(--ak-shadow-hover)]">
                    <a
                        href="{{ route('stocks.index', ['sector' => $sector->sector]) }}"
                        class="absolute inset-0 z-10 rounded-[1.5rem] focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
                        aria-label="{{ __('Aktien anzeigen') }}: {{ __($sector->sector) }}"
                    ></a>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-black text-[var(--ak-text)]">{{ __($sector->sector) }}</h2>
                            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ $sector->analyzed_count }} / {{ $sector->stocks_count }} {{ __('Aktien analysiert') }}</p>
                        </div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-300">
                            @switch($sector->sector)
                                @case('Technology')
                                    <x-heroicon-o-cpu-chip class="h-5 w-5" />
                                    @break
                                @case('Healthcare')
                                    <x-heroicon-o-heart class="h-5 w-5" />
                                    @break
                                @case('Financial Services')
                                    <x-heroicon-o-building-library class="h-5 w-5" />
                                    @break
                                @case('Energy')
                                    <x-heroicon-o-bolt class="h-5 w-5" />
                                    @break
                                @case('Industrials')
                                    <x-heroicon-o-cog-6-tooth class="h-5 w-5" />
                                    @break
                                @case('Basic Materials')
                                    <x-heroicon-o-cube-transparent class="h-5 w-5" />
                                    @break
                                @case('Communication Services')
                                    <x-heroicon-o-signal class="h-5 w-5" />
                                    @break
                                @case('Consumer Cyclical')
                                    <x-heroicon-o-shopping-cart class="h-5 w-5" />
                                    @break
                                @case('Consumer Defensive')
                                    <x-heroicon-o-shield-check class="h-5 w-5" />
                                    @break
                                @default
                                    <x-heroicon-o-building-office-2 class="h-5 w-5" />
                            @endswitch
                        </span>
                    </div>

                    <div class="mt-5">
                        <div class="mb-2">
                            <span class="text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Ø KI-Score') }}</span>
                        </div>
                        <div class="grid grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] items-center gap-3">
                            <div class="min-w-0">
                                <x-dashboard.stock-score-gauge :percent="$scorePercent" />
                            </div>
                            <span
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border px-3 py-2 text-base font-black shadow-sm {{ $scoreDirection === 'up' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-400' : ($scoreDirection === 'down' ? 'border-rose-400/30 bg-rose-400/10 text-rose-400' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]') }}"
                                title="{{ __('Veränderung des durchschnittlichen KI-Scores innerhalb der letzten 5 Tage') }}"
                            >
                                @if ($scoreDirection === 'up')
                                    <x-heroicon-o-arrow-trending-up class="h-6 w-6" />
                                @elseif ($scoreDirection === 'down')
                                    <x-heroicon-o-arrow-trending-down class="h-6 w-6" />
                                @else
                                    <x-heroicon-o-arrow-right class="h-6 w-6" />
                                @endif
                                <span>
                                    @if ($scoreChange !== null && abs($scoreChange) >= 0.05)
                                        {{ ($scoreChange > 0 ? '+' : '').number_format($scoreChange, 1, ',', '.') }}
                                    @else
                                        {{ __('stabil') }}
                                    @endif
                                </span>
                                <small class="text-xs font-bold opacity-70">5T</small>
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                        <p class="mb-2.5 text-[11px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]" title="{{ __('Der Status wird aus der KI-Bewertung und deinem gewählten Risikoprofil abgeleitet.') }}">{{ __('Aktive Signale') }}</p>
                        <div class="grid grid-cols-4 gap-1.5">
                            <a href="{{ route('stocks.index', ['sector' => $sector->sector, 'signal' => 'SELL']) }}" class="relative z-20 flex items-center justify-center gap-1 rounded-lg border px-1 py-2 text-[10px] font-black transition hover:brightness-125 {{ $sector->sell_count > 0 ? 'border-rose-400/30 bg-rose-400/10 text-rose-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)] opacity-45' }}">
                                SELL <span>{{ $sector->sell_count }}</span>
                            </a>
                            <a href="{{ route('stocks.index', ['sector' => $sector->sector, 'signal' => 'HOLD']) }}" class="relative z-20 flex items-center justify-center gap-1 rounded-lg border px-1 py-2 text-[10px] font-black transition hover:brightness-125 {{ $sector->hold_count > 0 ? 'border-amber-300/30 bg-amber-300/10 text-amber-300' : 'border-[var(--ak-border)] text-[var(--ak-muted)] opacity-45' }}">
                                HOLD <span>{{ $sector->hold_count }}</span>
                            </a>
                            <a href="{{ route('stocks.index', ['sector' => $sector->sector, 'signal' => 'WATCH']) }}" class="relative z-20 flex items-center justify-center gap-1 rounded-lg border px-1 py-2 text-[10px] font-black transition hover:brightness-125 {{ $sector->watch_count > 0 ? 'border-lime-300/30 bg-lime-300/10 text-lime-300' : 'border-[var(--ak-border)] text-[var(--ak-muted)] opacity-45' }}">
                                WATCH <span>{{ $sector->watch_count }}</span>
                            </a>
                            <a href="{{ route('stocks.index', ['sector' => $sector->sector, 'signal' => 'BUY']) }}" class="relative z-20 flex items-center justify-center gap-1 rounded-lg border px-1 py-2 text-[10px] font-black transition hover:brightness-125 {{ $sector->buy_count > 0 ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-400' : 'border-[var(--ak-border)] text-[var(--ak-muted)] opacity-45' }}">
                                BUY <span>{{ $sector->buy_count }}</span>
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-[var(--ak-border)] pt-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-[11px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Fundamentaldaten') }}</p>
                            <span class="text-[11px] text-[var(--ak-muted)]">{{ $sector->fundamental_count }} {{ __('Unternehmen') }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                            <div>
                                <dt class="text-xs text-[var(--ak-muted)]">{{ __('Ø KGV') }}</dt>
                                <dd class="mt-1 text-base font-black text-[var(--ak-text)]">{{ $pe !== null ? number_format($pe, 1, ',', '.') : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[var(--ak-muted)]">{{ __('Ø Gewinnmarge') }}</dt>
                                <dd class="mt-1 text-base font-black {{ ($profitMargin ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $profitMargin !== null ? number_format($profitMargin, 1, ',', '.').' %' : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[var(--ak-muted)]">{{ __('Ø Umsatzwachstum') }}</dt>
                                <dd class="mt-1 text-base font-black {{ ($revenueGrowth ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $revenueGrowth !== null ? (($revenueGrowth > 0 ? '+' : '').number_format($revenueGrowth, 1, ',', '.').' %') : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[var(--ak-muted)]">{{ __('Ø Dividendenrendite') }}</dt>
                                <dd class="mt-1 text-base font-black text-violet-300">{{ $dividendYield !== null ? number_format($dividendYield, 2, ',', '.').' %' : '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                </article>
                @empty
                    <div class="col-span-full rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-6 py-16 text-center text-sm text-[var(--ak-muted)]">
                        {{ __('Noch keine Sektordaten vorhanden.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>

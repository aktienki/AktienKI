@extends('layouts.aktienki')

@section('content')
    <x-detail-page-theme />
    <div class="ak-detail-design mx-auto w-full max-w-screen-2xl space-y-5 py-5">
        <header class="ak-detail-hero flex flex-col justify-between gap-4 rounded-2xl border border-cyan-400/25 bg-cyan-400/[.035] px-5 py-4 shadow-[0_18px_55px_rgba(6,182,212,.06)] sm:flex-row sm:items-center">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-black uppercase tracking-[.2em] text-cyan-400">{{ __('Watchlist') }}</p>
                    @if ($watchlist->is_default)
                        <span class="rounded-lg border border-violet-400/20 bg-violet-500/10 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-violet-300">{{ __('Standard') }}</span>
                    @endif
                </div>
                <h1 class="mt-2 text-3xl font-black text-[var(--ak-text)]">{{ $watchlist->name }}</h1>
                @if ($watchlist->description)
                    <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ $watchlist->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stocks.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-cyan-400/25 bg-cyan-400/[.07] px-4 text-xs font-bold text-cyan-300 transition hover:border-cyan-300/50 hover:bg-cyan-400/15">
                    <x-heroicon-o-plus class="h-4 w-4" />{{ __('Aktien hinzufügen') }}
                </a>
                <a href="{{ route('watchlists.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-amber-300/25 bg-amber-300/[.06] px-4 text-xs font-bold text-amber-200 transition hover:border-amber-300/45 hover:bg-amber-300/10">
                    <x-heroicon-o-cog-6-tooth class="h-4 w-4" />{{ __('Watchlists verwalten') }}
                </a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="overflow-hidden rounded-2xl border border-cyan-400/20 bg-cyan-400/[.025] p-4">
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ __('Aktien') }}</p>
                <p class="mt-2 text-2xl font-black text-[var(--ak-text)]">{{ $watchlist->items->count() }}</p>
            </article>
            <article class="overflow-hidden rounded-2xl border border-cyan-400/20 bg-cyan-400/[.025] p-4">
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ __('Durchschnittlicher Profit') }}</p>
                <p class="mt-2 text-2xl font-black {{ ($averageProfit ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $averageProfit !== null ? (($averageProfit > 0 ? '+' : '').number_format($averageProfit, 2, ',', '.').' %') : '—' }}
                </p>
            </article>
            <article class="overflow-hidden rounded-2xl border border-cyan-400/20 bg-cyan-400/[.025] p-4">
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ __('Berechnungsbasis') }}</p>
                <p class="mt-2 text-sm font-black text-[var(--ak-text)]">{{ __('Seit Aufnahme in die Watchlist') }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-[1.5rem] border border-cyan-400/25 bg-cyan-400/[.018] shadow-[0_18px_60px_rgba(6,182,212,.05)]">
            @if ($watchlist->items->isEmpty())
                <div class="px-6 py-20 text-center">
                    <x-heroicon-o-star class="mx-auto h-10 w-10 text-violet-300/50" />
                    <h2 class="mt-4 font-black text-[var(--ak-text)]">{{ __('Noch keine Aktien enthalten') }}</h2>
                    <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Öffne die Aktienliste und klicke bei einer Aktie auf den Stern.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ak-watchlist-table {{ $canViewSignalChanges ? 'has-signal-change' : '' }} w-full min-w-[850px] border-collapse text-left">
                        <thead>
                            <tr class="border-b border-cyan-400/20 bg-cyan-400/[.045] text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">
                                <th class="px-5 py-4">{{ __('Aktie') }}</th>
                                <th class="ak-watchlist-entry px-4 py-4 text-right"><span class="ak-watchlist-entry-desktop">{{ __('Einstiegskurs') }}</span><span class="ak-watchlist-entry-tablet">{{ __('Kurse') }}</span></th>
                                <th class="ak-watchlist-current px-4 py-4 text-right">{{ __('Aktueller Kurs') }}</th>
                                <th class="ak-watchlist-signal px-4 py-4 text-center">{{ __('Signal') }}</th>
                                <th class="px-4 py-4 text-center">{{ __('KI-Bewertung') }}</th>
                                @if ($canViewSignalChanges)<th class="ak-watchlist-signal-change px-4 py-4 text-center">{{ __('Signalwechsel') }}</th>@endif
                                <th class="ak-watchlist-profit px-4 py-4 text-center">{{ __('Profit') }}</th>
                                <th class="px-4 py-4 text-center">{{ __('Verlauf') }}</th>
                                <th class="w-16 px-4 py-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ak-border)]">
                            @foreach ($watchlist->items as $item)
                                @if ($item->instrument)
                                    @php
                                        $prediction = $latestPredictions->get($item->instrument_id);
                                        $entryPrice = is_numeric($item->entry_price) ? (float) $item->entry_price : null;
                                        $currentPrice = is_numeric($prediction?->current_price) ? (float) $prediction->current_price : null;
                                        $profitAbsolute = $entryPrice !== null && $currentPrice !== null ? $currentPrice - $entryPrice : null;
                                        $profitPercent = $entryPrice !== null && $entryPrice > 0 && $currentPrice !== null
                                            ? ($profitAbsolute / $entryPrice) * 100
                                            : null;
                                        $score = \App\Support\AiScore::toTen($prediction?->prediction_score);
                                        $scorePercent = \App\Support\AiScore::toPercent($prediction?->prediction_score);
                                        $scoreDonutColor = is_numeric($scorePercent)
                                            ? sprintf(
                                                'hsl(%.1f 78%% 52%%)',
                                                $scorePercent <= 50
                                                    ? ($scorePercent / 50) * 48
                                                    : 48 + (($scorePercent - 50) / 50) * 94,
                                            )
                                            : '#64748b';
                                        $qualityDonutColor = static function (?float $percent): string {
                                            if ($percent === null) return '#64748b';
                                            $percent = max(0, min(100, $percent));
                                            $hue = $percent <= 50 ? ($percent / 50) * 48 : 48 + (($percent - 50) / 50) * 94;
                                            return sprintf('hsl(%.1f 78%% 52%%)', $hue);
                                        };
                                        $toPercent = static fn ($value): ?float => is_numeric($value)
                                            ? max(0, min(100, (float) $value * ((float) $value <= 1 ? 100 : 1))) : null;
                                        $confidencePercent = $toPercent($prediction?->confidence);
                                        $stabilityPercent = $toPercent($prediction?->horizon_fusion_stability_score);
                                        $riskPercent = \App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor);
                                        $stats = $walkForwardStats->get($item->instrument_id);
                                        $hitRatePercent = is_numeric($stats?->hit_rate) ? max(0, min(100, (float) $stats->hit_rate)) : null;
                                        $profitPerTrade = is_numeric($stats?->average_profit_per_trade_percent) ? (float) $stats->average_profit_per_trade_percent : null;
                                        $profitScale = $profitPerTrade !== null ? max(0, min(100, 50 + ($profitPerTrade * 25))) : null;
                                        $currency = $item->entry_currency ?: $item->instrument->currency ?: 'USD';
                                        $stockIconUrl = route('stocks.icon', $item->instrument->id);
                                        $countryCode = strtoupper((string) $item->instrument->country);
                                        $countryFlag = strlen($countryCode) === 2 && function_exists('mb_chr')
                                            ? mb_chr(127397 + ord($countryCode[0])).mb_chr(127397 + ord($countryCode[1]))
                                            : '🌐';
                                        $itemIndices = $instrumentIndices->get($item->instrument_id, collect());
                                        $performancePoints = $performanceSeries->get($item->instrument_id, collect());
                                        $chartValues = collect($performancePoints)->pluck('value')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->values();
                                        $chartWidth = 130; $chartHeight = 38; $chartPad = 3;
                                        $chartMin = $chartValues->isNotEmpty() ? min((float) $chartValues->min(), 0.0) : 0.0;
                                        $chartMax = $chartValues->isNotEmpty() ? max((float) $chartValues->max(), 0.0) : 0.0;
                                        $chartRange = max(0.01, $chartMax - $chartMin);
                                        $chartPolyline = $chartValues->map(function (float $value, int $index) use ($chartValues, $chartWidth, $chartHeight, $chartPad, $chartMin, $chartRange): string {
                                            $x = $chartPad + ($index / max(1, $chartValues->count() - 1)) * ($chartWidth - 2 * $chartPad);
                                            $y = $chartPad + (($chartMin + $chartRange - $value) / $chartRange) * ($chartHeight - 2 * $chartPad);
                                            return number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
                                        })->implode(' ');
                                        $chartZeroY = $chartPad + (($chartMin + $chartRange) / $chartRange) * ($chartHeight - 2 * $chartPad);
                                        $chartPositive = ($chartValues->last() ?? 0) >= 0;
                                        $personalizedSignal = strtoupper((string) ($prediction?->personalized_signal ?: 'HOLD'));
                                        $signalTone = match ($personalizedSignal) {
                                            'BUY' => 'border-emerald-300/55 bg-emerald-400/15 text-emerald-300',
                                            'WAIT' => 'border-emerald-300/45 bg-emerald-400/10 text-emerald-300',
                                            'WATCH' => 'border-lime-300/40 bg-lime-400/10 text-lime-300',
                                            'SELL' => 'border-rose-300/45 bg-rose-400/10 text-rose-300',
                                            default => 'border-amber-300/40 bg-amber-400/10 text-amber-300',
                                        };
                                        $signalChange = $canViewSignalChanges ? $signalChanges->get($item->instrument_id) : null;
                                    @endphp
                                    <tr
                                        data-href="{{ route('stocks.show', ['symbol' => $item->instrument->symbol, 'prediction' => $prediction?->id, 'return_to' => request()->getRequestUri()]) }}"
                                        role="link"
                                        tabindex="0"
                                        onclick="if (!event.target.closest('a,button,input,select,label,form')) window.location.assign(this.dataset.href)"
                                        onkeydown="if (event.target === this && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); window.location.assign(this.dataset.href); }"
                                        class="cursor-pointer transition hover:bg-cyan-400/[.055] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-400/70"
                                    >
                                        <td class="ak-watchlist-stock px-5 py-4">
                                            <a href="{{ route('stocks.show', ['symbol' => $item->instrument->symbol, 'prediction' => $prediction?->id, 'return_to' => request()->getRequestUri()]) }}" class="group flex min-w-0 items-center gap-3">
                                                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-cyan-400/25 bg-cyan-400/[.06]">
                                                    <span class="flex h-full w-full items-center justify-center bg-cyan-400/[.08] text-xs font-black leading-none text-cyan-300">
                                                        {{ strtoupper(substr($item->instrument->symbol, 0, 2)) }}
                                                    </span>
                                                    <span class="absolute inset-0 z-10 flex items-center justify-center p-1.5" aria-hidden="true">
                                                        <img
                                                            src="{{ $stockIconUrl }}"
                                                            alt=""
                                                            class="h-full w-full object-contain opacity-0"
                                                            loading="eager"
                                                            onload="this.classList.remove('opacity-0'); this.parentElement.classList.add('bg-slate-50')"
                                                            onerror="this.parentElement.classList.add('hidden')"
                                                        >
                                                    </span>
                                                </span>
                                                <span class="min-w-0">
                                                    <strong class="block text-sm text-[var(--ak-text)] transition group-hover:text-cyan-300">{{ $item->instrument->symbol }}</strong>
                                                    <span class="block max-w-64 truncate text-xs text-[var(--ak-muted)]">{{ $item->instrument->name }}</span>
                                                    <span class="mt-1.5 flex min-w-0 flex-wrap items-center gap-1">
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-white/[.04] px-1.5 py-0.5 text-[9px] font-bold text-[var(--ak-muted)]" title="{{ __('Land') }}">
                                                            <span>{{ $countryFlag }}</span>{{ $countryCode ?: '—' }}
                                                        </span>
                                                        <span class="inline-flex max-w-28 items-center gap-1 rounded-md bg-cyan-400/[.08] px-1.5 py-0.5 text-[9px] font-bold text-cyan-300" title="{{ __('Index') }}">
                                                            <x-heroicon-o-chart-bar class="h-3 w-3 shrink-0" />
                                                            <span class="truncate">{{ $itemIndices->isNotEmpty() ? $itemIndices->pluck('symbol')->join(', ') : '—' }}</span>
                                                        </span>
                                                        <span class="inline-flex max-w-32 items-center gap-1 rounded-md bg-amber-300/[.07] px-1.5 py-0.5 text-[9px] font-bold text-amber-200/80" title="{{ __('Sektor') }}">
                                                            <x-sector-icon :sector="$item->instrument->sector" class="h-3 w-3 shrink-0" />
                                                            <span class="truncate">{{ __($item->instrument->sector ?: '—') }}</span>
                                                        </span>
                                                    </span>
                                                </span>
                                            </a>
                                        </td>
                                        <td class="ak-watchlist-entry px-4 py-4 text-right text-sm font-bold text-[var(--ak-text)]">
                                            <span class="ak-watchlist-entry-desktop">{{ $entryPrice !== null ? number_format($entryPrice, 2, ',', '.').' '.$currency : '—' }}</span>
                                            <span class="ak-watchlist-entry-tablet">
                                                <small>{{ __('Einstieg') }}</small><b>{{ $entryPrice !== null ? number_format($entryPrice, 2, ',', '.').' '.$currency : '—' }}</b>
                                                <small>{{ __('Aktuell') }}</small><b>{{ $currentPrice !== null ? number_format($currentPrice, 2, ',', '.').' '.$currency : '—' }}</b>
                                            </span>
                                        </td>
                                        <td class="ak-watchlist-current px-4 py-4 text-right text-sm font-black text-[var(--ak-text)]">
                                            <span class="block whitespace-nowrap">{{ $currentPrice !== null ? number_format($currentPrice, 2, ',', '.').' '.$currency : '—' }}</span>
                                        </td>
                                        <td class="ak-watchlist-signal px-4 py-4 text-center">
                                            <span class="inline-flex min-w-14 items-center justify-center rounded-md border px-2 py-1.5 text-[8px] font-black tracking-wide {{ $signalTone }}">{{ $personalizedSignal }}</span>
                                        </td>
                                        <td class="ak-watchlist-ai px-4 py-4">
                                            <div class="ak-watchlist-ai-metrics flex min-w-[27rem] items-center justify-center gap-3">
                                                @foreach ([
                                                    ['KI-Score', $scorePercent, $score !== null ? number_format($score, 1, ',', '.') : '—', $scoreDonutColor],
                                                    ['Konf.', $confidencePercent, $confidencePercent !== null ? number_format($confidencePercent, 0, ',', '.').'%' : '—', $qualityDonutColor($confidencePercent)],
                                                    ['Hit-Rate', $hitRatePercent, $hitRatePercent !== null ? number_format($hitRatePercent, 0, ',', '.').'%' : '—', $qualityDonutColor($hitRatePercent)],
                                                    ['Ø/Trade', $profitScale, $profitPerTrade !== null ? (($profitPerTrade > 0 ? '+' : '').number_format($profitPerTrade, 2, ',', '.').'%') : '—', $qualityDonutColor($profitScale)],
                                                    ['Stabilität', $stabilityPercent, $stabilityPercent !== null ? number_format($stabilityPercent, 0, ',', '.').'%' : '—', $qualityDonutColor($stabilityPercent)],
                                                    ['Risiko', $riskPercent, $riskPercent !== null ? number_format($riskPercent, 0, ',', '.').'%' : '—', $riskPercent !== null ? $qualityDonutColor(100 - $riskPercent) : '#64748b'],
                                                ] as [$label, $value, $display, $color])
                                                    <div class="screener-metric-donut {{ $label === 'KI-Score' ? 'screener-metric-donut-score' : '' }}" style="--donut-value: {{ number_format($value ?? 0, 2, '.', '') }}%; --donut-color: {{ $color }}" role="meter" aria-label="{{ __($label) }}" @if($value !== null) aria-valuenow="{{ number_format($value, 1, '.', '') }}" @endif>
                                                        <span>{{ $display }}</span><small>{{ __($label) }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        @if ($canViewSignalChanges)
                                            <td class="ak-watchlist-signal-change px-4 py-4 text-center">
                                                @if ($signalChange)
                                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-md border border-cyan-400/25 bg-cyan-400/[.07] px-2 py-1.5 text-[9px] font-black text-cyan-300" title="{{ __('Letzter Signalwechsel') }}">
                                                        {{ $signalChange['from'] }} <span aria-hidden="true">→</span> {{ $signalChange['to'] }}
                                                        <time class="text-[var(--ak-muted)]" datetime="{{ \Illuminate\Support\Carbon::parse($signalChange['date'])->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($signalChange['date'])->format('d.m.') }}</time>
                                                    </span>
                                                @else
                                                    <span class="text-xs text-[var(--ak-muted)]">—</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="ak-watchlist-profit px-4 py-4 text-center">
                                            <span class="block whitespace-nowrap text-xs font-bold {{ ($profitAbsolute ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $profitAbsolute !== null ? (($profitAbsolute > 0 ? '+' : '').number_format($profitAbsolute, 2, ',', '.').' '.$currency) : '—' }}</span>
                                            <span class="mt-1.5 inline-flex min-w-20 justify-center rounded-lg border px-2.5 py-1.5 text-xs font-black {{ $profitPercent === null ? 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]' : ($profitPercent >= 0 ? 'border-emerald-400/25 bg-emerald-400/10 text-emerald-400' : 'border-rose-400/25 bg-rose-400/10 text-rose-400') }}">
                                                {{ $profitPercent !== null ? (($profitPercent > 0 ? '+' : '').number_format($profitPercent, 2, ',', '.').' %') : '—' }}
                                            </span>
                                        </td>
                                        <td class="ak-watchlist-chart px-4 py-4 text-center">
                                            @if ($chartValues->count() >= 2)
                                                <svg class="ak-watchlist-chart-svg mx-auto h-10 w-32 overflow-visible" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="{{ __('Performanceverlauf seit Aufnahme') }}">
                                                    <line x1="{{ $chartPad }}" y1="{{ number_format($chartZeroY, 1, '.', '') }}" x2="{{ $chartWidth - $chartPad }}" y2="{{ number_format($chartZeroY, 1, '.', '') }}" stroke="currentColor" stroke-opacity=".16" stroke-width="1" />
                                                    <polyline points="{{ $chartPolyline }}" fill="none" stroke="{{ $chartPositive ? '#34d399' : '#fb7185' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            @else
                                                <span class="text-xs text-[var(--ak-muted)]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <form method="POST" action="{{ route('watchlists.items.destroy', [$watchlist->id, $item->instrument->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="{{ __('Aus Watchlist entfernen') }}" aria-label="{{ __('Aus Watchlist entfernen') }}: {{ $item->instrument->symbol }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-rose-400/10 hover:text-rose-400">
                                                    <x-heroicon-o-trash class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="text-center text-[10px] text-[var(--ak-muted)]">{{ __('Der Profit basiert auf dem beim Hinzufügen gespeicherten Aufnahmekurs und berücksichtigt keine Stückzahl, Gebühren oder Steuern.') }}</p>
    </div>
@endsection

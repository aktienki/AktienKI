@extends('layouts.aktienki')

@section('content')
    @php
        $scorePercent = \App\Support\AiScore::toPercent($prediction?->prediction_score);
        $confidencePercent = is_numeric($prediction?->confidence)
            ? max(0, min(100, (float) $prediction->confidence <= 1 ? (float) $prediction->confidence * 100 : (float) $prediction->confidence))
            : null;
        $displayRiskScore = $prediction?->risk_score ?? $prediction?->drawdown_risk_factor;
        $riskPercent = is_numeric($displayRiskScore)
            ? max(0, min(100, (float) $displayRiskScore <= 1 ? (float) $displayRiskScore * 100 : (float) $displayRiskScore))
            : null;
        $signal = strtoupper((string) ($prediction?->personalized_signal ?? 'HOLD'));
        $signalClass = $signal === 'BUY'
            ? 'border-emerald-300/70 bg-emerald-400/25 text-emerald-100 shadow-[0_0_18px_rgba(52,211,153,.25)]'
            : ($signal === 'SELL'
                ? 'border-rose-400/35 bg-rose-400/10 text-rose-300'
                : ($signal === 'WATCH'
                    ? 'border-lime-300/30 bg-lime-300/10 text-lime-300'
                    : 'border-amber-300/30 bg-amber-300/10 text-amber-300'));
        $trendValue = strtolower((string) ($predictionMetadata['trend'] ?? $prediction?->higher_timeframe_trend ?? 'neutral'));
        $trendLabel = match ($trendValue) {
            'bullish', 'up', 'uptrend' => __('Bullisch'),
            'bearish', 'down', 'downtrend' => __('Bärisch'),
            default => __('Neutral'),
        };
        $trendClass = match ($trendValue) {
            'bullish', 'up', 'uptrend' => 'text-emerald-400',
            'bearish', 'down', 'downtrend' => 'text-rose-400',
            default => 'text-amber-300',
        };
        $trendTimeframe = $predictionMetadata['trend_timeframe'] ?? $prediction?->interval ?? '1d';
        $higherTimeframe = is_array($predictionMetadata['higher_timeframe_trend'] ?? null)
            ? $predictionMetadata['higher_timeframe_trend']
            : [];
        $emaFast = $higherTimeframe['ema_fast'] ?? null;
        $emaSlow = $higherTimeframe['ema_slow'] ?? null;
        $currency = $instrument->currency ?: 'USD';
        $sectorColor = match ($instrument->sector) {
            'Technology' => '#a78bfa',
            'Healthcare' => '#f472b6',
            'Financial Services' => '#60a5fa',
            'Energy' => '#fbbf24',
            'Industrials' => '#94a3b8',
            'Basic Materials' => '#fb923c',
            'Communication Services' => '#22d3ee',
            'Consumer Cyclical' => '#a3e635',
            'Consumer Defensive' => '#4ade80',
            'Real Estate' => '#818cf8',
            'Utilities' => '#2dd4bf',
            default => '#a78bfa',
        };
        $isInAnyWatchlist = $instrumentWatchlistIds->isNotEmpty();
        $outlook20dPercent = is_numeric($prediction?->predicted_price_20d)
            && is_numeric($prediction?->current_price)
            && (float) $prediction->current_price !== 0.0
                ? (((float) $prediction->predicted_price_20d - (float) $prediction->current_price) / (float) $prediction->current_price) * 100
                : null;
        $chartPrevious = $chartCandles->count() >= 2 ? ($chartCandles->values()->get($chartCandles->count() - 2)['y'][3] ?? null) : null;
        $chartLast = $chartCandles->last()['y'][3] ?? null;
        $chartChange = is_numeric($chartPrevious) && (float) $chartPrevious !== 0.0 && is_numeric($chartLast)
            ? (((float) $chartLast - (float) $chartPrevious) / (float) $chartPrevious) * 100
            : null;
        $historicalSignal = strtoupper((string) ($prediction?->signal ?? $signal));
        $historicalSignalClass = match ($historicalSignal) {
            'BUY' => 'border-emerald-300/60 bg-emerald-400/20 text-emerald-200',
            'SELL' => 'border-rose-400/45 bg-rose-400/15 text-rose-300',
            'WATCH' => 'border-lime-300/35 bg-lime-300/10 text-lime-300',
            default => 'border-amber-300/35 bg-amber-300/10 text-amber-300',
        };
        $historicalStartPrice = is_numeric($prediction?->current_price) ? (float) $prediction->current_price : null;
        $historicalEndPrice = is_numeric($prediction?->actual_price)
            ? (float) $prediction->actual_price
            : (is_numeric($chartLast) ? (float) $chartLast : null);
        $historicalReturn = is_numeric($prediction?->actual_return)
            ? (float) $prediction->actual_return * 100
            : ($historicalStartPrice !== null && $historicalStartPrice !== 0.0 && $historicalEndPrice !== null
                ? (($historicalEndPrice - $historicalStartPrice) / $historicalStartPrice) * 100
                : null);
        $directionCorrectRaw = $prediction?->direction_correct;
        $directionCorrect = $directionCorrectRaw === null
            ? null
            : in_array($directionCorrectRaw, [true, 1, '1', 't', 'true'], true);
        $formatValue = function (string $key, mixed $value): string {
            if (is_bool($value)) return $value ? __('Ja') : __('Nein');
            if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (! is_numeric($value)) return (string) $value;

            $number = (float) $value;
            if (preg_match('/(Margins|Margin|Growth|returnOn|heldPercent|payoutRatio)$/i', $key)) {
                return number_format($number * 100, 2, ',', '.').' %';
            }
            if (abs($number) >= 1_000_000_000) return number_format($number / 1_000_000_000, 2, ',', '.').' Mrd.';
            if (abs($number) >= 1_000_000) return number_format($number / 1_000_000, 2, ',', '.').' Mio.';
            if (abs($number) >= 1_000) return number_format($number, 0, ',', '.');

            return number_format($number, 4, ',', '.');
        };
        $label = fn (string $key): string => str_replace(' · ', ' › ', \Illuminate\Support\Str::headline(
            str_replace('.', ' · ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $key))
        ));
    @endphp

    <div class="mx-auto flex h-[calc(100dvh-89px)] min-h-0 w-full max-w-screen-2xl flex-col py-4">
        <header class="mb-4 flex shrink-0 flex-col justify-between gap-4 border-b border-[var(--ak-border)] pb-3 sm:flex-row sm:items-end">
            <div class="flex min-w-0 items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-violet-400/25 bg-violet-500/10 text-xl font-black text-violet-300">
                    {{ strtoupper(substr($instrument->symbol, 0, 2)) }}
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="inline-flex max-w-full truncate rounded-xl border border-amber-300/30 bg-[linear-gradient(135deg,rgba(139,92,246,.18),rgba(251,191,36,.11))] px-3.5 py-1.5 text-2xl font-black text-[var(--ak-text)] shadow-[0_10px_28px_rgba(0,0,0,.16)]">{{ $instrument->name }}</h1>
                        <span class="rounded-lg bg-violet-500/10 px-2.5 py-1 text-xs font-black text-violet-300">{{ $instrument->symbol }}</span>
                    </div>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">
                        {{ __($instrument->sector ?: 'Keine Branche') }}
                        @if ($instrument->industry) · {{ $instrument->industry }} @endif
                        @if ($instrument->country) · {{ $instrument->country }} @endif
                        · {{ $currency }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto">
                @if ($userWatchlists->count() === 1)
                    @php
                        $singleWatchlist = $userWatchlists->first();
                        $isInSingleWatchlist = $instrumentWatchlistIds->contains((int) $singleWatchlist->id);
                    @endphp
                    <form method="POST" action="{{ route('watchlists.items.toggle', [$singleWatchlist->id, $instrument->id]) }}">
                        @csrf
                        <button type="submit" title="{{ $isInSingleWatchlist ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}" aria-label="{{ $isInSingleWatchlist ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border transition {{ $isInSingleWatchlist ? 'border-amber-300/35 bg-amber-300/10 text-amber-300' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-amber-300/30 hover:text-amber-300' }}">
                            @if ($isInSingleWatchlist)
                                <x-heroicon-s-star class="h-5 w-5" />
                            @else
                                <x-heroicon-o-star class="h-5 w-5" />
                            @endif
                        </button>
                    </form>
                @elseif ($userWatchlists->count() > 1)
                    <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" title="{{ __('Watchlist auswählen') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border transition {{ $isInAnyWatchlist ? 'border-amber-300/35 bg-amber-300/10 text-amber-300' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-amber-300/30 hover:text-amber-300' }}">
                            @if ($isInAnyWatchlist)
                                <x-heroicon-s-star class="h-5 w-5" />
                            @else
                                <x-heroicon-o-star class="h-5 w-5" />
                            @endif
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[#171325] p-2 shadow-2xl shadow-black/60" role="menu">
                            <p class="px-3 py-2 text-[10px] font-black uppercase tracking-[.14em] text-violet-300">{{ __('Watchlist auswählen') }}</p>
                            @foreach ($userWatchlists as $watchlist)
                                @php $isInWatchlist = $instrumentWatchlistIds->contains((int) $watchlist->id); @endphp
                                <form method="POST" action="{{ route('watchlists.items.toggle', [$watchlist->id, $instrument->id]) }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition hover:bg-violet-500/15" role="menuitem">
                                        <span class="truncate font-bold text-slate-200">{{ $watchlist->name }}</span>
                                        @if ($isInWatchlist)
                                            <x-heroicon-s-star class="h-4 w-4 shrink-0 text-amber-300" />
                                        @else
                                            <x-heroicon-o-star class="h-4 w-4 shrink-0 text-slate-500" />
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ route('watchlists.index') }}" title="{{ __('Zuerst Watchlist erstellen') }}" aria-label="{{ __('Zuerst Watchlist erstellen') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] transition hover:border-amber-300/30 hover:text-amber-300">
                        <x-heroicon-o-star class="h-5 w-5" />
                    </a>
                @endif

                <a href="{{ $returnTo ?: ($requestedPredictionId > 0 ? route('predictions.index') : route('stocks.index')) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]">
                    <x-heroicon-o-arrow-left class="h-4 w-4" />{{ $returnLabel ?: ($requestedPredictionId > 0 ? __('Zurück zu Prognosen') : __('Zur Aktienliste')) }}
                </a>
            </div>
        </header>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1 pb-3">
        @if ($requestedPredictionId > 0 && $prediction)
            <section class="rounded-[1.5rem] border border-amber-300/25 bg-[linear-gradient(120deg,rgba(245,158,11,.10),rgba(139,92,246,.07),var(--ak-card))] p-4 shadow-[var(--ak-shadow)]">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-300/10 text-amber-300">
                            <x-heroicon-o-clock class="h-4 w-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-black text-[var(--ak-text)]">{{ __('Historische Prognoseauswertung') }}</h2>
                            <p class="text-[10px] text-[var(--ak-muted)]">
                                {{ __('Prognose vom :date', ['date' => \Illuminate\Support\Carbon::parse($prediction->prediction_time)->format('d.m.Y H:i')]) }}
                            </p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-[var(--ak-muted)]">{{ __('Entwicklung nach dem Prognosezeitpunkt') }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
                    <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Gegebenes Signal') }}</p>
                        <span class="mt-1.5 inline-flex min-w-20 justify-center rounded-md border px-3 py-1 text-xs font-black {{ $historicalSignalClass }}">{{ $historicalSignal }}</span>
                    </div>
                    <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kurs bei Prognose') }}</p>
                        <p class="mt-1 text-base font-black tabular-nums text-[var(--ak-text)]">{{ $historicalStartPrice !== null ? number_format($historicalStartPrice, 2, ',', '.').' '.$currency : '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kurs danach') }}</p>
                        <p class="mt-1 text-base font-black tabular-nums text-[var(--ak-text)]">{{ $historicalEndPrice !== null ? number_format($historicalEndPrice, 2, ',', '.').' '.$currency : '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Tatsächliche Entwicklung') }}</p>
                        <p class="mt-1 text-base font-black tabular-nums {{ $historicalReturn === null ? 'text-[var(--ak-muted)]' : ($historicalReturn >= 0 ? 'text-emerald-400' : 'text-rose-400') }}">
                            {{ $historicalReturn !== null ? ($historicalReturn > 0 ? '+' : '').number_format($historicalReturn, 2, ',', '.').' %' : '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Ergebnis') }}</p>
                        @if ($directionCorrect !== null)
                            <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-black {{ $directionCorrect ? 'text-emerald-400' : 'text-rose-400' }}">
                                @if ($directionCorrect)
                                    <x-heroicon-o-check-circle class="h-5 w-5" />{{ __('Richtung korrekt') }}
                                @else
                                    <x-heroicon-o-x-circle class="h-5 w-5" />{{ __('Richtung verfehlt') }}
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm font-black text-[var(--ak-muted)]">{{ __('Noch nicht validiert') }}</p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="grid gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,.85fr)]">
            <article class="flex min-h-[350px] min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <div class="mb-4 flex shrink-0 items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Kurschart') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Kursentwicklung') }}</h2>
                        <p class="mt-1 text-xs text-[var(--ak-muted)]">
                            {{ __('Tageskerzen · letzte 3 Monate') }}
                        </p>
                        <div id="stock-indicator-buttons" class="mt-2 flex flex-wrap gap-1.5">
                            @foreach (['rsi' => 'RSI 14', 'sma20' => 'SMA 20', 'sma50' => 'SMA 50'] as $indicator => $indicatorLabel)
                                <button
                                    type="button"
                                    data-indicator="{{ $indicator }}"
                                    aria-pressed="{{ $indicator === 'rsi' ? 'true' : 'false' }}"
                                    class="rounded-lg border px-2.5 py-1 text-[9px] font-black uppercase tracking-wide transition {{ $indicator === 'rsi' ? 'border-violet-400/35 bg-violet-500/15 text-violet-300' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-violet-400/25 hover:text-[var(--ak-text)]' }}"
                                >
                                    {{ $indicatorLabel }}
                                </button>
                            @endforeach
                            <button
                                type="button"
                                data-chart-reset
                                title="{{ __('Chart zurücksetzen') }}"
                                aria-label="{{ __('Chart zurücksetzen') }}"
                                class="inline-flex items-center gap-1 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] transition hover:border-violet-400/25 hover:text-[var(--ak-text)]"
                            >
                                <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />Reset
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if ($chartLast !== null)
                            <span class="rounded-xl border border-violet-400/20 bg-violet-500/10 px-3 py-2 text-sm font-black text-violet-200">
                                {{ number_format((float) $chartLast, 2, ',', '.') }} {{ $currency }}
                            </span>
                        @endif
                        <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2 text-[10px] font-bold text-[var(--ak-muted)]">
                            {{ $chartCandles->count() }} {{ __('Tage') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-2 text-[10px] font-black uppercase tracking-wide text-emerald-400">
                            <i class="h-2 w-2 animate-pulse rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,.8)]"></i>
                            Live
                            <small id="stock-chart-updated" class="font-medium normal-case tracking-normal text-emerald-300/65"></small>
                        </span>
                        @if ($chartChange !== null)
                            <span id="stock-chart-change" title="{{ __('Tagesveränderung') }}" class="rounded-xl px-3 py-2 text-sm font-black {{ $chartChange >= 0 ? 'bg-emerald-400/10 text-emerald-400' : 'bg-rose-400/10 text-rose-400' }}">
                                1T · {{ $chartChange > 0 ? '+' : '' }}{{ number_format($chartChange, 2, ',', '.') }} %
                            </span>
                        @endif
                    </div>
                </div>
                @if ($chartCandles->isNotEmpty())
                    <div class="relative min-h-[200px] min-w-0 flex-1 overflow-hidden">
                        <div id="stock-detail-chart" class="absolute inset-0" aria-label="{{ __('Kurschart') }} {{ $instrument->symbol }}"></div>
                        <svg id="stock-indicator-overlay" class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible" aria-hidden="true"></svg>
                    </div>
                    <div id="stock-rsi-panel" class="mt-2 shrink-0 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 pb-1 pt-1.5">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">RSI 14</span>
                            <span id="stock-rsi-value" class="text-[10px] font-black text-violet-300">—</span>
                        </div>
                        <div id="stock-detail-rsi" class="h-[78px] min-w-0" aria-label="{{ __('RSI 14') }} {{ $instrument->symbol }}"></div>
                    </div>
                @else
                    <div class="grid min-h-[200px] flex-1 place-items-center rounded-2xl border border-dashed border-[var(--ak-border)] text-sm text-[var(--ak-muted)]">
                        {{ __('Keine OHLC-Tageskurse verfügbar.') }}
                    </div>
                @endif
            </article>

            <article class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Aktuelle KI-Analyse') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Persönliche Einordnung') }}</h2>
                    </div>
                    <span class="inline-flex h-8 min-w-20 items-center justify-center rounded-lg border px-3 text-xs font-black {{ $signalClass }}">{{ $signal }}</span>
                </div>

                @if ($prediction)
                    <div class="mt-6 space-y-5">
                        <div>
                            <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ __('KI-Score') }}</p>
                            <x-dashboard.stock-score-gauge :percent="$scorePercent" />
                        </div>
                        <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Aktueller Trend') }}</p>
                                <span class="rounded-md border border-current/20 px-1.5 py-0.5 text-[8px] font-black uppercase {{ $trendClass }}">{{ $trendTimeframe }}</span>
                            </div>
                            <p class="mt-1 text-base font-black {{ $trendClass }}">{{ $trendLabel }}</p>
                            @if (is_numeric($emaFast) && is_numeric($emaSlow))
                                <p class="mt-1 truncate text-[9px] text-[var(--ak-muted)]">
                                    EMA {{ number_format((float) $emaFast, 2, ',', '.') }} / {{ number_format((float) $emaSlow, 2, ',', '.') }}
                                </p>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                                <p class="mb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Konfidenz') }}</p>
                                <x-dashboard.stock-score-gauge :percent="$confidencePercent" compact purple percentage />
                            </div>
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                                <p class="mb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Risiko') }}</p>
                                <x-dashboard.stock-score-gauge :percent="$riskPercent" compact reverse />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                                <p class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Aktueller Kurs') }}</p>
                                <p class="mt-1 text-base font-black text-[var(--ak-text)]">{{ number_format((float) $prediction->current_price, 2, ',', '.') }} {{ $currency }}</p>
                            </div>
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                                <p class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Ziel 5 Tage') }}</p>
                                <p class="mt-1 text-base font-black text-violet-300">{{ is_numeric($prediction->predicted_price_5d) ? number_format((float) $prediction->predicted_price_5d, 2, ',', '.').' '.$currency : '—' }}</p>
                            </div>
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                                <p class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Ziel 20 Tage') }}</p>
                                <div class="mt-1 flex items-baseline justify-between gap-2">
                                    <p class="text-base font-black text-violet-300">{{ is_numeric($prediction->predicted_price_20d) ? number_format((float) $prediction->predicted_price_20d, 2, ',', '.').' '.$currency : '—' }}</p>
                                    @if ($outlook20dPercent !== null)
                                        <span class="text-[10px] font-black {{ $outlook20dPercent >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                            {{ $outlook20dPercent > 0 ? '+' : '' }}{{ number_format($outlook20dPercent, 2, ',', '.') }} %
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-8 rounded-xl border border-dashed border-[var(--ak-border)] p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Noch keine KI-Analyse vorhanden.') }}</div>
                @endif
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-400/10 text-cyan-300"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Stammdaten') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Verfügbare Angaben zum Instrument') }}</p></div>
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-3">
                    @foreach ([
                        __('Symbol') => $instrument->symbol,
                        'ISIN' => $instrument->isin,
                        __('Land') => $instrument->country,
                        __('Währung') => $instrument->currency,
                        __('Sektor') => __($instrument->sector ?: '—'),
                        __('Branche') => $instrument->industry,
                        __('Marktkapitalisierung') => $instrument->market_cap,
                        __('KGV') => is_numeric($fundamentalData['trailingPE'] ?? null)
                            ? number_format((float) $fundamentalData['trailingPE'], 2, ',', '.')
                            : null,
                        __('Dividende / Aktie') => is_numeric($fundamentalData['dividendRate'] ?? null)
                            ? number_format((float) $fundamentalData['dividendRate'], 2, ',', '.').' '.$currency
                            : null,
                        __('Dividendenrendite') => is_numeric($fundamentalData['dividendYield'] ?? null)
                            ? number_format((float) $fundamentalData['dividendYield'], 2, ',', '.').' %'
                            : null,
                        __('Handelbar') => (bool) $instrument->is_tradeable,
                        __('Aktiv') => (bool) $instrument->is_active,
                    ] as $name => $value)
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $name }}</dt>
                            @php
                                $sectorRanking = $name === __('KGV')
                                    ? ($sectorRankings['pe'] ?? null)
                                    : ($name === __('Dividendenrendite') ? ($sectorRankings['dividend'] ?? null) : null);
                            @endphp
                            <dd class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="break-words text-sm font-bold text-[var(--ak-text)]">{{ $value === null || $value === '' ? '—' : $formatValue((string) $name, $value) }}</span>
                                @if ($sectorRanking)
                                    <span class="inline-flex rounded-md border border-violet-400/20 bg-violet-500/10 px-2 py-1 text-[9px] font-bold text-violet-300">
                                        {{ __('Rang :rank von :total im Sektor', [
                                            'rank' => $sectorRanking['rank'],
                                            'total' => $sectorRanking['total'],
                                        ]) }}
                                    </span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            <article class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-300"><x-heroicon-o-sparkles class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Alle Prognosewerte') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Neueste verfügbare Modellberechnung') }}</p></div>
                </div>
                @if ($predictionData)
                    <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-3">
                        @foreach ($predictionData as $key => $value)
                            <div class="min-w-0">
                                <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $label($key) }}</dt>
                                <dd class="mt-1 break-words text-sm font-bold text-[var(--ak-text)]">{{ $formatValue($key, $value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-6 text-sm text-[var(--ak-muted)]">{{ __('Noch keine Prognosewerte vorhanden.') }}</p>
                @endif
            </article>
        </section>

        @if ($predictionExplanation || $predictionMetadata)
            <section class="grid gap-5 lg:grid-cols-2">
                <article class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-300"><x-heroicon-o-chat-bubble-left-right class="h-5 w-5" /></span>
                        <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Erklärung der KI-Analyse') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Strukturierte Begründung der Modellbewertung') }}</p></div>
                    </div>
                    @if ($predictionExplanation)
                        <div class="mt-5"><x-dashboard.structured-data :data="$predictionExplanation" /></div>
                    @else
                        <p class="mt-5 text-sm text-[var(--ak-muted)]">{{ __('Keine strukturierte Erklärung vorhanden.') }}</p>
                    @endif
                </article>

                <article class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-400/10 text-cyan-300"><x-heroicon-o-code-bracket-square class="h-5 w-5" /></span>
                        <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Analyse-Metadaten') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Zusätzliche Angaben der Modellberechnung') }}</p></div>
                    </div>
                    @if ($predictionMetadata)
                        <div class="mt-5"><x-dashboard.structured-data :data="$predictionMetadata" /></div>
                    @else
                        <p class="mt-5 text-sm text-[var(--ak-muted)]">{{ __('Keine Metadaten vorhanden.') }}</p>
                    @endif
                </article>
            </section>
        @endif

        <section class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-400/10 text-emerald-300"><x-heroicon-o-document-chart-bar class="h-5 w-5" /></span>
                    <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Fundamentaldaten') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Alle Werte des neuesten Fundamentaldatensatzes') }}</p></div>
                </div>
                @if ($fundamental?->snapshot_date)
                    <span class="text-xs font-bold text-[var(--ak-muted)]">{{ \Carbon\Carbon::parse($fundamental->snapshot_date)->format('d.m.Y') }}</span>
                @endif
            </div>
            @if ($fundamentalData)
                <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach (collect($fundamentalData)->sortKeys() as $key => $value)
                        <div class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                            <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $label($key) }}</dt>
                            <dd class="mt-1.5 break-words text-sm font-black text-[var(--ak-text)]">{{ $formatValue($key, $value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="mt-6 text-sm text-[var(--ak-muted)]">{{ __('Noch keine Fundamentaldaten vorhanden.') }}</p>
            @endif
        </section>

        @if ($instrumentMeta)
            <section class="rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-5 shadow-[var(--ak-shadow)]">
                <h2 class="font-black text-[var(--ak-text)]">{{ __('Weitere Instrumentdaten') }}</h2>
                <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach (collect($instrumentMeta)->sortKeys() as $key => $value)
                        <div><dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $label($key) }}</dt><dd class="mt-1 break-words text-sm font-bold text-[var(--ak-text)]">{{ $formatValue($key, $value) }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        <p class="pb-3 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Die Darstellung dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar.') }}</p>
        </div>
    </div>

    @if ($chartCandles->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const element = document.querySelector('#stock-detail-chart');
                if (!element || !window.ApexCharts) return;

                const initialCandles = @json($chartCandles->values());
                const initialWatchlistEntry = @json($watchlistEntry);
                const currency = @json($currency);
                const lightTheme = document.documentElement.dataset.theme === 'light';
                const sectorColor = lightTheme ? '#14b8a6' : @json($sectorColor);
                const predictedPrice20d = @json(is_numeric($prediction?->predicted_price_20d) ? (float) $prediction->predicted_price_20d : null);
                const chartFocusAt = @json($chartFocusAt?->getTimestampMs());
                const dataUrl = @json($chartDataUrl);
                const updatedElement = document.querySelector('#stock-chart-updated');
                const changeElement = document.querySelector('#stock-chart-change');
                const rsiElement = document.querySelector('#stock-detail-rsi');
                const rsiPanel = document.querySelector('#stock-rsi-panel');
                const rsiValueElement = document.querySelector('#stock-rsi-value');
                const indicatorOverlay = document.querySelector('#stock-indicator-overlay');
                const indicatorButtons = document.querySelectorAll('#stock-indicator-buttons [data-indicator]');
                const chartResetButton = document.querySelector('#stock-indicator-buttons [data-chart-reset]');
                let chart;
                let rsiChart;
                let currentCandles = initialCandles;
                let watchlistEntry = initialWatchlistEntry;
                let liveTimer;
                const activeIndicators = new Set(['rsi']);

                const addTradingDays = (timestamp, tradingDays) => {
                    const target = new Date(timestamp);
                    let remaining = tradingDays;

                    while (remaining > 0) {
                        target.setDate(target.getDate() + 1);
                        if (target.getDay() !== 0 && target.getDay() !== 6) remaining -= 1;
                    }

                    return target.getTime();
                };

                const movingAverageData = period => {
                    const closes = currentCandles.map(candle => Number(candle?.y?.[3]));

                    return currentCandles
                        .map((candle, index) => {
                            if (index < period - 1) return null;
                            const window = closes.slice(index - period + 1, index + 1);
                            if (window.some(value => !Number.isFinite(value))) return null;

                            return {
                                x: candle.x,
                                y: Number((window.reduce((sum, value) => sum + value, 0) / period).toFixed(4)),
                            };
                        })
                        .filter(Boolean);
                };

                const forecastOrigin = () => {
                    if (!Number.isFinite(chartFocusAt)) {
                        return currentCandles[currentCandles.length - 1];
                    }

                    return [...currentCandles]
                        .reverse()
                        .find(candle => new Date(candle.x).getTime() <= chartFocusAt)
                        ?? currentCandles[0];
                };

                const chartSeries = () => {
                    const series = [{
                        name: @json($instrument->symbol),
                        type: 'candlestick',
                        color: '#22c55e',
                        data: currentCandles,
                    }];

                    const lastCandle = forecastOrigin();
                    const lastTimestamp = lastCandle ? new Date(lastCandle.x).getTime() : NaN;
                    const lastClose = Number(lastCandle?.y?.[3]);

                    if (Number.isFinite(predictedPrice20d) && Number.isFinite(lastTimestamp) && Number.isFinite(lastClose)) {
                        const targetTimestamp = addTradingDays(lastTimestamp, 20);
                        const rangeAtTarget = [
                            Math.min(lastClose, predictedPrice20d),
                            Math.max(lastClose, predictedPrice20d),
                        ];

                        series.push({
                            name: @json(__('Prognosebereich')),
                            type: 'rangeArea',
                            data: [
                                { x: lastTimestamp, y: [lastClose, lastClose] },
                                { x: targetTimestamp, y: rangeAtTarget },
                            ],
                        });
                        series.push({
                            name: @json(__('Aktueller Kurs')),
                            type: 'line',
                            color: sectorColor,
                            data: [
                                { x: lastTimestamp, y: lastClose },
                                { x: targetTimestamp, y: lastClose },
                            ],
                        });
                        series.push({
                            name: @json(__('20-Tage-Ausblick')),
                            type: 'line',
                            color: sectorColor,
                            data: [
                                { x: lastTimestamp, y: lastClose },
                                { x: targetTimestamp, y: predictedPrice20d },
                            ],
                        });
                    }

                    return series;
                };

                const rsiData = (period = 14) => {
                    if (currentCandles.length <= period) return [];

                    const closes = currentCandles.map(candle => Number(candle?.y?.[3]));
                    let averageGain = 0;
                    let averageLoss = 0;

                    for (let index = 1; index <= period; index += 1) {
                        const change = closes[index] - closes[index - 1];
                        averageGain += Math.max(change, 0);
                        averageLoss += Math.max(-change, 0);
                    }

                    averageGain /= period;
                    averageLoss /= period;
                    const values = [];
                    const toRsi = () => averageLoss === 0
                        ? 100
                        : 100 - (100 / (1 + (averageGain / averageLoss)));

                    values.push({ x: currentCandles[period].x, y: Number(toRsi().toFixed(2)) });

                    for (let index = period + 1; index < closes.length; index += 1) {
                        const change = closes[index] - closes[index - 1];
                        averageGain = ((averageGain * (period - 1)) + Math.max(change, 0)) / period;
                        averageLoss = ((averageLoss * (period - 1)) + Math.max(-change, 0)) / period;
                        values.push({ x: currentCandles[index].x, y: Number(toRsi().toFixed(2)) });
                    }

                    return values;
                };

                const chartTimeRange = () => {
                    if (Number.isFinite(chartFocusAt)) {
                        const fiftyDays = 50 * 24 * 60 * 60 * 1000;

                        return {
                            min: chartFocusAt - fiftyDays,
                            max: chartFocusAt + fiftyDays,
                        };
                    }

                    const firstTimestamp = new Date(currentCandles[0]?.x).getTime();
                    const lastTimestamp = new Date(currentCandles[currentCandles.length - 1]?.x).getTime();

                    return {
                        min: Number.isFinite(firstTimestamp) ? firstTimestamp : undefined,
                        max: Number.isFinite(lastTimestamp) ? addTradingDays(lastTimestamp, 20) : undefined,
                    };
                };

                const chartPriceRange = () => {
                    const values = currentCandles
                        .flatMap(candle => Array.isArray(candle?.y) ? candle.y.map(Number) : [])
                        .filter(Number.isFinite);
                    if (Number.isFinite(predictedPrice20d)) values.push(predictedPrice20d);
                    if (watchlistEntry?.price && Number.isFinite(Number(watchlistEntry.price))) values.push(Number(watchlistEntry.price));
                    if (values.length === 0) return {};

                    const minimum = Math.min(...values);
                    const maximum = Math.max(...values);
                    const padding = Math.max((maximum - minimum) * 0.08, maximum * 0.01);

                    return { min: minimum - padding, max: maximum + padding };
                };

                const styleForecastLines = () => {
                    const lastClose = Number(forecastOrigin()?.y?.[3]);
                    if (!Number.isFinite(predictedPrice20d) || !Number.isFinite(lastClose)) return;

                    const linePaths = element.querySelectorAll('path.apexcharts-line');
                    if (linePaths.length < 2) return;

                    const currentPricePath = linePaths[linePaths.length - 2];
                    const forecastPath = linePaths[linePaths.length - 1];

                    [
                        [currentPricePath, '1.25'],
                        [forecastPath, '1.75'],
                    ].forEach(([path, width]) => {
                        path.setAttribute('stroke', sectorColor);
                        path.setAttribute('stroke-width', width);
                        path.setAttribute('stroke-dasharray', '2 6');
                        path.setAttribute('stroke-linecap', 'round');
                        path.setAttribute('stroke-opacity', '0.58');
                        path.style.stroke = sectorColor;
                        path.style.strokeWidth = width;
                        path.style.strokeDasharray = '2 6';
                        path.style.strokeOpacity = '0.58';
                    });
                };

                const options = () => {
                    const light = document.documentElement.dataset.theme === 'light';
                    const series = chartSeries();
                    const lastClose = Number(forecastOrigin()?.y?.[3]);
                    const positiveOutlook = Number.isFinite(predictedPrice20d)
                        && Number.isFinite(lastClose)
                        && predictedPrice20d >= lastClose;
                    const outlookColor = positiveOutlook ? '#22c55e' : '#ef4444';
                    const entryAnnotation = watchlistEntry?.price ? [{
                        y: Number(watchlistEntry.price),
                        borderColor: '#f59e0b',
                        strokeDashArray: 6,
                        label: {
                            position: 'left',
                            borderColor: '#f59e0b',
                            text: `${@json(__('Watchlist-Aufnahmekurs'))}: ${Number(watchlistEntry.price).toFixed(2)} ${watchlistEntry.currency || currency}`,
                            style: {
                                background: '#f59e0b',
                                color: '#111827',
                                fontSize: '10px',
                                fontWeight: 700,
                            },
                        },
                    }] : [];
                    const focusAnnotation = Number.isFinite(chartFocusAt) ? [{
                        x: chartFocusAt,
                        borderColor: '#f59e0b',
                        strokeDashArray: 5,
                        label: {
                            orientation: 'horizontal',
                            borderColor: '#f59e0b',
                            text: @json(__('Prognosezeitpunkt')),
                            style: {
                                background: '#f59e0b',
                                color: '#111827',
                                fontSize: '10px',
                                fontWeight: 800,
                            },
                        },
                    }] : [];

                    return {
                        chart: {
                            type: 'line',
                            height: '100%',
                            background: 'transparent',
                            toolbar: { show: false },
                            zoom: { enabled: true },
                            animations: { enabled: true, speed: 350 },
                        },
                        series,
                        colors: series.map(item => item.name === @json(__('Prognosebereich')) ? outlookColor : item.color),
                        stroke: {
                            width: series.map(item => item.type === 'candlestick'
                                ? 1
                                : (item.type === 'rangeArea'
                                    ? 0
                                    : (item.name === @json(__('20-Tage-Ausblick'))
                                        ? 1.75
                                        : (item.name === @json(__('Aktueller Kurs')) ? 1.25 : 2)))),
                            curve: 'straight',
                            dashArray: series.map(item => [@json(__('20-Tage-Ausblick')), @json(__('Aktueller Kurs'))].includes(item.name) ? 2 : 0),
                            lineCap: 'round',
                        },
                        fill: {
                            type: series.map(item => item.type === 'rangeArea' ? 'pattern' : 'solid'),
                            opacity: series.map(item => item.type === 'candlestick' ? 1 : (item.type === 'rangeArea' ? 0.26 : 0)),
                            pattern: {
                                style: series.map(item => item.type === 'rangeArea' ? 'slantedLines' : 'verticalLines'),
                                width: 7,
                                height: 7,
                                strokeWidth: 0.75,
                            },
                        },
                        markers: {
                            size: series.map(item => item.name === @json(__('20-Tage-Ausblick')) ? 5 : 0),
                            strokeWidth: 2,
                            strokeColors: sectorColor,
                            colors: [sectorColor],
                        },
                        plotOptions: {
                            candlestick: {
                                colors: { upward: '#22c55e', downward: '#ef4444' },
                                wick: { useFillColor: true },
                            },
                        },
                        annotations: { yaxis: entryAnnotation, xaxis: focusAnnotation },
                        dataLabels: { enabled: false },
                        grid: { borderColor: light ? 'rgba(51,65,85,.12)' : 'rgba(148,163,184,.10)', strokeDashArray: 4 },
                        xaxis: {
                            type: 'datetime',
                            min: chartTimeRange().min,
                            max: chartTimeRange().max,
                            labels: {
                                format: 'dd.MM.',
                                style: { colors: light ? '#64748b' : '#94a3b8' },
                                datetimeUTC: false,
                            },
                            axisBorder: { show: false },
                            axisTicks: { show: false },
                        },
                        yaxis: {
                            opposite: true,
                            min: chartPriceRange().min,
                            max: chartPriceRange().max,
                            forceNiceScale: false,
                            decimalsInFloat: 2,
                            labels: { formatter: value => value.toFixed(2) + ' ' + currency, style: { colors: [light ? '#64748b' : '#94a3b8'] } },
                        },
                        tooltip: { theme: light ? 'light' : 'dark', x: { format: 'dd.MM.yyyy' } },
                        theme: { mode: light ? 'light' : 'dark' }
                    };
                };

                const rsiOptions = () => {
                    const light = document.documentElement.dataset.theme === 'light';
                    const values = rsiData();

                    return {
                        chart: {
                            type: 'line',
                            height: 78,
                            background: 'transparent',
                            toolbar: { show: false },
                            zoom: { enabled: false },
                            animations: { enabled: true, speed: 250 },
                            parentHeightOffset: 0,
                        },
                        series: [{ name: 'RSI 14', data: values }],
                        colors: [sectorColor],
                        stroke: { width: 2, curve: 'smooth' },
                        markers: { size: 0 },
                        dataLabels: { enabled: false },
                        grid: {
                            borderColor: light ? 'rgba(51,65,85,.10)' : 'rgba(148,163,184,.08)',
                            strokeDashArray: 4,
                            padding: { top: -8, right: 8, bottom: -10, left: 2 },
                        },
                        annotations: {
                            yaxis: [
                                { y: 70, borderColor: 'rgba(248,113,113,.55)', strokeDashArray: 4 },
                                { y: 30, borderColor: 'rgba(74,222,128,.55)', strokeDashArray: 4 },
                            ],
                        },
                        xaxis: {
                            type: 'datetime',
                            min: chartTimeRange().min,
                            max: chartTimeRange().max,
                            labels: { show: false },
                            axisBorder: { show: false },
                            axisTicks: { show: false },
                            tooltip: { enabled: false },
                        },
                        yaxis: {
                            min: 0,
                            max: 100,
                            tickAmount: 2,
                            opposite: true,
                            labels: {
                                formatter: value => Math.round(value),
                                style: { colors: [light ? '#64748b' : '#94a3b8'], fontSize: '9px' },
                            },
                        },
                        tooltip: {
                            theme: light ? 'light' : 'dark',
                            x: { format: 'dd.MM.yyyy' },
                            y: { formatter: value => Number(value).toFixed(2) },
                        },
                        theme: { mode: light ? 'light' : 'dark' },
                    };
                };

                const updateRsiValue = () => {
                    if (!rsiValueElement) return;
                    const latestRsi = rsiData().at(-1)?.y;
                    rsiValueElement.textContent = Number.isFinite(latestRsi) ? Number(latestRsi).toFixed(2).replace('.', ',') : '—';
                    rsiValueElement.className = `text-[10px] font-black ${
                        latestRsi >= 70 ? 'text-rose-400' : (latestRsi <= 30 ? 'text-emerald-400' : 'text-violet-300')
                    }`;
                };

                const syncIndicatorUi = () => {
                    indicatorButtons.forEach(button => {
                        const active = activeIndicators.has(button.dataset.indicator);
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                        button.className = `rounded-lg border px-2.5 py-1 text-[9px] font-black uppercase tracking-wide transition ${
                            active
                                ? 'border-violet-400/35 bg-violet-500/15 text-violet-300'
                                : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-violet-400/25 hover:text-[var(--ak-text)]'
                        }`;
                    });

                    if (rsiPanel) rsiPanel.classList.toggle('hidden', !activeIndicators.has('rsi'));
                };

                const drawSmaLines = () => {
                    if (!indicatorOverlay || !chart?.w?.globals) return;

                    const width = indicatorOverlay.clientWidth;
                    const height = indicatorOverlay.clientHeight;
                    const globals = chart.w.globals;
                    const timeRange = chartTimeRange();
                    const priceRange = chartPriceRange();
                    if (!width || !height || !Number.isFinite(timeRange.min) || !Number.isFinite(timeRange.max)
                        || !Number.isFinite(priceRange.min) || !Number.isFinite(priceRange.max)) return;

                    indicatorOverlay.setAttribute('viewBox', `0 0 ${width} ${height}`);
                    indicatorOverlay.replaceChildren();

                    const left = Number(globals.translateX) || 0;
                    const top = Number(globals.translateY) || 0;
                    const plotWidth = Number(globals.gridWidth) || width;
                    const plotHeight = Number(globals.gridHeight) || height;
                    const xSpan = timeRange.max - timeRange.min;
                    const ySpan = priceRange.max - priceRange.min;

                    [
                        ['sma20', 20, '#fb923c'],
                        ['sma50', 50, '#60a5fa'],
                    ].forEach(([indicator, period, color]) => {
                        if (!activeIndicators.has(indicator)) return;

                        const points = movingAverageData(period)
                            .map(point => {
                                const timestamp = new Date(point.x).getTime();
                                if (!Number.isFinite(timestamp) || !Number.isFinite(point.y)) return null;

                                return {
                                    x: left + ((timestamp - timeRange.min) / xSpan) * plotWidth,
                                    y: top + plotHeight - ((point.y - priceRange.min) / ySpan) * plotHeight,
                                };
                            })
                            .filter(Boolean);
                        if (points.length < 2) return;

                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        path.setAttribute('d', points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' '));
                        path.setAttribute('fill', 'none');
                        path.setAttribute('stroke', color);
                        path.setAttribute('stroke-width', '2.25');
                        path.setAttribute('stroke-dasharray', '8 6');
                        path.setAttribute('stroke-linecap', 'round');
                        path.setAttribute('stroke-linejoin', 'round');
                        path.setAttribute('opacity', '0.62');
                        path.setAttribute('vector-effect', 'non-scaling-stroke');
                        path.style.display = 'block';
                        path.style.filter = 'none';
                        indicatorOverlay.appendChild(path);
                    });
                };

                chart = new window.ApexCharts(element, options());
                chart.render().then(() => {
                    styleForecastLines();
                    drawSmaLines();
                });
                if (indicatorOverlay && window.ResizeObserver) {
                    new ResizeObserver(() => drawSmaLines()).observe(indicatorOverlay);
                }
                if (rsiElement) {
                    rsiChart = new window.ApexCharts(rsiElement, rsiOptions());
                    rsiChart.render();
                    updateRsiValue();
                }
                syncIndicatorUi();
                indicatorButtons.forEach(button => {
                    button.addEventListener('click', async () => {
                        const indicator = button.dataset.indicator;
                        if (activeIndicators.has(indicator)) {
                            activeIndicators.delete(indicator);
                        } else {
                            activeIndicators.add(indicator);
                        }

                        syncIndicatorUi();
                        if (indicator !== 'rsi') {
                            drawSmaLines();
                        } else if (activeIndicators.has('rsi') && rsiChart) {
                            await rsiChart.updateOptions(rsiOptions(), false, true);
                            updateRsiValue();
                        }
                    });
                });
                chartResetButton?.addEventListener('click', async () => {
                    activeIndicators.clear();
                    activeIndicators.add('rsi');
                    syncIndicatorUi();
                    drawSmaLines();
                    chart.resetSeries();
                    await chart.updateOptions(options(), false, true);
                    styleForecastLines();
                    if (rsiChart) {
                        await rsiChart.updateOptions(rsiOptions(), false, true);
                        updateRsiValue();
                    }
                });
                window.addEventListener('aktienki:theme-changed', async () => {
                    await chart.updateOptions(options(), false, true);
                    styleForecastLines();
                    drawSmaLines();
                    if (rsiChart) await rsiChart.updateOptions(rsiOptions(), false, true);
                });

                const refreshChart = async () => {
                    if (document.visibilityState !== 'visible') return;

                    try {
                        const response = await fetch(dataUrl, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            cache: 'no-store'
                        });
                        if (!response.ok) return;

                        const payload = await response.json();
                        const nextCandles = Array.isArray(payload.candles) ? payload.candles : [];
                        const entryChanged = JSON.stringify(payload.watchlist_entry) !== JSON.stringify(watchlistEntry);
                        if (JSON.stringify(nextCandles) !== JSON.stringify(currentCandles) || entryChanged) {
                            currentCandles = nextCandles;
                            watchlistEntry = payload.watchlist_entry || null;
                            await chart.updateOptions(options(), false, true);
                            styleForecastLines();
                            drawSmaLines();
                            if (rsiChart) {
                                await rsiChart.updateOptions(rsiOptions(), false, true);
                                updateRsiValue();
                            }

                            const previous = Number(currentCandles[currentCandles.length - 2]?.y?.[3]);
                            const last = Number(currentCandles.at(-1)?.y?.[3]);
                            const change = Number.isFinite(previous) && previous !== 0 && Number.isFinite(last)
                                ? ((last - previous) / previous) * 100
                                : null;
                            if (changeElement && change !== null) {
                                changeElement.textContent = `1T · ${change > 0 ? '+' : ''}${change.toFixed(2).replace('.', ',')} %`;
                                changeElement.className = `rounded-xl px-3 py-2 text-sm font-black ${change >= 0 ? 'bg-emerald-400/10 text-emerald-400' : 'bg-rose-400/10 text-rose-400'}`;
                            }
                        }

                        if (updatedElement) {
                            updatedElement.textContent = new Date(payload.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        }
                    } catch (_) {
                        // Keep the last valid chart visible during temporary connection issues.
                    }
                };

                const startLiveUpdates = () => {
                    window.clearInterval(liveTimer);
                    liveTimer = window.setInterval(refreshChart, 60000);
                };

                if (!Number.isFinite(chartFocusAt)) {
                    refreshChart();
                    startLiveUpdates();
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            refreshChart();
                            startLiveUpdates();
                        } else {
                            window.clearInterval(liveTimer);
                        }
                    });
                }
                window.addEventListener('beforeunload', () => window.clearInterval(liveTimer));
            });
        </script>
    @endif
@endsection

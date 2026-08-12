@extends('layouts.aktienki')

@section('content')
    <style>
        #stock-detail-page {
            --stock-detail-accent: #06b6d4;
            --stock-detail-accent-bright: #22d3ee;
        }

        #stock-detail-page .stock-detail-panel {
            --stock-panel-padding: 1rem;
            position: relative;
            border-color: color-mix(in srgb, var(--ak-border) 76%, var(--stock-detail-accent) 24%) !important;
            background:
                radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .12), transparent 28%),
                linear-gradient(145deg, rgba(255, 255, 255, .98), rgba(244, 250, 249, .96)) !important;
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .9) inset,
                0 0 0 1px rgba(14, 116, 144, .055),
                0 14px 34px rgba(15, 23, 42, .105),
                0 4px 12px rgba(14, 116, 144, .07) !important;
        }

        :root:not([data-theme="light"]) #stock-detail-page .stock-detail-panel {
            background:
                radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .11), transparent 30%),
                linear-gradient(145deg, rgba(22, 35, 45, .98), rgba(13, 25, 34, .98)) !important;
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .045) inset,
                0 0 0 1px rgba(34, 211, 238, .065),
                0 18px 42px rgba(0, 0, 0, .34),
                0 5px 16px rgba(6, 182, 212, .055) !important;
        }

        #stock-detail-page .stock-detail-panel-compact {
            --stock-panel-padding: .75rem;
        }

        #stock-detail-page .stock-detail-panel::before {
            content: '';
            position: absolute;
            z-index: 3;
            inset: 0 18% auto;
            height: 2px;
            border-radius: 0 0 999px 999px;
            background: linear-gradient(90deg, transparent, rgba(8, 145, 178, .62), rgba(34, 211, 238, .78), transparent);
            pointer-events: none;
        }

        #stock-detail-page .stock-detail-card-head {
            position: relative;
            margin: calc(var(--stock-panel-padding) * -1) calc(var(--stock-panel-padding) * -1) .75rem;
            padding: var(--stock-panel-padding);
            border-bottom: 1px solid rgba(8, 145, 178, .28);
            background:
                radial-gradient(circle at 5% 0%, rgba(34, 211, 238, .22), transparent 40%),
                linear-gradient(108deg, rgba(8, 145, 178, .24), rgba(6, 182, 212, .14) 42%, rgba(6, 182, 212, .08) 72%, transparent);
            box-shadow: 0 6px 16px rgba(14, 116, 144, .055);
        }

        #stock-detail-page .ak-prediction-donut {
            width: 46px;
            height: 46px;
            flex-basis: 46px;
            box-shadow: 0 0 0 1px var(--ak-border), 0 3px 8px rgba(15, 23, 42, .10);
            filter: none !important;
        }

        #stock-detail-page .ak-prediction-donut::after {
            z-index: 0;
            inset: 3px !important;
            background: #f7fbfa !important;
            opacity: 1 !important;
        }

        :root:not([data-theme="light"]) #stock-detail-page .ak-prediction-donut::after {
            background: #1b2a33 !important;
        }

        #stock-detail-page .ak-prediction-donut > span {
            z-index: 2;
        }

        #stock-detail-page .stock-company-mark {
            border-color: color-mix(in srgb, var(--stock-detail-accent) 35%, transparent) !important;
            background: color-mix(in srgb, var(--stock-detail-accent) 12%, transparent) !important;
            color: var(--stock-detail-accent) !important;
        }

        #stock-detail-page .stock-company-title {
            border-color: color-mix(in srgb, var(--stock-detail-accent) 30%, var(--ak-border)) !important;
            background: linear-gradient(135deg, color-mix(in srgb, var(--ak-card) 89%, var(--stock-detail-accent) 11%), var(--ak-card)) !important;
        }

        #stock-detail-page .stock-company-symbol {
            border: 1px solid color-mix(in srgb, var(--stock-detail-accent) 24%, transparent);
            background: color-mix(in srgb, var(--stock-detail-accent) 10%, transparent) !important;
            color: var(--stock-detail-accent) !important;
        }

        #stock-detail-page .text-violet-300,
        #stock-detail-page .text-violet-200 {
            color: #22d3ee !important;
        }

        #stock-detail-page .bg-violet-500\/10,
        #stock-detail-page .bg-violet-500\/15 {
            background-color: color-mix(in srgb, var(--stock-detail-accent) 11%, transparent) !important;
        }

        #stock-detail-page .border-violet-400\/20,
        #stock-detail-page .border-violet-400\/25,
        #stock-detail-page .border-violet-400\/30,
        #stock-detail-page .border-violet-400\/35 {
            border-color: color-mix(in srgb, var(--stock-detail-accent) 32%, transparent) !important;
        }

        @media (min-width: 1024px) {
            .stock-overview-grid {
                height: calc(100% - 3rem);
            }

        }

        @media (min-width: 900px) {
            .stock-overview-grid-with-evaluation {
                grid-template-columns: minmax(320px, .9fr) minmax(0, 1.3fr) minmax(230px, .55fr);
            }

            .stock-overview-grid-with-evaluation > .stock-overview-chart {
                order: 2;
            }

            .stock-overview-grid-with-evaluation > .stock-overview-analysis {
                order: 1;
            }

            .stock-overview-grid-with-evaluation > .stock-overview-evaluation {
                order: 3;
            }
        }
    </style>
    @php
        $scorePercent = \App\Support\AiScore::toPercent($prediction?->prediction_score);
        $confidencePercent = is_numeric($prediction?->confidence)
            ? max(0, min(100, (float) $prediction->confidence <= 1 ? (float) $prediction->confidence * 100 : (float) $prediction->confidence))
            : null;
        $displayRiskScore = $prediction?->risk_score ?? $prediction?->drawdown_risk_factor;
        $riskPercent = is_numeric($displayRiskScore)
            ? max(20, min(100, (float) $displayRiskScore <= 1 ? (float) $displayRiskScore * 100 : (float) $displayRiskScore))
            : null;
        $signal = strtoupper((string) ($prediction?->personalized_signal ?? 'HOLD'));
        $signalClass = $signal === 'BUY'
            ? 'border-teal-300/70 bg-teal-400/25 text-teal-100 shadow-[0_0_18px_rgba(34, 211, 238,.22)]'
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
            'bullish', 'up', 'uptrend' => 'text-teal-400',
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
            'Communication Services' => '#fb923c',
            'Consumer Cyclical' => '#a3e635',
            'Consumer Defensive' => '#4ade80',
            'Real Estate' => '#818cf8',
            'Utilities' => '#22d3ee',
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
        $historicalSignal = $signal;
        $historicalSignalClass = match ($historicalSignal) {
            'BUY' => 'border-teal-300/60 bg-teal-400/20 text-teal-200',
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

    <div
        id="stock-detail-page"
        x-data="{ stockTab: 'overview' }"
        x-init="$watch('stockTab', value => $nextTick(() => window.dispatchEvent(new CustomEvent('stock-tab-changed', { detail: { tab: value } }))))"
        class="mx-auto flex h-[calc(100dvh-89px)] min-h-0 w-full max-w-screen-2xl flex-col py-4"
    >
        <header class="mb-4 flex shrink-0 flex-col justify-between gap-4 border-b border-[var(--ak-border)] pb-3 sm:flex-row sm:items-end">
            <div class="flex min-w-0 items-center gap-4">
                <span class="stock-company-mark flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border text-xl font-black">
                    {{ strtoupper(substr($instrument->symbol, 0, 2)) }}
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="stock-company-title inline-flex max-w-full truncate rounded-xl border px-3.5 py-1.5 text-2xl font-black text-[var(--ak-text)] shadow-[0_10px_28px_rgba(0,0,0,.12)]">{{ $instrument->name }}</h1>
                        <span class="stock-company-symbol rounded-lg px-2.5 py-1 text-xs font-black">{{ $instrument->symbol }}</span>
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-sm text-[var(--ak-muted)]">
                        <x-sector-icon :sector="$instrument->sector" class="h-4 w-4 shrink-0 text-teal-500" />
                        <span>{{ __($instrument->sector ?: 'Keine Branche') }}
                        @if ($instrument->industry) · {{ $instrument->industry }} @endif
                        @if ($instrument->country) · {{ $instrument->country }} @endif
                        · {{ $currency }}</span>
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2 self-start sm:self-auto">
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

                <a href="{{ route('stocks.chart-analysis', $instrument->symbol) }}" class="inline-flex h-10 w-36 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-teal-500/25 bg-teal-500/10 px-3 text-xs font-black text-teal-600 transition hover:border-teal-500/45 hover:bg-teal-500/15">
                    <x-heroicon-o-chart-bar-square class="h-4 w-4" />{{ __('Chartanalyse') }}
                </a>

                <a href="{{ $returnTo ?: ($requestedPredictionId > 0 ? route('predictions.index') : route('stocks.index')) }}" class="inline-flex h-10 w-44 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]">
                    <x-heroicon-o-arrow-left class="h-4 w-4 shrink-0" /><span class="truncate">{{ $returnLabel ?: ($requestedPredictionId > 0 ? __('Zurück zu Prognosen') : __('Zur Aktienliste')) }}</span>
                </a>
            </div>
        </header>

        <nav class="mb-4 flex shrink-0 items-end gap-1 overflow-x-auto border-b border-[var(--ak-border)] px-1" aria-label="{{ __('Bereiche der Aktienanalyse') }}">
            @foreach ([
                ['overview', __('Übersicht'), 'heroicon-o-squares-2x2'],
                ['indicators', __('Indikatoren Statistik'), 'heroicon-o-presentation-chart-line'],
                ['indicator-matrix', __('Indikator Matrix'), 'heroicon-o-squares-plus'],
                ['heatmap', __('Heatmap'), 'heroicon-o-table-cells'],
                ['fundamentals', __('Fundamentals'), 'heroicon-o-building-library'],
                ['aki', __('aKI Daten'), 'heroicon-o-cpu-chip'],
                ['analysis', __('Analyse'), 'heroicon-o-sparkles'],
            ] as [$tabKey, $tabLabel, $tabIcon])
                <button
                    type="button"
                    @click="stockTab = '{{ $tabKey }}'; $nextTick(() => window.dispatchEvent(new Event('resize')))"
                    :aria-selected="stockTab === '{{ $tabKey }}'"
                    class="relative -mb-px inline-flex h-9 shrink-0 items-center gap-1.5 rounded-t-xl border px-3 text-[10px] font-black uppercase tracking-[.08em] transition"
                    :class="stockTab === '{{ $tabKey }}'
                        ? 'border-[var(--ak-border)] border-b-[var(--ak-card-strong)] bg-[var(--ak-card-strong)] text-teal-500'
                        : 'border-transparent bg-transparent text-[var(--ak-muted)] hover:border-[var(--ak-border)] hover:bg-[var(--ak-surface-muted)] hover:text-[var(--ak-text)]'"
                    role="tab"
                >
                    <x-dynamic-component :component="$tabIcon" class="h-3.5 w-3.5" />
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>

        <div
            class="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1 pb-3"
            :class="stockTab === 'overview' ? 'lg:overflow-hidden' : ''"
        >
        <section
            x-show="stockTab === 'overview'"
            class="stock-overview-grid {{ $requestedPredictionId > 0 && $prediction ? 'stock-overview-grid-with-evaluation' : 'lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,.85fr)]' }} grid min-h-0 gap-4"
        >
            <article class="stock-detail-panel stock-overview-chart flex min-h-[350px] min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)] lg:h-full lg:min-h-0">
                <div class="stock-detail-card-head flex shrink-0 items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Kurschart') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Kursentwicklung') }}</h2>
                        <p class="mt-1 text-xs text-[var(--ak-muted)]">
                            {{ __('Tageskerzen · letzte 100 Handelstage') }}
                        </p>
                        <div id="stock-indicator-buttons" class="mt-2 flex flex-nowrap items-center gap-1.5 whitespace-nowrap">
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
                        <div class="rounded-xl border border-teal-500/25 bg-teal-500/[.09] px-3 py-1.5 text-right">
                            <p
                                data-live-symbol="{{ $instrument->symbol }}"
                                data-stock-live-price
                                data-live-currency="{{ $currency }}"
                                data-live-decimals="2"
                                class="whitespace-nowrap text-sm font-black tabular-nums text-teal-400"
                            >{{ number_format((float) ($chartLast ?? $prediction?->current_price ?? 0), 2, ',', '.') }} {{ $currency }}</p>
                            <p class="mt-0.5 flex items-center justify-end gap-1 text-[7px] font-black uppercase tracking-wide text-teal-400/75">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-teal-400 shadow-[0_0_5px_rgba(34, 211, 238,.55)]"></span>
                                TwelveData · <span data-live-time-symbol="{{ $instrument->symbol }}" data-stock-live-time>{{ !empty($prediction->current_quote_time) ? \Illuminate\Support\Carbon::parse($prediction->current_quote_time)->timezone('Europe/Berlin')->format('H:i:s') : __('wartet') }}</span>
                            </p>
                        </div>
                        <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2 text-[10px] font-bold text-[var(--ak-muted)]">
                            {{ $chartCandles->count() }} {{ __('Tage') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-amber-400/20 bg-amber-400/[.08] px-2.5 py-2 text-[9px] font-black uppercase tracking-wide text-amber-500">
                            <i class="h-0.5 w-5 rounded-full bg-amber-500"></i>{{ __('Historischer KI-Score') }}
                        </span>
                        @if ($latestSignalTransition)
                            @php
                                $transitionTarget = strtoupper((string) ($latestSignalTransition['to'] ?? 'HOLD'));
                                $transitionClasses = match ($transitionTarget) {
                                    'BUY' => 'border-emerald-400/35 bg-emerald-400/10 text-emerald-400',
                                    'WATCH' => 'border-lime-400/35 bg-lime-400/10 text-lime-400',
                                    'SELL' => 'border-rose-400/35 bg-rose-400/10 text-rose-400',
                                    default => 'border-amber-400/35 bg-amber-400/10 text-amber-400',
                                };
                            @endphp
                            <span
                                class="inline-flex items-center gap-1.5 rounded-xl border px-2.5 py-2 text-[9px] font-black uppercase tracking-wide {{ $transitionClasses }}"
                                title="{{ __('Letzter Signalwechsel') }}"
                            >
                                <i class="h-4 border-l border-dashed border-current opacity-70"></i>
                                {{ strtoupper((string) ($latestSignalTransition['from'] ?? '—')) }}
                                <x-heroicon-o-arrow-right class="h-3 w-3" />
                                {{ $transitionTarget }}
                                <span class="opacity-70">· {{ \Carbon\CarbonImmutable::createFromTimestampMs((int) $latestSignalTransition['x'])->format('d.m.Y') }}</span>
                            </span>
                        @endif
                        <span id="stock-chart-pattern-badge" class="{{ empty($chartPatterns) ? 'hidden ' : '' }}inline-flex items-center gap-1.5 rounded-xl border px-2.5 py-2 text-[9px] font-black uppercase tracking-wide {{ collect($chartPatterns)->contains(fn ($pattern) => ($pattern['direction'] ?? '') === 'bullish') ? 'border-emerald-400/35 bg-emerald-400/10 text-emerald-400' : 'border-rose-400/35 bg-rose-400/10 text-rose-400' }}">
                            <x-heroicon-o-chart-bar class="h-3.5 w-3.5" />
                            <span data-pattern-label>{{ collect($chartPatterns)->pluck('name')->join(' · ') }}</span>
                        </span>
                    </div>
                </div>
                @if ($chartCandles->isNotEmpty())
                    <div class="relative min-h-[160px] min-w-0 flex-1 overflow-hidden lg:min-h-0">
                        <div id="stock-detail-chart" class="absolute inset-0" aria-label="{{ __('Kurschart') }} {{ $instrument->symbol }}"></div>
                        <svg id="stock-indicator-overlay" class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible" aria-hidden="true"></svg>
                    </div>
                    <div id="stock-rsi-panel" class="mt-2 shrink-0 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 pb-1 pt-1.5">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">RSI 14</span>
                            <span id="stock-rsi-value" class="text-[10px] font-black text-violet-300">—</span>
                        </div>
                        <div id="stock-detail-rsi" class="h-16 min-w-0" aria-label="{{ __('RSI 14') }} {{ $instrument->symbol }}"></div>
                    </div>
                @else
                    <div class="grid min-h-[200px] flex-1 place-items-center rounded-2xl border border-dashed border-[var(--ak-border)] text-sm text-[var(--ak-muted)]">
                        {{ __('Keine OHLC-Tageskurse verfügbar.') }}
                    </div>
                @endif
            </article>

            <article x-show="stockTab === 'overview'" class="stock-detail-panel stock-detail-panel-compact stock-overview-analysis min-h-0 overflow-y-auto rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)] lg:h-full">
                <div class="stock-detail-card-head flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Aktuelle KI-Analyse') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Persönliche Einordnung') }}</h2>
                    </div>
                    <span class="inline-flex h-8 min-w-20 items-center justify-center rounded-lg border px-3 text-xs font-black {{ $signalClass }}">{{ $signal }}</span>
                </div>

                @if ($prediction)
                    @php
                        $analysisScore10 = \App\Support\AiScore::toTen($prediction->prediction_score);
                        $analysisModelTierCode = $modelQuality?->tier_code ?: 'unqualified';
                        $analysisModelTierName = $modelQuality?->tier_name ? __($modelQuality->tier_name) : __('Nicht qualifiziert');
                        $analysisModelTierClass = match ($analysisModelTierCode) {
                            'top' => 'ak-model-tier-top',
                            'strong' => 'ak-model-tier-strong',
                            'solid' => 'ak-model-tier-solid',
                            'test' => 'ak-model-tier-test',
                            default => 'ak-model-tier-unqualified',
                        };
                        $analysisChallengerTierCode = $modelChallenger?->tier_code ?: 'unqualified';
                        $analysisChallengerTierName = $modelChallenger?->tier_name ? __($modelChallenger->tier_name) : __('Nicht qualifiziert');
                        $analysisChallengerTierClass = match ($analysisChallengerTierCode) {
                            'top' => 'ak-model-tier-top',
                            'strong' => 'ak-model-tier-strong',
                            'solid' => 'ak-model-tier-solid',
                            'test' => 'ak-model-tier-test',
                            default => 'ak-model-tier-unqualified',
                        };
                        $analysisChallengerQualityPercent = is_numeric($modelChallenger?->quality_score)
                            ? max(0, min(100, (float) $modelChallenger->quality_score <= 1
                                ? (float) $modelChallenger->quality_score * 100
                                : (float) $modelChallenger->quality_score))
                            : null;
                        $analysisModelQualityPercent = is_numeric($modelQuality?->quality_score)
                            ? max(0, min(100, (float) $modelQuality->quality_score <= 1
                                ? (float) $modelQuality->quality_score * 100
                                : (float) $modelQuality->quality_score))
                            : null;
                        $analysisModelQualityColor = match (true) {
                            $analysisModelQualityPercent === null => '#64748b',
                            $analysisModelQualityPercent < 40 => '#e35f72',
                            $analysisModelQualityPercent < 60 => '#f28a45',
                            $analysisModelQualityPercent < 75 => '#e5b643',
                            $analysisModelQualityPercent < 88 => '#91c94b',
                            default => '#22c58b',
                        };
                        $analysisScoreColor = match (true) {
                            $scorePercent === null => '#64748b',
                            $scorePercent < 40 => '#e35f72',
                            $scorePercent < 55 => '#f28a45',
                            $scorePercent < 70 => '#e5b643',
                            $scorePercent < 82 => '#84cc16',
                            default => '#22c58b',
                        };
                        $analysisRiskColor = match (true) {
                            $riskPercent === null => '#64748b',
                            $riskPercent <= 20 => '#dc3f58',
                            default => sprintf('hsl(%.1f 72%% 43%%)', 52 + (($riskPercent - 20) / 80 * 68)),
                        };
                    @endphp
                    <div class="mt-3 space-y-3">
                        <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2">
                            <p class="mb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Modellranking') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="min-w-0 rounded-lg border border-amber-400/20 bg-amber-400/[.06] px-2.5 py-2">
                                    <p class="text-[8px] font-black uppercase tracking-[.12em] text-amber-500">{{ __('Champion') }}</p>
                                    <p class="mt-1 truncate text-xs font-black text-[var(--ak-text)]">{{ $modelQuality?->model_alias ?: '—' }}</p>
                                    <span class="ak-model-tier mt-1.5 {{ $analysisModelTierClass }}">{{ $analysisModelTierName }}</span>
                                </div>
                                <div class="min-w-0 rounded-lg border border-teal-500/20 bg-teal-500/[.06] px-2.5 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-[8px] font-black uppercase tracking-[.12em] text-teal-500">{{ __('Challenger') }}</p>
                                        @if ($analysisChallengerQualityPercent !== null)
                                            <span class="shrink-0 rounded-md border border-teal-500/20 bg-teal-500/[.08] px-1.5 py-0.5 text-[8px] font-black tabular-nums text-teal-500" title="{{ __('Challenger Quality Score') }}">
                                                {{ number_format($analysisChallengerQualityPercent, 1, ',', '.') }} %
                                            </span>
                                        @endif
                                    </div>
                                    @if ($modelChallenger)
                                        <p class="mt-1 truncate text-xs font-black text-[var(--ak-text)]">{{ $modelChallenger->model_alias ?: '—' }}</p>
                                        <span class="ak-model-tier mt-1.5 !w-full !max-w-none !flex-1 {{ $analysisChallengerTierClass }}">{{ $analysisChallengerTierName }}</span>
                                    @else
                                        <p class="mt-1 text-xs font-bold text-[var(--ak-muted)]">{{ __('Kein aktiver Challenger') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="flex min-h-[88px] min-w-0 flex-col items-center justify-start gap-2.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-2">
                                <p class="w-full truncate text-center text-[8px] font-black uppercase leading-none tracking-[.035em] text-[var(--ak-muted)]">{{ __('KI-Score') }}</p>
                                <div class="ak-prediction-donut" style="--value:{{ $scorePercent ?? 0 }}%;--color:{{ $analysisScoreColor }}" role="meter" aria-label="{{ __('KI-Score') }}" aria-valuemin="0" aria-valuemax="10" aria-valuenow="{{ $analysisScore10 ?? 0 }}">
                                    <span>{{ $analysisScore10 !== null ? number_format($analysisScore10, 1, ',', '.') : '—' }}<small>{{ $analysisScore10 !== null ? '/10' : '' }}</small></span>
                                </div>
                            </div>
                            <div class="flex min-h-[88px] min-w-0 flex-col items-center justify-start gap-2.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-2">
                                <p class="w-full truncate text-center text-[8px] font-black uppercase leading-none tracking-[.025em] text-[var(--ak-muted)]">{{ __('Modellqualität') }}</p>
                                <div class="ak-prediction-donut" style="--value:{{ $analysisModelQualityPercent ?? 0 }}%;--color:{{ $analysisModelQualityColor }}" role="meter" aria-label="{{ __('Modellqualität') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($analysisModelQualityPercent ?? 0) }}">
                                    <span>{{ $analysisModelQualityPercent !== null ? number_format($analysisModelQualityPercent, 0, ',', '.') : '—' }}<small>{{ $analysisModelQualityPercent !== null ? '%' : '' }}</small></span>
                                </div>
                            </div>
                            <div class="flex min-h-[88px] min-w-0 flex-col items-center justify-start gap-2.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-2">
                                <p class="w-full truncate text-center text-[8px] font-black uppercase leading-none tracking-[.035em] text-[var(--ak-muted)]">{{ __('Risiko') }}</p>
                                <div class="ak-prediction-donut" style="--value:{{ $riskPercent ?? 0 }}%;--color:{{ $analysisRiskColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($riskPercent ?? 0) }}">
                                    <span>{{ $riskPercent !== null ? number_format($riskPercent, 0, ',', '.') : '—' }}<small>{{ $riskPercent !== null ? '%' : '' }}</small></span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2">
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
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2">
                                <p class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Ziel 5 Tage') }}</p>
                                <p class="mt-1 text-base font-black text-violet-300">{{ is_numeric($prediction->predicted_price_5d) ? number_format((float) $prediction->predicted_price_5d, 2, ',', '.').' '.$currency : '—' }}</p>
                            </div>
                            <div class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2">
                                <p class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Ziel 20 Tage') }}</p>
                                <div class="mt-1 min-w-0">
                                    <p class="truncate text-base font-black text-violet-300">{{ is_numeric($prediction->predicted_price_20d) ? number_format((float) $prediction->predicted_price_20d, 2, ',', '.').' '.$currency : '—' }}</p>
                                    @if ($outlook20dPercent !== null)
                                        <span class="mt-0.5 block truncate text-[10px] font-black {{ $outlook20dPercent >= 0 ? 'text-teal-400' : 'text-rose-400' }}">
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

            @if ($requestedPredictionId > 0 && $prediction)
                <aside class="stock-detail-panel stock-detail-panel-compact stock-overview-evaluation min-h-0 overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)] lg:h-full">
                    <div class="stock-detail-card-head flex items-center gap-2">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-300/10 text-amber-300"><x-heroicon-o-clock class="h-4 w-4" /></span>
                        <div class="min-w-0">
                            <h2 class="text-xs font-black leading-tight text-[var(--ak-text)]">{{ __('Historische Prognoseauswertung') }}</h2>
                            <p class="mt-0.5 text-[9px] text-[var(--ak-muted)]">{{ __('Prognose vom :date', ['date' => \Illuminate\Support\Carbon::parse($prediction->prediction_time)->format('d.m.Y H:i')]) }}</p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2">
                            <span class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Gegebenes Signal') }}</span>
                            <div class="mt-1 flex items-center justify-between gap-2"><span class="rounded-md border px-2 py-0.5 text-[10px] font-black {{ $historicalSignalClass }}">{{ $historicalSignal }}</span><span class="text-[9px] text-[var(--ak-muted)]">{{ $signalChangedAt?->format('d.m.Y') ?? '—' }}</span></div>
                        </div>
                        @foreach ([
                            [__('Kurs bei Prognose'), $historicalStartPrice !== null ? number_format($historicalStartPrice, 2, ',', '.').' '.$currency : '—', 'text-[var(--ak-text)]'],
                            [__('Kurs danach'), $historicalEndPrice !== null ? number_format($historicalEndPrice, 2, ',', '.').' '.$currency : '—', 'text-[var(--ak-text)]'],
                            [__('Tatsächliche Entwicklung'), $historicalReturn !== null ? ($historicalReturn > 0 ? '+' : '').number_format($historicalReturn, 2, ',', '.').' %' : '—', $historicalReturn === null ? 'text-[var(--ak-muted)]' : ($historicalReturn >= 0 ? 'text-teal-400' : 'text-rose-400')],
                        ] as [$historyLabel, $historyValue, $historyTone])
                            <div class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2">
                                <span class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $historyLabel }}</span>
                                <span class="mt-1 block text-sm font-black tabular-nums {{ $historyTone }}">{{ $historyValue }}</span>
                            </div>
                        @endforeach
                        <div class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2">
                            <span class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ergebnis') }}</span>
                            <span class="mt-1 block text-[10px] font-black {{ $directionCorrect === null ? 'text-[var(--ak-muted)]' : ($directionCorrect ? 'text-teal-400' : 'text-rose-400') }}">{{ $directionCorrect === null ? __('Noch nicht validiert') : ($directionCorrect ? __('Richtung korrekt') : __('Richtung verfehlt')) }}</span>
                        </div>
                    </div>
                </aside>
            @endif
        </section>

        @php
            $indicatorDataPointCount = $indicatorCards->max(fn (array $card): int => count($card['points'])) ?? 0;
            $indicatorOverallProbability = $indicatorCards
                ->pluck('currentProbability')
                ->filter(fn ($value) => is_numeric($value))
                ->avg();
        @endphp
        <section x-cloak x-show="stockTab === 'indicators'" class="space-y-4">
            <article class="stock-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <div class="stock-detail-card-head flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-teal-500/25 bg-teal-500/10 text-teal-500">
                            <x-heroicon-o-presentation-chart-line class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Indikatoren Statistik') }}</p>
                            <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Historische Indikatoranalyse') }}</h2>
                            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Realisierte 20-Tage-Fälle aus den letzten drei Jahren') }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                            {{ number_format($indicatorDataPointCount, 0, ',', '.') }} {{ __('Datenpunkte') }}
                        </span>
                        <span class="rounded-xl border px-3 py-2 text-sm font-black tabular-nums {{ ($indicatorOverallProbability ?? 0) >= 50 ? 'border-teal-500/25 bg-teal-500/10 text-teal-500' : 'border-rose-500/25 bg-rose-500/10 text-rose-500' }}">
                            {{ $indicatorOverallProbability !== null ? number_format($indicatorOverallProbability, 1, ',', '.').' %' : '—' }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Mittlere Steigwahrscheinlichkeit') }}</span>
                    <div class="min-w-44 flex-1"><x-dashboard.score-stripes :percent="$indicatorOverallProbability ?? 0" palette="teal" /></div>
                </div>
            </article>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($indicatorCards as $index => $card)
                    <article class="stock-detail-panel stock-detail-panel-compact flex h-[230px] min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
                        <div class="stock-detail-card-head flex shrink-0 items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-teal-500/20 bg-teal-500/[.08] text-teal-500">
                                    <x-heroicon-o-chart-bar-square class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-black text-[var(--ak-text)]">{{ $card['label'] }}</p>
                                    <p class="mt-0.5 truncate text-[8px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">{{ __('20-Tage-Steigwahrscheinlichkeit') }}</p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="rounded-lg border border-amber-400/20 bg-amber-400/[.08] px-2 py-1 text-sm font-black tabular-nums text-amber-500">
                                    {{ is_numeric($card['currentValue']) ? number_format($card['currentValue'], 2, ',', '.').' '.$card['unit'] : '—' }}
                                </p>
                                @if (is_numeric($card['currentProbability']))
                                    <p class="mt-1 text-[8px] font-black">
                                        <span class="text-teal-500">{{ number_format($card['currentProbability'], 1, ',', '.') }} % ↑</span>
                                        <span class="text-[var(--ak-muted)]"> · </span>
                                        <span class="text-rose-500">{{ number_format($card['currentFallProbability'], 1, ',', '.') }} % ↓</span>
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="min-h-0 flex-1 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-1 py-0.5">
                            <div id="stock-indicator-probability-chart-{{ $index }}" class="h-full min-h-0"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @php
            $indicatorMatrixCards = $indicatorCards->keyBy('label');
            $indicatorMatrixBuild = static function (array $trendCard, array $oscillatorCard): array {
                $trendPoints = collect($trendCard['points'])->keyBy('date');
                $joined = collect($oscillatorCard['points'])->map(function (array $oscillatorPoint) use ($trendPoints): ?array {
                    $trendPoint = $trendPoints->get($oscillatorPoint['date']);
                    if (! $trendPoint || ! is_numeric($trendPoint['x'] ?? null) || ! is_numeric($oscillatorPoint['x'] ?? null)) return null;

                    return [
                        'trend' => (float) $trendPoint['x'],
                        'oscillator' => (float) $oscillatorPoint['x'],
                        'up' => (bool) $oscillatorPoint['up'],
                    ];
                })->filter()->values();
                $quantiles = static function ($values): array {
                    $sorted = collect($values)->sort()->values();
                    if ($sorted->isEmpty()) return [];

                    return collect([.2, .4, .6, .8])->map(
                        fn (float $quantile): float => (float) $sorted->get((int) floor(($sorted->count() - 1) * $quantile))
                    )->all();
                };
                $trendLimits = $quantiles($joined->pluck('trend'));
                $oscillatorLimits = $quantiles($joined->pluck('oscillator'));
                $binFor = static function (float $value, array $limits): int {
                    foreach ($limits as $index => $limit) {
                        if ($value <= $limit) return $index;
                    }

                    return 4;
                };
                $cells = array_fill(0, 5, array_fill(0, 5, ['samples' => 0, 'up' => 0]));
                foreach ($joined as $point) {
                    $x = $binFor($point['trend'], $trendLimits);
                    $y = $binFor($point['oscillator'], $oscillatorLimits);
                    $cells[$y][$x]['samples']++;
                    if ($point['up']) $cells[$y][$x]['up']++;
                }
                foreach ($cells as $y => $row) {
                    foreach ($row as $x => $cell) {
                        $cells[$y][$x]['probability'] = $cell['samples'] > 0 ? ($cell['up'] / $cell['samples']) * 100 : null;
                    }
                }

                return [
                    'trend' => $trendCard,
                    'oscillator' => $oscillatorCard,
                    'cells' => $cells,
                    'samples' => $joined->count(),
                    'trendAxis' => $joined->isEmpty() ? [] : [
                        (float) $joined->min('trend'), ...$trendLimits, (float) $joined->max('trend'),
                    ],
                    'oscillatorAxis' => $joined->isEmpty() ? [] : [
                        (float) $joined->min('oscillator'), ...$oscillatorLimits, (float) $joined->max('oscillator'),
                    ],
                    'currentX' => is_numeric($trendCard['currentValue'] ?? null) ? $binFor((float) $trendCard['currentValue'], $trendLimits) : null,
                    'currentY' => is_numeric($oscillatorCard['currentValue'] ?? null) ? $binFor((float) $oscillatorCard['currentValue'], $oscillatorLimits) : null,
                ];
            };
            $indicatorMatrixPairs = collect([
                [__('Momentum 10T'), 'Stochastik %K'],
                ['ADX 14', 'Stochastik %K'],
                ['MACD Histogramm', 'Stochastik %K'],
            ])->map(function (array $pair) use ($indicatorMatrixCards, $indicatorMatrixBuild): ?array {
                $trendCard = $indicatorMatrixCards->get($pair[0]);
                $oscillatorCard = $indicatorMatrixCards->get($pair[1]);

                return $trendCard && $oscillatorCard ? $indicatorMatrixBuild($trendCard, $oscillatorCard) : null;
            })->filter()->values();
        @endphp
        <section x-cloak x-show="stockTab === 'indicator-matrix'" class="space-y-4">
            <article class="stock-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <div class="stock-detail-card-head flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-teal-500/25 bg-teal-500/10 text-teal-500">
                            <x-heroicon-o-squares-plus class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Indikator Matrix') }}</p>
                            <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Trend trifft Stochastik') }}</h2>
                            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Historische 20-Tage-Steigwahrscheinlichkeit nach gemeinsamen Indikatorzuständen') }}</p>
                        </div>
                    </div>
                    <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                        {{ $indicatorMatrixPairs->count() }} {{ __('Vergleiche') }}
                    </span>
                </div>

                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(22rem, 26rem)); justify-content: start;">
                    @forelse ($indicatorMatrixPairs as $matrix)
                        <article class="min-w-0 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-2.5">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-black text-[var(--ak-text)]">{{ $matrix['trend']['label'] }} × {{ $matrix['oscillator']['label'] }}</p>
                                    <p class="mt-1 text-[8px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">{{ number_format($matrix['samples'], 0, ',', '.') }} {{ __('gemeinsame Fälle') }}</p>
                                </div>
                                <div class="flex gap-1.5 text-[8px] font-black uppercase">
                                    <span class="rounded-md bg-rose-500/15 px-1.5 py-1 text-rose-500">↓</span>
                                    <span class="rounded-md bg-amber-400/15 px-1.5 py-1 text-amber-500">50 %</span>
                                    <span class="rounded-md bg-teal-500/15 px-1.5 py-1 text-teal-500">↑</span>
                                </div>
                            </div>
                            <table class="mx-auto border-separate border-spacing-0.5" style="width: 17.25rem; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th style="width: 3rem;"></th>
                                        @foreach ([0, 1, 2, 3, 4] as $x)
                                            @php
                                                $xFrom = $matrix['trendAxis'][$x] ?? null;
                                                $xTo = $matrix['trendAxis'][$x + 1] ?? null;
                                            @endphp
                                            <th class="pb-0.5 text-center text-[6px] font-black tabular-nums text-[var(--ak-muted)]" title="{{ $xFrom !== null && $xTo !== null ? number_format($xFrom, 2, ',', '.').' – '.number_format($xTo, 2, ',', '.') : '—' }}">
                                                {{ $xFrom !== null && $xTo !== null ? number_format(($xFrom + $xTo) / 2, 1, ',', '.') : '—' }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ([4, 3, 2, 1, 0] as $y)
                                    @php
                                        $yFrom = $matrix['oscillatorAxis'][$y] ?? null;
                                        $yTo = $matrix['oscillatorAxis'][$y + 1] ?? null;
                                    @endphp
                                    <tr>
                                        <th class="pr-1 text-right text-[7px] font-black tabular-nums text-[var(--ak-muted)]" title="{{ $yFrom !== null && $yTo !== null ? number_format($yFrom, 1, ',', '.').' – '.number_format($yTo, 1, ',', '.') : '—' }}">
                                            {{ $yFrom !== null && $yTo !== null ? number_format(($yFrom + $yTo) / 2, 0, ',', '.') : '—' }}
                                        </th>
                                    @foreach ([0, 1, 2, 3, 4] as $x)
                                        @php
                                            $cell = $matrix['cells'][$y][$x];
                                            $probability = $cell['probability'];
                                            $cellClass = $probability === null
                                                ? 'border-[var(--ak-border)] bg-[var(--ak-card)] text-[var(--ak-muted)]'
                                                : ($probability >= 65
                                                    ? 'border-teal-400/45 bg-teal-500/35 text-[var(--ak-text)]'
                                                    : ($probability >= 55
                                                        ? 'border-teal-400/30 bg-teal-500/20 text-[var(--ak-text)]'
                                                        : ($probability >= 45
                                                            ? 'border-amber-400/30 bg-amber-400/20 text-[var(--ak-text)]'
                                                            : ($probability >= 35
                                                                ? 'border-rose-400/30 bg-rose-500/20 text-[var(--ak-text)]'
                                                                : 'border-rose-400/45 bg-rose-500/35 text-[var(--ak-text)]'))));
                                            $isCurrentCell = $matrix['currentX'] === $x && $matrix['currentY'] === $y;
                                            $cellBackgroundStyle = '';
                                            if ($probability !== null && $probability >= 65) {
                                                $cellBackgroundStyle = 'background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(34,197,94,.30));';
                                            } elseif ($probability !== null && $probability >= 55) {
                                                $cellBackgroundStyle = 'background: linear-gradient(135deg, rgba(16,185,129,.07), rgba(34,197,94,.19));';
                                            } elseif ($probability !== null && $probability >= 45) {
                                                $cellBackgroundStyle = 'background: linear-gradient(135deg, rgba(245,158,11,.05), rgba(250,204,21,.14));';
                                            } elseif ($probability !== null && $probability >= 35) {
                                                $cellBackgroundStyle = 'background: linear-gradient(135deg, rgba(244,63,94,.05), rgba(244,63,94,.14));';
                                            } elseif ($probability !== null) {
                                                $cellBackgroundStyle = 'background: linear-gradient(135deg, rgba(244,63,94,.09), rgba(225,29,72,.23));';
                                            }
                                        @endphp
                                        <td class="p-0.5">
                                            <div
                                                class="relative flex h-9 flex-col items-center justify-center rounded-md border text-center {{ $cellClass }} {{ $isCurrentCell ? 'z-10 border-cyan-300' : '' }}"
                                                style="{{ $cellBackgroundStyle }} {{ $isCurrentCell ? 'box-shadow: 0 0 0 3px #22d3ee, 0 0 16px rgba(34,211,238,.72); transform: scale(1.08);' : '' }}"
                                                title="{{ $isCurrentCell ? __('Aktuelle Indikatorkombination').' · ' : '' }}{{ $cell['samples'] }} {{ __('Fälle') }}"
                                            >
                                                @if ($isCurrentCell)
                                                    <span class="absolute right-0.5 top-0.5 grid h-2.5 w-2.5 place-items-center rounded-full border border-white/70 bg-cyan-400 shadow-[0_0_7px_rgba(34,211,238,1)]">
                                                        <span class="h-1 w-1 rounded-full bg-slate-950"></span>
                                                    </span>
                                                @endif
                                                <span class="text-[8px] font-black leading-none tabular-nums">{{ $probability !== null ? number_format($probability, 0, ',', '.').' %' : '—' }}</span>
                                                <span class="mt-1 text-[6px] font-bold leading-none opacity-65">n={{ $cell['samples'] }}</span>
                                            </div>
                                        </td>
                                    @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="mt-1.5 grid grid-cols-[auto_1fr] items-center gap-2 text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                                <span class="rounded-md border border-[var(--ak-border)] px-1.5 py-1">Y · {{ $matrix['oscillator']['label'] }}</span>
                                <span class="text-center">X · {{ $matrix['trend']['label'] }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-center gap-1.5 rounded-md border border-cyan-400/25 bg-cyan-400/[.08] px-2 py-1 text-[7px] font-black uppercase tracking-wide text-cyan-400">
                                <span class="h-2 w-2 rounded-sm border-2 border-cyan-300 shadow-[0_0_6px_rgba(34,211,238,.8)]"></span>
                                {{ __('Cyan markiert die aktuelle Kombination') }}
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[var(--ak-border)] px-4 py-10 text-center text-xs font-bold text-[var(--ak-muted)] xl:col-span-3">
                            {{ __('Keine gemeinsamen Indikatordaten verfügbar.') }}
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section x-cloak x-show="stockTab === 'analysis'" class="rounded-[1.5rem] border border-teal-500/20 bg-[linear-gradient(120deg,rgba(6, 182, 212,.07),var(--ak-card))] p-5 shadow-[var(--ak-shadow)]">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-teal-500/25 bg-teal-500/10 p-1.5 shadow-[0_0_18px_rgba(6, 182, 212,.08)]">
                        <img src="{{ asset('assets/aki-robot-logo.svg') }}" alt="{{ __('aKI Logo') }}" class="h-full w-full object-contain">
                    </span>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-600">{{ __('aKI-Kommentar') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Analyse und Risikoeinordnung') }}</h2>
                    </div>
                </div>
            </div>

            @if ($aiAssessment)
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    @foreach ([
                        [__('Chancen'), $aiAssessmentOpportunities, 'text-teal-500', 'border-teal-500/15 bg-teal-500/[.05]'],
                        [__('Risiken'), $aiAssessmentRisks, 'text-rose-500', 'border-rose-500/15 bg-rose-500/[.05]'],
                        [__('Schlüsselfaktoren'), $aiAssessmentFactors, 'text-amber-500', 'border-amber-500/15 bg-amber-500/[.05]'],
                    ] as [$assessmentTitle, $assessmentItems, $assessmentTone, $assessmentBox])
                        <div class="rounded-xl border p-3 {{ $assessmentBox }}">
                            <p class="text-[9px] font-black uppercase tracking-[.12em] {{ $assessmentTone }}">{{ $assessmentTitle }}</p>
                            <ul class="mt-2 space-y-1.5">
                                @forelse ($assessmentItems as $assessmentItem)
                                    <li class="flex gap-2 text-xs leading-5 text-[var(--ak-muted)]">
                                        <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-current {{ $assessmentTone }}"></span>
                                        <span>{{ is_scalar($assessmentItem) ? $assessmentItem : json_encode($assessmentItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</span>
                                    </li>
                                @empty
                                    <li class="text-xs text-[var(--ak-muted)]">—</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-[9px] font-bold text-[var(--ak-muted)]">
                    {{ \Illuminate\Support\Carbon::parse($aiAssessment->assessment_date)->format('d.m.Y') }}
                    · {{ $aiAssessment->model }}
                    · {{ __('Keine Anlageberatung') }}
                </p>
            @else
                <div class="mt-4 rounded-xl border border-dashed border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 py-5 text-center">
                    <p class="text-sm font-bold text-[var(--ak-muted)]">{{ __('Für diese Aktie ist noch kein gespeicherter aKI-Kommentar vorhanden.') }}</p>
                </div>
            @endif

        </section>

        @include('stocks.partials.backtest-heatmap')

        <section x-cloak x-show="stockTab === 'fundamentals' || stockTab === 'aki'" class="grid gap-5">
            <article x-show="stockTab === 'fundamentals'" class="min-h-0">
                @php
                    $fundamentalPercent = static function (mixed $value): ?string {
                        if (! is_numeric($value)) return null;
                        $number = (float) $value;
                        if (abs($number) <= 1) $number *= 100;

                        return number_format($number, 2, ',', '.').' %';
                    };
                    $fundamentalGroups = [
                        [
                            'title' => __('Instrument'),
                            'subtitle' => __('Identifikation und Handelswährung'),
                            'icon' => 'heroicon-o-identification',
                            'values' => [
                                ['label' => __('Symbol'), 'value' => $instrument->symbol],
                                ['label' => 'ISIN', 'value' => $instrument->isin],
                                ['label' => __('Land'), 'value' => $instrument->country],
                                ['label' => __('Währung'), 'value' => $instrument->currency],
                            ],
                        ],
                        [
                            'title' => __('Einordnung'),
                            'subtitle' => __('Geschäftsbereich und Marktsegment'),
                            'icon' => 'heroicon-o-squares-2x2',
                            'values' => [
                                ['label' => __('Sektor'), 'value' => __($instrument->sector ?: '—')],
                                ['label' => __('Branche'), 'value' => $instrument->industry],
                            ],
                        ],
                        [
                            'title' => __('Bewertung'),
                            'subtitle' => __('Größe und aktuelle Bewertung'),
                            'icon' => 'heroicon-o-scale',
                            'values' => [
                                ['label' => __('Marktkapitalisierung'), 'value' => $fundamentalData['marketCap'] ?? $instrument->market_cap],
                                [
                                    'label' => __('KGV'),
                                    'value' => is_numeric($fundamentalData['trailingPE'] ?? null)
                                        ? number_format((float) $fundamentalData['trailingPE'], 2, ',', '.')
                                        : null,
                                    'ranking' => $sectorRankings['pe'] ?? null,
                                ],
                                ['label' => __('Forward-KGV'), 'value' => is_numeric($fundamentalData['forwardPE'] ?? null) ? number_format((float) $fundamentalData['forwardPE'], 2, ',', '.') : null],
                                ['label' => __('Kurs-Buchwert-Verhältnis'), 'value' => is_numeric($fundamentalData['priceToBook'] ?? null) ? number_format((float) $fundamentalData['priceToBook'], 2, ',', '.') : null],
                            ],
                        ],
                        [
                            'title' => __('Ausschüttung'),
                            'subtitle' => __('Dividende und Rendite'),
                            'icon' => 'heroicon-o-banknotes',
                            'values' => [
                                [
                                    'label' => __('Dividende / Aktie'),
                                    'value' => is_numeric($fundamentalData['dividendRate'] ?? null)
                                        ? number_format((float) $fundamentalData['dividendRate'], 2, ',', '.').' '.$currency
                                        : null,
                                ],
                                [
                                    'label' => __('Dividendenrendite'),
                                    'value' => $fundamentalPercent($fundamentalData['dividendYield'] ?? null),
                                    'ranking' => $sectorRankings['dividend'] ?? null,
                                ],
                            ],
                        ],
                        [
                            'title' => __('Profitabilität'),
                            'subtitle' => __('Margen und Kapitalrenditen'),
                            'icon' => 'heroicon-o-arrow-trending-up',
                            'values' => [
                                ['label' => __('Nettomarge'), 'value' => $fundamentalPercent($fundamentalData['profitMargins'] ?? null)],
                                ['label' => __('Operative Marge'), 'value' => $fundamentalPercent($fundamentalData['operatingMargins'] ?? null)],
                                ['label' => __('Eigenkapitalrendite'), 'value' => $fundamentalPercent($fundamentalData['returnOnEquity'] ?? null)],
                                ['label' => __('Gesamtkapitalrendite'), 'value' => $fundamentalPercent($fundamentalData['returnOnAssets'] ?? null)],
                            ],
                        ],
                        [
                            'title' => __('Ergebnis und Wachstum'),
                            'subtitle' => __('Umsatz, Ergebnis und Dynamik'),
                            'icon' => 'heroicon-o-chart-bar-square',
                            'values' => [
                                ['label' => __('Umsatz'), 'value' => $fundamentalData['totalRevenue'] ?? null],
                                ['label' => __('Umsatzwachstum'), 'value' => $fundamentalPercent($fundamentalData['revenueGrowth'] ?? null)],
                                ['label' => __('Bruttogewinn'), 'value' => $fundamentalData['grossProfits'] ?? null],
                                ['label' => 'EBITDA', 'value' => $fundamentalData['ebitda'] ?? null],
                            ],
                        ],
                        [
                            'title' => __('Bilanz und Liquidität'),
                            'subtitle' => __('Liquidität und Verschuldung'),
                            'icon' => 'heroicon-o-building-library',
                            'values' => [
                                ['label' => __('Liquide Mittel'), 'value' => $fundamentalData['totalCash'] ?? null],
                                ['label' => __('Gesamtverschuldung'), 'value' => $fundamentalData['totalDebt'] ?? null],
                                ['label' => __('Verschuldungsgrad'), 'value' => is_numeric($fundamentalData['debtToEquity'] ?? null) ? number_format((float) $fundamentalData['debtToEquity'], 2, ',', '.') : null],
                                ['label' => __('Liquiditätsgrad'), 'value' => is_numeric($fundamentalData['currentRatio'] ?? null) ? number_format((float) $fundamentalData['currentRatio'], 2, ',', '.') : null],
                            ],
                        ],
                        [
                            'title' => __('Cashflow'),
                            'subtitle' => __('Operativer und freier Mittelzufluss'),
                            'icon' => 'heroicon-o-arrows-right-left',
                            'values' => [
                                ['label' => __('Operativer Cashflow'), 'value' => $fundamentalData['operatingCashflow'] ?? null],
                                ['label' => __('Freier Cashflow'), 'value' => $fundamentalData['freeCashflow'] ?? null],
                                ['label' => __('Nettogewinn'), 'value' => $fundamentalData['netIncomeToCommon'] ?? null],
                            ],
                        ],
                        [
                            'title' => __('Handelsstatus'),
                            'subtitle' => __('Verfügbarkeit in aktienKI.com'),
                            'icon' => 'heroicon-o-check-badge',
                            'values' => [
                                ['label' => __('Handelbar'), 'value' => (bool) $instrument->is_tradeable],
                                ['label' => __('Aktiv'), 'value' => (bool) $instrument->is_active],
                            ],
                        ],
                    ];
                @endphp

                <div class="mb-3 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-400/10 text-amber-300"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span>
                    <div>
                        <h2 class="font-black text-[var(--ak-text)]">{{ __('Fundamentaldaten') }}</h2>
                        <p class="text-xs text-[var(--ak-muted)]">{{ __('Thematisch zusammengefasste Unternehmens- und Instrumentendaten') }}</p>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($fundamentalGroups as $group)
                        <section class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                            <div class="flex items-center gap-2.5 border-b border-[var(--ak-border)] pb-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-400/10 text-amber-300">
                                    <x-dynamic-component :component="$group['icon']" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-black text-[var(--ak-text)]">{{ $group['title'] }}</h3>
                                    <p class="truncate text-[10px] text-[var(--ak-muted)]">{{ $group['subtitle'] }}</p>
                                </div>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3">
                                @foreach ($group['values'] as $item)
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $item['label'] }}</dt>
                                        <dd class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <span class="break-words text-sm font-bold text-[var(--ak-text)]">
                                                {{ $item['value'] === null || $item['value'] === '' ? '—' : $formatValue((string) $item['label'], $item['value']) }}
                                            </span>
                                            @if ($item['ranking'] ?? null)
                                                <span class="inline-flex rounded-md border border-teal-400/20 bg-teal-500/10 px-1.5 py-0.5 text-[9px] font-bold text-teal-300">
                                                    {{ __('Rang :rank von :total im Sektor', [
                                                        'rank' => $item['ranking']['rank'],
                                                        'total' => $item['ranking']['total'],
                                                    ]) }}
                                                </span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endforeach
                </div>
            </article>

            <article id="aki-data-panel" x-show="stockTab === 'aki'" class="flex min-h-0 flex-col overflow-hidden">
                <div class="flex shrink-0 items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-300"><x-heroicon-o-sparkles class="h-4 w-4" /></span>
                    <div><h2 class="text-base font-black text-[var(--ak-text)]">{{ __('Alle Prognosewerte') }}</h2><p class="text-xs text-[var(--ak-muted)]">{{ __('Neueste verfügbare Modellberechnung') }}</p></div>
                </div>
                @if ($modelQuality)
                    @php
                        $modelTierCode = $modelQuality->tier_code ?: 'unqualified';
                        $modelTierName = $modelQuality->tier_name ? __($modelQuality->tier_name) : __('Nicht qualifiziert');
                        $modelTierClass = match ($modelTierCode) {
                            'top' => 'ak-model-tier-top',
                            'strong' => 'ak-model-tier-strong',
                            'solid' => 'ak-model-tier-solid',
                            'test' => 'ak-model-tier-test',
                            default => 'ak-model-tier-unqualified',
                        };
                        $modelQualityPercent = is_numeric($modelQuality->quality_score)
                            ? ((float) $modelQuality->quality_score <= 1
                                ? (float) $modelQuality->quality_score * 100
                                : (float) $modelQuality->quality_score)
                            : null;
                    @endphp
                @endif
                <div class="mt-2 grid min-h-0 flex-1 items-stretch gap-2 md:grid-cols-2 xl:grid-cols-3 xl:grid-rows-[minmax(0,1fr)_auto]">
                    @include('stocks.partials.top-stock-analysis')
                    <div class="h-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]">
                        <p class="mb-1 text-[10px] font-black uppercase tracking-[.12em] text-teal-500">{{ __('Modelldaten') }}</p>
                        @if ($modelQuality || $predictionData)
                            <dl class="grid grid-cols-2 content-start gap-x-3 gap-y-4">
                                @if ($modelQuality)
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Modell') }}</dt>
                                        <dd class="truncate text-[13px] font-bold text-[var(--ak-text)]">{{ $modelQuality->model_alias ?: '—' }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Qualitätsstufe') }}</dt>
                                        <dd class="mt-0.5"><span class="ak-model-tier {{ $modelTierClass }}">{{ $modelTierName }}</span></dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Modellqualität') }}</dt>
                                        <dd class="text-[13px] font-bold text-[var(--ak-text)]">{{ $modelQualityPercent !== null ? number_format($modelQualityPercent, 1, ',', '.').' %' : '—' }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Letztes Training') }}</dt>
                                        <dd class="text-[13px] font-bold tabular-nums text-[var(--ak-text)]">
                                            {{ $modelQuality->trained_at ? \Illuminate\Support\Carbon::parse($modelQuality->trained_at)->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—' }}
                                        </dd>
                                    </div>
                                @endif
                                @foreach ($predictionData as $key => $value)
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $label($key) }}</dt>
                                        <dd class="break-words text-[13px] font-bold text-[var(--ak-text)]">
                                            @if ($key === 'quality_gate_passed')
                                                <span class="ak-model-tier {{ $value === null ? 'border-slate-500/25 bg-slate-500/10 text-slate-400' : ($value ? 'border-teal-500/30 bg-teal-500/15 text-teal-400' : 'border-rose-500/30 bg-rose-500/15 text-rose-400') }}">
                                                    {{ $value === null ? '—' : ($value ? __('Bestanden') : __('Nicht bestanden')) }}
                                            </span>
                                            @elseif ($value === null)
                                                —
                                            @else
                                                {{ $formatValue($key, $value) }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <p class="text-sm text-[var(--ak-muted)]">{{ __('Noch keine Prognosewerte vorhanden.') }}</p>
                        @endif
                    </div>
                    @if ($ensembleData)
                        <div class="h-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)] md:col-span-2 xl:col-span-1">
                            <p class="mb-1 text-[10px] font-black uppercase tracking-[.12em] text-teal-500">{{ __('Ensemble-Daten') }}</p>
                            <dl class="grid grid-cols-2 content-start gap-x-3 gap-y-4">
                                @foreach ($ensembleData as $ensembleLabel => $ensembleValue)
                                    <div class="min-w-0">
                                        <dt class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $ensembleLabel }}</dt>
                                        <dd class="break-words text-[13px] font-bold text-[var(--ak-text)]">
                                            @if ($ensembleLabel === __('Ensemble-Veto'))
                                                <span class="ak-model-tier {{ $ensembleValue === null ? 'border-slate-500/25 bg-slate-500/10 text-slate-400' : ($ensembleValue ? 'border-rose-500/30 bg-rose-500/15 text-rose-400' : 'border-teal-500/30 bg-teal-500/15 text-teal-400') }}">
                                                    {{ $ensembleValue === null ? '—' : ($ensembleValue ? __('Ja') : __('Nein')) }}
                                                </span>
                                            @elseif ($ensembleValue === null)
                                                —
                                            @elseif (is_bool($ensembleValue))
                                                {{ $ensembleValue ? __('Ja') : __('Nein') }}
                                            @elseif ($ensembleLabel === __('Ensemble-Score') && is_numeric($ensembleValue))
                                                {{ number_format((float) $ensembleValue, 1, ',', '.') }} %
                                            @elseif (in_array($ensembleLabel, [
                                                __('Relative Streuung'),
                                                __('Modellübereinstimmung'),
                                                __('Ø Modellqualität'),
                                                __('Schwächste Modellqualität'),
                                                __('Ø Stabilität'),
                                                __('Statistische Zuverlässigkeit'),
                                            ], true) && is_numeric($ensembleValue))
                                                {{ number_format((float) $ensembleValue * 100, 1, ',', '.') }} %
                                            @elseif ($ensembleLabel === __('Ø Profit-Faktor') && is_numeric($ensembleValue))
                                                {{ number_format((float) $ensembleValue, 2, ',', '.') }}
                                            @else
                                                {{ $ensembleValue }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                    @if ($modelQuality)
                        <div
                            style="grid-column: 1 / -1; height: 4rem;"
                            class="w-full overflow-hidden rounded-lg border border-[var(--ak-border)] bg-amber-500/[.06] px-2.5 py-1.5 {{ $modelTierCode === 'top' ? 'invisible pointer-events-none' : '' }}"
                            @if ($modelTierCode === 'top') aria-hidden="true" @endif
                        >
                            <p class="text-xs font-black uppercase tracking-[.11em] text-amber-500">{{ __('Warum kein Quality-Gate-Modell?') }}</p>
                            @if ($modelQualityGateReasons->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @foreach ($modelQualityGateReasons as $gateReason)
                                        <span class="rounded border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-1 text-[11px] font-bold text-[var(--ak-muted)]">
                                            {{ $gateReason['name'] }}:
                                            <strong class="text-[var(--ak-text)]">{{ number_format($gateReason['actual'], $gateReason['unit'] === '%' ? 1 : 2, ',', '.') }}{{ $gateReason['unit'] }}</strong>
                                            {{ $gateReason['direction'] === 'min' ? '<' : '>' }}
                                            {{ number_format($gateReason['threshold'], $gateReason['unit'] === '%' ? 1 : 2, ',', '.') }}{{ $gateReason['unit'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1.5 text-[11px] font-medium text-[var(--ak-muted)]">{{ __('Das Modell erfüllt derzeit nicht alle Freigabekriterien der Quality-Gate-Stufe.') }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </article>
        </section>

        <p id="stock-disclaimer" class="pb-3 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Die Darstellung dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar.') }}</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const panel = document.querySelector('#aki-data-panel');
            const disclaimer = document.querySelector('#stock-disclaimer');
            if (!panel) return;

            const fitAkiPanel = () => {
                if (window.innerWidth < 1024) {
                    panel.style.height = '';
                    return;
                }
                if (panel.offsetParent === null) return;

                const top = panel.getBoundingClientRect().top;
                const footerHeight = disclaimer?.getBoundingClientRect().height ?? 0;
                const availableHeight = Math.floor(window.innerHeight - top - footerHeight - 12);

                panel.style.height = availableHeight >= 360 ? `${availableHeight}px` : '';
            };

            window.addEventListener('resize', () => window.requestAnimationFrame(fitAkiPanel));
            window.requestAnimationFrame(fitAkiPanel);
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.ApexCharts) return;
            const cards = @json($indicatorCards);
            let rendered = false;

            const renderIndicatorCards = () => {
                if (rendered) return;
                rendered = true;

                cards.forEach((card, index) => {
                    const element = document.querySelector(`#stock-indicator-probability-chart-${index}`);
                    if (!element || !card.points?.length) return;
                    const sorted = [...card.points].sort((left, right) => left.x - right.x);
                    const binSize = Math.max(8, Math.ceil(sorted.length / 24));
                    const histogram = [];

                    for (let start = 0; start < sorted.length; start += binSize) {
                        const sample = sorted.slice(start, start + binSize);
                        if (!sample.length) continue;
                        const probability = sample.filter(point => point.up).length / sample.length * 100;
                        histogram.push({
                            x: sample.reduce((sum, point) => sum + point.x, 0) / sample.length,
                            y: probability,
                            fillColor: probability > 55 ? '#2a9d96' : (probability < 45 ? '#b86470' : '#b6a15b'),
                        });
                    }

                    const chart = new ApexCharts(element, {
                        series: [{ name: @json(__('20-Tage-Steigwahrscheinlichkeit')), data: histogram }],
                        chart: {
                            type: 'bar',
                            height: 198,
                            background: 'transparent',
                            animations: { enabled: false },
                            toolbar: { show: false },
                            zoom: { enabled: false },
                            selection: { enabled: false },
                            parentHeightOffset: 0,
                        },
                        plotOptions: { bar: { columnWidth: '82%', borderRadius: 2, borderRadiusApplication: 'end' } },
                        fill: { type: 'solid', opacity: .7 },
                        stroke: { width: 0 },
                        dataLabels: { enabled: false },
                        grid: {
                            borderColor: 'rgba(100,116,139,.14)',
                            strokeDashArray: 3,
                            padding: { top: 4, right: 8, bottom: 4, left: 4 },
                        },
                        xaxis: {
                            type: 'numeric',
                            tickAmount: 4,
                            labels: {
                                formatter: value => Number(value).toFixed(2),
                                style: { colors: '#82909f', fontSize: '8px' },
                            },
                            axisBorder: { show: true, color: 'rgba(148,163,184,.38)' },
                            axisTicks: { show: false },
                        },
                        yaxis: {
                            min: 0,
                            max: 100,
                            tickAmount: 4,
                            labels: {
                                formatter: value => `${Math.round(value)} %`,
                                style: { colors: ['#82909f'], fontSize: '8px' },
                            },
                        },
                        tooltip: {
                            x: { formatter: value => `${card.label}: ${Number(value).toFixed(2)} ${card.unit || ''}` },
                            y: { formatter: value => `${Number(value).toFixed(1)} %` },
                        },
                        annotations: {
                            yaxis: [{ y: 50, borderColor: '#94a3b8', strokeDashArray: 5 }],
                            ...(Number.isFinite(card.currentValue) ? {
                                xaxis: [{
                                    x: card.currentValue,
                                    borderColor: '#f4c75b',
                                    strokeDashArray: 4,
                                    label: {
                                        text: @json(__('Aktuell')),
                                        style: { background: '#f4c75b', color: '#171717', fontSize: '8px', fontWeight: 800 },
                                    },
                                }],
                            } : {}),
                        },
                    });
                    element.__aktienkiChart = chart;
                    chart.render();
                });
            };

            window.addEventListener('stock-tab-changed', event => {
                if (event.detail?.tab === 'indicators') renderIndicatorCards();
            });
        });
    </script>

    @if ($chartCandles->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const element = document.querySelector('#stock-detail-chart');
                if (!element || !window.ApexCharts) return;

                const initialCandles = @json($chartCandles->values());
                const historicalAiScores = @json($historicalAiScores->values());
                const historicalSignalTransitions = @json($historicalSignalTransitions->values());
                const initialWatchlistEntry = @json($watchlistEntry);
                const initialChartPatterns = @json($chartPatterns);
                const currency = @json($currency);
                const lightTheme = document.documentElement.dataset.theme === 'light';
                const sectorColor = lightTheme ? '#06b6d4' : @json($sectorColor);
                const predictedPrice20d = @json(is_numeric($prediction?->predicted_price_20d) ? (float) $prediction->predicted_price_20d : null);
                const chartFocusAt = @json($chartFocusAt?->getTimestampMs());
                const liveSourceSymbol = @json($requestedPredictionId === 0 ? $instrument->symbol : null);
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
                let chartPatterns = initialChartPatterns;
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
                    return [{
                        name: @json($instrument->symbol),
                        type: 'candlestick',
                        data: currentCandles,
                    }];
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
                    const origin = forecastOrigin();
                    const forecastStart = origin ? new Date(origin.x).getTime() : NaN;
                    const forecastEnd = Number.isFinite(forecastStart)
                        ? addTradingDays(forecastStart, 20)
                        : NaN;

                    if (Number.isFinite(chartFocusAt)) {
                        const fiftyDays = 50 * 24 * 60 * 60 * 1000;

                        return {
                            min: chartFocusAt - fiftyDays,
                            max: Number.isFinite(forecastEnd) ? forecastEnd : chartFocusAt + fiftyDays,
                        };
                    }

                    const firstTimestamp = new Date(currentCandles[0]?.x).getTime();

                    return {
                        min: Number.isFinite(firstTimestamp) ? firstTimestamp : undefined,
                        max: Number.isFinite(forecastEnd) ? forecastEnd : undefined,
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

                const options = (includeSeries = true) => {
                    const light = document.documentElement.dataset.theme === 'light';
                    const series = chartSeries();
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

                    const chartOptions = {
                        chart: {
                            type: 'candlestick',
                            height: '100%',
                            background: 'transparent',
                            toolbar: { show: false },
                            zoom: {
                                enabled: false,
                                allowMouseWheelZoom: false,
                            },
                            selection: { enabled: false },
                            pan: { enabled: false },
                            animations: { enabled: true, speed: 350 },
                        },
                        stroke: { width: 1 },
                        fill: { opacity: 1 },
                        plotOptions: {
                            bar: {
                                columnWidth: '62%',
                            },
                            candlestick: {
                                colors: { upward: '#22c55e', downward: '#ef4444' },
                                wick: { useFillColor: false },
                            },
                        },
                        annotations: { yaxis: entryAnnotation, xaxis: focusAnnotation },
                        dataLabels: { enabled: false },
                        states: {
                            hover: { filter: { type: 'none' } },
                            active: { filter: { type: 'none' } },
                        },
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
                            crosshairs: { show: false },
                        },
                        yaxis: {
                            opposite: true,
                            min: chartPriceRange().min,
                            max: chartPriceRange().max,
                            forceNiceScale: false,
                            decimalsInFloat: 2,
                            labels: { formatter: value => value.toFixed(2) + ' ' + currency, style: { colors: [light ? '#64748b' : '#94a3b8'] } },
                        },
                        tooltip: {
                            theme: light ? 'light' : 'dark',
                            x: { format: 'dd.MM.yyyy' },
                            y: {
                                formatter: value => Number.isFinite(Number(value))
                                    ? `${Number(value).toFixed(2)} ${currency}`
                                    : '—',
                            },
                        },
                        theme: { mode: light ? 'light' : 'dark' }
                    };
                    if (includeSeries) chartOptions.series = series;

                    return chartOptions;
                };

                const rsiOptions = () => {
                    const light = document.documentElement.dataset.theme === 'light';
                    const values = rsiData();

                    return {
                        chart: {
                            type: 'line',
                            height: 64,
                            background: 'transparent',
                            toolbar: { show: false },
                            zoom: { enabled: false, allowMouseWheelZoom: false },
                            selection: { enabled: false },
                            pan: { enabled: false },
                            animations: { enabled: true, speed: 250 },
                            parentHeightOffset: 0,
                        },
                        series: [{ name: 'RSI 14', data: values }],
                        colors: [sectorColor],
                        stroke: { width: 2, curve: 'smooth' },
                        markers: { size: 0 },
                        dataLabels: { enabled: false },
                        states: {
                            hover: { filter: { type: 'none' } },
                            active: { filter: { type: 'none' } },
                        },
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
                    if (!indicatorOverlay) return;

                    const width = indicatorOverlay.clientWidth;
                    const height = indicatorOverlay.clientHeight;
                    const timeRange = chartTimeRange();
                    const priceRange = chartPriceRange();
                    if (!width || !height || !Number.isFinite(timeRange.min) || !Number.isFinite(timeRange.max)
                        || !Number.isFinite(priceRange.min) || !Number.isFinite(priceRange.max)) return;

                    indicatorOverlay.setAttribute('viewBox', `0 0 ${width} ${height}`);
                    indicatorOverlay.replaceChildren();

                    const left = 18;
                    const top = 16;
                    const plotWidth = Math.max(1, width - left - 86);
                    const plotHeight = Math.max(1, height - top - 32);
                    const xSpan = timeRange.max - timeRange.min;
                    const ySpan = priceRange.max - priceRange.min;

                    const forecastCandle = forecastOrigin();
                    const forecastStart = forecastCandle ? new Date(forecastCandle.x).getTime() : NaN;
                    const forecastStartPrice = Number(forecastCandle?.y?.[3]);
                    if (Number.isFinite(predictedPrice20d) && Number.isFinite(forecastStart) && Number.isFinite(forecastStartPrice)) {
                        const forecastEnd = addTradingDays(forecastStart, 20);
                        const positive = predictedPrice20d >= forecastStartPrice;
                        const forecastColor = positive ? '#22c55e' : '#ef4444';
                        const toX = timestamp => left + ((timestamp - timeRange.min) / xSpan) * plotWidth;
                        const toY = price => top + plotHeight - ((price - priceRange.min) / ySpan) * plotHeight;

                        const definitions = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                        const pattern = document.createElementNS('http://www.w3.org/2000/svg', 'pattern');
                        const patternId = `forecast-hatch-${@json($instrument->id)}`;
                        pattern.setAttribute('id', patternId);
                        pattern.setAttribute('width', '7');
                        pattern.setAttribute('height', '7');
                        pattern.setAttribute('patternUnits', 'userSpaceOnUse');
                        pattern.setAttribute('patternTransform', 'rotate(35)');
                        const hatch = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                        hatch.setAttribute('x1', '0');
                        hatch.setAttribute('y1', '0');
                        hatch.setAttribute('x2', '0');
                        hatch.setAttribute('y2', '7');
                        hatch.setAttribute('stroke', forecastColor);
                        hatch.setAttribute('stroke-width', '1');
                        hatch.setAttribute('stroke-opacity', '0.38');
                        pattern.appendChild(hatch);
                        definitions.appendChild(pattern);
                        indicatorOverlay.appendChild(definitions);

                        const triangle = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                        triangle.setAttribute('points', [
                            `${toX(forecastStart)},${toY(forecastStartPrice)}`,
                            `${toX(forecastEnd)},${toY(Math.max(forecastStartPrice, predictedPrice20d))}`,
                            `${toX(forecastEnd)},${toY(Math.min(forecastStartPrice, predictedPrice20d))}`,
                        ].join(' '));
                        triangle.setAttribute('fill', `url(#${patternId})`);
                        triangle.setAttribute('stroke', forecastColor);
                        triangle.setAttribute('stroke-width', '1.15');
                        triangle.setAttribute('stroke-dasharray', '5 5');
                        triangle.setAttribute('stroke-opacity', '0.62');
                        triangle.setAttribute('stroke-linejoin', 'round');
                        triangle.setAttribute('vector-effect', 'non-scaling-stroke');
                        indicatorOverlay.appendChild(triangle);
                    }

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

                const svgNode = (name, attributes = {}) => {
                    const node = document.createElementNS('http://www.w3.org/2000/svg', name);
                    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
                    return node;
                };

                const renderMainChart = () => {
                    const width = element.clientWidth;
                    const height = element.clientHeight;
                    if (!width || !height) return;

                    const light = document.documentElement.dataset.theme === 'light';
                    const timeRange = chartTimeRange();
                    const priceRange = chartPriceRange();
                    const left = 18;
                    const top = 16;
                    const right = 86;
                    const bottom = 32;
                    const plotWidth = Math.max(1, width - left - right);
                    const plotHeight = Math.max(1, height - top - bottom);
                    const xSpan = Math.max(1, timeRange.max - timeRange.min);
                    const ySpan = Math.max(0.0001, priceRange.max - priceRange.min);
                    const toX = timestamp => left + ((timestamp - timeRange.min) / xSpan) * plotWidth;
                    const toY = price => top + plotHeight - ((price - priceRange.min) / ySpan) * plotHeight;
                    const svg = svgNode('svg', {
                        viewBox: `0 0 ${width} ${height}`,
                        width: '100%',
                        height: '100%',
                        role: 'img',
                    });
                    const gridColor = light ? 'rgba(51,65,85,.13)' : 'rgba(148,163,184,.11)';
                    const labelColor = light ? '#64748b' : '#94a3b8';

                    for (let index = 0; index <= 4; index += 1) {
                        const ratio = index / 4;
                        const y = top + ratio * plotHeight;
                        const price = priceRange.max - ratio * ySpan;
                        svg.appendChild(svgNode('line', {
                            x1: left, x2: left + plotWidth, y1: y, y2: y,
                            stroke: gridColor, 'stroke-dasharray': '4 5',
                        }));
                        const label = svgNode('text', {
                            x: left + plotWidth + 10, y: y + 3,
                            fill: labelColor, 'font-size': '10', 'font-family': 'inherit',
                        });
                        label.textContent = `${price.toFixed(2)} ${currency}`;
                        svg.appendChild(label);
                    }

                    const visibleCandles = currentCandles.filter(candle => {
                        const timestamp = new Date(candle.x).getTime();
                        return Number.isFinite(timestamp) && timestamp >= timeRange.min && timestamp <= timeRange.max;
                    });
                    const candleWidth = Math.max(2.5, Math.min(7, (plotWidth / Math.max(visibleCandles.length, 1)) * 0.42));

                    visibleCandles.forEach(candle => {
                        const timestamp = new Date(candle.x).getTime();
                        const [open, high, low, close] = candle.y.map(Number);
                        if (![open, high, low, close].every(Number.isFinite)) return;
                        const x = toX(timestamp);
                        const color = close >= open ? '#22c55e' : '#ef4444';
                        svg.appendChild(svgNode('line', {
                            x1: x, x2: x, y1: toY(high), y2: toY(low),
                            stroke: color, 'stroke-width': '1', 'stroke-opacity': '.72',
                        }));
                        svg.appendChild(svgNode('rect', {
                            x: x - candleWidth / 2,
                            y: Math.min(toY(open), toY(close)),
                            width: candleWidth,
                            height: Math.max(2, Math.abs(toY(open) - toY(close))),
                            rx: '.75',
                            fill: color,
                        }));
                    });

                    chartPatterns.forEach((pattern, index) => {
                        const from = Number(pattern.from);
                        const to = Number(pattern.to);
                        const low = Number(pattern.low);
                        const high = Number(pattern.high);
                        if (![from, to, low, high].every(Number.isFinite) || to < timeRange.min || from > timeRange.max) return;
                        const bullish = pattern.direction === 'bullish';
                        const color = bullish ? '#22c55e' : '#ef4444';
                        const candleStep = visibleCandles.length > 1
                            ? Math.abs(toX(new Date(visibleCandles[1].x).getTime()) - toX(new Date(visibleCandles[0].x).getTime()))
                            : 8;
                        const x1 = Math.max(left, toX(from) - candleStep * .48);
                        const x2 = Math.min(left + plotWidth, toX(to) + candleStep * .48);
                        const y1 = Math.max(top, toY(high) - 7);
                        const y2 = Math.min(top + plotHeight, toY(low) + 7);
                        svg.appendChild(svgNode('rect', {
                            x: x1, y: y1, width: Math.max(8, x2 - x1), height: Math.max(12, y2 - y1), rx: '4',
                            fill: color, 'fill-opacity': '.08', stroke: color, 'stroke-width': '1.5',
                            'stroke-dasharray': '3 2', 'vector-effect': 'non-scaling-stroke',
                        }));
                        const labelText = `${bullish ? '↗' : '↘'} ${pattern.name}`;
                        const labelWidth = Math.max(72, labelText.length * 5.3 + 12);
                        const labelX = Math.max(left, Math.min(left + plotWidth - labelWidth, (x1 + x2 - labelWidth) / 2));
                        const labelY = Math.max(top + 2, y1 - 18 - ((index % 2) * 16));
                        svg.appendChild(svgNode('rect', {
                            x: labelX, y: labelY, width: labelWidth, height: 15, rx: '4',
                            fill: color, 'fill-opacity': '.18', stroke: color, 'stroke-opacity': '.55',
                        }));
                        const label = svgNode('text', {
                            x: labelX + labelWidth / 2, y: labelY + 10.5, fill: color,
                            'font-size': '8', 'font-weight': '800', 'text-anchor': 'middle', 'font-family': 'inherit',
                        });
                        label.textContent = labelText;
                        svg.appendChild(label);
                    });

                    const visibleAiScores = historicalAiScores
                        .map(point => ({ x: new Date(point.x).getTime(), y: Number(point.y) }))
                        .filter(point => Number.isFinite(point.x) && Number.isFinite(point.y)
                            && point.x >= timeRange.min && point.x <= timeRange.max);
                    if (visibleAiScores.length >= 2) {
                        const toAiY = score => top + plotHeight - (Math.max(0, Math.min(10, score)) / 10) * plotHeight;
                        const aiPath = svgNode('path', {
                            d: visibleAiScores.map((point, index) =>
                                `${index === 0 ? 'M' : 'L'} ${toX(point.x).toFixed(2)} ${toAiY(point.y).toFixed(2)}`
                            ).join(' '),
                            fill: 'none',
                            stroke: '#f59e0b',
                            'stroke-width': '2',
                            'stroke-linecap': 'round',
                            'stroke-linejoin': 'round',
                            'stroke-opacity': '.82',
                            'vector-effect': 'non-scaling-stroke',
                        });
                        svg.appendChild(aiPath);

                        [10, 5, 0].forEach(score => {
                            const label = svgNode('text', {
                                x: left + 4,
                                y: toAiY(score) + (score === 10 ? 10 : (score === 0 ? -4 : 3)),
                                fill: '#f59e0b',
                                'fill-opacity': '.72',
                                'font-size': '8',
                                'font-weight': '700',
                                'font-family': 'inherit',
                            });
                            label.textContent = `KI ${score}`;
                            svg.appendChild(label);
                        });
                    }

                    const signalColors = {
                        BUY: '#22c55e',
                        WATCH: '#84cc16',
                        HOLD: '#f59e0b',
                        SELL: '#ef4444',
                    };
                    historicalSignalTransitions
                        .map(transition => ({ ...transition, x: new Date(transition.x).getTime(), score: Number(transition.score) }))
                        .filter(transition => Number.isFinite(transition.x)
                            && transition.x >= timeRange.min && transition.x <= timeRange.max)
                        .forEach((transition, index) => {
                            const x = toX(transition.x);
                            const color = signalColors[transition.to] || '#94a3b8';
                            svg.appendChild(svgNode('line', {
                                x1: x, x2: x, y1: top, y2: top + plotHeight,
                                stroke: color,
                                'stroke-width': '1',
                                'stroke-dasharray': '3 5',
                                'stroke-opacity': '.52',
                                'vector-effect': 'non-scaling-stroke',
                            }));

                            const labelText = `${transition.to} · ${Number.isFinite(transition.score) ? transition.score.toFixed(1) : '—'}`;
                            const labelWidth = Math.max(48, labelText.length * 5.2 + 10);
                            const labelX = Math.max(left, Math.min(left + plotWidth - labelWidth, x - labelWidth / 2));
                            const labelY = top + 4 + ((index % 2) * 17);
                            svg.appendChild(svgNode('rect', {
                                x: labelX, y: labelY,
                                width: labelWidth, height: 14, rx: '4',
                                fill: color, 'fill-opacity': '.16',
                                stroke: color, 'stroke-opacity': '.42',
                            }));
                            const label = svgNode('text', {
                                x: labelX + labelWidth / 2,
                                y: labelY + 10,
                                fill: color,
                                'font-size': '8',
                                'font-weight': '800',
                                'text-anchor': 'middle',
                                'font-family': 'inherit',
                            });
                            label.textContent = labelText;
                            svg.appendChild(label);
                        });

                    if (watchlistEntry?.price && Number.isFinite(Number(watchlistEntry.price))) {
                        const entryY = toY(Number(watchlistEntry.price));
                        svg.appendChild(svgNode('line', {
                            x1: left, x2: left + plotWidth, y1: entryY, y2: entryY,
                            stroke: '#f59e0b', 'stroke-width': '1',
                            'stroke-dasharray': '6 6', 'stroke-opacity': '.8',
                        }));
                    }

                    if (Number.isFinite(chartFocusAt)) {
                        const focusX = toX(chartFocusAt);
                        svg.appendChild(svgNode('line', {
                            x1: focusX, x2: focusX, y1: top, y2: top + plotHeight,
                            stroke: '#f59e0b', 'stroke-width': '1',
                            'stroke-dasharray': '5 6', 'stroke-opacity': '.65',
                        }));
                    }

                    const tickCount = 7;
                    for (let index = 0; index < tickCount; index += 1) {
                        const timestamp = timeRange.min + (xSpan * index / (tickCount - 1));
                        const label = svgNode('text', {
                            x: toX(timestamp), y: height - 8,
                            fill: labelColor, 'font-size': '10', 'text-anchor': 'middle',
                            'font-family': 'inherit',
                        });
                        label.textContent = new Date(timestamp).toLocaleDateString([], { day: '2-digit', month: '2-digit' });
                        svg.appendChild(label);
                    }

                    element.replaceChildren(svg);
                    drawSmaLines();
                };

                renderMainChart();
                if (indicatorOverlay && window.ResizeObserver) {
                    new ResizeObserver(() => renderMainChart()).observe(element);
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
                    renderMainChart();
                    if (rsiChart) {
                        await rsiChart.updateOptions(rsiOptions(), false, true);
                        updateRsiValue();
                    }
                });
                window.addEventListener('aktienki:theme-changed', async () => {
                    renderMainChart();
                    if (rsiChart) await rsiChart.updateOptions(rsiOptions(), false, true);
                });
                window.addEventListener('aktienki:live-price', event => {
                    if (!liveSourceSymbol || String(event.detail?.symbol ?? '').toUpperCase() !== String(liveSourceSymbol).toUpperCase()) return;

                    const price = Number(event.detail?.price);
                    const timestamp = Number(event.detail?.timestamp) * 1000;
                    if (!Number.isFinite(price) || price <= 0 || !Number.isFinite(timestamp)) return;

                    const tradingDay = new Intl.DateTimeFormat('en-CA', {
                        timeZone: 'Europe/Berlin', year: 'numeric', month: '2-digit', day: '2-digit',
                    }).format(new Date(timestamp));
                    const lastCandle = currentCandles.at(-1);
                    const lastTradingDay = lastCandle ? new Intl.DateTimeFormat('en-CA', {
                        timeZone: 'Europe/Berlin', year: 'numeric', month: '2-digit', day: '2-digit',
                    }).format(new Date(lastCandle.x)) : null;

                    if (lastCandle && lastTradingDay === tradingDay) {
                        const [open, high, low] = lastCandle.y.map(Number);
                        lastCandle.y = [
                            Number.isFinite(open) ? open : price,
                            Number.isFinite(high) ? Math.max(high, price) : price,
                            Number.isFinite(low) ? Math.min(low, price) : price,
                            price,
                        ];
                    } else {
                        currentCandles = [...currentCandles, { x: new Date(timestamp).toISOString(), y: [price, price, price, price] }];
                    }

                    renderMainChart();
                    if (updatedElement) {
                        updatedElement.textContent = new Date(timestamp).toLocaleTimeString(document.documentElement.lang, {
                            hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Europe/Berlin',
                        });
                    }
                });

                const liveQuoteUrl = @js(route('stocks.live-quote', ['symbol' => $instrument->symbol]));
                const refreshTwelveDataQuote = async () => {
                    try {
                        const response = await fetch(liveQuoteUrl, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store',
                        });
                        if (!response.ok) return;
                        const quote = await response.json();
                        const price = Number(quote.price);
                        const timestamp = Number(quote.timestamp);
                        if (!Number.isFinite(price) || price <= 0) return;

                        const priceElement = document.querySelector('[data-stock-live-price]');
                        const timeElement = document.querySelector('[data-stock-live-time]');
                        const currency = quote.currency || priceElement?.dataset.liveCurrency || '';
                        if (priceElement) {
                            priceElement.textContent = `${price.toLocaleString(document.documentElement.lang, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}${currency ? ` ${currency}` : ''}`;
                        }
                        if (timeElement && Number.isFinite(timestamp)) {
                            timeElement.textContent = new Date(timestamp * 1000).toLocaleTimeString(
                                document.documentElement.lang,
                                { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: 'Europe/Berlin' },
                            );
                        }
                        window.dispatchEvent(new CustomEvent('aktienki:live-price', {
                            detail: { symbol: @js($instrument->symbol), price, timestamp },
                        }));
                    } catch (_) {
                        // Der zuletzt bekannte Kurs bleibt bei einem kurzzeitigen API-Fehler sichtbar.
                    }
                };
                refreshTwelveDataQuote();
                const liveQuoteTimer = window.setInterval(refreshTwelveDataQuote, 2_000);
                window.addEventListener('pagehide', () => window.clearInterval(liveQuoteTimer), { once: true });

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
                        const nextChartPatterns = Array.isArray(payload.chart_patterns) ? payload.chart_patterns : [];
                        const entryChanged = JSON.stringify(payload.watchlist_entry) !== JSON.stringify(watchlistEntry);
                        if (JSON.stringify(nextCandles) !== JSON.stringify(currentCandles) || JSON.stringify(nextChartPatterns) !== JSON.stringify(chartPatterns) || entryChanged) {
                            currentCandles = nextCandles;
                            chartPatterns = nextChartPatterns;
                            watchlistEntry = payload.watchlist_entry || null;
                            renderMainChart();
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

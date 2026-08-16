@extends('layouts.aktienki')

@section('content')
    <style>
        #stock-detail-page {
            --stock-detail-accent: #22d3ee;
            --stock-detail-accent-bright: #22d3ee;
        }

        #stock-detail-page .stock-detail-panel {
            --stock-panel-padding: 1rem;
            position: relative;
            border-color: color-mix(in srgb, var(--ak-border) 68%, #22d3ee 32%) !important;
            border-bottom-color: rgba(34, 211, 238, .58) !important;
            background:
                radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .10), transparent 30%),
                rgba(255, 255, 255, .28) !important;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .9) inset,
                0 -1px 0 rgba(34, 211, 238, .20) inset,
                0 0 0 1px rgba(34, 211, 238, .075),
                0 14px 34px rgba(15, 23, 42, .105),
                0 4px 12px rgba(14, 116, 144, .07) !important;
        }

        :root:not([data-theme="light"]) #stock-detail-page .stock-detail-panel {
            background:
                radial-gradient(circle at 94% 100%, rgba(34, 211, 238, .10), transparent 30%),
                rgba(7, 24, 38, .42) !important;
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .045) inset,
                0 -1px 0 rgba(34, 211, 238, .22) inset,
                0 0 0 1px rgba(34, 211, 238, .065),
                0 18px 42px rgba(0, 0, 0, .34),
                0 5px 16px rgba(6, 182, 212, .055) !important;
        }

        #stock-detail-page .stock-detail-panel-compact {
            --stock-panel-padding: .75rem;
        }

        #stock-chart-card:fullscreen,
        #stock-chart-card:-webkit-full-screen {
            width: 100vw !important;
            height: 100vh !important;
            max-width: none !important;
            max-height: none !important;
            margin: 0 !important;
            border-radius: 0 !important;
            padding: 1.25rem !important;
            background: var(--ak-page, #071826) !important;
        }

        #stock-chart-card:fullscreen #stock-chart-fullscreen-close,
        #stock-chart-card:-webkit-full-screen #stock-chart-fullscreen-close {
            display: inline-flex !important;
        }

        #stock-detail-page .stock-detail-panel::before {
            content: '';
            position: absolute;
            z-index: 3;
            inset: 12% auto 12% 0;
            width: 3px;
            height: auto;
            border-radius: 0 999px 999px 0;
            background: linear-gradient(180deg, transparent, rgba(34, 211, 238, .58) 18%, #22d3ee 50%, rgba(34, 211, 238, .58) 82%, transparent);
            box-shadow: 0 0 14px rgba(34, 211, 238, .34);
            pointer-events: none;
        }

        #stock-detail-page .stock-detail-card-head {
            position: relative;
            margin: calc(var(--stock-panel-padding) * -1) calc(var(--stock-panel-padding) * -1) .75rem;
            padding: var(--stock-panel-padding);
            border-bottom: 1px solid rgba(34, 211, 238, .28);
            background:
                radial-gradient(circle at 5% 0%, rgba(34, 211, 238, .22), transparent 40%),
                linear-gradient(108deg, rgba(34, 211, 238, .20), rgba(6, 182, 212, .11) 55%, transparent);
            box-shadow: 0 6px 16px rgba(6, 182, 212, .07);
        }

        #stock-detail-page .ak-prediction-donut {
            width: 46px;
            height: 46px;
            min-width: 46px;
            min-height: 46px;
            flex: 0 0 46px;
            aspect-ratio: 1 / 1;
            box-shadow: 0 0 0 1px var(--ak-border), 0 3px 8px rgba(15, 23, 42, .10);
            filter: none !important;
        }

        #stock-detail-page .ak-prediction-donut.screener-metric-donut-score {
            width: 64px;
            height: 64px;
            min-width: 64px;
            min-height: 64px;
            flex-basis: 64px;
        }

        @media (min-width: 768px) and (max-width: 1279px) {
            #stock-detail-page .stock-analysis-donuts .screener-metric-donut {
                width: 42px;
                height: 42px;
                min-width: 42px;
                min-height: 42px;
                flex: 0 0 42px;
                aspect-ratio: 1 / 1;
            }

            #stock-detail-page .stock-analysis-donuts .screener-metric-donut-score {
                width: 58px;
                height: 58px;
                min-width: 58px;
                min-height: 58px;
                flex-basis: 58px;
            }

            #stock-detail-page .stock-horizon-cards {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
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

        :root:not([data-theme="light"]) #stock-detail-page .stock-analysis-donuts .screener-metric-donut {
            background: conic-gradient(
                color-mix(in srgb, var(--donut-color) 84%, #a5f3fc 16%) var(--donut-value),
                rgba(148, 203, 213, .22) 0
            );
            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--donut-color) 38%, transparent),
                0 0 18px color-mix(in srgb, var(--donut-color) 52%, transparent),
                inset 0 0 8px color-mix(in srgb, var(--donut-color) 18%, transparent);
        }

        :root:not([data-theme="light"]) #stock-detail-page .stock-analysis-donuts .screener-metric-donut::after {
            background: #132833 !important;
        }

        :root:not([data-theme="light"]) #stock-detail-page .stock-analysis-donuts .screener-metric-donut span {
            color: color-mix(in srgb, var(--donut-color) 88%, white 12%) !important;
            text-shadow: 0 0 12px color-mix(in srgb, var(--donut-color) 72%, transparent);
        }

        :root:not([data-theme="light"]) #stock-detail-page .stock-analysis-donuts .screener-metric-donut small {
            color: #b7d5dc !important;
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
            border-style: none !important;
            border-color: transparent !important;
            background: transparent !important;
            box-shadow: none !important;
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

        #stock-detail-page .text-teal-300,
        #stock-detail-page .text-teal-400,
        #stock-detail-page .text-teal-500,
        #stock-detail-page .text-teal-600,
        #stock-detail-page .text-teal-700 { color: #22d3ee !important; }

        #stock-detail-page .border-teal-400\/20,
        #stock-detail-page .border-teal-500\/15,
        #stock-detail-page .border-teal-500\/20,
        #stock-detail-page .border-teal-500\/25 { border-color: rgba(34, 211, 238, .28) !important; }

        #stock-detail-page .bg-teal-500\/\[\.05\],
        #stock-detail-page .bg-teal-500\/\[\.06\],
        #stock-detail-page .bg-teal-500\/\[\.08\],
        #stock-detail-page .bg-teal-500\/10 { background-color: rgba(34, 211, 238, .09) !important; }

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

        #stock-detail-page .stock-collapsible-section {
            border-radius: 1rem;
        }

        #stock-detail-page .stock-collapsible-toggle {
            display: flex;
            width: 100%;
            min-height: 2.75rem;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid rgba(34, 211, 238, .26);
            border-radius: .8rem;
            padding: .65rem .9rem;
            color: #22d3ee;
            background: rgba(34, 211, 238, .065);
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .12em;
            text-align: left;
            text-transform: uppercase;
        }

        #stock-detail-page .stock-collapsible-copy {
            min-width: 0;
            flex: 1;
        }

        #stock-detail-page .stock-collapsible-description {
            display: block;
            margin-top: .2rem;
            color: var(--ak-muted);
            font-size: .58rem;
            font-weight: 700;
            letter-spacing: .025em;
            line-height: 1.25;
            text-transform: none;
        }

        #stock-detail-page .stock-collapsible-icon {
            display: grid;
            width: 2rem;
            height: 2rem;
            flex: 0 0 2rem;
            place-items: center;
            border: 1px solid rgba(34, 211, 238, .28);
            border-radius: .55rem;
            background: rgba(34, 211, 238, .08);
        }

        #stock-detail-page .stock-collapsible-icon svg {
            width: 1rem;
            height: 1rem;
        }

        #stock-detail-page .stock-collapsible-toggle:hover {
            background: rgba(34, 211, 238, .11);
        }

        #stock-detail-page .stock-collapsible-chevron {
            width: 1rem;
            height: 1rem;
            transition: transform .2s ease;
        }

        #stock-detail-page .stock-collapsible-toggle[aria-expanded="true"] .stock-collapsible-chevron {
            transform: rotate(180deg);
        }

        #stock-detail-page .stock-collapsible-content {
            margin-top: .75rem;
        }

        @media (min-width: 768px) {
            #stock-detail-page .chart-pattern-stat-row {
                display: grid;
                grid-template-columns: minmax(180px, .85fr) minmax(180px, 1fr) minmax(180px, .8fr);
                align-items: center;
            }
        }

        @media (min-width: 1280px) {
            .stock-overview-grid {
                height: calc(100% - 3rem);
            }

        }

        /* Tablets need content-driven height. A viewport-locked overview made
           the historical evaluation end below the visible card boundary. */
        @media (min-width: 768px) and (max-width: 1279px) {
            #stock-detail-page {
                height: auto !important;
                min-height: calc(100dvh - 89px);
            }

            #stock-detail-page > div.min-h-0.flex-1 {
                overflow: visible !important;
            }

            #stock-detail-page .stock-overview-grid {
                height: auto !important;
                align-items: start;
            }

            #stock-detail-page .stock-overview-analysis {
                height: auto !important;
                min-height: max-content;
                overflow: visible !important;
            }
        }

        @media (min-width: 900px) {
            .stock-overview-grid-with-evaluation {
                grid-template-columns: minmax(320px, .78fr) minmax(0, 1.52fr);
            }

            .stock-overview-grid-with-evaluation > .stock-overview-chart {
                order: 2;
            }

            .stock-overview-grid-with-evaluation > .stock-overview-analysis {
                order: 1;
            }

        }
        /* Light mode uses petrol for the complete stock-detail chrome. */
        :root[data-theme="light"] #stock-detail-page {
            --stock-detail-accent: #00656f;
            --stock-detail-accent-bright: #007c87;
        }

        :root[data-theme="light"] #stock-detail-page .stock-detail-panel {
            border-color: rgba(0, 101, 111, .34) !important;
            border-bottom-color: rgba(0, 101, 111, .55) !important;
            background:
                radial-gradient(circle at 94% 100%, rgba(0, 101, 111, .10), transparent 30%),
                rgba(255, 255, 255, .34) !important;
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .92) inset,
                0 -1px 0 rgba(0, 101, 111, .20) inset,
                0 0 0 1px rgba(0, 101, 111, .08),
                0 14px 34px rgba(15, 23, 42, .10),
                0 4px 12px rgba(0, 79, 87, .08) !important;
        }

        :root[data-theme="light"] #stock-detail-page .stock-detail-panel::before {
            background: linear-gradient(180deg, transparent, rgba(0, 101, 111, .58) 18%, #00656f 50%, rgba(0, 101, 111, .58) 82%, transparent) !important;
            box-shadow: 0 0 12px rgba(0, 101, 111, .28) !important;
        }

        :root[data-theme="light"] #stock-detail-page .stock-detail-card-head {
            border-bottom-color: rgba(0, 101, 111, .26) !important;
            background:
                radial-gradient(circle at 5% 0%, rgba(0, 101, 111, .18), transparent 40%),
                linear-gradient(108deg, rgba(0, 101, 111, .14), rgba(0, 124, 135, .07) 55%, transparent) !important;
            box-shadow: 0 6px 16px rgba(0, 79, 87, .07) !important;
        }

        :root[data-theme="light"] #stock-detail-page :is(
            [class*="text-cyan-"],
            .text-teal-300,
            .text-teal-400,
            .text-teal-500,
            .text-teal-600,
            .text-teal-700,
            .text-violet-200,
            .text-violet-300
        ) {
            color: #00656f !important;
        }

        :root[data-theme="light"] #stock-detail-page [class*="border-cyan-"] {
            border-color: rgba(0, 101, 111, .30) !important;
        }

        :root[data-theme="light"] #stock-detail-page [class*="bg-cyan-"] {
            background-color: rgba(0, 101, 111, .11) !important;
        }

        :root[data-theme="light"] #stock-detail-page .stock-collapsible-toggle {
            border-color: rgba(0, 101, 111, .28);
            color: #00656f;
            background: rgba(0, 101, 111, .08);
        }
    </style>
    @php
        $scorePercent = \App\Support\AiScore::toPercent($prediction?->prediction_score);
        $confidencePercent = is_numeric($prediction?->confidence)
            ? max(0, min(100, (float) $prediction->confidence <= 1 ? (float) $prediction->confidence * 100 : (float) $prediction->confidence))
            : null;
        $riskPercent = \App\Support\RiskScore::toPercent(
            $prediction?->risk_score,
            $prediction?->drawdown_risk_factor,
            $modelQuality?->maximum_drawdown,
        );
        $signal = strtoupper((string) ($prediction?->personalized_signal ?? 'HOLD'));
        $signalClass = $signal === 'BUY'
            ? 'border-teal-300/70 bg-teal-400/25 text-teal-100 shadow-[0_0_18px_rgba(34, 211, 238,.22)]'
            : ($signal === 'SELL'
                ? 'border-rose-400/35 bg-rose-400/10 text-rose-300'
                : ($signal === 'WATCH'
                    ? 'border-lime-300/30 bg-lime-300/10 text-lime-300'
                    : ($signal === 'WAIT'
                        ? 'border-emerald-300/70 bg-emerald-400/25 text-emerald-100 shadow-[0_0_18px_rgba(16,185,129,.22)]'
                        : 'border-amber-300/30 bg-amber-300/10 text-amber-300')));
        $signalLabel = $signal;
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
            'WAIT' => 'border-emerald-300/60 bg-emerald-400/20 text-emerald-200',
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

                <x-paper-depot-buy :portfolios="$paperPortfolios" :instrument-id="$instrument->id" :instrument-name="$instrument->name" :currency="$instrument->currency" :price="$prediction?->current_price" :score="\App\Support\AiScore::toPercent($prediction?->prediction_score)" />

                <a href="{{ route('stocks.report', ['symbol' => $instrument->symbol, 'prediction' => $prediction?->id, 'v' => now()->timestamp]) }}" title="{{ __('Ausführlichen PDF-Bericht für :stock herunterladen', ['stock' => $instrument->name]) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-cyan-400/25 bg-cyan-400/10 px-3 text-[10px] font-black uppercase tracking-wide text-cyan-400 transition hover:border-cyan-400/50 hover:bg-cyan-400/15">
                    <x-heroicon-o-document-arrow-down class="h-4 w-4" />
                    <span>{{ __('Bericht') }}</span>
                </a>

                <a href="{{ $returnTo ?: ($requestedPredictionId > 0 ? route('predictions.index') : route('stocks.index')) }}" class="inline-flex h-10 w-44 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)]">
                    <x-heroicon-o-arrow-left class="h-4 w-4 shrink-0" /><span class="truncate">{{ $returnLabel ?: ($requestedPredictionId > 0 ? __('Zurück zu Prognosen') : __('Zur Aktienliste')) }}</span>
                </a>
            </div>
        </header>

        <div
            class="min-h-0 flex-1 space-y-5 overflow-y-auto pr-1 pb-3"
        >
        <section
            class="stock-overview-grid {{ $requestedPredictionId > 0 && $prediction ? 'stock-overview-grid-with-evaluation' : 'lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,.85fr)]' }} grid min-h-0 gap-4"
        >
            <article id="stock-chart-card" class="stock-detail-panel stock-overview-chart flex min-h-[350px] min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)] lg:h-full lg:min-h-0">
                <button
                    id="stock-chart-fullscreen-close"
                    type="button"
                    class="absolute right-20 top-5 z-50 hidden items-center gap-2 rounded-xl border border-cyan-300/40 bg-[#071826]/95 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-cyan-300 shadow-xl backdrop-blur transition hover:border-cyan-300/70 hover:bg-cyan-400/15"
                    aria-label="{{ __('Vollbild beenden') }}"
                    title="{{ __('Vollbild beenden') }}"
                >
                    <x-heroicon-o-arrows-pointing-in class="h-4 w-4" />
                    {{ __('Vollbild beenden') }}
                </button>
                <div class="stock-detail-card-head flex shrink-0 items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Kurschart') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Kursentwicklung') }}</h2>
                        <p class="mt-1 text-xs text-[var(--ak-muted)]">
                            {{ __('Tageskerzen · Zeitraum und Indikatoren frei wählbar') }}
                        </p>
                        <div id="stock-chart-period-buttons" class="mt-2 flex flex-wrap items-center gap-1 sm:flex-nowrap">
                            @foreach ([22 => '1M', 66 => '3M', 132 => '6M', 252 => '1J', 0 => 'Max'] as $periodDays => $periodLabel)
                                <button type="button" data-chart-period="{{ $periodDays }}" aria-pressed="{{ $periodDays === 132 ? 'true' : 'false' }}" class="min-w-0 rounded-lg border px-2 py-1 text-[9px] font-black uppercase tracking-wide transition {{ $periodDays === 132 ? 'border-cyan-400/35 bg-cyan-400/15 text-cyan-400' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-cyan-400/25 hover:text-[var(--ak-text)]' }}">{{ $periodLabel }}</button>
                            @endforeach
                        </div>
                        <details class="group mt-1.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] {{ $canUseChartIndicators ? '' : 'opacity-55 grayscale' }}">
                            <summary aria-disabled="{{ $canUseChartIndicators ? 'false' : 'true' }}" class="flex list-none items-center justify-between gap-3 px-3 py-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] transition [&::-webkit-details-marker]:hidden {{ $canUseChartIndicators ? 'cursor-pointer hover:text-cyan-400' : 'pointer-events-none cursor-not-allowed' }}">
                                <span class="inline-flex items-center gap-2">
                                    <x-heroicon-o-adjustments-horizontal class="h-4 w-4 text-cyan-400" />
                                    {{ __('Indikatoren') }}
                                    @unless ($canUseChartIndicators)
                                        <span class="ak-plan-badge ak-plan-badge--plus">PLUS</span>
                                    @endunless
                                </span>
                                <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform group-open:rotate-180" />
                            </summary>
                            <div id="stock-indicator-buttons" class="flex flex-wrap items-center gap-1.5 border-t border-[var(--ak-border)] px-2.5 py-2.5">
                            <span id="stock-chart-pattern-badge" class="{{ empty($chartPatterns) ? 'hidden ' : '' }}mb-1 inline-flex w-full items-start gap-2 rounded-lg border border-cyan-400/25 bg-cyan-400/[.07] px-2.5 py-2 text-[9px] font-black uppercase leading-relaxed tracking-wide text-cyan-300">
                                <x-heroicon-o-chart-bar class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span data-pattern-label>{{ collect($chartPatterns)->pluck('name')->join(' · ') }}</span>
                            </span>
                            @foreach ([
                                'rsi' => 'RSI 14', 'sma20' => 'SMA 20', 'sma50' => 'SMA 50', 'sma200' => 'SMA 200',
                                'ema20' => 'EMA 20', 'ema50' => 'EMA 50', 'bollinger' => 'Bollinger', 'sar' => 'SAR',
                                'macd' => 'MACD', 'adx' => 'ADX 14', 'atr' => 'ATR 14', 'stochastic' => 'Stochastik',
                                'cci' => 'CCI 20', 'mfi' => 'MFI 14', 'vwap' => 'VWAP', 'obv' => 'OBV',
                                'williams' => 'Williams %R', 'roc' => 'ROC 12', 'volatility' => 'Volatilität', 'momentum' => 'Momentum 10',
                                'support' => 'Unterstützung', 'resistance' => 'Widerstand', 'patterns' => 'Chartmuster',
                            ] as $indicator => $indicatorLabel)
                                <button
                                    type="button"
                                    data-indicator="{{ $indicator }}"
                                    @disabled(! $canUseChartIndicators)
                                    aria-pressed="false"
                                    class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] transition hover:border-violet-400/25 hover:text-[var(--ak-text)]"
                                >
                                    {{ $indicatorLabel }}
                                </button>
                            @endforeach
                            <button
                                type="button"
                                data-chart-reset
                                @disabled(! $canUseChartIndicators)
                                title="{{ __('Chart zurücksetzen') }}"
                                aria-label="{{ __('Chart zurücksetzen') }}"
                                class="inline-flex items-center gap-1 rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] transition hover:border-violet-400/25 hover:text-[var(--ak-text)]"
                            >
                                <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />Reset
                            </button>
                            </div>
                        </details>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <div data-stock-live-card class="rounded-xl border border-cyan-500/25 bg-cyan-500/[.09] px-3 py-1.5 text-right transition-colors duration-300">
                            <p
                                data-live-symbol="{{ $instrument->symbol }}"
                                data-stock-live-price
                                data-live-currency="{{ $currency }}"
                                data-live-decimals="2"
                                class="whitespace-nowrap text-sm font-black tabular-nums text-cyan-400 transition-colors duration-300"
                            >{{ number_format((float) ($chartLast ?? $prediction?->current_price ?? 0), 2, ',', '.') }} {{ $currency }}</p>
                            <p data-stock-live-meta class="mt-0.5 flex items-center justify-end gap-1 text-[7px] font-black uppercase tracking-wide text-cyan-400/75 transition-colors duration-300">
                                <span data-stock-live-dot class="h-1.5 w-1.5 {{ $canViewRealtime && $marketSession['open'] ? 'animate-pulse bg-cyan-400 shadow-[0_0_5px_rgba(34,211,238,.55)]' : 'bg-slate-500' }} rounded-full"></span>
                                <span data-stock-live-status>{{ $canViewRealtime ? ($marketSession['open'] ? __('TwelveData Realtime') : __('Börse geschlossen')) : __('Realtime ab Pro') }}</span>
                                @if ($canViewRealtime)
                                    · <span data-live-time-symbol="{{ $instrument->symbol }}" data-stock-live-time>{{ !empty($prediction->current_quote_time) ? \Illuminate\Support\Carbon::parse($prediction->current_quote_time)->timezone('Europe/Berlin')->format('H:i:s') : __('wartet') }}</span>
                                @endif
                            </p>
                        </div>
                        <span data-stock-live-change class="inline-flex min-w-24 flex-col items-center justify-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 px-2.5 py-1.5 text-cyan-400 transition-colors duration-300">
                            <strong data-stock-live-change-value class="text-[11px] font-black tabular-nums">—</strong>
                            <small class="mt-0.5 text-[7px] font-black uppercase tracking-wide opacity-75">{{ __('Tagesperformance') }}</small>
                        </span>
                        <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-2 text-[10px] font-bold text-[var(--ak-muted)]">
                            {{ $chartCandles->count() }} {{ __('Tage') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-amber-400/20 bg-amber-400/[.08] px-2.5 py-2 text-[9px] font-black uppercase tracking-wide text-amber-500">
                            <i class="h-0.5 w-5 rounded-full bg-amber-500"></i>{{ __('Historischer KI-Score') }}
                        </span>
                        <button
                            type="button"
                            onclick="document.getElementById('stock-horizon-stability-modal')?.showModal()"
                            title="{{ __('Stabilitätswerte der Prognosehorizonte') }}"
                            aria-label="{{ __('Stabilitätswerte anzeigen') }}"
                            class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-cyan-400/40 bg-cyan-400/15 px-2.5 text-[9px] font-black uppercase tracking-wide text-cyan-300 shadow-[0_0_10px_rgba(34,211,238,.12)] transition hover:border-cyan-300/70 hover:bg-cyan-400/20"
                        >
                            <x-heroicon-o-adjustments-horizontal class="h-4 w-4" />
                            <span>{{ __('Filterwerte') }}</span>
                        </button>
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
                        <button
                            id="stock-chart-fullscreen"
                            type="button"
                            title="{{ __('Chart maximieren') }}"
                            aria-label="{{ __('Chart maximieren') }}"
                            aria-pressed="false"
                            @disabled(! $canUseChartZoom)
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border px-2.5 transition {{ $canUseChartZoom ? 'border-cyan-400/25 bg-cyan-400/10 text-cyan-400 hover:border-cyan-400/50 hover:bg-cyan-400/15' : 'cursor-not-allowed border-slate-500/20 bg-slate-500/10 text-slate-500 grayscale' }}"
                        >
                            <x-heroicon-o-arrows-pointing-out data-fullscreen-open class="h-5 w-5" />
                            <x-heroicon-o-arrows-pointing-in data-fullscreen-close class="hidden h-5 w-5" />
                            @unless ($canUseChartZoom)<span class="ak-plan-badge ak-plan-badge--pro">PRO</span>@endunless
                        </button>
                    </div>
                </div>
                @if ($chartCandles->isNotEmpty())
                    <div class="relative min-h-[160px] min-w-0 flex-1 overflow-hidden lg:min-h-0">
                        <div id="stock-detail-chart" class="absolute inset-0" aria-label="{{ __('Kurschart') }} {{ $instrument->symbol }}"></div>
                        <svg id="stock-indicator-overlay" class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible" aria-hidden="true"></svg>
                        <div id="stock-chart-zoom-selection" class="pointer-events-none absolute bottom-8 top-4 z-20 hidden rounded border border-cyan-300 bg-cyan-300/10 shadow-[0_0_12px_rgba(34,211,238,.22)]" aria-hidden="true"></div>
                    </div>
                    <div id="stock-rsi-panel" class="mt-2 hidden shrink-0 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-transparent px-2 pb-1 pt-1.5">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">RSI 14</span>
                            <span id="stock-rsi-value" class="text-[10px] font-black text-violet-300">—</span>
                        </div>
                        <div id="stock-detail-rsi" class="h-16 min-w-0" aria-label="{{ __('RSI 14') }} {{ $instrument->symbol }}"></div>
                    </div>
                    <div id="stock-secondary-indicator-panels" class="mt-2 grid max-h-44 shrink-0 grid-cols-1 gap-2 overflow-y-auto"></div>
                @else
                    <div class="grid min-h-[200px] flex-1 place-items-center rounded-2xl border border-dashed {{ empty($historicalChartAllowed) ? 'border-amber-400/25 bg-amber-400/[.04]' : 'border-[var(--ak-border)]' }} px-6 text-center text-sm text-[var(--ak-muted)]">
                        <div class="max-w-xl">
                            @if (empty($historicalChartAllowed))
                                <x-heroicon-o-shield-exclamation class="mx-auto mb-3 h-8 w-8 text-amber-400" />
                                <strong class="block text-sm font-black text-amber-300">{{ __('Historischer Chart nicht verfügbar') }}</strong>
                                <span class="mt-2 block text-xs leading-5">{{ $historicalChartRestrictionReason }}</span>
                            @else
                                {{ __('Keine OHLC-Tageskurse verfügbar.') }}
                            @endif
                        </div>
                    </div>
                @endif
            </article>
            <dialog id="stock-horizon-stability-modal" class="m-auto w-[min(94vw,920px)] rounded-2xl border border-cyan-400/30 bg-[var(--ak-card)] p-0 text-[var(--ak-text)] shadow-2xl backdrop:bg-slate-950/75">
                <div class="border-b border-[var(--ak-border)] px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Prognosestabilität') }}</p>
                            <h2 class="mt-1 text-lg font-black">{{ __('Werte je Horizont') }}</h2>
                            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Noise- und Stabilitätswerte der Prognosen für 5, 10, 15 und 20 Handelstage.') }}</p>
                        </div>
                        <button type="button" onclick="this.closest('dialog').close()" aria-label="{{ __('Schließen') }}" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)] hover:text-cyan-400">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="w-full min-w-[760px] text-left text-xs">
                        <thead class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                            <tr class="border-b border-[var(--ak-border)]">
                                <th class="px-2 py-2">{{ __('Horizont') }}</th>
                                <th class="px-2 py-2">{{ __('Kursziel') }}</th>
                                <th class="px-2 py-2">{{ __('Rendite') }}</th>
                                <th class="px-2 py-2">{{ __('Stabilität') }}</th>
                                <th class="px-2 py-2">{{ __('Richtungskonsistenz') }}</th>
                                <th class="px-2 py-2">{{ __('Streuung') }}</th>
                                <th class="px-2 py-2">{{ __('Noise') }}</th>
                                <th class="px-2 py-2">{{ __('Stabilitätsfilter') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([5, 10, 15, 20] as $days)
                                @php
                                    $stability = $horizonStability[$days] ?? [];
                                @endphp
                                <tr class="border-b border-[var(--ak-border)]/70 last:border-0">
                                    <td class="px-2 py-3 font-black text-cyan-400">{{ $days }}T</td>
                                    <td class="px-2 py-3 font-bold">{{ is_numeric($stability['price'] ?? null) ? number_format((float) $stability['price'], 2, ',', '.').' '.$currency : '—' }}</td>
                                    <td class="px-2 py-3 font-black {{ !is_numeric($stability['return'] ?? null) ? 'text-[var(--ak-muted)]' : ((float) $stability['return'] >= 0 ? 'text-emerald-400' : 'text-rose-400') }}">{{ is_numeric($stability['return'] ?? null) ? (((float) $stability['return'] > 0 ? '+' : '').number_format((float) $stability['return'], 2, ',', '.').' %') : '—' }}</td>
                                    <td class="px-2 py-3">{{ is_numeric($stability['stability_score'] ?? null) ? number_format((float) $stability['stability_score'] * 100, 1, ',', '.').' %' : __('Keine Daten') }}</td>
                                    <td class="px-2 py-3">{{ is_numeric($stability['direction_consistency'] ?? null) ? number_format((float) $stability['direction_consistency'] * 100, 1, ',', '.').' %' : __('Keine Daten') }}</td>
                                    <td class="px-2 py-3">{{ is_numeric($stability['dispersion'] ?? null) ? number_format((float) $stability['dispersion'] * 100, 2, ',', '.').' %' : __('Keine Daten') }}</td>
                                    @foreach (['noise_passed', 'stability_passed'] as $gate)
                                        <td class="px-2 py-3">
                                            @if (($stability[$gate] ?? null) === null)
                                                <span class="text-[var(--ak-muted)]">{{ __('Keine Daten') }}</span>
                                            @elseif ($stability[$gate])
                                                <span class="font-black text-emerald-400">{{ __('Bestanden') }}</span>
                                            @else
                                                <span class="font-black text-rose-400">{{ __('Nicht bestanden') }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-4 text-[10px] leading-5 text-[var(--ak-muted)]">{{ __('Hinweis: Noise-Filter und Horizontfusion bewerten die gemeinsame Form der Prognosekurve. Identische Fusionswerte in mehreren Zeilen sind daher möglich.') }}</p>
                </div>
            </dialog>

            <article class="stock-detail-panel stock-detail-panel-compact stock-overview-analysis min-h-0 overflow-y-auto rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)] lg:h-full">
                <div class="stock-detail-card-head flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.16em] text-violet-300">{{ __('Aktuelle KI-Analyse') }}</p>
                        <h2 class="mt-1 font-black text-[var(--ak-text)]">{{ __('Persönliche Einordnung') }}</h2>
                    </div>
                    @if ($signal === 'WAIT' && $canViewRealtime)
                        <button type="button" onclick="document.getElementById('entry-signal-alert-modal')?.showModal()" class="inline-flex h-8 min-w-24 items-center justify-center gap-1.5 rounded-lg border border-emerald-300/70 bg-emerald-400/25 px-3 text-xs font-black text-emerald-100 shadow-[0_0_18px_rgba(16,185,129,.22)] transition hover:bg-emerald-400/35">
                            <x-heroicon-o-clock class="h-4 w-4" />{{ $signalLabel }}
                        </button>
                    @else
                        <span class="inline-flex h-8 min-w-20 items-center justify-center rounded-lg border px-3 text-xs font-black {{ $signalClass }}">{{ $signalLabel }}</span>
                    @endif
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
                        $analysisQualityDonutColor = static function (?float $percent): string {
                            if ($percent === null) return '#64748b';
                            $percent = max(0, min(100, $percent));
                            $hue = $percent <= 50
                                ? ($percent / 50) * 48
                                : 48 + (($percent - 50) / 50) * 94;
                            return sprintf('hsl(%.1f 92%% 58%%)', $hue);
                        };
                        $analysisScoreColor = $analysisQualityDonutColor($scorePercent);
                        $analysisModelQualityColor = $analysisQualityDonutColor($analysisModelQualityPercent);
                        $analysisRiskColor = $analysisQualityDonutColor($riskPercent !== null ? 100 - $riskPercent : null);
                        $analysisHitRatePercent = is_numeric($detailWalkForwardStats?->hit_rate)
                            ? max(0, min(100, (float) $detailWalkForwardStats->hit_rate)) : null;
                        $analysisProfitPerTrade = is_numeric($detailWalkForwardStats?->average_profit_per_trade_percent)
                            ? (float) $detailWalkForwardStats->average_profit_per_trade_percent : null;
                        $analysisProfitPerTradeScale = $analysisProfitPerTrade !== null
                            ? max(0, min(100, 50 + ($analysisProfitPerTrade * 25))) : null;
                        $analysisStabilityValue = $prediction->horizon_fusion_stability_score
                            ?? $modelQuality?->model_stability;
                        $analysisStabilityPercent = is_numeric($analysisStabilityValue)
                            ? max(0, min(100, (float) $analysisStabilityValue * ((float) $analysisStabilityValue <= 1 ? 100 : 1)))
                            : null;
                        $analysisConfidenceColor = $analysisQualityDonutColor($confidencePercent);
                        $analysisHitRateColor = $analysisQualityDonutColor($analysisHitRatePercent);
                        $analysisProfitPerTradeColor = $analysisQualityDonutColor($analysisProfitPerTradeScale);
                        $analysisStabilityColor = $analysisQualityDonutColor($analysisStabilityPercent);
                    @endphp
                    <div class="mt-3 space-y-3">
                        <div class="rounded-xl border border-[var(--ak-border)] bg-transparent p-3">
                            <p class="mb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('KI-Bewertung') }}</p>
                            <div class="stock-analysis-donuts flex min-h-[76px] w-full flex-nowrap items-center justify-between gap-1.5 overflow-visible">
                                <div class="screener-metric-donut screener-metric-donut-score" style="--donut-value: {{ number_format($scorePercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $analysisScoreColor }}" role="meter" aria-label="{{ __('KI-Score') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($scorePercent ?? 0) }}">
                                    <span>{{ $scorePercent !== null ? number_format($scorePercent, 0, ',', '.') : '—' }}</span><small>{{ __('KI-Score') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($confidencePercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $analysisConfidenceColor }}" role="meter" aria-label="{{ __('Konfidenz') }}" aria-valuemin="0" aria-valuemax="100" @if($confidencePercent !== null) aria-valuenow="{{ round($confidencePercent) }}" @endif>
                                    <span>{{ $confidencePercent !== null ? number_format($confidencePercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Konf.') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($analysisHitRatePercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $analysisHitRateColor }}" role="meter" aria-label="{{ __('Hit-Rate') }}" aria-valuemin="0" aria-valuemax="100" @if($analysisHitRatePercent !== null) aria-valuenow="{{ round($analysisHitRatePercent) }}" @endif>
                                    <span>{{ $analysisHitRatePercent !== null ? number_format($analysisHitRatePercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Hit-Rate') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($analysisProfitPerTradeScale ?? 0, 2, '.', '') }}%; --donut-color: {{ $analysisProfitPerTradeColor }}" role="meter" aria-label="{{ __('Durchschnittlicher Netto-Profit je Trade') }}" @if($analysisProfitPerTrade !== null) aria-valuenow="{{ $analysisProfitPerTrade }}" @endif>
                                    <span>{{ $analysisProfitPerTrade !== null ? (($analysisProfitPerTrade > 0 ? '+' : '').number_format($analysisProfitPerTrade, 2, ',', '.').'%') : '—' }}</span><small>{{ __('Ø/Trade') }}</small>
                                </div>
                                <div class="screener-metric-donut" style="--donut-value: {{ number_format($analysisStabilityPercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $analysisStabilityColor }}" role="meter" aria-label="{{ __('Stabilität') }}" aria-valuemin="0" aria-valuemax="100" @if($analysisStabilityPercent !== null) aria-valuenow="{{ round($analysisStabilityPercent) }}" @endif>
                                    <span>{{ $analysisStabilityPercent !== null ? number_format($analysisStabilityPercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Stabilität') }}</small>
                                </div>
                                <div class="screener-metric-donut screener-risk-donut" style="--donut-value: {{ number_format($riskPercent ?? 0, 2, '.', '') }}%; --donut-color: {{ $analysisRiskColor }}" role="meter" aria-label="{{ __('Risiko') }}" aria-valuemin="0" aria-valuemax="100" @if($riskPercent !== null) aria-valuenow="{{ round($riskPercent) }}" @endif>
                                    <span>{{ $riskPercent !== null ? number_format($riskPercent, 0, ',', '.').'%' : '—' }}</span><small>{{ __('Risiko') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-[var(--ak-border)] bg-transparent p-2">
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
                        <div x-data="{ reminderOpen: false, reminderDays: 5, reminderTarget: null, reminderReturn: null, reminderIntent: 'interested' }" class="stock-horizon-cards grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ([5, 10, 15, 20] as $horizonDays)
                                @php
                                    $horizonTarget = $horizonTargets[$horizonDays] ?? ['price' => null, 'return' => null];
                                    $horizonReturn = $horizonTarget['return'];
                                @endphp
                                <button type="button" @if($canViewRealtime) @click="reminderDays={{ $horizonDays }}; reminderTarget=@js($horizonTarget['price']); reminderReturn=@js($horizonReturn); reminderIntent='interested'; reminderOpen=true" @endif title="{{ $canViewRealtime ? __('Kauferinnerung einrichten') : __('Ab Pro verfügbar') }}" class="relative min-w-0 rounded-xl border border-[var(--ak-border)] bg-transparent p-2 text-left transition {{ $canViewRealtime ? 'hover:border-cyan-400/45 hover:bg-cyan-400/[.05]' : 'cursor-default opacity-75' }}">
                                    @unless($canViewRealtime)<span class="ak-plan-badge ak-plan-badge--pro absolute right-1.5 top-1.5">PRO</span>@endunless
                                    <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $horizonDays }} {{ __('Tage') }}</p>
                                    <p class="mt-1 truncate text-sm font-black text-cyan-400">{{ is_numeric($horizonTarget['price']) ? number_format((float) $horizonTarget['price'], 2, ',', '.').' '.$currency : '—' }}</p>
                                    <span class="mt-0.5 block truncate text-[9px] font-black {{ !is_numeric($horizonReturn) ? 'text-[var(--ak-muted)]' : ($horizonReturn >= 0 ? 'text-emerald-400' : 'text-rose-400') }}">
                                        {{ is_numeric($horizonReturn) ? (($horizonReturn > 0 ? '+' : '').number_format((float) $horizonReturn, 2, ',', '.').' %') : __('Keine Prognose') }}
                                    </span>
                                </button>
                            @endforeach
                            <template x-teleport="body">
                                <div x-cloak x-show="reminderOpen" class="fixed inset-0 z-[160] grid place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="reminderOpen=false" @keydown.escape.window="reminderOpen=false">
                                    <form method="POST" action="{{ route('stocks.purchase-reminder.store', $instrument->id) }}" class="w-full max-w-lg rounded-2xl border border-cyan-400/30 bg-[#0d1b2d] p-5 shadow-2xl">
                                        @csrf
                                        <input type="hidden" name="prediction_id" value="{{ $prediction->id }}">
                                        <input type="hidden" name="horizon_days" :value="reminderDays">
                                        <input type="hidden" name="intent" :value="reminderIntent">
                                        <input type="hidden" name="purchase_price" value="{{ $prediction->current_price }}">
                                        <div class="flex items-start justify-between">
                                            <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-400">PRO · {{ __('Prognose-Erinnerung') }}</p><h2 class="mt-1 text-xl font-black text-white"><span x-text="reminderDays"></span> {{ __('Tage') }}</h2></div>
                                            <button type="button" @click="reminderOpen=false" class="text-slate-400"><x-heroicon-o-x-mark class="h-5 w-5"/></button>
                                        </div>
                                        <div class="mt-4 grid grid-cols-2 gap-2">
                                            <div class="rounded-xl border border-cyan-400/20 bg-cyan-400/[.05] p-3"><small class="text-slate-400">{{ __('Kursziel') }}</small><strong class="mt-1 block text-cyan-300" x-text="reminderTarget == null ? '—' : new Intl.NumberFormat('de-DE',{minimumFractionDigits:2,maximumFractionDigits:2}).format(reminderTarget)+' {{ $currency }}'"></strong></div>
                                            <div class="rounded-xl border border-amber-300/20 bg-amber-300/[.05] p-3"><small class="text-slate-400">{{ __('E-Mail am') }}</small><strong class="mt-1 block text-amber-300" x-text="new Intl.DateTimeFormat('de-DE').format(new Date(Date.now()+reminderDays*86400000))"></strong></div>
                                        </div>
                                        @if($signal === 'WAIT')<p class="mt-3 rounded-xl border border-emerald-400/25 bg-emerald-400/[.07] p-3 text-xs font-bold text-emerald-300">{{ __('WAIT: Eine spätere Kaufprüfung ist hier besonders sinnvoll. Die E-Mail zeigt auch, ob das Signal inzwischen auf BUY gewechselt ist.') }}</p>@endif
                                        <fieldset class="mt-4">
                                            <legend class="mb-2 text-xs font-black uppercase tracking-wide text-slate-300">{{ __('Was möchtest du tun?') }}</legend>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <button type="button" @click="reminderIntent='interested'" :aria-pressed="reminderIntent === 'interested'" :class="reminderIntent === 'interested' ? 'border-emerald-400 bg-emerald-400/15 ring-2 ring-emerald-400/25' : 'border-white/10 bg-white/[.03]'" class="relative min-h-24 rounded-xl border p-3 text-left text-sm font-black text-emerald-300 transition">
                                                    <span class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full border" :class="reminderIntent === 'interested' ? 'border-emerald-400 bg-emerald-400 text-slate-950' : 'border-slate-500'" x-text="reminderIntent === 'interested' ? '✓' : ''"></span>
                                                    <span class="block pr-7">{{ __('Ich möchte kaufen') }}</span><small class="mt-1 block font-medium text-slate-300">{{ __('Am gewählten Tag per E-Mail erneut prüfen') }}</small>
                                                </button>
                                                <button type="button" @click="reminderIntent='purchased'" :aria-pressed="reminderIntent === 'purchased'" :class="reminderIntent === 'purchased' ? 'border-cyan-400 bg-cyan-400/15 ring-2 ring-cyan-400/25' : 'border-white/10 bg-white/[.03]'" class="relative min-h-24 rounded-xl border p-3 text-left text-sm font-black text-cyan-200 transition">
                                                    <span class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full border" :class="reminderIntent === 'purchased' ? 'border-cyan-400 bg-cyan-400 text-slate-950' : 'border-slate-500'" x-text="reminderIntent === 'purchased' ? '✓' : ''"></span>
                                                    <span class="block pr-7">{{ __('Ich habe gekauft') }}</span><small class="mt-1 block font-medium text-slate-300">{{ __('Verkaufserinnerung erhalten') }}</small>
                                                </button>
                                            </div>
                                        </fieldset>
                                        <div x-show="reminderIntent === 'purchased'" x-cloak class="mt-3 rounded-xl border border-cyan-300/20 bg-cyan-400/[.05] p-3">
                                            <p class="text-[10px] font-black uppercase tracking-wide text-cyan-200">{{ __('Dynamische Exit-Überwachung') }}</p>
                                            <div class="mt-2 grid gap-2">
                                                <label class="flex items-start gap-2 text-[10px] text-slate-300"><input type="checkbox" name="fixed_20d_exit_enabled" value="1" class="mt-0.5 h-4 w-4 rounded bg-slate-900 text-cyan-400"><span><b class="block text-white">{{ __('Fixer Exit nach 20 Tagen') }}</b>{{ __('Spätester Verkauf nach 20 Handelstagen.') }}</span></label>
                                                <label class="flex items-start gap-2 text-[10px] text-slate-300"><input type="checkbox" name="dynamic_horizon_exit_enabled" value="1" checked class="mt-0.5 h-4 w-4 rounded bg-slate-900 text-cyan-400"><span><b class="block text-white">{{ __('Prognosehorizont') }}</b>{{ __('Bestes Ziel aus 5/10/15/20 Tagen überwachen.') }}</span></label>
                                                <label class="flex items-start gap-2 text-[10px] text-slate-300"><input type="checkbox" name="support_stop_enabled" value="1" class="mt-0.5 h-4 w-4 rounded bg-slate-900 text-cyan-400"><span><b class="block text-white">{{ __('Unterstützungs-Stop') }}</b>{{ __('Stop 1 % unter bestätigter Unterstützung.') }}</span></label>
                                                <label class="flex items-start gap-2 text-[10px] text-slate-300"><input type="checkbox" name="resistance_trailing_stop_enabled" value="1" class="mt-0.5 h-4 w-4 rounded bg-slate-900 text-cyan-400"><span><b class="block text-white">{{ __('Widerstands-Trailing-Stop') }}</b>{{ __('Nach Ausbruch Stop 1 % unter den ehemaligen Widerstand nachziehen.') }}</span></label>
                                            </div>
                                        </div>
                                        <button type="submit" class="mt-4 h-11 w-full rounded-xl bg-cyan-400 text-sm font-black text-slate-950 transition hover:bg-cyan-300">{{ __('Erinnerung speichern') }}</button>
                                        <button type="button" @click="reminderOpen=false" class="mt-2 h-9 w-full rounded-lg border border-white/10 text-xs font-black text-slate-400">{{ __('Abbrechen') }}</button>
                                    </form>
                                </div>
                            </template>
                        </div>
                        @if ($requestedPredictionId > 0)
                            <div class="rounded-xl border border-[var(--ak-border)] bg-transparent p-2.5">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-300/10 text-amber-300"><x-heroicon-o-clock class="h-4 w-4" /></span>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Historische Prognoseauswertung') }}</p>
                                        <p class="text-[8px] text-[var(--ak-muted)]">{{ __('Prognose vom :date', ['date' => \Illuminate\Support\Carbon::parse($prediction->prediction_time)->format('d.m.Y H:i')]) }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-lg border border-[var(--ak-border)] bg-transparent px-2.5 py-2">
                                        <span class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Gegebenes Signal') }}</span>
                                        <div class="mt-1 flex items-center justify-between gap-2"><span class="rounded-md border px-2 py-0.5 text-[10px] font-black {{ $historicalSignalClass }}">{{ $historicalSignal }}</span><span class="text-[8px] text-[var(--ak-muted)]">{{ $signalChangedAt?->format('d.m.Y') ?? '—' }}</span></div>
                                    </div>
                                    @foreach ([
                                        [__('Kurs bei Prognose'), $historicalStartPrice !== null ? number_format($historicalStartPrice, 2, ',', '.').' '.$currency : '—', 'text-[var(--ak-text)]'],
                                        [__('Kurs danach'), $historicalEndPrice !== null ? number_format($historicalEndPrice, 2, ',', '.').' '.$currency : '—', 'text-[var(--ak-text)]'],
                                        [__('Tatsächliche Entwicklung'), $historicalReturn !== null ? ($historicalReturn > 0 ? '+' : '').number_format($historicalReturn, 2, ',', '.').' %' : '—', $historicalReturn === null ? 'text-[var(--ak-muted)]' : ($historicalReturn >= 0 ? 'text-teal-400' : 'text-rose-400')],
                                    ] as [$historyLabel, $historyValue, $historyTone])
                                        <div class="rounded-lg border border-[var(--ak-border)] bg-transparent px-2.5 py-2">
                                            <span class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $historyLabel }}</span>
                                            <span class="mt-1 block text-xs font-black tabular-nums {{ $historyTone }}">{{ $historyValue }}</span>
                                        </div>
                                    @endforeach
                                    <div class="col-span-2 rounded-lg border border-[var(--ak-border)] bg-transparent px-2.5 py-2">
                                        <span class="block text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ergebnis') }}</span>
                                        <span class="mt-1 block text-[10px] font-black {{ $directionCorrect === null ? 'text-[var(--ak-muted)]' : ($directionCorrect ? 'text-teal-400' : 'text-rose-400') }}">{{ $directionCorrect === null ? __('Noch nicht validiert') : ($directionCorrect ? __('Richtung korrekt') : __('Richtung verfehlt')) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="mt-8 rounded-xl border border-dashed border-[var(--ak-border)] p-8 text-center text-sm text-[var(--ak-muted)]">{{ __('Noch keine KI-Analyse vorhanden.') }}</div>
                @endif
            </article>
            @if ($signal === 'WAIT' && $canViewRealtime)
                <dialog id="entry-signal-alert-modal" class="m-auto w-[min(92vw,440px)] rounded-2xl border border-emerald-400/35 bg-[var(--ak-card)] p-0 text-[var(--ak-text)] shadow-2xl backdrop:bg-slate-950/75">
                    <form method="POST" action="{{ route('stocks.entry-alert.store', $instrument->id) }}" class="p-5">
                        @csrf
                        <input type="hidden" name="prediction_id" value="{{ $prediction->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-[.15em] text-emerald-400">PRO · {{ __('Einstiegsalarm') }}</p>
                                <h2 class="mt-1 text-lg font-black">{{ __('Per E-Mail informieren?') }}</h2>
                            </div>
                            <button type="button" onclick="this.closest('dialog').close()" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--ak-border)] text-[var(--ak-muted)]"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-[var(--ak-muted)]">{{ __('Du erhältst einmalig eine E-Mail, sobald sich der Status von WAIT auf BUY ändert. Danach wird der Alarm automatisch beendet.') }}</p>
                        <fieldset class="mt-4 space-y-2">
                            <legend class="mb-2 text-[9px] font-black uppercase tracking-wide text-emerald-400">{{ __('Wann informieren?') }}</legend>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-emerald-400/25 bg-emerald-400/[.07] p-3">
                                <input type="radio" name="notification_mode" value="buy_only" checked class="mt-0.5 text-emerald-500 focus:ring-emerald-400/30">
                                <span><strong class="block text-sm text-[var(--ak-text)]">{{ __('Nur bei BUY') }}</strong><small class="mt-1 block text-xs leading-5 text-[var(--ak-muted)]">{{ __('E-Mail erst beim tatsächlichen Wechsel auf BUY.') }}</small></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                                <input type="radio" name="notification_mode" value="wait_or_buy" class="mt-0.5 text-emerald-500 focus:ring-emerald-400/30">
                                <span><strong class="block text-sm text-[var(--ak-text)]">{{ __('Bei WAIT oder BUY') }}</strong><small class="mt-1 block text-xs leading-5 text-[var(--ak-muted)]">{{ __('Bei der nächsten positiven Tagesprognose informieren – auch wenn WAIT bestehen bleibt.') }}</small></span>
                            </label>
                        </fieldset>
                        <p class="mt-3 rounded-lg border border-rose-400/15 bg-rose-400/[.05] px-3 py-2 text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Bei HOLD oder SELL wird keine E-Mail gesendet. Der Alarm bleibt für eine spätere positive Prognose aktiv.') }}</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" onclick="this.closest('dialog').close()" class="h-10 rounded-lg border border-[var(--ak-border)] px-4 text-xs font-black text-[var(--ak-muted)]">{{ __('Nein') }}</button>
                            <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-300/50 bg-emerald-400/20 px-4 text-xs font-black text-emerald-200 hover:bg-emerald-400/30"><x-heroicon-o-envelope class="h-4 w-4" />{{ __('Ja, informieren') }}</button>
                        </div>
                    </form>
                </dialog>
            @endif

        </section>

        @if ($canUseChartIndicators)
        @php
            $indicatorDataPointCount = $indicatorCards->max(fn (array $card): int => count($card['points'])) ?? 0;
            $indicatorOverallProbability = $indicatorCards
                ->pluck('currentProbability')
                ->filter(fn ($value) => is_numeric($value))
                ->avg();
        @endphp
        <section data-stock-collapsible="indicators" data-stock-collapsible-title="{{ __('Indikatoren Statistik') }}" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                    <div class="min-w-0">
                        <h2 class="text-xs font-black uppercase tracking-[.14em] text-cyan-400">{{ __('Historische Indikatoranalyse') }}</h2>
                        <p class="mt-1 text-[11px] text-[var(--ak-muted)]">{{ __('Realisierte 20-Tage-Fälle aus den letzten drei Jahren') }}</p>
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
            <div class="px-1 pb-5">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Mittlere Steigwahrscheinlichkeit') }}</span>
                    <div class="min-w-44 flex-1"><x-dashboard.score-stripes :percent="$indicatorOverallProbability ?? 0" /></div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($indicatorCards as $index => $card)
                    @php
                        $indicatorForecastRising = is_numeric($card['currentProbability'] ?? null)
                            ? (float) $card['currentProbability'] >= (float) ($card['currentFallProbability'] ?? (100 - (float) $card['currentProbability']))
                            : null;
                        $indicatorForecastBorder = $indicatorForecastRising === null
                            ? null
                            : ($indicatorForecastRising ? 'rgba(34,197,94,.62)' : 'rgba(244,63,94,.62)');
                        $indicatorForecastBackground = $indicatorForecastRising === null
                            ? null
                            : ($indicatorForecastRising
                                ? 'linear-gradient(145deg, rgba(34,197,94,.12), rgba(34,197,94,.04))'
                                : 'linear-gradient(145deg, rgba(244,63,94,.12), rgba(244,63,94,.04))');
                    @endphp
                    <article
                        class="stock-detail-panel stock-detail-panel-compact flex h-[230px] min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]"
                        @if ($indicatorForecastBorder) style="border-color: {{ $indicatorForecastBorder }} !important; border-bottom-color: {{ $indicatorForecastBorder }} !important; background: {{ $indicatorForecastBackground }} !important;" @endif
                    >
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
                        <div class="min-h-0 flex-1 overflow-hidden rounded-xl border border-cyan-400/20 bg-transparent px-1 py-0.5">
                            <div id="stock-indicator-probability-chart-{{ $index }}" class="h-full min-h-0"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($canViewChartPatterns)
        <section data-stock-collapsible="chart-patterns" data-stock-collapsible-title="{{ __('Chartformationen') }}">
            <article class="stock-detail-panel overflow-hidden rounded-[1.5rem] border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                <div class="grid gap-2 xl:grid-cols-2">
                    @foreach ($chartPatternStats as $patternStat)
                        @php
                            $patternBullish = $patternStat['direction'] === 'bullish';
                            $patternTone = $patternBullish ? 'text-emerald-400' : 'text-rose-400';
                            $performance = $patternStat['average_performance'];
                            $performanceTone = $performance === null ? 'text-[var(--ak-muted)]' : ($performance >= 0 ? 'text-emerald-400' : 'text-rose-400');
                            $patternExample = collect($patternStat['example'] ?? []);
                            $exampleLow = $patternExample->isNotEmpty() ? (float) $patternExample->min('low') : 0;
                            $exampleHigh = $patternExample->isNotEmpty() ? (float) $patternExample->max('high') : 1;
                            $exampleRange = max(.000001, $exampleHigh - $exampleLow);
                        @endphp
                        <div class="chart-pattern-stat-row grid min-h-[88px] items-center gap-4 rounded-xl border border-cyan-400/15 bg-cyan-400/[.035] px-3 py-2.5">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-current/30 bg-current/[.06] {{ $patternTone }}">
                                    @if ($patternBullish)
                                        <x-heroicon-o-arrow-trending-up class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-arrow-trending-down class="h-5 w-5" />
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-black text-[var(--ak-text)]">{{ $patternStat['name'] }}</p>
                                    <p class="mt-1 text-[9px] font-bold {{ $patternTone }}">
                                        {{ $patternStat['latest_at'] ? __('Zuletzt erkannt: :date', ['date' => \Illuminate\Support\Carbon::parse($patternStat['latest_at'])->format('d.m.Y')]) : __('In drei Jahren nicht erkannt') }}
                                    </p>
                                </div>
                            </div>
                            <div class="h-14 overflow-hidden rounded-lg border border-cyan-400/15 bg-transparent px-1">
                                @if ($patternExample->isNotEmpty())
                                    <svg viewBox="0 0 150 54" class="h-full w-full" role="img" aria-label="{{ __('Kursbeispiel für :pattern', ['pattern' => $patternStat['name']]) }}">
                                        <line x1="3" y1="50" x2="147" y2="50" stroke="#22d3ee" stroke-opacity=".18" />
                                        @foreach ($patternExample as $exampleIndex => $exampleCandle)
                                            @php
                                                $exampleX = 13 + $exampleIndex * (124 / max(1, $patternExample->count() - 1));
                                                $exampleOpenY = 47 - (((float) $exampleCandle['open'] - $exampleLow) / $exampleRange) * 40;
                                                $exampleCloseY = 47 - (((float) $exampleCandle['close'] - $exampleLow) / $exampleRange) * 40;
                                                $exampleHighY = 47 - (((float) $exampleCandle['high'] - $exampleLow) / $exampleRange) * 40;
                                                $exampleLowY = 47 - (((float) $exampleCandle['low'] - $exampleLow) / $exampleRange) * 40;
                                                $exampleColor = (float) $exampleCandle['close'] >= (float) $exampleCandle['open'] ? '#22c55e' : '#ef4444';
                                                $isPatternCandle = $exampleIndex === min(3, $patternExample->count() - 1);
                                            @endphp
                                            <line x1="{{ $exampleX }}" y1="{{ $exampleHighY }}" x2="{{ $exampleX }}" y2="{{ $exampleLowY }}" stroke="{{ $exampleColor }}" stroke-width="1" />
                                            <rect x="{{ $exampleX - 4 }}" y="{{ min($exampleOpenY, $exampleCloseY) }}" width="8" height="{{ max(2, abs($exampleCloseY - $exampleOpenY)) }}" rx="1" fill="{{ $exampleColor }}" @if($isPatternCandle) stroke="#22d3ee" stroke-width="1.5" @endif />
                                        @endforeach
                                    </svg>
                                @else
                                    <span class="grid h-full place-items-center text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Kein Beispiel') }}</span>
                                @endif
                            </div>
                            <div class="grid min-w-0 grid-cols-3 gap-1.5 border-l border-cyan-400/15 pl-2 text-right">
                                <div>
                                    <span class="block text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Fälle') }}</span>
                                    <strong class="mt-1 block text-xs tabular-nums text-[var(--ak-text)]">{{ number_format($patternStat['samples'], 0, ',', '.') }}</strong>
                                </div>
                                <div>
                                    <span class="block text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Treffer') }}</span>
                                    <strong class="mt-1 block text-xs tabular-nums {{ is_numeric($patternStat['hit_rate']) && $patternStat['hit_rate'] >= 50 ? 'text-emerald-400' : 'text-rose-400' }}">{{ is_numeric($patternStat['hit_rate']) ? number_format($patternStat['hit_rate'], 1, ',', '.').'%' : '—' }}</strong>
                                </div>
                                <div>
                                    <span class="block text-[7px] font-black uppercase tracking-wide text-[var(--ak-muted)]">Ø 20T</span>
                                    <strong class="mt-1 block whitespace-nowrap text-xs tabular-nums {{ $performanceTone }}">{{ is_numeric($performance) ? (($performance > 0 ? '+' : '').number_format($performance, 2, ',', '.').'%') : '—' }}</strong>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Bei bearischen Mustern wird die Performance richtungsbereinigt: Ein fallender Kurs zählt dort als positiver Treffer. Vergangene Muster sind keine Garantie für zukünftige Ergebnisse.') }}</p>
            </article>
        </section>
        @else
        <section data-stock-collapsible="chart-patterns" data-stock-collapsible-title="{{ __('Chartformationen') }}" data-stock-pro-locked>
            <div class="flex min-h-32 flex-col items-center justify-center rounded-2xl border border-dashed border-cyan-400/20 bg-cyan-400/[.025] px-5 py-8 text-center">
                <span class="ak-plan-badge ak-plan-badge--pro">PRO</span>
                <p class="mt-3 text-sm font-black text-[var(--ak-text)]">{{ __('Chartformationen sind im Pro-Tarif verfügbar.') }}</p>
                <p class="mt-1 max-w-xl text-xs leading-5 text-[var(--ak-muted)]">{{ __('Enthält erkannte Muster, Beispielcharts und deren historische Performance.') }}</p>
            </div>
        </section>
        @endif

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
        <section data-stock-collapsible="indicator-matrix" data-stock-collapsible-title="{{ __('Indikator Matrix') }}" class="space-y-4">
            <div>
                <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                    <div>
                        <h2 class="text-xs font-black uppercase tracking-[.14em] text-cyan-400">{{ __('Trend trifft Stochastik') }}</h2>
                        <p class="mt-1 text-[11px] text-[var(--ak-muted)]">{{ __('Historische 20-Tage-Steigwahrscheinlichkeit nach gemeinsamen Indikatorzuständen') }}</p>
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
            </div>
        </section>
        @else
        <section data-stock-collapsible="indicators" data-stock-collapsible-title="{{ __('Indikatoren Statistik') }}" data-stock-pro-locked>
            <div class="flex min-h-32 flex-col items-center justify-center rounded-2xl border border-dashed border-cyan-400/20 bg-cyan-400/[.025] px-5 py-8 text-center">
                <span class="ak-plan-badge ak-plan-badge--pro">PRO</span>
                <p class="mt-3 text-sm font-black text-[var(--ak-text)]">{{ __('Chart-Indikatoren und historische Indikatorstatistiken sind im Plus-Tarif verfügbar.') }}</p>
                <p class="mt-1 max-w-xl text-xs leading-5 text-[var(--ak-muted)]">{{ __('Enthält Einzelanalysen, Steigwahrscheinlichkeiten und Heatmaps der Indikatorkombinationen.') }}</p>
            </div>
        </section>

        <section data-stock-collapsible="chart-patterns" data-stock-collapsible-title="{{ __('Chartformationen') }}" data-stock-pro-locked>
            <div class="flex min-h-32 flex-col items-center justify-center rounded-2xl border border-dashed border-cyan-400/20 bg-cyan-400/[.025] px-5 py-8 text-center">
                <span class="ak-plan-badge ak-plan-badge--pro">PRO</span>
                <p class="mt-3 text-sm font-black text-[var(--ak-text)]">{{ __('Chartformationen sind im Pro-Tarif verfügbar.') }}</p>
                <p class="mt-1 max-w-xl text-xs leading-5 text-[var(--ak-muted)]">{{ __('Enthält erkannte Muster, Beispielcharts und deren historische Performance.') }}</p>
            </div>
        </section>
        @endif

        <section data-stock-collapsible="analysis" data-stock-collapsible-title="{{ __('Analyse und Risikoeinordnung') }}">
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

        <section class="grid gap-5">
            <article data-stock-collapsible="fundamentals" data-stock-collapsible-title="{{ __('Fundamentals') }}" class="min-h-0">
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
                    ];
                @endphp

                <div class="mb-3 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-400/10 text-amber-300"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span>
                    <div>
                        <h2 class="font-black text-[var(--ak-text)]">{{ __('Fundamentaldaten') }}</h2>
                        <p class="text-xs text-[var(--ak-muted)]">{{ __('Thematisch zusammengefasste Unternehmens- und Instrumentendaten') }}</p>
                    </div>
                </div>

                <div class="ak-table-wrap mx-auto w-full max-w-2xl !rounded-xl !border-cyan-400/10 overflow-x-auto">
                    <table class="ak-table w-full min-w-[36rem] table-fixed text-left [&_td]:!px-2.5 [&_td]:!py-1.5 [&_th]:!px-2.5 [&_th]:!py-2">
                        <thead>
                            <tr>
                                <th class="w-[23%]">{{ __('Bereich') }}</th>
                                <th class="w-[28%]">{{ __('Kennzahl') }}</th>
                                <th class="w-[27%] !text-right">{{ __('Wert') }}</th>
                                <th class="w-[22%] !text-right">{{ __('Sektorvergleich') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fundamentalGroups as $group)
                                @foreach ($group['values'] as $itemIndex => $item)
                                    <tr>
                                        @if ($itemIndex === 0)
                                            <th rowspan="{{ count($group['values']) }}" style="border-top: 1px solid rgba(251,191,36,.48) !important" class="!bg-cyan-400/[.018] align-top normal-case !tracking-normal">
                                                <span class="flex items-start gap-2.5">
                                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-400/10 text-amber-300">
                                                        <x-dynamic-component :component="$group['icon']" class="h-3.5 w-3.5" />
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block text-xs font-black text-[var(--ak-text)]">{{ $group['title'] }}</span>
                                                        <span class="mt-1 block text-[9px] font-medium leading-4 text-[var(--ak-muted)]">{{ $group['subtitle'] }}</span>
                                                    </span>
                                                </span>
                                            </th>
                                        @endif
                                        <td style="border-top: 1px solid {{ $itemIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-[11px] font-bold text-[var(--ak-muted)]">{{ $item['label'] }}</td>
                                        <td style="border-top: 1px solid {{ $itemIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="break-words text-right text-[13px] font-black tabular-nums text-[var(--ak-text)]">
                                            {{ $item['value'] === null || $item['value'] === '' ? '—' : $formatValue((string) $item['label'], $item['value']) }}
                                        </td>
                                        <td style="border-top: 1px solid {{ $itemIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-right">
                                            @if ($item['ranking'] ?? null)
                                                <span class="inline-flex rounded-md border border-cyan-400/20 bg-cyan-400/10 px-2 py-1 text-[9px] font-bold text-cyan-300">
                                                    {{ __('Rang :rank von :total', [
                                                        'rank' => $item['ranking']['rank'],
                                                        'total' => $item['ranking']['total'],
                                                    ]) }}
                                                </span>
                                            @else
                                                <span class="text-[11px] text-[var(--ak-muted)]">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article id="aki-data-panel" data-stock-collapsible="aki" data-stock-collapsible-title="{{ __('aKI Daten') }}" class="flex min-h-0 flex-col overflow-hidden">
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
                @php
                    $akiModelRows = collect();
                    if ($modelQuality) {
                        $akiModelRows->push(['label' => __('Modell'), 'value' => $modelQuality->model_alias ?: '—', 'type' => 'text']);
                        $akiModelRows->push(['label' => __('Qualitätsstufe'), 'value' => $modelTierName, 'type' => 'tier']);
                        $akiModelRows->push(['label' => __('Modellqualität'), 'value' => $modelQualityPercent, 'type' => 'percent']);
                        $akiModelRows->push(['label' => __('Letztes Training'), 'value' => $modelQuality->trained_at ? \Illuminate\Support\Carbon::parse($modelQuality->trained_at)->timezone(config('app.timezone'))->format('d.m.Y H:i') : null, 'type' => 'text']);
                    }
                    foreach ($predictionData as $key => $value) $akiModelRows->push(['label' => $label($key), 'value' => $value, 'type' => $key === 'quality_gate_passed' ? 'gate' : 'prediction', 'key' => $key]);
                @endphp
                <div class="ak-table-wrap mx-auto mt-3 w-full max-w-2xl !rounded-xl !border-cyan-400/10 overflow-x-auto">
                    <table class="ak-table w-full min-w-[36rem] table-fixed text-left [&_td]:!px-2.5 [&_td]:!py-1.5 [&_th]:!px-2.5 [&_th]:!py-2">
                        <thead><tr><th class="w-[23%]">{{ __('Bereich') }}</th><th class="w-[28%]">{{ __('Kennzahl') }}</th><th class="w-[27%] !text-right">{{ __('Wert') }}</th><th class="w-[22%] !text-right">{{ __('Einordnung') }}</th></tr></thead>
                        <tbody>
                            @foreach ($topStockFactorRatings as $rowIndex => $topFactor)
                                <tr>
                                    @if ($rowIndex === 0)<th rowspan="{{ $topStockFactorRatings->count() }}" style="border-top: 1px solid rgba(251,191,36,.48) !important" class="!bg-cyan-400/[.018] align-top normal-case !tracking-normal"><span class="text-xs font-black text-[var(--ak-text)]">{{ __('Faktorbewertung') }}</span><span class="mt-1 block text-[9px] font-medium text-[var(--ak-muted)]">{{ __('Bewertung von 0 bis 10') }}</span></th>@endif
                                    <td style="border-top: 1px solid {{ $rowIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-[11px] font-bold text-[var(--ak-muted)]">{{ $topFactor['label'] }}</td>
                                    <td style="border-top: 1px solid {{ $rowIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-right text-[13px] font-black text-[var(--ak-text)]">{{ $topFactor['rating'] !== null ? $topFactor['rating'].' / 10' : '—' }}</td>
                                    <td style="border-top: 1px solid {{ $rowIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-right text-[11px] text-[var(--ak-muted)]">—</td>
                                </tr>
                            @endforeach
                            @foreach ($akiModelRows as $rowIndex => $row)
                                <tr>
                                    @if ($rowIndex === 0)<th rowspan="{{ $akiModelRows->count() }}" style="border-top: 1px solid rgba(251,191,36,.48) !important" class="!bg-cyan-400/[.018] align-top normal-case !tracking-normal"><span class="text-xs font-black text-[var(--ak-text)]">{{ __('Modelldaten') }}</span><span class="mt-1 block text-[9px] font-medium text-[var(--ak-muted)]">{{ __('Aktuelle Modellberechnung') }}</span></th>@endif
                                    <td style="border-top: 1px solid {{ $rowIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-[11px] font-bold text-[var(--ak-muted)]">{{ $row['label'] }}</td>
                                    <td style="border-top: 1px solid {{ $rowIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="break-words text-right text-[13px] font-black text-[var(--ak-text)]">
                                        @if ($row['type'] === 'tier')<span class="ak-model-tier {{ $modelTierClass }}">{{ $row['value'] }}</span>
                                        @elseif ($row['type'] === 'percent'){{ is_numeric($row['value']) ? number_format((float) $row['value'], 1, ',', '.').' %' : '—' }}
                                        @elseif ($row['type'] === 'gate')<span class="ak-model-tier {{ $row['value'] === null ? 'border-slate-500/25 bg-slate-500/10 text-slate-400' : ($row['value'] ? 'border-teal-500/30 bg-teal-500/15 text-teal-400' : 'border-rose-500/30 bg-rose-500/15 text-rose-400') }}">{{ $row['value'] === null ? '—' : ($row['value'] ? __('Bestanden') : __('Nicht bestanden')) }}</span>
                                        @elseif ($row['type'] === 'prediction' && $row['value'] !== null){{ $formatValue($row['key'], $row['value']) }}
                                        @else{{ $row['value'] ?? '—' }}@endif
                                    </td>
                                    <td style="border-top: 1px solid {{ $rowIndex === 0 ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-right text-[11px] text-[var(--ak-muted)]">—</td>
                                </tr>
                            @endforeach
                            @foreach ($ensembleData as $ensembleLabel => $ensembleValue)
                                <tr>
                                    @if ($loop->first)<th rowspan="{{ count($ensembleData) }}" style="border-top: 1px solid rgba(251,191,36,.48) !important" class="!bg-cyan-400/[.018] align-top normal-case !tracking-normal"><span class="text-xs font-black text-[var(--ak-text)]">{{ __('Ensemble-Daten') }}</span><span class="mt-1 block text-[9px] font-medium text-[var(--ak-muted)]">{{ __('Zusammenführung der Modelle') }}</span></th>@endif
                                    <td style="border-top: 1px solid {{ $loop->first ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-[11px] font-bold text-[var(--ak-muted)]">{{ $ensembleLabel }}</td>
                                    <td style="border-top: 1px solid {{ $loop->first ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-right text-[13px] font-black text-[var(--ak-text)]">
                                        @if ($ensembleLabel === __('Ensemble-Veto'))<span class="ak-model-tier {{ $ensembleValue === null ? 'border-slate-500/25 bg-slate-500/10 text-slate-400' : ($ensembleValue ? 'border-rose-500/30 bg-rose-500/15 text-rose-400' : 'border-teal-500/30 bg-teal-500/15 text-teal-400') }}">{{ $ensembleValue === null ? '—' : ($ensembleValue ? __('Ja') : __('Nein')) }}</span>
                                        @elseif ($ensembleValue === null) —
                                        @elseif (is_bool($ensembleValue)){{ $ensembleValue ? __('Ja') : __('Nein') }}
                                        @elseif ($ensembleLabel === __('Ensemble-Score') && is_numeric($ensembleValue)){{ number_format((float) $ensembleValue, 1, ',', '.') }} %
                                        @elseif (in_array($ensembleLabel, [__('Relative Streuung'), __('Modellübereinstimmung'), __('Ø Modellqualität'), __('Schwächste Modellqualität'), __('Ø Stabilität'), __('Statistische Zuverlässigkeit')], true) && is_numeric($ensembleValue)){{ number_format((float) $ensembleValue * 100, 1, ',', '.') }} %
                                        @elseif ($ensembleLabel === __('Ø Profit-Faktor') && is_numeric($ensembleValue)){{ number_format((float) $ensembleValue, 2, ',', '.') }}
                                        @else{{ $ensembleValue }}@endif
                                    </td>
                                    <td style="border-top: 1px solid {{ $loop->first ? 'rgba(251,191,36,.48)' : 'rgba(34,211,238,.13)' }} !important" class="text-right text-[11px] text-[var(--ak-muted)]">—</td>
                                </tr>
                            @endforeach
                            @if ($modelQuality && $modelTierCode !== 'top')
                                @forelse ($modelQualityGateReasons as $gateReason)
                                    <tr>
                                        @if ($loop->first)<th rowspan="{{ max(1, $modelQualityGateReasons->count()) }}" class="!border-t !border-amber-400/25 !bg-cyan-400/[.018] align-top normal-case !tracking-normal"><span class="text-xs font-black text-[var(--ak-text)]">{{ __('Quality Gate') }}</span><span class="mt-1 block text-[9px] font-medium text-[var(--ak-muted)]">{{ __('Nicht erfüllte Kriterien') }}</span></th>@endif
                                        <td class="{{ $loop->first ? '!border-t !border-amber-400/25' : '' }} text-[11px] font-bold text-[var(--ak-muted)]">{{ $gateReason['name'] }}</td>
                                        <td class="{{ $loop->first ? '!border-t !border-amber-400/25' : '' }} text-right text-[12px] font-black text-amber-400">{{ number_format($gateReason['actual'], $gateReason['unit'] === '%' ? 1 : 2, ',', '.') }}{{ $gateReason['unit'] }} {{ $gateReason['direction'] === 'min' ? '<' : '>' }} {{ number_format($gateReason['threshold'], $gateReason['unit'] === '%' ? 1 : 2, ',', '.') }}{{ $gateReason['unit'] }}</td>
                                        <td class="{{ $loop->first ? '!border-t !border-amber-400/25' : '' }} text-right text-[11px] text-[var(--ak-muted)]">—</td>
                                    </tr>
                                @empty
                                    <tr><th class="!border-t !border-amber-400/25 !bg-cyan-400/[.018] normal-case !tracking-normal">{{ __('Quality Gate') }}</th><td colspan="3" class="!border-t !border-amber-400/25 text-[11px] text-[var(--ak-muted)]">{{ __('Das Modell erfüllt derzeit nicht alle Freigabekriterien.') }}</td></tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <p id="stock-disclaimer" class="pb-3 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Die Darstellung dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar.') }}</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const analysisSection = document.querySelector('[data-stock-collapsible="analysis"]');
            const stockDisclaimer = document.querySelector('#stock-disclaimer');
            if (analysisSection && stockDisclaimer) {
                stockDisclaimer.parentNode.insertBefore(analysisSection, stockDisclaimer);
            }

            const indicatorSection = document.querySelector('[data-stock-collapsible="indicators"]');
            const matrixSection = document.querySelector('[data-stock-collapsible="indicator-matrix"]');
            if (indicatorSection && matrixSection) {
                const heading = document.createElement('div');
                heading.className = 'mt-5 flex items-center gap-3 border-t border-cyan-400/20 pt-4';
                heading.innerHTML = `<span class="grid h-9 w-9 place-items-center rounded-lg border border-cyan-400/25 bg-cyan-400/10 text-cyan-400"><svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true"><path d="M3 3h5v5H3V3Zm9 0h5v5h-5V3ZM3 12h5v5H3v-5Zm9 0h5v5h-5v-5Z" stroke="currentColor" stroke-width="1.5"/></svg></span><div><p class="text-[10px] font-black uppercase tracking-[.14em] text-cyan-400">${@json(__('Indikator-Heatmaps'))}</p><p class="text-xs text-[var(--ak-muted)]">${@json(__('Gemeinsame Wirkung der wichtigsten Indikatorkombinationen'))}</p></div>`;
                indicatorSection.appendChild(heading);
                while (matrixSection.firstChild) indicatorSection.appendChild(matrixSection.firstChild);
                matrixSection.remove();
            }

            const sectionMeta = {
                indicators: {
                    description: @json(__('Historische Einzelcharts und Heatmaps der technischen Indikatoren.')),
                    icon: '<path d="M3 16V9m4 7V5m4 11v-4m4 4V3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
                },
                'chart-patterns': {
                    description: @json(__('Zuletzt gefundene Formationen mit Beispielchart und historischer 20-Tage-Performance.')),
                    icon: '<path d="M3 15 7 10l3 2 4-7 3 3M3 17h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
                },
                analysis: {
                    description: @json(__('Chancen, Risiken und die aktuelle Einordnung der Aktie.')),
                    icon: '<path d="m10 2 1.2 4.1L15 8l-3.8 1.9L10 14l-1.2-4.1L5 8l3.8-1.9L10 2Zm5 10 .7 2.3L18 15.5l-2.3 1.2L15 19l-.7-2.3-2.3-1.2 2.3-1.2L15 12Z" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round"/>',
                },
                heatmap: {
                    description: @json(__('Historische Ergebnisse nach KI-Score und Konfidenz.')),
                    icon: '<path d="M3 3h5v5H3V3Zm9 0h5v5h-5V3ZM3 12h5v5H3v-5Zm9 0h5v5h-5v-5Z" stroke="currentColor" stroke-width="1.45"/>',
                },
                fundamentals: {
                    description: @json(__('Unternehmens-, Bewertungs- und Bilanzkennzahlen.')),
                    icon: '<path d="M3 17h14M5 17V8h10v9M4 8l6-5 6 5M8 11v3m4-3v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
                },
                aki: {
                    description: @json(__('Modelldaten, Qualitätswerte und vollständige Prognoseinformationen.')),
                    icon: '<rect x="4" y="4" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 8h4v4H8V8ZM7 1v3m6-3v3M7 16v3m6-3v3M1 7h3m-3 6h3m12-6h3m-3 6h3" stroke="currentColor" stroke-width="1.35"/>',
                },
            };

            document.querySelectorAll('[data-stock-collapsible]').forEach(section => {
                const key = section.dataset.stockCollapsible;
                const title = section.dataset.stockCollapsibleTitle || key;
                const meta = sectionMeta[key] || { description: '', icon: '' };
                const proLocked = section.hasAttribute('data-stock-pro-locked');
                const content = document.createElement('div');
                content.className = 'stock-collapsible-content';
                while (section.firstChild) content.appendChild(section.firstChild);

                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'stock-collapsible-toggle';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.innerHTML = `<span class="stock-collapsible-icon"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true">${meta.icon}</svg></span><span class="stock-collapsible-copy"><span class="flex items-center gap-2">${title}${proLocked ? '<span class="ak-plan-badge ak-plan-badge--pro">PRO</span>' : ''}</span><small class="stock-collapsible-description">${meta.description}</small></span><svg class="stock-collapsible-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
                content.hidden = true;
                section.classList.add('stock-collapsible-section');
                section.append(toggle, content);

                toggle.addEventListener('click', () => {
                    const open = toggle.getAttribute('aria-expanded') !== 'true';
                    toggle.setAttribute('aria-expanded', String(open));
                    content.hidden = !open;
                    if (open) {
                        window.dispatchEvent(new CustomEvent('stock-detail-section-opened', { detail: { section: key } }));
                        window.requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
                    }
                });
            });

        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const panel = document.querySelector('#aki-data-panel');
            const disclaimer = document.querySelector('#stock-disclaimer');
            if (!panel) return;

            const fitAkiPanel = () => {
                const collapsibleContent = panel.querySelector(':scope > .stock-collapsible-content');
                if (collapsibleContent?.hidden) {
                    panel.style.height = '';
                    return;
                }
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
                            height: Math.max(140, element.clientHeight || 140),
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
                            padding: { top: 4, right: 8, bottom: 12, left: 4 },
                        },
                        xaxis: {
                            type: 'numeric',
                            tickAmount: 4,
                            labels: {
                                formatter: value => Number(value).toFixed(2),
                                style: { colors: '#82909f', fontSize: '8px' },
                            },
                            axisBorder: { show: true, color: 'rgba(148,163,184,.38)' },
                            axisTicks: { show: true, color: 'rgba(148,163,184,.30)', height: 3 },
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

            window.addEventListener('stock-detail-section-opened', event => {
                if (event.detail?.section === 'indicators') renderIndicatorCards();
            });
        });
    </script>

    @php
        $chartHorizonTargets = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($horizonTargets): array {
            $price = data_get($horizonTargets, $days.'.price');

            return [$days => is_numeric($price) ? (float) $price : null];
        })->all();
    @endphp
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
                const forecastHorizonTargets = @json($chartHorizonTargets);
                const chartFocusAt = @json($chartFocusAt?->getTimestampMs());
                const forecastBasePrice = @json(is_numeric($prediction?->current_price) ? (float) $prediction->current_price : null);
                const liveSourceSymbol = @json($requestedPredictionId === 0 ? $instrument->symbol : null);
                const dataUrl = @json($chartDataUrl);
                const updatedElement = document.querySelector('#stock-chart-updated');
                const changeElement = document.querySelector('#stock-chart-change');
                const rsiElement = document.querySelector('#stock-detail-rsi');
                const rsiPanel = document.querySelector('#stock-rsi-panel');
                const rsiValueElement = document.querySelector('#stock-rsi-value');
                const secondaryPanels = document.querySelector('#stock-secondary-indicator-panels');
                const indicatorOverlay = document.querySelector('#stock-indicator-overlay');
                const indicatorButtons = document.querySelectorAll('#stock-indicator-buttons [data-indicator]');
                const periodButtons = document.querySelectorAll('#stock-chart-period-buttons [data-chart-period]');
                const chartResetButton = document.querySelector('#stock-indicator-buttons [data-chart-reset]');
                const zoomSelection = document.querySelector('#stock-chart-zoom-selection');
                const chartCard = document.querySelector('#stock-chart-card');
                const fullscreenButton = document.querySelector('#stock-chart-fullscreen');
                const fullscreenCloseButton = document.querySelector('#stock-chart-fullscreen-close');
                const canUseChartIndicators = @json($canUseChartIndicators);
                const canUseChartZoom = @json($canUseChartZoom);
                let chart;
                let rsiChart;
                const secondaryCharts = new Map();
                let currentCandles = initialCandles;
                let chartPatterns = initialChartPatterns;
                let watchlistEntry = initialWatchlistEntry;
                let liveTimer;
                let selectedPeriod = 132;
                let zoomTimeRange = null;
                let zoomDragStart = null;
                let zoomInteractionActive = false;
                const activeIndicators = new Set();

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

                const emaData = period => {
                    const multiplier = 2 / (period + 1);
                    let ema = null;
                    return currentCandles.map(candle => {
                        const close = Number(candle?.y?.[3]);
                        if (!Number.isFinite(close)) return null;
                        ema = ema === null ? close : ((close - ema) * multiplier) + ema;
                        return { x: candle.x, y: ema };
                    }).filter(Boolean);
                };

                const atrData = (period = 14) => {
                    let atr = null;
                    return currentCandles.map((candle, index) => {
                        if (index === 0) return null;
                        const high = Number(candle.y[1]); const low = Number(candle.y[2]);
                        const previousClose = Number(currentCandles[index - 1].y[3]);
                        const trueRange = Math.max(high - low, Math.abs(high - previousClose), Math.abs(low - previousClose));
                        atr = atr === null ? trueRange : ((atr * (period - 1)) + trueRange) / period;
                        return index >= period ? { x: candle.x, y: atr } : null;
                    }).filter(Boolean);
                };

                const stochasticData = (period = 14) => currentCandles.map((candle, index) => {
                    if (index < period - 1) return null;
                    const sample = currentCandles.slice(index - period + 1, index + 1);
                    const high = Math.max(...sample.map(item => Number(item.y[1])));
                    const low = Math.min(...sample.map(item => Number(item.y[2])));
                    return { x: candle.x, y: high === low ? 50 : ((Number(candle.y[3]) - low) / (high - low)) * 100 };
                }).filter(Boolean);

                const momentumData = (period = 10) => currentCandles.map((candle, index) => {
                    if (index < period) return null;
                    const previous = Number(currentCandles[index - period].y[3]);
                    return previous ? { x: candle.x, y: ((Number(candle.y[3]) / previous) - 1) * 100 } : null;
                }).filter(Boolean);

                const rocData = (period = 12) => momentumData(period);

                const cciData = (period = 20) => {
                    const typical = currentCandles.map(candle => (Number(candle.y[1]) + Number(candle.y[2]) + Number(candle.y[3])) / 3);
                    return currentCandles.map((candle, index) => {
                        if (index < period - 1) return null;
                        const sample = typical.slice(index - period + 1, index + 1);
                        const average = sample.reduce((sum, number) => sum + number, 0) / period;
                        const deviation = sample.reduce((sum, number) => sum + Math.abs(number - average), 0) / period;
                        return { x: candle.x, y: deviation ? (typical[index] - average) / (.015 * deviation) : 0 };
                    }).filter(Boolean);
                };

                const williamsData = (period = 14) => currentCandles.map((candle, index) => {
                    if (index < period - 1) return null;
                    const sample = currentCandles.slice(index - period + 1, index + 1);
                    const high = Math.max(...sample.map(item => Number(item.y[1])));
                    const low = Math.min(...sample.map(item => Number(item.y[2])));
                    return { x: candle.x, y: high === low ? -50 : -100 * ((high - Number(candle.y[3])) / (high - low)) };
                }).filter(Boolean);

                const vwapData = () => {
                    let cumulativePriceVolume = 0;
                    let cumulativeVolume = 0;
                    return currentCandles.map(candle => {
                        const volume = Number(candle.volume);
                        if (!Number.isFinite(volume) || volume <= 0) return null;
                        const typical = (Number(candle.y[1]) + Number(candle.y[2]) + Number(candle.y[3])) / 3;
                        cumulativePriceVolume += typical * volume;
                        cumulativeVolume += volume;
                        return { x: candle.x, y: cumulativePriceVolume / cumulativeVolume };
                    }).filter(Boolean);
                };

                const obvData = () => {
                    let obv = 0;
                    return currentCandles.map((candle, index) => {
                        const volume = Number(candle.volume);
                        if (!Number.isFinite(volume)) return null;
                        if (index) {
                            const close = Number(candle.y[3]);
                            const previous = Number(currentCandles[index - 1].y[3]);
                            if (close > previous) obv += volume;
                            if (close < previous) obv -= volume;
                        }
                        return { x: candle.x, y: obv };
                    }).filter(Boolean);
                };

                const mfiData = (period = 14) => {
                    const flows = currentCandles.map((candle, index) => {
                        const typical = (Number(candle.y[1]) + Number(candle.y[2]) + Number(candle.y[3])) / 3;
                        const previousTypical = index ? (Number(currentCandles[index - 1].y[1]) + Number(currentCandles[index - 1].y[2]) + Number(currentCandles[index - 1].y[3])) / 3 : typical;
                        const flow = typical * Number(candle.volume);
                        return { positive: typical >= previousTypical ? flow : 0, negative: typical < previousTypical ? flow : 0 };
                    });
                    return currentCandles.map((candle, index) => {
                        if (index < period || !Number.isFinite(Number(candle.volume))) return null;
                        const sample = flows.slice(index - period + 1, index + 1);
                        const positive = sample.reduce((sum, item) => sum + item.positive, 0);
                        const negative = sample.reduce((sum, item) => sum + item.negative, 0);
                        return { x: candle.x, y: negative ? 100 - (100 / (1 + positive / negative)) : 100 };
                    }).filter(Boolean);
                };

                const volatilityData = (period = 20) => {
                    const returns = currentCandles.map((candle, index) => index ? Math.log(Number(candle.y[3]) / Number(currentCandles[index - 1].y[3])) : null);
                    return currentCandles.map((candle, index) => {
                        if (index < period) return null;
                        const sample = returns.slice(index - period + 1, index + 1).filter(Number.isFinite);
                        const average = sample.reduce((sum, value) => sum + value, 0) / sample.length;
                        const deviation = Math.sqrt(sample.reduce((sum, value) => sum + ((value - average) ** 2), 0) / sample.length);
                        return { x: candle.x, y: deviation * Math.sqrt(252) * 100 };
                    }).filter(Boolean);
                };

                const macdData = () => {
                    const fast = emaData(12); const slow = emaData(26);
                    const fastByTime = new Map(fast.map(point => [String(point.x), point.y]));
                    return slow.map(point => ({ x: point.x, y: (fastByTime.get(String(point.x)) ?? point.y) - point.y }));
                };

                const adxData = (period = 14) => {
                    let smoothedTr = 0; let smoothedPlus = 0; let smoothedMinus = 0; let adx = null;
                    return currentCandles.map((candle, index) => {
                        if (!index) return null;
                        const previous = currentCandles[index - 1];
                        const high = Number(candle.y[1]); const low = Number(candle.y[2]); const previousClose = Number(previous.y[3]);
                        const upMove = high - Number(previous.y[1]); const downMove = Number(previous.y[2]) - low;
                        const tr = Math.max(high - low, Math.abs(high - previousClose), Math.abs(low - previousClose));
                        const plus = upMove > downMove && upMove > 0 ? upMove : 0;
                        const minus = downMove > upMove && downMove > 0 ? downMove : 0;
                        smoothedTr = index <= period ? smoothedTr + tr : smoothedTr - (smoothedTr / period) + tr;
                        smoothedPlus = index <= period ? smoothedPlus + plus : smoothedPlus - (smoothedPlus / period) + plus;
                        smoothedMinus = index <= period ? smoothedMinus + minus : smoothedMinus - (smoothedMinus / period) + minus;
                        if (index < period || !smoothedTr) return null;
                        const plusDi = 100 * smoothedPlus / smoothedTr; const minusDi = 100 * smoothedMinus / smoothedTr;
                        const dx = plusDi + minusDi ? 100 * Math.abs(plusDi - minusDi) / (plusDi + minusDi) : 0;
                        adx = adx === null ? dx : ((adx * (period - 1)) + dx) / period;
                        return { x: candle.x, y: adx };
                    }).filter(Boolean);
                };

                const bollingerData = (period = 20, deviations = 2) => {
                    const closes = currentCandles.map(candle => Number(candle?.y?.[3]));
                    return currentCandles.map((candle, index) => {
                        if (index < period - 1) return null;
                        const sample = closes.slice(index - period + 1, index + 1);
                        if (sample.some(value => !Number.isFinite(value))) return null;
                        const middle = sample.reduce((sum, value) => sum + value, 0) / period;
                        const variance = sample.reduce((sum, value) => sum + ((value - middle) ** 2), 0) / period;
                        const deviation = Math.sqrt(variance) * deviations;
                        return { x: candle.x, middle, upper: middle + deviation, lower: middle - deviation };
                    }).filter(Boolean);
                };

                const parabolicSarData = (step = .02, maximum = .2) => {
                    if (currentCandles.length < 3) return [];
                    let bullish = Number(currentCandles[1].y[3]) >= Number(currentCandles[0].y[3]);
                    let extreme = bullish ? Number(currentCandles[0].y[1]) : Number(currentCandles[0].y[2]);
                    let sar = bullish ? Number(currentCandles[0].y[2]) : Number(currentCandles[0].y[1]);
                    let acceleration = step;
                    const result = [];
                    for (let index = 1; index < currentCandles.length; index += 1) {
                        const candle = currentCandles[index];
                        const high = Number(candle.y[1]); const low = Number(candle.y[2]);
                        sar += acceleration * (extreme - sar);
                        if (bullish) {
                            sar = Math.min(sar, Number(currentCandles[index - 1].y[2]), index > 1 ? Number(currentCandles[index - 2].y[2]) : low);
                            if (low < sar) { bullish = false; sar = extreme; extreme = low; acceleration = step; }
                            else if (high > extreme) { extreme = high; acceleration = Math.min(maximum, acceleration + step); }
                        } else {
                            sar = Math.max(sar, Number(currentCandles[index - 1].y[1]), index > 1 ? Number(currentCandles[index - 2].y[1]) : high);
                            if (high > sar) { bullish = true; sar = extreme; extreme = high; acceleration = step; }
                            else if (low < extreme) { extreme = low; acceleration = Math.min(maximum, acceleration + step); }
                        }
                        result.push({ x: candle.x, y: sar, bullish });
                    }
                    return result;
                };

                const forecastOrigin = () => {
                    if (!Number.isFinite(chartFocusAt)) {
                        return currentCandles[currentCandles.length - 1];
                    }

                    const candle = [...currentCandles]
                        .reverse()
                        .find(candle => new Date(candle.x).getTime() <= chartFocusAt)
                        ?? currentCandles[0];

                    if (!candle || !Number.isFinite(Number(forecastBasePrice))) return candle;

                    return {
                        ...candle,
                        y: [candle.y?.[0], candle.y?.[1], candle.y?.[2], Number(forecastBasePrice)],
                    };
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
                        const focusIndex = Math.max(0, currentCandles.findLastIndex(candle => new Date(candle.x).getTime() <= chartFocusAt));
                        const firstVisibleIndex = selectedPeriod > 0 ? Math.max(0, focusIndex - selectedPeriod + 1) : 0;
                        const firstVisibleTimestamp = new Date(currentCandles[firstVisibleIndex]?.x).getTime();

                        const range = {
                            min: Number.isFinite(firstVisibleTimestamp) ? firstVisibleTimestamp : chartFocusAt,
                            max: Number.isFinite(forecastEnd) ? forecastEnd : chartFocusAt,
                        };

                        return zoomTimeRange ?? range;
                    }

                    const firstVisibleIndex = selectedPeriod > 0 ? Math.max(0, currentCandles.length - selectedPeriod) : 0;
                    const firstTimestamp = new Date(currentCandles[firstVisibleIndex]?.x).getTime();

                    const range = {
                        min: Number.isFinite(firstTimestamp) ? firstTimestamp : undefined,
                        max: Number.isFinite(forecastEnd) ? forecastEnd : undefined,
                    };

                    return zoomTimeRange ?? range;
                };

                const chartPriceRange = () => {
                    const timeRange = chartTimeRange();
                    const values = currentCandles
                        .filter(candle => {
                            const timestamp = new Date(candle.x).getTime();
                            return (!Number.isFinite(timeRange.min) || timestamp >= timeRange.min)
                                && (!Number.isFinite(timeRange.max) || timestamp <= timeRange.max);
                        })
                        .flatMap(candle => Array.isArray(candle?.y) ? candle.y.map(Number) : [])
                        .filter(Number.isFinite);
                    if (activeIndicators.has('bollinger')) {
                        bollingerData().filter(point => new Date(point.x).getTime() >= timeRange.min).forEach(point => values.push(point.upper, point.lower));
                    }
                    if (activeIndicators.has('sar')) {
                        parabolicSarData().filter(point => new Date(point.x).getTime() >= timeRange.min).forEach(point => values.push(point.y));
                    }
                    if (activeIndicators.has('vwap')) {
                        vwapData().filter(point => new Date(point.x).getTime() >= timeRange.min).forEach(point => values.push(point.y));
                    }
                    Object.values(forecastHorizonTargets)
                        .filter(value => value !== null && value !== undefined && value !== '' && Number(value) > 0)
                        .map(Number)
                        .filter(Number.isFinite)
                        .forEach(value => values.push(value));
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

                const secondaryIndicatorDefinitions = {
                    macd: { label: 'MACD', color: '#a78bfa', unit: '', values: () => macdData(), reference: 0 },
                    adx: { label: 'ADX 14', color: '#22d3ee', unit: '', values: () => adxData(14), reference: 25 },
                    atr: { label: 'ATR 14', color: '#f59e0b', unit: ` ${currency}`, values: () => atrData(14) },
                    stochastic: { label: 'Stochastik %K', color: '#38bdf8', unit: ' %', values: () => stochasticData(14), min: 0, max: 100, reference: 80, secondReference: 20 },
                    cci: { label: 'CCI 20', color: '#f59e0b', unit: '', values: () => cciData(20), reference: 100, secondReference: -100 },
                    mfi: { label: 'MFI 14', color: '#2dd4bf', unit: '', values: () => mfiData(14), min: 0, max: 100, reference: 80, secondReference: 20 },
                    obv: { label: 'OBV', color: '#60a5fa', unit: '', values: () => obvData(), reference: 0 },
                    williams: { label: 'Williams %R', color: '#e879f9', unit: ' %', values: () => williamsData(14), min: -100, max: 0, reference: -20, secondReference: -80 },
                    roc: { label: 'ROC 12', color: '#34d399', unit: ' %', values: () => rocData(12), reference: 0 },
                    volatility: { label: 'Volatilität 20T', color: '#fb7185', unit: ' %', values: () => volatilityData(20) },
                    momentum: { label: 'Momentum 10T', color: '#84cc16', unit: ' %', values: () => momentumData(10), reference: 0 },
                };

                const renderSecondaryPanels = async () => {
                    if (!secondaryPanels) return;
                    secondaryCharts.forEach(instance => instance.destroy());
                    secondaryCharts.clear();
                    secondaryPanels.replaceChildren();

                    for (const [key, definition] of Object.entries(secondaryIndicatorDefinitions)) {
                        if (!activeIndicators.has(key)) continue;
                        const values = definition.values();
                        const latest = values.at(-1)?.y;
                        const panel = document.createElement('div');
                        panel.className = 'min-w-0 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-transparent px-2 pb-1 pt-1.5';
                        const head = document.createElement('div');
                        head.className = 'flex items-center justify-between px-1';
                        const label = document.createElement('span');
                        label.className = 'text-[9px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]';
                        label.textContent = definition.label;
                        const value = document.createElement('span');
                        value.className = 'text-[10px] font-black text-cyan-400';
                        value.textContent = Number.isFinite(latest) ? `${Number(latest).toFixed(2).replace('.', ',')}${definition.unit}` : '—';
                        const chartElement = document.createElement('div');
                        chartElement.className = 'h-16 min-w-0';
                        head.append(label, value);
                        panel.append(head, chartElement);
                        secondaryPanels.append(panel);

                        const light = document.documentElement.dataset.theme === 'light';
                        const annotations = [definition.reference, definition.secondReference]
                            .filter(Number.isFinite)
                            .map(reference => ({ y: reference, borderColor: 'rgba(148,163,184,.42)', strokeDashArray: 4 }));
                        const instance = new window.ApexCharts(chartElement, {
                            chart: { type: 'line', height: 64, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false }, parentHeightOffset: 0 },
                            series: [{ name: definition.label, data: values }],
                            colors: [definition.color],
                            stroke: { width: 2, curve: 'smooth' },
                            markers: { size: 0 },
                            dataLabels: { enabled: false },
                            annotations: { yaxis: annotations },
                            grid: { borderColor: light ? 'rgba(51,65,85,.10)' : 'rgba(148,163,184,.08)', strokeDashArray: 4, padding: { top: -8, right: 8, bottom: -10, left: 2 } },
                            xaxis: { type: 'datetime', min: chartTimeRange().min, max: chartTimeRange().max, labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
                            yaxis: { min: definition.min, max: definition.max, opposite: true, tickAmount: 2, labels: { formatter: number => Number(number).toFixed(0), style: { colors: [light ? '#64748b' : '#94a3b8'], fontSize: '9px' } } },
                            tooltip: { theme: light ? 'light' : 'dark', x: { format: 'dd.MM.yyyy' }, y: { formatter: number => `${Number(number).toFixed(2)}${definition.unit}` } },
                            theme: { mode: light ? 'light' : 'dark' },
                        });
                        secondaryCharts.set(key, instance);
                        await instance.render();
                    }
                };

                const syncIndicatorUi = () => {
                    indicatorButtons.forEach(button => {
                        const active = activeIndicators.has(button.dataset.indicator);
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                        button.className = `rounded-lg border px-2.5 py-1 text-[9px] font-black uppercase tracking-wide transition ${
                            active
                                ? (button.dataset.indicator === 'bollinger'
                                    ? 'border-fuchsia-400/50 bg-fuchsia-500/15 text-fuchsia-300 shadow-[0_0_10px_rgba(192,132,252,.18)]'
                                    : 'border-violet-400/35 bg-violet-500/15 text-violet-300')
                                : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-violet-400/25 hover:text-[var(--ak-text)]'
                        }`;
                    });

                    if (rsiPanel) rsiPanel.classList.toggle('hidden', !activeIndicators.has('rsi'));
                };

                const syncPeriodUi = () => {
                    periodButtons.forEach(button => {
                        const active = Number(button.dataset.chartPeriod) === selectedPeriod;
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                        button.className = `min-w-0 rounded-lg border px-2 py-1 text-[9px] font-black uppercase tracking-wide transition ${active
                            ? 'border-cyan-400/35 bg-cyan-400/15 text-cyan-400'
                            : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] hover:border-cyan-400/25 hover:text-[var(--ak-text)]'}`;
                    });
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
                    const overlayX = timestamp => left + ((timestamp - timeRange.min) / xSpan) * plotWidth;
                    const overlayY = price => top + plotHeight - ((price - priceRange.min) / ySpan) * plotHeight;

                    const forecastCandle = forecastOrigin();
                    const forecastStart = forecastCandle ? new Date(forecastCandle.x).getTime() : NaN;
                    const forecastStartPrice = Number(forecastCandle?.y?.[3]);
                    const horizonPoints = [5, 10, 15, 20]
                        .map(days => {
                            const value = forecastHorizonTargets[days];

                            return {
                                days,
                                price: value === null || value === undefined || value === '' || Number(value) <= 0 ? null : Number(value),
                            };
                        })
                        .filter(point => Number.isFinite(point.price));
                    if (horizonPoints.length && Number.isFinite(forecastStart) && Number.isFinite(forecastStartPrice)) {
                        const toX = timestamp => left + ((timestamp - timeRange.min) / xSpan) * plotWidth;
                        const toY = price => top + plotHeight - ((price - priceRange.min) / ySpan) * plotHeight;
                        const points = [
                            { days: 0, timestamp: forecastStart, price: forecastStartPrice },
                            ...horizonPoints.map(point => ({
                                ...point,
                                timestamp: addTradingDays(forecastStart, point.days),
                            })),
                        ];
                        for (let index = 1; index < points.length; index += 1) {
                            const previous = points[index - 1];
                            const point = points[index];
                            const segmentColor = point.price >= previous.price ? '#22c55e' : '#ef4444';
                            const previousX = toX(previous.timestamp);
                            const previousY = toY(previous.price);
                            const pointX = toX(point.timestamp);
                            const pointY = toY(point.price);
                            const triangleCornerX = point.price >= previous.price ? pointX : previousX;
                            const triangleCornerY = Math.max(previousY, pointY);
                            const gradientId = `forecast-segment-gradient-${@json($instrument->id)}-${index}`;
                            const glowId = `forecast-segment-glow-${@json($instrument->id)}-${index}`;
                            const definitions = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                            const gradient = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
                            gradient.setAttribute('id', gradientId);
                            gradient.setAttribute('x1', '0%');
                            gradient.setAttribute('y1', '0%');
                            gradient.setAttribute('x2', '0%');
                            gradient.setAttribute('y2', '100%');
                            const gradientTop = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
                            gradientTop.setAttribute('offset', '0%');
                            gradientTop.setAttribute('stop-color', segmentColor);
                            gradientTop.setAttribute('stop-opacity', '.42');
                            const gradientBottom = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
                            gradientBottom.setAttribute('offset', '100%');
                            gradientBottom.setAttribute('stop-color', segmentColor);
                            gradientBottom.setAttribute('stop-opacity', '.10');
                            gradient.append(gradientTop, gradientBottom);
                            const glow = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
                            glow.setAttribute('id', glowId);
                            glow.setAttribute('x', '-20%');
                            glow.setAttribute('y', '-20%');
                            glow.setAttribute('width', '140%');
                            glow.setAttribute('height', '140%');
                            const blur = document.createElementNS('http://www.w3.org/2000/svg', 'feGaussianBlur');
                            blur.setAttribute('stdDeviation', '3');
                            glow.appendChild(blur);
                            definitions.append(gradient, glow);
                            indicatorOverlay.appendChild(definitions);

                            const segmentGlow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                            segmentGlow.setAttribute('points', [
                                `${previousX},${previousY}`,
                                `${pointX},${pointY}`,
                                `${triangleCornerX},${triangleCornerY}`,
                            ].join(' '));
                            segmentGlow.setAttribute('fill', segmentColor);
                            segmentGlow.setAttribute('fill-opacity', '.20');
                            segmentGlow.setAttribute('filter', `url(#${glowId})`);
                            indicatorOverlay.appendChild(segmentGlow);

                            const segmentArea = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                            segmentArea.setAttribute('points', [
                                `${previousX},${previousY}`,
                                `${pointX},${pointY}`,
                                `${triangleCornerX},${triangleCornerY}`,
                            ].join(' '));
                            segmentArea.setAttribute('fill', `url(#${gradientId})`);
                            segmentArea.setAttribute('fill-opacity', '1');
                            segmentArea.setAttribute('stroke', 'none');
                            indicatorOverlay.appendChild(segmentArea);

                            const segmentLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                            segmentLine.setAttribute('x1', previousX);
                            segmentLine.setAttribute('y1', previousY);
                            segmentLine.setAttribute('x2', pointX);
                            segmentLine.setAttribute('y2', pointY);
                            segmentLine.setAttribute('stroke', segmentColor);
                            segmentLine.setAttribute('stroke-width', '2.25');
                            segmentLine.setAttribute('stroke-opacity', '0.92');
                            segmentLine.setAttribute('stroke-linecap', 'round');
                            segmentLine.setAttribute('vector-effect', 'non-scaling-stroke');
                            indicatorOverlay.appendChild(segmentLine);
                        }

                        points.slice(1).forEach((point, pointIndex) => {
                            const previous = points[pointIndex];
                            const pointColor = point.price >= previous.price ? '#22c55e' : '#ef4444';
                            const x = toX(point.timestamp);
                            const y = toY(point.price);
                            const marker = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                            marker.setAttribute('cx', x);
                            marker.setAttribute('cy', y);
                            marker.setAttribute('r', '3.25');
                            marker.setAttribute('fill', '#0f172a');
                            marker.setAttribute('stroke', pointColor);
                            marker.setAttribute('stroke-width', '2');
                            marker.setAttribute('vector-effect', 'non-scaling-stroke');
                            indicatorOverlay.appendChild(marker);

                            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                            label.setAttribute('x', x);
                            label.setAttribute('y', Math.max(top + 9, y - 8));
                            label.setAttribute('fill', pointColor);
                            label.setAttribute('font-size', '8');
                            label.setAttribute('font-weight', '800');
                            label.setAttribute('text-anchor', 'middle');
                            label.textContent = `${point.days}T`;
                            indicatorOverlay.appendChild(label);
                        });
                    }

                    [
                        ['sma20', () => movingAverageData(20), '#fb923c', '8 6'],
                        ['sma50', () => movingAverageData(50), '#60a5fa', '8 6'],
                        ['sma200', () => movingAverageData(200), '#f43f5e', '10 6'],
                        ['ema20', () => emaData(20), '#22c55e', ''],
                        ['ema50', () => emaData(50), '#a78bfa', ''],
                        ['vwap', () => vwapData(), '#22d3ee', '3 3'],
                    ].forEach(([indicator, dataSource, color, dashArray]) => {
                        if (!activeIndicators.has(indicator)) return;

                        const points = dataSource()
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
                        if (dashArray) path.setAttribute('stroke-dasharray', dashArray);
                        path.setAttribute('stroke-linecap', 'round');
                        path.setAttribute('stroke-linejoin', 'round');
                        path.setAttribute('opacity', '0.62');
                        path.setAttribute('vector-effect', 'non-scaling-stroke');
                        path.style.display = 'block';
                        path.style.filter = 'none';
                        indicatorOverlay.appendChild(path);
                    });

                    if (activeIndicators.has('bollinger')) {
                        const points = bollingerData().filter(point => {
                            const timestamp = new Date(point.x).getTime();
                            return timestamp >= timeRange.min && timestamp <= timeRange.max;
                        });
                        if (points.length >= 2) {
                            const upper = points.map(point => `${overlayX(new Date(point.x).getTime()).toFixed(2)},${overlayY(point.upper).toFixed(2)}`);
                            const lower = [...points].reverse().map(point => `${overlayX(new Date(point.x).getTime()).toFixed(2)},${overlayY(point.lower).toFixed(2)}`);
                            const band = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                            band.setAttribute('points', [...upper, ...lower].join(' '));
                            band.setAttribute('fill', '#a78bfa');
                            band.setAttribute('fill-opacity', '.14');
                            band.setAttribute('stroke', '#c4b5fd');
                            band.setAttribute('stroke-opacity', '.92');
                            band.setAttribute('stroke-width', '1.8');
                            band.setAttribute('vector-effect', 'non-scaling-stroke');
                            indicatorOverlay.appendChild(band);
                            const middle = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                            middle.setAttribute('d', points.map((point, index) => `${index ? 'L' : 'M'} ${overlayX(new Date(point.x).getTime()).toFixed(2)} ${overlayY(point.middle).toFixed(2)}`).join(' '));
                            middle.setAttribute('fill', 'none');
                            middle.setAttribute('stroke', '#fbbf24');
                            middle.setAttribute('stroke-opacity', '.92');
                            middle.setAttribute('stroke-dasharray', '4 4');
                            middle.setAttribute('stroke-width', '1.5');
                            indicatorOverlay.appendChild(middle);
                        }
                    }

                    if (activeIndicators.has('sar')) {
                        parabolicSarData().forEach(point => {
                            const timestamp = new Date(point.x).getTime();
                            if (timestamp < timeRange.min || timestamp > timeRange.max) return;
                            const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                            dot.setAttribute('cx', overlayX(timestamp).toFixed(2));
                            dot.setAttribute('cy', overlayY(point.y).toFixed(2));
                            dot.setAttribute('r', '1.8');
                            dot.setAttribute('fill', point.bullish ? '#22c55e' : '#ef4444');
                            dot.setAttribute('fill-opacity', '.82');
                            indicatorOverlay.appendChild(dot);
                        });
                    }
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

                    if (activeIndicators.has('support') && visibleCandles.length >= 7) {
                        const latestClose = Number(visibleCandles.at(-1)?.y?.[3]);
                        const lows = visibleCandles.map(candle => Number(candle?.y?.[2]));
                        const candidates = [];

                        for (let index = 2; index < lows.length - 2; index += 1) {
                            const low = lows[index];
                            if (!Number.isFinite(low) || (Number.isFinite(latestClose) && low > latestClose * 1.005)) continue;
                            if (low <= lows[index - 1] && low <= lows[index - 2]
                                && low <= lows[index + 1] && low <= lows[index + 2]) {
                                candidates.push({ price: low, index });
                            }
                        }

                        const tolerance = Math.max((priceRange.max - priceRange.min) * 0.012, (latestClose || 1) * 0.006);
                        const zones = [];
                        candidates.forEach(candidate => {
                            const zone = zones.find(item => Math.abs(item.price - candidate.price) <= tolerance);
                            if (zone) {
                                zone.points.push(candidate);
                                zone.price = zone.points.reduce((sum, point) => sum + point.price, 0) / zone.points.length;
                            } else {
                                zones.push({ price: candidate.price, points: [candidate] });
                            }
                        });

                        zones
                            .map(zone => ({
                                ...zone,
                                score: zone.points.length * 10 + Math.max(...zone.points.map(point => point.index)) / visibleCandles.length,
                            }))
                            .sort((leftZone, rightZone) => rightZone.score - leftZone.score)
                            .slice(0, 3)
                            .sort((leftZone, rightZone) => rightZone.price - leftZone.price)
                            .forEach((zone, index) => {
                                const y = toY(zone.price);
                                const color = index === 0 ? '#22d3ee' : '#06b6d4';
                                svg.appendChild(svgNode('line', {
                                    x1: left, x2: left + plotWidth, y1: y, y2: y,
                                    stroke: color, 'stroke-width': index === 0 ? '1.5' : '1',
                                    'stroke-dasharray': index === 0 ? '7 5' : '4 5',
                                    'stroke-opacity': index === 0 ? '.82' : '.55',
                                    'vector-effect': 'non-scaling-stroke',
                                }));
                                const label = svgNode('text', {
                                    x: left + plotWidth - 5, y: Math.max(top + 9, y - 4),
                                    fill: color, 'fill-opacity': index === 0 ? '.95' : '.72',
                                    'font-size': '8', 'font-weight': '800', 'text-anchor': 'end',
                                    'font-family': 'inherit',
                                });
                                label.textContent = `${@json(__('Unterstützung'))} ${zone.price.toFixed(2)} ${currency}`;
                                svg.appendChild(label);
                            });
                    }

                    if (activeIndicators.has('resistance') && visibleCandles.length >= 7) {
                        const latestClose = Number(visibleCandles.at(-1)?.y?.[3]);
                        const highs = visibleCandles.map(candle => Number(candle?.y?.[1]));
                        const candidates = [];

                        for (let index = 2; index < highs.length - 2; index += 1) {
                            const high = highs[index];
                            if (!Number.isFinite(high) || (Number.isFinite(latestClose) && high < latestClose * .995)) continue;
                            if (high >= highs[index - 1] && high >= highs[index - 2]
                                && high >= highs[index + 1] && high >= highs[index + 2]) {
                                candidates.push({ price: high, index });
                            }
                        }

                        const tolerance = Math.max((priceRange.max - priceRange.min) * .012, (latestClose || 1) * .006);
                        const zones = [];
                        candidates.forEach(candidate => {
                            const zone = zones.find(item => Math.abs(item.price - candidate.price) <= tolerance);
                            if (zone) {
                                zone.points.push(candidate);
                                zone.price = zone.points.reduce((sum, point) => sum + point.price, 0) / zone.points.length;
                            } else {
                                zones.push({ price: candidate.price, points: [candidate] });
                            }
                        });

                        zones
                            .map(zone => ({
                                ...zone,
                                score: zone.points.length * 10 + Math.max(...zone.points.map(point => point.index)) / visibleCandles.length,
                            }))
                            .sort((leftZone, rightZone) => rightZone.score - leftZone.score)
                            .slice(0, 3)
                            .sort((leftZone, rightZone) => leftZone.price - rightZone.price)
                            .forEach((zone, index) => {
                                const y = toY(zone.price);
                                const color = index === 0 ? '#fb923c' : '#f97316';
                                svg.appendChild(svgNode('line', {
                                    x1: left, x2: left + plotWidth, y1: y, y2: y,
                                    stroke: color, 'stroke-width': index === 0 ? '1.5' : '1',
                                    'stroke-dasharray': index === 0 ? '7 5' : '4 5',
                                    'stroke-opacity': index === 0 ? '.86' : '.58',
                                    'vector-effect': 'non-scaling-stroke',
                                }));
                                const label = svgNode('text', {
                                    x: left + plotWidth - 5, y: Math.max(top + 9, y - 4),
                                    fill: color, 'fill-opacity': index === 0 ? '.98' : '.75',
                                    'font-size': '8', 'font-weight': '800', 'text-anchor': 'end',
                                    'font-family': 'inherit',
                                });
                                label.textContent = `${@json(__('Widerstand'))} ${zone.price.toFixed(2)} ${currency}`;
                                svg.appendChild(label);
                            });
                    }

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

                    if (activeIndicators.has('patterns')) chartPatterns.forEach((pattern, index) => {
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
                    const fullscreenChart = (document.fullscreenElement || document.webkitFullscreenElement) === chartCard;
                    for (let index = 0; index < tickCount; index += 1) {
                        const timestamp = timeRange.min + (xSpan * index / (tickCount - 1));
                        const label = svgNode('text', {
                            x: toX(timestamp), y: height - 8,
                            fill: labelColor, 'font-size': '10', 'text-anchor': 'middle',
                            'font-family': 'inherit',
                        });
                        label.textContent = new Date(timestamp).toLocaleDateString([], fullscreenChart
                            ? { day: '2-digit', month: '2-digit', year: 'numeric' }
                            : { day: '2-digit', month: '2-digit' });
                        svg.appendChild(label);
                    }

                    element.replaceChildren(svg);
                    drawSmaLines();
                };

                const rerenderAllCharts = async () => {
                    renderMainChart();
                    if (activeIndicators.has('rsi') && rsiElement && !rsiChart) {
                        rsiChart = new window.ApexCharts(rsiElement, rsiOptions());
                        await rsiChart.render();
                        updateRsiValue();
                    } else if (activeIndicators.has('rsi') && rsiChart) {
                        await rsiChart.updateOptions(rsiOptions(), false, true);
                        updateRsiValue();
                    }
                    await renderSecondaryPanels();
                };

                const chartBounds = () => {
                    const first = new Date(currentCandles[0]?.x).getTime();
                    const origin = forecastOrigin();
                    const last = origin ? addTradingDays(new Date(origin.x).getTime(), 20) : new Date(currentCandles.at(-1)?.x).getTime();
                    return { min: first, max: last };
                };

                const clampZoomRange = (min, max) => {
                    const bounds = chartBounds();
                    const minimumSpan = 5 * 86400000;
                    let span = Math.max(minimumSpan, max - min);
                    span = Math.min(span, bounds.max - bounds.min);
                    let nextMin = min;
                    let nextMax = min + span;
                    if (nextMin < bounds.min) { nextMin = bounds.min; nextMax = bounds.min + span; }
                    if (nextMax > bounds.max) { nextMax = bounds.max; nextMin = bounds.max - span; }
                    return { min: nextMin, max: nextMax };
                };

                const syncZoomInteraction = () => {
                    element.style.cursor = canUseChartZoom && zoomInteractionActive ? 'crosshair' : 'default';
                    element.style.boxShadow = canUseChartZoom && zoomInteractionActive
                        ? 'inset 0 0 0 1px rgba(34, 211, 238, .55)'
                        : 'none';
                    element.removeAttribute('title');
                };
                syncZoomInteraction();
                element.addEventListener('wheel', event => {
                    if (!canUseChartZoom || !zoomInteractionActive) return;
                    event.preventDefault();
                    const range = chartTimeRange();
                    if (!Number.isFinite(range.min) || !Number.isFinite(range.max)) return;
                    const rect = element.getBoundingClientRect();
                    const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left - 18) / Math.max(1, rect.width - 104)));
                    const anchor = range.min + ((range.max - range.min) * ratio);
                    const factor = event.deltaY < 0 ? .82 : 1.22;
                    const nextMin = anchor - ((anchor - range.min) * factor);
                    const nextMax = anchor + ((range.max - anchor) * factor);
                    zoomTimeRange = clampZoomRange(nextMin, nextMax);
                    rerenderAllCharts();
                }, { passive: false });
                element.addEventListener('pointerdown', event => {
                    if (!canUseChartZoom) return;
                    if (event.button !== 0) return;
                    if (!zoomInteractionActive) {
                        zoomInteractionActive = true;
                        syncZoomInteraction();
                        return;
                    }
                    zoomDragStart = event.clientX;
                    if (zoomSelection) {
                        const rect = element.getBoundingClientRect();
                        const left = Math.max(18, Math.min(rect.width - 86, event.clientX - rect.left));
                        zoomSelection.style.left = `${left}px`;
                        zoomSelection.style.width = '0px';
                        zoomSelection.classList.remove('hidden');
                    }
                    element.setPointerCapture?.(event.pointerId);
                });
                element.addEventListener('pointermove', event => {
                    if (!Number.isFinite(zoomDragStart) || !zoomSelection) return;
                    const rect = element.getBoundingClientRect();
                    const start = Math.max(18, Math.min(rect.width - 86, zoomDragStart - rect.left));
                    const current = Math.max(18, Math.min(rect.width - 86, event.clientX - rect.left));
                    zoomSelection.style.left = `${Math.min(start, current)}px`;
                    zoomSelection.style.width = `${Math.abs(current - start)}px`;
                });
                element.addEventListener('pointerup', event => {
                    if (!Number.isFinite(zoomDragStart)) return;
                    const start = zoomDragStart;
                    zoomDragStart = null;
                    zoomSelection?.classList.add('hidden');
                    if (Math.abs(event.clientX - start) < 18) return;
                    const rect = element.getBoundingClientRect();
                    const range = chartTimeRange();
                    const toTime = clientX => range.min + Math.max(0, Math.min(1, (clientX - rect.left - 18) / Math.max(1, rect.width - 104))) * (range.max - range.min);
                    zoomTimeRange = clampZoomRange(Math.min(toTime(start), toTime(event.clientX)), Math.max(toTime(start), toTime(event.clientX)));
                    rerenderAllCharts();
                });
                element.addEventListener('pointercancel', () => {
                    zoomDragStart = null;
                    zoomSelection?.classList.add('hidden');
                });
                element.addEventListener('dblclick', () => {
                    if (!canUseChartZoom || !zoomInteractionActive) return;
                    zoomTimeRange = null;
                    rerenderAllCharts();
                });
                document.addEventListener('pointerdown', event => {
                    if (!zoomInteractionActive || element.contains(event.target)) return;
                    zoomInteractionActive = false;
                    zoomDragStart = null;
                    zoomSelection?.classList.add('hidden');
                    syncZoomInteraction();
                });

                const syncFullscreenButton = () => {
                    const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
                    const active = fullscreenElement === chartCard;
                    fullscreenButton?.setAttribute('aria-pressed', active ? 'true' : 'false');
                    fullscreenButton?.setAttribute('aria-label', active ? @json(__('Vollbild beenden')) : @json(__('Chart maximieren')));
                    fullscreenButton?.setAttribute('title', active ? @json(__('Vollbild beenden')) : @json(__('Chart maximieren')));
                    fullscreenButton?.querySelector('[data-fullscreen-open]')?.classList.toggle('hidden', active);
                    fullscreenButton?.querySelector('[data-fullscreen-close]')?.classList.toggle('hidden', !active);
                    window.setTimeout(() => rerenderAllCharts(), 80);
                };
                fullscreenButton?.addEventListener('click', async () => {
                    if (!canUseChartZoom) return;
                    const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
                    if (fullscreenElement === chartCard) {
                        if (document.exitFullscreen) await document.exitFullscreen();
                        else document.webkitExitFullscreen?.();
                        return;
                    }
                    if (chartCard?.requestFullscreen) await chartCard.requestFullscreen();
                    else chartCard?.webkitRequestFullscreen?.();
                });
                fullscreenCloseButton?.addEventListener('click', async () => {
                    if (document.exitFullscreen) await document.exitFullscreen();
                    else document.webkitExitFullscreen?.();
                });
                document.addEventListener('fullscreenchange', syncFullscreenButton);
                document.addEventListener('webkitfullscreenchange', syncFullscreenButton);

                renderMainChart();
                if (indicatorOverlay && window.ResizeObserver) {
                    new ResizeObserver(() => renderMainChart()).observe(element);
                }
                syncIndicatorUi();
                syncPeriodUi();
                renderSecondaryPanels();
                periodButtons.forEach(button => {
                    button.addEventListener('click', async () => {
                        selectedPeriod = Number(button.dataset.chartPeriod);
                        zoomTimeRange = null;
                        syncPeriodUi();
                        await rerenderAllCharts();
                    });
                });
                indicatorButtons.forEach(button => {
                    button.addEventListener('click', async () => {
                        if (!canUseChartIndicators) return;
                        const indicator = button.dataset.indicator;
                        if (activeIndicators.has(indicator)) {
                            activeIndicators.delete(indicator);
                        } else {
                            activeIndicators.add(indicator);
                        }

                        syncIndicatorUi();
                        await rerenderAllCharts();
                    });
                });
                chartResetButton?.addEventListener('click', async () => {
                    if (!canUseChartIndicators) return;
                    activeIndicators.clear();
                    selectedPeriod = 132;
                    zoomTimeRange = null;
                    syncIndicatorUi();
                    syncPeriodUi();
                    await rerenderAllCharts();
                });
                window.addEventListener('aktienki:theme-changed', async () => {
                    await rerenderAllCharts();
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
                const canViewRealtime = @json($canViewRealtime);
                let marketTimezone = @json($marketSession['timezone']);
                let lastLivePrice = null;
                const updateLiveClock = () => {
                    const timeElement = document.querySelector('[data-stock-live-time]');
                    if (!timeElement || !canViewRealtime) return;
                    try {
                        timeElement.textContent = new Date().toLocaleTimeString(document.documentElement.lang, {
                            hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: marketTimezone,
                        });
                    } catch (_) {
                        timeElement.textContent = new Date().toLocaleTimeString(document.documentElement.lang, {
                            hour: '2-digit', minute: '2-digit', second: '2-digit',
                        });
                    }
                };
                const setLiveQuoteTone = change => {
                    const card = document.querySelector('[data-stock-live-card]');
                    const priceElement = document.querySelector('[data-stock-live-price]');
                    const changeElement = document.querySelector('[data-stock-live-change]');
                    const metaElement = document.querySelector('[data-stock-live-meta]');
                    const dotElement = document.querySelector('[data-stock-live-dot]');
                    const positive = Number(change) > 0;
                    const negative = Number(change) < 0;
                    if (card) card.className = `rounded-xl border px-3 py-1.5 text-right transition-colors duration-300 ${
                        positive ? 'border-emerald-400/35 bg-emerald-400/10' : (negative ? 'border-rose-400/35 bg-rose-400/10' : 'border-cyan-500/25 bg-cyan-500/[.09]')
                    }`;
                    if (priceElement) priceElement.className = `whitespace-nowrap text-sm font-black tabular-nums transition-colors duration-300 ${positive ? 'text-emerald-400' : (negative ? 'text-rose-400' : 'text-cyan-400')}`;
                    if (changeElement) changeElement.className = `inline-flex min-w-24 flex-col items-center justify-center rounded-xl border px-2.5 py-1.5 transition-colors duration-300 ${
                        positive ? 'border-emerald-400/35 bg-emerald-400/12 text-emerald-400' : (negative ? 'border-rose-400/35 bg-rose-400/12 text-rose-400' : 'border-cyan-400/25 bg-cyan-400/10 text-cyan-400')
                    }`;
                    if (metaElement) metaElement.className = `mt-0.5 flex items-center justify-end gap-1 text-[7px] font-black uppercase tracking-wide transition-colors duration-300 ${positive ? 'text-emerald-400/80' : (negative ? 'text-rose-400/80' : 'text-cyan-400/75')}`;
                    if (dotElement) dotElement.className = `h-1.5 w-1.5 animate-pulse rounded-full ${positive ? 'bg-emerald-400 shadow-[0_0_5px_rgba(52,211,153,.55)]' : (negative ? 'bg-rose-400 shadow-[0_0_5px_rgba(251,113,133,.55)]' : 'bg-cyan-400 shadow-[0_0_5px_rgba(34,211,238,.55)]')}`;
                };
                const refreshTwelveDataQuote = async () => {
                    if (!canViewRealtime || document.visibilityState !== 'visible') return;
                    try {
                        const response = await fetch(liveQuoteUrl, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store',
                        });
                        if (!response.ok) return;
                        const quote = await response.json();
                        const statusElement = document.querySelector('[data-stock-live-status]');
                        if (quote.timezone) marketTimezone = quote.timezone;
                        if (quote.market_open === false) {
                            if (statusElement) statusElement.textContent = @json(__('Börse geschlossen'));
                            document.querySelector('[data-stock-live-dot]')?.classList.remove('animate-pulse');
                            return;
                        }
                        const price = Number(quote.price);
                        const timestamp = Number(quote.timestamp);
                        if (!Number.isFinite(price) || price <= 0) return;

                        const liveChange = Number.isFinite(Number(quote.change_percent))
                            ? Number(quote.change_percent)
                            : (Number.isFinite(lastLivePrice) && lastLivePrice !== 0 ? ((price - lastLivePrice) / lastLivePrice) * 100 : 0);
                        lastLivePrice = price;
                        setLiveQuoteTone(liveChange);
                        const changeValueElement = document.querySelector('[data-stock-live-change-value]');
                        if (changeValueElement) {
                            changeValueElement.textContent = `${liveChange > 0 ? '+' : ''}${liveChange.toLocaleString(document.documentElement.lang, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })} %`;
                        }

                        const priceElement = document.querySelector('[data-stock-live-price]');
                        const timeElement = document.querySelector('[data-stock-live-time]');
                        const currency = quote.currency || priceElement?.dataset.liveCurrency || '';
                        if (priceElement) {
                            priceElement.textContent = `${price.toLocaleString(document.documentElement.lang, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}${currency ? ` ${currency}` : ''}`;
                        }
                        updateLiveClock();
                        if (statusElement) statusElement.textContent = @json(__('TwelveData Realtime'));
                        window.dispatchEvent(new CustomEvent('aktienki:live-price', {
                            detail: { symbol: @js($instrument->symbol), price, timestamp },
                        }));
                    } catch (_) {
                        // Der zuletzt bekannte Kurs bleibt bei einem kurzzeitigen API-Fehler sichtbar.
                    }
                };
                let liveQuoteTimer = null;
                let liveClockTimer = null;
                if (canViewRealtime) {
                    updateLiveClock();
                    refreshTwelveDataQuote();
                    liveQuoteTimer = window.setInterval(refreshTwelveDataQuote, 2_000);
                    liveClockTimer = window.setInterval(updateLiveClock, 1_000);
                    window.addEventListener('pagehide', () => {
                        window.clearInterval(liveQuoteTimer);
                        window.clearInterval(liveClockTimer);
                    }, { once: true });
                }

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

@php
    $points = collect($chart['points'] ?? [])
        ->filter(fn (array $point): bool => is_numeric($point['timestamp'] ?? null) && is_numeric($point['close'] ?? null))
        ->sortBy('timestamp')
        ->values();
    $closes = $points->pluck('close')->map(fn ($value): float => (float) $value)->values();
    $latestPrice = $closes->isNotEmpty() ? (float) $closes->last() : null;
    $predictionPrice = $latestPrice !== null && is_numeric($expectedReturnPercent)
        ? $latestPrice * (1 + ((float) $expectedReturnPercent / 100))
        : null;
    $chartMin = $closes->isNotEmpty() ? (float) $closes->min() : 0.0;
    $chartMax = $closes->isNotEmpty() ? (float) $closes->max() : 1.0;
    if ($predictionPrice !== null) {
        $chartMin = min($chartMin, $predictionPrice);
        $chartMax = max($chartMax, $predictionPrice);
    }
    $baseRange = max($chartMax - $chartMin, 0.000001);
    $chartMin -= $baseRange * .16;
    $chartMax += $baseRange * .16;
    $chartRange = max($chartMax - $chartMin, 0.000001);
    $forecastStartX = 500.0;
    $chartPolyline = $closes->count() > 1
        ? $closes->map(fn (float $value, int $index): string => sprintf(
            '%.1f,%.1f',
            $index * $forecastStartX / ($closes->count() - 1),
            100 - (($value - $chartMin) / $chartRange) * 76,
        ))->implode(' ')
        : '';
    $latestY = $latestPrice !== null ? 100 - (($latestPrice - $chartMin) / $chartRange) * 76 : null;
    $predictionY = $predictionPrice !== null ? 100 - (($predictionPrice - $chartMin) / $chartRange) * 76 : null;
    $firstTimestamp = is_numeric($points->first()['timestamp'] ?? null) ? (int) $points->first()['timestamp'] : null;
    $lastTimestamp = is_numeric($points->last()['timestamp'] ?? null) ? (int) $points->last()['timestamp'] : null;
    $transitionTimestamp = filled($transitionAt) ? \Illuminate\Support\Carbon::parse($transitionAt)->timestamp : null;
    $transitionX = $firstTimestamp !== null && $lastTimestamp !== null && $lastTimestamp > $firstTimestamp
        && $transitionTimestamp !== null && $transitionTimestamp >= $firstTimestamp && $transitionTimestamp <= $lastTimestamp
            ? (($transitionTimestamp - $firstTimestamp) / ($lastTimestamp - $firstTimestamp)) * $forecastStartX
            : null;
    $predictionDate = filled($predictionAt) ? \Illuminate\Support\Carbon::parse($predictionAt)->format('d.m.Y') : null;
    $transitionDate = filled($transitionAt) ? \Illuminate\Support\Carbon::parse($transitionAt)->format('d.m.Y') : null;
    $gradientId = 'screener-serving-line-'.$instrumentId;
    $areaGradientId = 'screener-serving-area-'.$instrumentId;
@endphp

<div class="screener-chart-plus hidden lg:block" data-chart-plus data-chart-data-url="{{ route('stocks.chart-data', ['symbol' => $symbol]) }}" data-symbol="{{ $symbol }}" data-forecast-points='@json($forecastPoints)' data-forecast-at="{{ $predictionAt }}">
    <div class="screener-chart-plus-toolbar">
        <div><strong>{{ __('Chart+') }}</strong><small>{{ $symbol }} · {{ __('Technische Analyse') }}</small></div>
        <span class="screener-chart-periods">
            @foreach(['1m' => '1M', '3m' => '3M', '6m' => '6M', '1y' => '1J', 'all' => __('Max')] as $period => $label)
                <button type="button" data-chart-plus-period="{{ $period }}" class="{{ $period === '1m' ? 'is-active' : '' }}">{{ $label }}</button>
            @endforeach
        </span>
        <span class="screener-chart-indicators">
            <button type="button" data-chart-plus-indicator="sma20">SMA 20</button>
            <button type="button" data-chart-plus-indicator="sma50">SMA 50</button>
            <button type="button" data-chart-plus-indicator="rsi">RSI 14</button>
        </span>
        <a href="{{ route('stocks.show', $symbol) }}#stock-chart-card">{{ __('Vollbild') }} ↗</a>
    </div>
    <div data-chart-plus-canvas class="screener-chart-plus-canvas"><span>{{ __('Chart wird geladen …') }}</span></div>
    <div data-chart-plus-rsi class="screener-chart-plus-rsi" hidden></div>
</div>

<div class="lg:hidden" data-chart-cache-hit="{{ !empty($chart['cache_hit']) ? 'true' : 'false' }}" data-chart-cached-at="{{ $chart['cached_at'] ?? '' }}">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-1 text-[9px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">
        <span>{{ __('Chart · 1 Jahr') }} · {{ $chart['currency'] ?? '—' }}</span>
        <span class="flex gap-2">
            @if ($transitionDate)
                <span class="text-violet-300">│ {{ __('Signalwechsel') }} {{ $transitionDate }}</span>
            @endif
            @if ($predictionPrice !== null && $forecastHorizon !== null)
                <span class="text-amber-300">— {{ __('Prognose :days Tage', ['days' => $forecastHorizon]) }}</span>
            @endif
        </span>
    </div>

    @if ($chartPolyline !== '')
        <svg viewBox="0 0 600 128" class="h-24 w-full" role="img" aria-label="{{ __('Kursverlauf des letzten Jahres mit Prognose') }}" preserveAspectRatio="none">
            <defs>
                <linearGradient id="{{ $gradientId }}" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#2563eb"/><stop offset="1" stop-color="#0d9488"/></linearGradient>
                <linearGradient id="{{ $areaGradientId }}" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0" stop-color="#0ea5e9" stop-opacity=".30"/>
                    <stop offset=".48" stop-color="#0891b2" stop-opacity=".12"/>
                    <stop offset="1" stop-color="#0f766e" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path d="M0 108H600" stroke="#0d9488" stroke-opacity=".28" stroke-width="1.4"/>
            @foreach([0, 125, 250, 375, 500] as $tickX)
                <line x1="{{ $tickX }}" y1="108" x2="{{ $tickX }}" y2="112" stroke="#0d9488" stroke-opacity=".28" stroke-width="1"/>
            @endforeach
            <g fill="#94a3b8" font-size="7" font-weight="700">
                <text x="0" y="124" text-anchor="start">−1J</text><text x="125" y="124" text-anchor="middle">−9M</text><text x="250" y="124" text-anchor="middle">−6M</text><text x="375" y="124" text-anchor="middle">−3M</text><text x="500" y="124" text-anchor="middle">{{ __('Heute') }}</text><text x="600" y="124" text-anchor="end">{{ $forecastHorizon ?? 20 }}T</text>
            </g>
            @if ($transitionX !== null)
                <line x1="{{ number_format($transitionX, 1, '.', '') }}" y1="4" x2="{{ number_format($transitionX, 1, '.', '') }}" y2="108" stroke="#c084fc" stroke-width="1.5" stroke-dasharray="4 4">
                    <title>{{ __('Signalwechsel') }} {{ $transitionFrom }} → {{ $signal }} · {{ $transitionDate }}</title>
                </line>
            @endif
            <polygon points="{{ $chartPolyline }} 500,108 0,108" fill="url(#{{ $areaGradientId }})"/>
            <polyline points="{{ $chartPolyline }}" fill="none" stroke="url(#{{ $gradientId }})" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            @if ($predictionY !== null && $latestY !== null)
                <line x1="500" y1="4" x2="500" y2="108" stroke="#fbbf24" stroke-opacity=".85" stroke-width="1.5" stroke-dasharray="4 4"><title>{{ __('Signaldatum') }} {{ $predictionDate ?: '—' }}</title></line>
                <text x="494" y="11" text-anchor="end" fill="#fbbf24" font-size="7" font-weight="800">{{ __('Signal') }} {{ $predictionDate }}</text>
                <line x1="500" y1="{{ number_format($latestY, 1, '.', '') }}" x2="600" y2="{{ number_format($predictionY, 1, '.', '') }}" stroke="#fbbf24" stroke-width="2.5" stroke-dasharray="7 5"/>
                <circle cx="500" cy="{{ number_format($latestY, 1, '.', '') }}" r="2.5" fill="#fbbf24"/><circle cx="600" cy="{{ number_format($predictionY, 1, '.', '') }}" r="3" fill="#fbbf24"/>
            @endif
        </svg>
    @else
        <div class="flex h-24 items-center justify-center px-4 text-center text-xs italic text-[var(--ak-muted)]">
            {{ $restrictionReason ?: __('Der Kurschart ist momentan nicht verfügbar.') }}
        </div>
    @endif
</div>

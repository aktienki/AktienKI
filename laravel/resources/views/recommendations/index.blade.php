<x-app-layout>
    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300 shadow-[0_0_22px_rgba(245,158,11,.08)]">
                    <x-heroicon-o-sparkles class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-700">{{ __('Datenbasierte Auswahl') }}</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight">{{ __('Top-Empfehlungen') }}</h1>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Die drei aktuell stärksten Aktien aus KI-Score, Konfidenz, Risiko und Renditepotenzial.') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-[9px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">
                <span><b class="text-teal-700">40 %</b> {{ __('KI-Score') }}</span>
                <span><b class="text-teal-700">25 %</b> {{ __('Konfidenz') }}</span>
                <span><b class="text-teal-700">20 %</b> {{ __('Risiko') }}</span>
                <span><b class="text-teal-700">15 %</b> {{ __('Rendite') }}</span>
            </div>
        </div>

        @php
            $countryFlags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'CH' => '🇨🇭', 'NL' => '🇳🇱', 'AU' => '🇦🇺', 'CA' => '🇨🇦', 'BR' => '🇧🇷', 'ZA' => '🇿🇦'];
            $hasFilters = $country !== '' || $sector !== '' || $exchangeId > 0;
        @endphp

        <form method="GET" action="{{ route('recommendations.index') }}" class="mb-4 grid shrink-0 gap-2 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)] sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
            <label class="relative">
                <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Land') }}</span>
                <select name="country" onchange="this.form.submit()" class="ak-input h-11 w-full pb-1 pt-4 text-xs font-bold">
                    <option value="">{{ __('Alle Länder') }}</option>
                    @foreach ($countries as $option)
                        <option value="{{ $option }}" @selected($country === $option)>{{ $countryFlags[$option] ?? '🌐' }} {{ $option }}</option>
                    @endforeach
                </select>
            </label>

            <label class="relative">
                <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Börsenplatz') }}</span>
                <select name="exchange" onchange="this.form.submit()" class="ak-input h-11 w-full pb-1 pt-4 text-xs font-bold">
                    <option value="">{{ __('Alle Börsenplätze') }}</option>
                    @foreach ($exchanges as $option)
                        <option value="{{ $option->id }}" @selected($exchangeId === (int) $option->id)>{{ $countryFlags[$option->country] ?? '🌐' }} {{ $option->name }} · {{ $option->code }} ({{ $option->stocks_count }})</option>
                    @endforeach
                </select>
            </label>

            <label class="relative sm:col-span-2 lg:col-span-1">
                <span class="pointer-events-none absolute left-3 top-1.5 z-10 text-[8px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)]">{{ __('Sektor') }}</span>
                <select name="sector" onchange="this.form.submit()" class="ak-input h-11 w-full pb-1 pt-4 text-xs font-bold">
                    <option value="">{{ __('Alle Sektoren') }}</option>
                    @foreach ($sectors as $option)
                        <option value="{{ $option }}" @selected($sector === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>

            <a href="{{ route('recommendations.index') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700 {{ $hasFilters ? '' : 'pointer-events-none opacity-40' }}">
                <x-heroicon-o-arrow-path class="h-4 w-4" />{{ __('Zurücksetzen') }}
            </a>
        </form>

        <section class="grid min-h-0 flex-1 gap-4 lg:grid-cols-3">
            @forelse ($recommendations as $recommendation)
                @php
                    $rank = $loop->iteration;
                    $signal = strtoupper((string) ($recommendation->personalized_signal ?: 'HOLD'));
                    $signalClass = match ($signal) {
                        'BUY' => 'border-[#2b8f7b] bg-[#197864] text-white',
                        'WATCH' => 'border-[#789545] bg-[#657f39] text-white',
                        'SELL' => 'border-[#bd5b6c] bg-[#a94759] text-white',
                        default => 'border-[#bd8737] bg-[#a97429] text-white',
                    };
                    $rankClass = match ($rank) {
                        1 => 'border-amber-300/45 bg-amber-300/15 text-amber-300',
                        2 => 'border-slate-300/30 bg-slate-300/10 text-slate-300',
                        default => 'border-orange-300/30 bg-orange-300/10 text-orange-300',
                    };
                    $recommendationWatchlistIds = $watchlistMemberships->get((int) $recommendation->instrument_id, collect());
                    $isWatched = $recommendationWatchlistIds->isNotEmpty();
                @endphp

                <article class="group flex min-h-0 flex-col overflow-hidden rounded-2xl border {{ $rank === 1 ? 'border-amber-400/35' : 'border-[var(--ak-border)]' }} bg-[var(--ak-card)] shadow-[var(--ak-shadow)] transition hover:-translate-y-0.5 hover:border-teal-500/35">
                    <div class="flex items-start justify-between gap-3 border-b border-[var(--ak-border)] p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="relative flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[var(--ak-border)] bg-white">
                                <span class="text-[10px] font-black text-teal-700">{{ strtoupper(substr($recommendation->symbol, 0, 2)) }}</span>
                                <img src="{{ route('stocks.icon', $recommendation->instrument_id) }}" alt="" class="absolute inset-1 h-10 w-10 object-contain" loading="eager" onerror="this.remove()">
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="truncate text-xl font-black text-teal-700">{{ $recommendation->symbol }}</h2>
                                    <span class="inline-flex h-7 items-center rounded-md border px-2 text-[9px] font-black {{ $signalClass }}">{{ $signal }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs font-bold text-[var(--ak-text)]">{{ $recommendation->name }}</p>
                                <p class="mt-1 truncate text-[10px] text-[var(--ak-muted)]">{{ $recommendation->country ?: '—' }} · {{ $recommendation->sector ?: '—' }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($userWatchlists->count() === 1)
                                @php $singleWatchlist = $userWatchlists->first(); @endphp
                                <form method="POST" action="{{ route('watchlists.items.toggle', [$singleWatchlist->id, $recommendation->instrument_id]) }}">
                                    @csrf
                                    <input type="hidden" name="prediction_id" value="{{ $recommendation->prediction_id }}">
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] transition hover:border-amber-400/35 hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-400' : 'text-[var(--ak-muted)] hover:text-amber-400' }}" title="{{ $isWatched ? __('Aus Watchlist entfernen') : __('Zur Watchlist hinzufügen') }}">
                                        @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                    </button>
                                </form>
                            @elseif ($userWatchlists->count() > 1)
                                <div x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] transition hover:border-amber-400/35 hover:bg-amber-300/10 {{ $isWatched ? 'text-amber-400' : 'text-[var(--ak-muted)] hover:text-amber-400' }}" title="{{ __('Watchlist auswählen') }}">
                                        @if ($isWatched)<x-heroicon-s-star class="h-5 w-5" />@else<x-heroicon-o-star class="h-5 w-5" />@endif
                                    </button>
                                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-11 z-40 w-52 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-2 shadow-2xl">
                                        <p class="px-2 pb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Watchlist auswählen') }}</p>
                                        @foreach ($userWatchlists as $watchlist)
                                            @php $isInWatchlist = $recommendationWatchlistIds->contains((int) $watchlist->id); @endphp
                                            <form method="POST" action="{{ route('watchlists.items.toggle', [$watchlist->id, $recommendation->instrument_id]) }}">
                                                @csrf
                                                <input type="hidden" name="prediction_id" value="{{ $recommendation->prediction_id }}">
                                                <button type="submit" class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-2 text-left text-xs font-bold text-[var(--ak-text)] transition hover:bg-teal-500/10">
                                                    <span class="truncate">{{ $watchlist->name }}</span>
                                                    @if ($isInWatchlist)
                                                        <x-heroicon-s-star class="h-4 w-4 shrink-0 text-amber-400" />
                                                    @else
                                                        <x-heroicon-o-plus class="h-4 w-4 shrink-0 text-[var(--ak-muted)]" />
                                                    @endif
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a href="{{ route('watchlists.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)] transition hover:border-amber-400/35 hover:bg-amber-300/10 hover:text-amber-400" title="{{ __('Zuerst Watchlist erstellen') }}">
                                    <x-heroicon-o-star class="h-5 w-5" />
                                </a>
                            @endif
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl border text-sm font-black {{ $rankClass }}">#{{ $rank }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-4">
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] p-3">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Gesamtwertung') }}</p>
                            <p class="mt-1 text-2xl font-black tabular-nums text-teal-700">{{ number_format($recommendation->recommendation_score, 1, ',', '.') }}<span class="ml-1 text-xs text-[var(--ak-muted)]">/100</span></p>
                        </div>
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] p-3 text-right">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Rendite 20 Tage') }}</p>
                            <p class="mt-1 text-xl font-black tabular-nums {{ $recommendation->expected_return_20d >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $recommendation->expected_return_20d >= 0 ? '+' : '' }}{{ number_format($recommendation->expected_return_20d, 2, ',', '.') }} %</p>
                        </div>
                    </div>

                    <div class="mx-4 mb-4 overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]">
                        <div class="flex items-center justify-between px-3 pt-2">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kursverlauf · 32 Tage') }}</p>
                            <span class="text-[9px] font-bold text-teal-700">{{ __('Tageskerzen') }}</span>
                        </div>
                        <div id="recommendation-chart-{{ $recommendation->instrument_id }}" class="relative h-[118px] w-full" aria-label="{{ __('Kurschart für :symbol', ['symbol' => $recommendation->symbol]) }}">
                            <span data-chart-placeholder class="absolute inset-0 flex items-center justify-center gap-2 text-[9px] font-bold text-[var(--ak-muted)]">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-teal-500/25 border-t-teal-600"></span>
                                {{ __('Kursdaten werden geladen') }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-2.5 px-4 pb-4">
                        <div><div class="mb-1.5 flex justify-between text-[10px] font-bold"><span class="text-[var(--ak-muted)]">{{ __('KI-Score') }}</span><span>{{ number_format($recommendation->score_10, 1, ',', '.') }} / 10</span></div><div class="h-1.5 rounded-full bg-[var(--ak-surface-muted)]"><div class="h-full rounded-full bg-teal-600" style="width: {{ $recommendation->score_percent }}%"></div></div></div>
                        <div><div class="mb-1.5 flex justify-between text-[10px] font-bold"><span class="text-[var(--ak-muted)]">{{ __('Konfidenz') }}</span><span>{{ number_format($recommendation->confidence_percent, 0, ',', '.') }} %</span></div><div class="h-1.5 rounded-full bg-[var(--ak-surface-muted)]"><div class="h-full rounded-full bg-sky-600/75" style="width: {{ $recommendation->confidence_percent }}%"></div></div></div>
                        <div><div class="mb-1.5 flex justify-between text-[10px] font-bold"><span class="text-[var(--ak-muted)]">{{ __('Risiko') }}</span><span>{{ number_format($recommendation->risk_percent, 0, ',', '.') }} %</span></div><div class="h-1.5 rounded-full bg-[var(--ak-surface-muted)]"><div class="h-full rounded-full bg-amber-500/75" style="width: {{ $recommendation->risk_percent }}%"></div></div></div>
                    </div>

                    <div class="mt-auto flex items-center justify-between border-t border-[var(--ak-border)] px-4 py-3">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Aktueller Kurs') }}</p>
                            <p class="mt-0.5 text-sm font-black tabular-nums">{{ number_format($recommendation->current_price, 2, ',', '.') }} {{ $recommendation->currency ?: 'EUR' }}</p>
                        </div>
                        <a href="{{ route('stocks.show', ['symbol' => $recommendation->symbol, 'prediction' => $recommendation->prediction_id, 'return_to' => request()->getRequestUri()]) }}" class="inline-flex h-9 items-center gap-2 rounded-xl bg-teal-700 px-3 text-xs font-black text-white transition hover:bg-teal-600">
                            {{ __('Details') }}<x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                    </div>
                </article>
            @empty
                <div class="col-span-full grid place-items-center rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-10 text-center">
                    <div>
                        <x-heroicon-o-sparkles class="mx-auto h-10 w-10 text-[var(--ak-muted)]" />
                        <h2 class="mt-4 font-black">{{ __('Noch keine Empfehlungen verfügbar') }}</h2>
                        <p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Sobald vollständige Prognosen vorliegen, erscheinen hier die drei bestbewerteten Aktien.') }}</p>
                    </div>
                </div>
            @endforelse
        </section>

        <p class="mt-3 shrink-0 text-center text-[10px] text-[var(--ak-muted)]">{{ __('Die Bewertung dient der Recherche und stellt keine Anlageberatung dar.') }}</p>
    </div>

    @php
        $recommendationCharts = $recommendations->mapWithKeys(function ($recommendation) {
            return [
                (string) $recommendation->instrument_id => [
                    'symbol' => $recommendation->symbol,
                    'currency' => $recommendation->currency ?: 'EUR',
                    'candles' => $recommendation->candles,
                    'forecast_return' => $recommendation->expected_return_20d,
                    'data_url' => route('stocks.chart-data', ['symbol' => $recommendation->symbol]),
                ],
            ];
        })->all();
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.ApexCharts) return;

            const charts = @json($recommendationCharts);
            const light = document.documentElement.dataset.theme === 'light';

            Object.entries(charts).forEach(async ([instrumentId, stock]) => {
                const element = document.querySelector(`#recommendation-chart-${instrumentId}`);
                if (!element) return;

                let candles = Array.isArray(stock.candles) ? stock.candles : [];
                if (candles.length < 10 && stock.data_url) {
                    try {
                        const response = await fetch(stock.data_url, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (response.ok) {
                            const payload = await response.json();
                            candles = Array.isArray(payload.candles) ? payload.candles : candles;
                        }
                    } catch (error) {
                        console.warn(`Chartdaten für ${stock.symbol} konnten nicht geladen werden.`, error);
                    }
                }

                const placeholder = element.querySelector('[data-chart-placeholder]');
                if (candles.length === 0) {
                    if (placeholder) {
                        placeholder.innerHTML = @json(__('Keine Kursdaten verfügbar'));
                    }
                    return;
                }
                placeholder?.remove();

                const addTradingDays = (timestamp, tradingDays) => {
                    const target = new Date(timestamp);
                    let remaining = tradingDays;

                    while (remaining > 0) {
                        target.setDate(target.getDate() + 1);
                        if (target.getDay() !== 0 && target.getDay() !== 6) remaining -= 1;
                    }

                    return target.getTime();
                };
                const lastCandle = candles[candles.length - 1];
                const lastTimestamp = Number(lastCandle?.x);
                const lastClose = Number(lastCandle?.y?.[3]);
                const forecastReturn = Number(stock.forecast_return);
                const forecastTarget = Number.isFinite(lastClose) && Number.isFinite(forecastReturn)
                    ? lastClose * (1 + (forecastReturn / 100))
                    : null;
                const targetTimestamp = Number.isFinite(lastTimestamp) ? addTradingDays(lastTimestamp, 20) : null;
                const positiveForecast = Number.isFinite(forecastTarget) && forecastTarget >= lastClose;
                const forecastColor = positiveForecast ? '#14b8a6' : '#e56b75';
                const series = [{
                    name: stock.symbol,
                    type: 'candlestick',
                    data: candles,
                }];

                if (Number.isFinite(forecastTarget) && Number.isFinite(targetTimestamp)) {
                    series.push({
                        name: @json(__('Prognosebereich')),
                        type: 'rangeArea',
                        data: [
                            { x: lastTimestamp, y: [lastClose, lastClose] },
                            { x: targetTimestamp, y: [Math.min(lastClose, forecastTarget), Math.max(lastClose, forecastTarget)] },
                        ],
                    });
                    series.push({
                        name: @json(__('Aktueller Kurs')),
                        type: 'line',
                        data: [
                            { x: lastTimestamp, y: lastClose },
                            { x: targetTimestamp, y: lastClose },
                        ],
                    });
                    series.push({
                        name: @json(__('20-Tage-Ausblick')),
                        type: 'line',
                        data: [
                            { x: lastTimestamp, y: lastClose },
                            { x: targetTimestamp, y: forecastTarget },
                        ],
                    });
                }

                const values = candles
                    .flatMap(candle => Array.isArray(candle.y) ? candle.y.map(Number) : [])
                    .filter(Number.isFinite);
                if (Number.isFinite(forecastTarget)) values.push(forecastTarget);
                const minimum = Math.min(...values);
                const maximum = Math.max(...values);
                const padding = Math.max((maximum - minimum) * 0.08, maximum * 0.005);

                const miniChart = new ApexCharts(element, {
                    chart: {
                        type: 'line',
                        height: 118,
                        background: 'transparent',
                        toolbar: { show: false },
                        zoom: { enabled: false },
                        animations: { enabled: true, speed: 300 },
                        parentHeightOffset: 0,
                        sparkline: { enabled: false },
                    },
                    series,
                    colors: series.map(item => item.type === 'candlestick' ? '#14b8a6' : forecastColor),
                    plotOptions: {
                        candlestick: {
                            colors: { upward: '#14b8a6', downward: '#e56b75' },
                            wick: { useFillColor: true },
                        },
                    },
                    stroke: {
                        width: series.map(item => item.type === 'candlestick'
                            ? 1
                            : (item.type === 'rangeArea'
                                ? 0
                                : (item.name === @json(__('20-Tage-Ausblick')) ? 1.75 : 1.25))),
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
                        strokeColors: forecastColor,
                        colors: [forecastColor],
                    },
                    dataLabels: { enabled: false },
                    grid: {
                        borderColor: light ? 'rgba(51,65,85,.11)' : 'rgba(148,163,184,.10)',
                        strokeDashArray: 4,
                        padding: { left: 2, right: 2, top: -8, bottom: -8 },
                    },
                    xaxis: {
                        type: 'datetime',
                        labels: { show: false },
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        tooltip: { enabled: false },
                    },
                    yaxis: {
                        opposite: true,
                        min: minimum - padding,
                        max: maximum + padding,
                        forceNiceScale: false,
                        decimalsInFloat: 2,
                        tickAmount: 3,
                        labels: {
                            minWidth: 42,
                            maxWidth: 50,
                            formatter: value => Number(value).toFixed(2),
                            style: { colors: [light ? '#64748b' : '#94a3b8'], fontSize: '8px' },
                        },
                    },
                    tooltip: {
                        theme: light ? 'light' : 'dark',
                        x: { format: 'dd.MM.yyyy' },
                    },
                    theme: { mode: light ? 'light' : 'dark' },
                });

                miniChart.render().then(() => {
                    const linePaths = element.querySelectorAll('path.apexcharts-line');
                    if (linePaths.length < 2) return;

                    [
                        [linePaths[linePaths.length - 2], '1.25'],
                        [linePaths[linePaths.length - 1], '1.75'],
                    ].forEach(([path, width]) => {
                        path.setAttribute('stroke', forecastColor);
                        path.setAttribute('stroke-width', width);
                        path.setAttribute('stroke-dasharray', '2 6');
                        path.setAttribute('stroke-linecap', 'round');
                        path.setAttribute('stroke-opacity', '0.58');
                        path.style.stroke = forecastColor;
                        path.style.strokeWidth = width;
                        path.style.strokeDasharray = '2 6';
                        path.style.strokeOpacity = '0.58';
                    });
                });
            });
        });
    </script>
</x-app-layout>

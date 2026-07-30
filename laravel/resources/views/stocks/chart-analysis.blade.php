@extends('layouts.aktienki')

@section('content')
    <style>
        .aki-indicator-card {
            height: 278px;
        }
        .aki-indicator-card__header {
            height: 52px;
        }
        .aki-indicator-card__chart {
            height: 226px;
            min-height: 226px;
        }
        @media (min-width: 1024px) {
            .aki-indicator-grid {
                --aki-card-height: clamp(270px, 30vh, 320px);
                height: auto;
                grid-template-rows: repeat(2, var(--aki-card-height));
                align-content: start;
            }
            .aki-indicator-card {
                height: var(--aki-card-height);
                max-height: 320px;
                min-height: 0;
                aspect-ratio: auto;
            }
            .aki-indicator-card__chart {
                height: calc(100% - 52px);
                min-height: 218px;
            }
        }
    </style>

    @php
        $countryFlags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'CH' => '🇨🇭', 'NL' => '🇳🇱', 'AU' => '🇦🇺', 'CA' => '🇨🇦'];
        $dataPointCount = $indicatorCards->max(fn (array $card): int => count($card['points'])) ?? 0;
        $overallProbability = $indicatorCards
            ->pluck('currentProbability')
            ->filter(fn ($value) => is_numeric($value))
            ->avg();
    @endphp

    <div class="mx-auto flex h-[calc(100dvh-89px)] min-h-0 w-full max-w-screen-2xl flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-3 flex shrink-0 flex-col gap-3 border-b border-[var(--ak-border)] pb-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <span class="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-teal-500/25 bg-teal-500/10 text-sm font-black text-teal-600">
                    {{ strtoupper(substr($instrument->symbol, 0, 2)) }}
                    <img src="{{ route('stocks.icon', $instrument->id) }}" alt="" class="absolute inset-2 h-10 w-10 object-contain" onerror="this.remove()">
                </span>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-600">{{ __('Indikatoren und 20-Tage-Wahrscheinlichkeit') }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-2xl font-black">{{ $instrument->name }}</h1>
                        <span class="rounded-lg border border-amber-400/25 bg-amber-400/10 px-2 py-1 text-xs font-black text-amber-500">{{ $instrument->symbol }}</span>
                    </div>
                    <div class="mt-1.5 flex max-w-xl items-center gap-3">
                        <span class="shrink-0 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                            {{ number_format($dataPointCount, 0, ',', '.') }} {{ __('Datenpunkte') }}
                        </span>
                        <div class="w-48 min-w-0 sm:w-64">
                            <x-dashboard.score-stripes :percent="$overallProbability ?? 0" />
                        </div>
                        <span class="shrink-0 text-[10px] font-black tabular-nums {{ ($overallProbability ?? 0) >= 50 ? 'text-emerald-500' : 'text-rose-500' }}">
                            {{ $overallProbability !== null ? number_format($overallProbability, 1, ',', '.').' %' : '—' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs font-bold text-[var(--ak-muted)]">
                        {{ $countryFlags[$instrument->country] ?? '🌐' }} {{ $instrument->country ?: '—' }}
                        · {{ $exchange?->code ?: __('Keine Exchange') }}
                        · <span class="inline-flex items-center gap-1"><x-sector-icon :sector="$instrument->sector" class="h-3.5 w-3.5 text-teal-500" />{{ $instrument->sector ?: '—' }}</span>
                    </p>
                </div>
            </div>
            <a href="{{ route('stocks.show', $instrument->symbol) }}" class="inline-flex h-10 items-center gap-2 self-start rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:text-teal-600 lg:self-auto">
                <x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zur Aktiendetailseite') }}
            </a>
        </header>

        <div class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-2">
            <p class="max-w-3xl text-xs leading-5 text-[var(--ak-muted)]">
                {{ __('Die geglättete Fläche zeigt, wie häufig der Kurs bei vergleichbaren historischen Indikatorwerten 20 Handelstage später gestiegen ist.') }}
            </p>
            <div class="flex items-center gap-3 text-[9px] font-black uppercase text-[var(--ak-muted)]">
                <span class="inline-flex items-center gap-1.5"><i class="h-2 w-8 rounded-full bg-gradient-to-r from-rose-500 via-amber-400 to-emerald-500"></i>{{ __('20-Tage-Steigwahrscheinlichkeit') }}</span>
                <span class="inline-flex items-center gap-1.5"><i class="h-3 w-0.5 bg-amber-500"></i>{{ __('Aktueller Wert') }}</span>
            </div>
        </div>

        <main class="min-h-0 flex-1 overflow-y-auto pr-1">
            <div class="aki-indicator-grid grid min-h-0 auto-rows-fr gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($indicatorCards as $index => $card)
                    <article class="aki-indicator-card flex flex-col overflow-hidden rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                        <div class="aki-indicator-card__header flex shrink-0 items-center justify-between gap-2 border-b border-[var(--ak-border)] px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-black">{{ $card['label'] }}</p>
                                <p class="mt-0.5 truncate text-[8px] font-bold text-[var(--ak-muted)]">{{ __('Histogramm aus realisierten 20-Tage-Fällen') }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="flex items-center justify-end gap-1.5 text-sm font-black tabular-nums text-amber-500">
                                    <span>{{ is_numeric($card['currentValue']) ? number_format($card['currentValue'], 2, ',', '.').' '.$card['unit'] : '—' }}</span>
                                    @if (is_numeric($card['fiveDayChange']))
                                        <span
                                            class="inline-flex h-5 min-w-[4.4rem] items-center justify-center gap-1 rounded-md border px-1.5 text-[8px] font-black
                                                {{ $card['fiveDayDirection'] === 'up'
                                                    ? 'border-emerald-400/35 bg-emerald-400/12 text-emerald-400'
                                                    : ($card['fiveDayDirection'] === 'down'
                                                        ? 'border-rose-400/35 bg-rose-400/12 text-rose-400'
                                                        : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]') }}"
                                            title="{{ __('Veränderung des Indikatorwertes gegenüber vor fünf Handelstagen') }}"
                                        >
                                            <span>5T</span>
                                            @if ($card['fiveDayDirection'] === 'up')<span>↑</span>
                                            @elseif ($card['fiveDayDirection'] === 'down')<span>↓</span>
                                            @else<span>→</span>@endif
                                            <span>{{ ($card['fiveDayChange'] > 0 ? '+' : '').number_format($card['fiveDayChange'], 2, ',', '.') }}{{ $card['unit'] }}</span>
                                        </span>
                                    @endif
                                </p>
                                @if (is_numeric($card['currentProbability']))
                                    <p class="text-[8px] font-black">
                                        <span class="text-emerald-500">{{ number_format($card['currentProbability'], 1, ',', '.') }} % ↑</span>
                                        <span class="mx-0.5 text-[var(--ak-muted)]">·</span>
                                        <span class="text-rose-500">{{ number_format($card['currentFallProbability'], 1, ',', '.') }} % ↓</span>
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div id="indicator-probability-chart-{{ $index }}" class="aki-indicator-card__chart shrink-0"></div>
                    </article>
                @endforeach
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.ApexCharts) return;
            const indicatorCards = @json($indicatorCards);

            indicatorCards.forEach((card, index) => {
                const element = document.querySelector(`#indicator-probability-chart-${index}`);
                if (!element || !card.points?.length) return;
                const sorted = [...card.points].sort((a, b) => a.x - b.x);
                const binSize = Math.max(8, Math.ceil(sorted.length / 24));
                const histogram = [];
                for (let start = 0; start < sorted.length; start += binSize) {
                    const sample = sorted.slice(start, start + binSize);
                    if (!sample.length) continue;
                    const probability = sample.filter(point => point.up).length / sample.length * 100;
                    histogram.push({
                        x: sample.reduce((sum, point) => sum + point.x, 0) / sample.length,
                        y: probability,
                        samples: sample.length,
                        fillColor: probability > 55
                            ? '#22c58b'
                            : (probability < 45 ? '#e35f72' : '#e5b643'),
                    });
                }
                element.__aktienkiChart?.destroy?.();
                element.replaceChildren();
                const indicatorChart = new ApexCharts(element, {
                    series: [
                        { name: '{{ __('20-Tage-Steigwahrscheinlichkeit') }}', data: histogram },
                    ],
                    chart: {
                        type: 'bar',
                        height: Math.max(190, element.clientHeight - 16),
                        background: 'transparent',
                        animations: { enabled: false },
                        toolbar: { show: false },
                        zoom: { enabled: false },
                        selection: { enabled: false },
                        parentHeightOffset: 0,
                        redrawOnParentResize: true,
                    },
                    colors: ['#e5b643'],
                    plotOptions: {
                        bar: {
                            columnWidth: '82%',
                            borderRadius: 2,
                            borderRadiusApplication: 'end',
                        },
                    },
                    stroke: { width: 0 },
                    fill: {
                        type: 'solid',
                        opacity: 0.78,
                    },
                    dataLabels: { enabled: false },
                    grid: { borderColor: 'rgba(100,116,139,.14)', strokeDashArray: 3, padding: { top: 6, right: 10, bottom: 8, left: 6 } },
                    xaxis: {
                        type: 'numeric',
                        tickAmount: 6,
                        title: { text: `${card.label}${card.unit ? ` (${card.unit})` : ''}`, style: { color: '#82909f', fontSize: '9px' } },
                        labels: { formatter: value => Number(value).toFixed(2), style: { colors: '#82909f', fontSize: '9px' } },
                        axisBorder: { show: true, color: 'rgba(148,163,184,.48)', height: 1 },
                        axisTicks: { show: true, color: 'rgba(148,163,184,.38)', height: 4 },
                    },
                    yaxis: {
                        min: 0,
                        max: 100,
                        tickAmount: 5,
                        title: { text: '{{ __('20-Tage-Steigwahrscheinlichkeit') }}', style: { color: '#82909f', fontSize: '9px' } },
                        labels: { formatter: value => `${Math.round(value)} %`, style: { colors: '#82909f', fontSize: '9px' } },
                    },
                    legend: {
                        show: false,
                        position: 'top',
                        horizontalAlign: 'left',
                        fontSize: '9px',
                        labels: { colors: '#82909f' },
                        markers: { size: 4 },
                    },
                    tooltip: {
                        shared: true,
                        intersect: false,
                        x: { formatter: value => `${card.label}: ${Number(value).toFixed(2)} ${card.unit || ''}` },
                        y: { formatter: value => `${value?.toFixed(1)} % {{ __('Steigwahrscheinlichkeit') }}` },
                    },
                    annotations: {
                        yaxis: [{
                            y: 50,
                            borderColor: '#94a3b8',
                            strokeDashArray: 5,
                            label: {
                                text: '{{ __('Neutral 50 %') }}',
                                borderColor: '#64748b',
                                style: { background: '#64748b', color: '#fff', fontSize: '8px' },
                            },
                        }],
                        ...(Number.isFinite(card.currentValue) ? {
                        xaxis: [{
                            x: card.currentValue,
                            borderColor: '#f4c75b',
                            strokeDashArray: 4,
                            label: {
                                text: '{{ __('Aktuell') }}',
                                borderColor: '#f4c75b',
                                style: { background: '#f4c75b', color: '#171717', fontSize: '9px', fontWeight: 800 },
                            },
                        }],
                        } : {}),
                    },
                });
                element.__aktienkiChart = indicatorChart;
                indicatorChart.render();
            });
        });
    </script>
@endsection

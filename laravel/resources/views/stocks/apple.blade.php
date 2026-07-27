@extends('layouts.aktienki')

@section('content')
    <div class="mx-auto flex w-full max-w-screen-2xl flex-col gap-3 py-3 sm:gap-4 sm:py-5">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] text-2xl font-black text-[var(--ak-text)] shadow-[var(--ak-shadow)]">A</span>
                <div>
                    <div class="flex items-center gap-3"><h1 class="text-2xl font-black text-[var(--ak-text)]">Apple Inc.</h1><span class="rounded-lg bg-[var(--ak-accent-soft)] px-2 py-1 text-xs font-black text-[var(--ak-accent)]">AAPL</span></div>
                    <p class="mt-1 text-sm text-[var(--ak-muted)]">NASDAQ · USD · {{ __('Aktienkurs') }}</p>
                </div>
            </div>
            @if ($isDemo)
                <span class="self-start rounded-full border border-amber-400/20 bg-amber-400/10 px-3 py-1.5 text-xs font-bold text-amber-400 sm:self-auto">{{ __('Demo-Daten – keine aktuellen Marktdaten vorhanden') }}</span>
            @else
                <span class="self-start rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-xs font-bold text-emerald-400 sm:self-auto">{{ __('Lokale Marktdaten') }}</span>
            @endif
        </header>

        <section class="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 md:grid-cols-4">
            @foreach ([
                [__('Letzter Kurs'), '$'.number_format($lastPrice, 2, ',', '.'), $change >= 0 ? 'text-emerald-400' : 'text-rose-400'],
                [__('Zeitraum'), count($points).' '.__('Handelstage'), 'text-[var(--ak-text)]'],
                [__('Periodenhoch'), '$'.number_format($periodHigh, 2, ',', '.'), 'text-[var(--ak-text)]'],
                [__('Periodentief'), '$'.number_format($periodLow, 2, ',', '.'), 'text-[var(--ak-text)]'],
            ] as [$label, $value, $color])
                <article class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)] backdrop-blur-xl"><p class="text-[10px] font-bold uppercase tracking-[.15em] text-[var(--ak-muted)]">{{ $label }}</p><p class="mt-2 text-xl font-black {{ $color }}">{{ $value }}</p></article>
            @endforeach
        </section>

        <section class="min-h-[350px] flex-1 rounded-[1.25rem] border border-[var(--ak-border)] bg-[var(--ak-card-strong)] p-4 shadow-[var(--ak-shadow)] backdrop-blur-xl sm:min-h-[390px] sm:rounded-[1.75rem] sm:p-6 lg:min-h-[420px]">
            <div class="mb-3 flex items-center justify-between">
                <div><h2 class="font-black text-[var(--ak-text)]">{{ __('Kursentwicklung') }}</h2><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Schlusskurse des dargestellten Zeitraums') }}</p></div>
                <span class="rounded-xl px-3 py-2 text-sm font-black {{ $change >= 0 ? 'bg-emerald-400/10 text-emerald-400' : 'bg-rose-400/10 text-rose-400' }}">{{ $change >= 0 ? '+' : '' }}{{ number_format($change, 2, ',', '.') }} %</span>
            </div>
            <div id="apple-price-chart" class="h-[280px] w-full sm:h-[320px] lg:h-[360px]" aria-label="{{ __('Apple Aktienchart') }}"></div>
        </section>

        <p class="text-center text-[10px] leading-4 text-[var(--ak-muted)]">{{ __('Die Darstellung dient ausschließlich Informationszwecken und stellt keine Anlageberatung dar.') }}</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const element = document.querySelector('#apple-price-chart');
            if (!element || !window.ApexCharts) return;

            const points = @json($points->values());
            let chart;

            const options = () => {
                const light = document.documentElement.dataset.theme === 'light';
                return {
                    chart: { type: 'area', height: '100%', background: 'transparent', toolbar: { show: false }, zoom: { enabled: true }, animations: { enabled: true, speed: 500 } },
                    series: [{ name: 'AAPL', data: points }],
                    colors: [light ? '#14b8a6' : '#8b5cf6'],
                    stroke: { curve: 'smooth', width: 3 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .38, opacityTo: .03, stops: [0, 95, 100] } },
                    dataLabels: { enabled: false },
                    markers: { size: 0, hover: { size: 5 } },
                    grid: { borderColor: light ? 'rgba(51,65,85,.12)' : 'rgba(148,163,184,.10)', strokeDashArray: 4 },
                    xaxis: { type: 'datetime', labels: { style: { colors: light ? '#64748b' : '#94a3b8' }, datetimeUTC: false }, axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { opposite: true, labels: { formatter: value => '$' + value.toFixed(0), style: { colors: [light ? '#64748b' : '#94a3b8'] } } },
                    tooltip: { theme: light ? 'light' : 'dark', x: { format: 'dd.MM.yyyy' }, y: { formatter: value => '$' + value.toFixed(2) } },
                    theme: { mode: light ? 'light' : 'dark' }
                };
            };

            chart = new window.ApexCharts(element, options());
            chart.render();
            window.addEventListener('aktienki:theme-changed', () => chart.updateOptions(options(), false, true));
        });
    </script>
@endsection

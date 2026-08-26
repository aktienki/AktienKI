<x-app-layout>
    <style>
        .signal-history-donut{position:relative;display:grid;width:42px;height:42px;place-items:center;border-radius:999px;background:conic-gradient(var(--donut-color) var(--donut-value),rgba(148,163,184,.22) 0);box-shadow:0 3px 10px color-mix(in srgb,var(--donut-color) 22%,transparent)}
        .signal-history-donut:after{content:"";position:absolute;inset:5px;border-radius:999px;background:var(--ak-card)}
        .signal-history-donut span{position:relative;z-index:1;font-size:10px;font-weight:900;color:var(--ak-text);font-variant-numeric:tabular-nums}
        .signal-history-donut small{font-size:7px;margin-left:1px;color:var(--ak-muted)}
        .signal-history-chart{height:112px;width:100%}.signal-history-chart-grid{stroke:rgba(148,163,184,.2);stroke-dasharray:4 5}.signal-history-chart-axis{stroke:rgba(148,163,184,.32)}.signal-history-chart-label{fill:var(--ak-muted);font-size:7px;font-weight:700}.signal-history-chart-line{fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
        .signal-history-panel{min-width:0;border-color:rgba(251,146,60,.28)!important;background:color-mix(in srgb,var(--ak-card) 60%,transparent)!important}
        .signal-history-stat{min-width:0;border:1px solid rgba(251,146,60,.20);border-radius:.75rem;background:rgba(251,146,60,.045)}
        .signal-history-panel table thead{background:rgba(251,146,60,.055)}
        .signal-history-panel > .overflow-x-auto:last-child tbody td{padding-top:.4rem!important;padding-bottom:.4rem!important;font-size:.72rem}
        .signal-history-panel > .overflow-x-auto:last-child tbody .signal-history-donut{width:34px;height:34px}
        .signal-history-panel > .overflow-x-auto:last-child tbody .signal-history-donut:after{inset:4px}
        .signal-history-chart-grid-layout{display:grid;grid-template-columns:1fr;gap:1rem}
        .signal-history-stat-grid{display:grid;min-width:900px;grid-template-columns:repeat(6,minmax(130px,1fr));gap:.65rem}
        .signal-history-table-grid{display:grid;grid-template-columns:1fr;gap:1rem}
        .signal-history-score-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}
        @media(min-width:720px){.signal-history-chart-grid-layout{grid-template-columns:repeat(3,minmax(0,1fr))}.signal-history-table-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(min-width:720px){.signal-history-score-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(min-width:1280px){.signal-history-score-grid{grid-template-columns:repeat(6,minmax(0,1fr))}}
        :root[data-theme="light"] .signal-history-panel{background:rgba(255,255,255,.88)!important;box-shadow:0 10px 28px rgba(15,23,42,.08)!important}
        :root[data-theme="light"] .signal-history-stat{background:rgba(251,146,60,.045)}
    </style>
    <main id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)]">
    <div class="ak-container mx-auto max-w-[1800px] py-5">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-[var(--ak-text)]">{{ __('Trade Performance') }}</h1>
                @if($demo)<p class="mt-2 inline-flex rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-600">{{ __('Simulationsvorschau – nicht mit echten Ergebnissen vermischt') }}</p>@endif
            </div>
        </div>
        @php
            $chartPoints = function (array $values): string {
                if (count($values) < 2) return '';
                $min = min($values); $max = max($values); $range = max(0.0001, $max - $min); $last = count($values) - 1;
                return collect($values)->map(fn ($value, $index) => round(4 + ($index / $last) * 292, 1).','.round(84 - (($value - $min) / $range) * 70, 1))->implode(' ');
            };
        @endphp
        <div class="signal-history-chart-grid-layout mb-5">
            @foreach($benchmarkData as $benchmark)
                @php
                    $combinedValues = array_merge($benchmark['strategy_values'], $benchmark['values']);
                    $chartMin = $combinedValues !== [] ? min($combinedValues) : 0;
                    $chartMax = $combinedValues !== [] ? max($combinedValues) : 0;
                    $chartRange = max(.0001, $chartMax - $chartMin);
                    $comparisonPoints = function (array $values) use ($chartMin, $chartRange): string {
                        if (count($values) < 2) return '';
                        $last = count($values) - 1;
                        return collect($values)->map(fn ($value, $index) => round(8 + ($index / $last) * 284, 1).','.round(78 - (($value - $chartMin) / $chartRange) * 64, 1))->implode(' ');
                    };
                    $dateLabels = collect($benchmark['dates'])->isNotEmpty() ? collect([0, (int) floor((count($benchmark['dates']) - 1) / 2), count($benchmark['dates']) - 1])->unique()->map(fn ($index) => [
                        'x' => 8 + ($index / max(1, count($benchmark['dates']) - 1)) * 284,
                        'label' => \Carbon\Carbon::parse($benchmark['dates'][$index])->format('d.m.'),
                    ]) : collect();
                @endphp
                <section class="signal-history-panel ak-card ak-dashboard-card ak-card-static rounded-2xl p-4">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[.16em] text-orange-400">{{ __('Strategie') }} vs. {{ $benchmark['label'] }}</p><p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Performance im gewählten Zeitraum') }}</p></div><div class="text-right text-[10px] font-black"><p class="text-cyan-400">{{ __('Strategie') }} {{ $benchmark['strategy_performance'] === null ? '—' : sprintf('%+.2f %%', $benchmark['strategy_performance']) }}</p><p class="mt-1" style="color:{{ $benchmark['color'] }}">{{ $benchmark['label'] }} {{ $benchmark['performance'] === null ? '—' : sprintf('%+.2f %%', $benchmark['performance']) }}</p></div></div>
                    @if(count($benchmark['values']) > 1 && count($benchmark['strategy_values']) > 1)<svg class="signal-history-chart mt-3" viewBox="0 0 300 100" role="img" aria-label="{{ __('Strategie im Vergleich zum :benchmark über die Zeit', ['benchmark' => $benchmark['label']]) }}"><line x1="8" x2="292" y1="14" y2="14" class="signal-history-chart-grid"/><line x1="8" x2="292" y1="46" y2="46" class="signal-history-chart-grid"/><line x1="8" x2="292" y1="78" y2="78" class="signal-history-chart-grid"/><line x1="8" x2="292" y1="82" y2="82" class="signal-history-chart-axis"/>@foreach($dateLabels as $dateLabel)<line x1="{{ $dateLabel['x'] }}" x2="{{ $dateLabel['x'] }}" y1="82" y2="86" class="signal-history-chart-axis"/><text x="{{ $dateLabel['x'] }}" y="96" text-anchor="{{ $loop->first ? 'start' : ($loop->last ? 'end' : 'middle') }}" class="signal-history-chart-label">{{ $dateLabel['label'] }}</text>@endforeach<polyline points="{{ $comparisonPoints($benchmark['strategy_values']) }}" class="signal-history-chart-line" stroke="#06b6d4"/><polyline points="{{ $comparisonPoints($benchmark['values']) }}" class="signal-history-chart-line" stroke="{{ $benchmark['color'] }}" stroke-width="1.2"/></svg><div class="mt-2 flex items-center gap-4 text-[9px] font-bold text-[var(--ak-muted)]"><span class="inline-flex items-center gap-1.5"><i class="h-0.5 w-5 bg-cyan-400"></i>{{ __('Strategie') }}</span><span class="inline-flex items-center gap-1.5"><i class="h-0.5 w-5" style="background:{{ $benchmark['color'] }}"></i>{{ $benchmark['label'] }}</span></div>@else<div class="mt-3 grid h-[110px] place-items-center rounded-xl bg-slate-500/5 text-xs text-[var(--ak-muted)]">{{ __('Keine vergleichbaren Kursdaten') }}</div>@endif
                </section>
            @endforeach
        </div>
        <section class="signal-history-panel ak-card ak-dashboard-card ak-card-static mb-5 overflow-hidden rounded-2xl p-4">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div><p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Backtest-Statistik') }}</p><h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Gesamtauswertung') }}</h2></div>
                <p class="text-[9px] text-[var(--ak-muted)]">{{ __('Aktuelle Walk-Forward-Läufe') }}</p>
            </div>
            <div class="overflow-x-auto pb-1">
            <div class="signal-history-stat-grid">
            @foreach([
                ['Trades',$stats->transitions,'text-cyan-500'],
                ['Ausgewertet',$stats->closed,'text-cyan-500'],
                ['Trefferquote',number_format($stats->win_rate,1,',','.').' %','text-cyan-400'],
                ['Ø Profit je Trade',($stats->profit_per_trade > 0 ? '+' : '').number_format($stats->profit_per_trade,2,',','.').' %',$stats->profit_per_trade >= 0 ? 'text-emerald-400' : 'text-rose-400'],
                [__('Performance pro Jahr'),($stats->annual_performance > 0 ? '+' : '').number_format($stats->annual_performance,2,',','.').' %',$stats->annual_performance < 0 ? 'text-rose-400' : 'text-cyan-400'],
                ['Max. Drawdown',number_format($stats->max_drawdown,2,',','.').' %',$stats->max_drawdown > 0 ? 'text-rose-400' : 'text-cyan-400'],
            ] as [$label,$value,$color])
                <article class="rounded-xl border border-orange-400/15 bg-orange-400/[.04] p-3">
                    <p class="truncate text-[8px] font-black uppercase tracking-wider text-cyan-400" title="{{ $label }}">{{ $label }}</p>
                    <p class="mt-2 truncate text-lg font-black tabular-nums text-white" title="{{ $value }}">{{ $value }}</p>
                </article>
            @endforeach
            </div>
            </div>
        </section>
        <section class="signal-history-panel ak-card ak-dashboard-card ak-card-static mb-5 overflow-hidden rounded-2xl p-4">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div><p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Historischer KI-Score') }}</p><h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Performance nach Score-Bereich') }}</h2></div>
                <p class="text-[9px] text-[var(--ak-muted)]">{{ __('Durchschnitt je Walk-Forward-Trade') }}</p>
            </div>
            <div class="signal-history-score-grid">
                @foreach($scoreStats as $scoreStat)
                    <article class="rounded-xl border border-orange-400/15 bg-orange-400/[.04] p-3">
                        <div class="flex items-center justify-between gap-2"><strong class="text-sm font-black text-orange-400">KI {{ $scoreStat['label'] }}</strong><small class="text-[8px] font-black text-[var(--ak-muted)]">{{ $scoreStat['trades'] }} {{ __('Trades') }}</small></div>
                        <div class="mt-3 grid grid-cols-3 gap-1.5 text-center">
                            @foreach([
                                [__('Performance'), $scoreStat['performance'], ($scoreStat['performance'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'],
                                [__('Hitrate'), $scoreStat['hit_rate'], 'text-cyan-400'],
                                [__('Ø Drawdown'), $scoreStat['average_drawdown'], 'text-rose-400'],
                            ] as [$metricLabel,$metricValue,$metricClass])
                                <span class="min-w-0"><small class="block truncate text-[7px] font-black uppercase text-[var(--ak-muted)]">{{ $metricLabel }}</small><b class="mt-1 block truncate text-[11px] tabular-nums {{ $metricValue === null ? 'text-[var(--ak-muted)]' : $metricClass }}">{{ $metricValue === null ? '—' : (($metricValue > 0 && $metricLabel === __('Performance') ? '+' : '').number_format($metricValue, 1, ',', '.').'%') }}</b></span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        @php
            $flags = ['DE'=>'🇩🇪','US'=>'🇺🇸','CN'=>'🇨🇳','HK'=>'🇭🇰','GB'=>'🇬🇧','FR'=>'🇫🇷','NL'=>'🇳🇱','CH'=>'🇨🇭','AT'=>'🇦🇹','IT'=>'🇮🇹','ES'=>'🇪🇸','DK'=>'🇩🇰','SE'=>'🇸🇪','NO'=>'🇳🇴','FI'=>'🇫🇮','JP'=>'🇯🇵','KR'=>'🇰🇷','CA'=>'🇨🇦','AU'=>'🇦🇺','IN'=>'🇮🇳'];
        @endphp
        <section class="signal-history-panel ak-card ak-dashboard-card ak-card-static overflow-hidden rounded-2xl">
            <div class="border-b border-orange-400/15 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-[.18em] text-orange-400">{{ __('Aktienperformance') }}</p><h2 class="mt-0.5 text-sm font-black text-[var(--ak-text)]">{{ __('Kumulierte Ergebnisse je Aktie') }}</h2></div>
            <div class="overflow-x-auto"><table class="min-w-[880px] w-full text-left text-xs"><thead class="text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]"><tr><th class="px-4 py-2.5">{{ __('Aktie') }}</th><th class="px-3 py-2.5 text-right">{{ __('Trades') }}</th><th class="px-3 py-2.5 text-right">{{ __('Gewinne kumulativ') }}</th><th class="px-3 py-2.5 text-right">{{ __('Verluste kumulativ') }}</th><th class="px-3 py-2.5 text-right">{{ __('Gesamtgewinn') }}</th><th class="px-3 py-2.5 text-right">{{ __('Ø Rendite') }}</th><th class="px-4 py-2.5 text-right">{{ __('Max. Drawdown') }}</th></tr></thead><tbody class="divide-y divide-[var(--ak-border)]">
                @forelse($stockPerformance as $stock)
                    <tr class="transition hover:bg-cyan-400/[.035]">
                        <td class="px-4 py-2"><a href="{{ route('stocks.show', ['symbol' => $stock['symbol'], 'return_to' => '/predictions/signal-history']) }}" class="flex min-w-[220px] items-center gap-2.5"><span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg border border-cyan-400/25 bg-cyan-400/[.08] text-[10px] font-black text-cyan-400">@if($stock['logo_url'])<img src="{{ $stock['logo_url'] }}" alt="" class="h-full w-full object-contain">@else{{ strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $stock['symbol']), 0, 2)) }}@endif</span><span class="min-w-0"><strong class="block truncate text-xs text-[var(--ak-text)]">{{ $stock['symbol'] }}</strong><small class="mt-0.5 block truncate text-[10px] text-[var(--ak-muted)]"><span class="mr-1">{{ $flags[$stock['country']] ?? '🌐' }}</span>{{ $stock['name'] }}</small></span></a></td>
                        <td class="px-3 py-2 text-right font-bold tabular-nums text-[var(--ak-text)]">{{ $stock['trades'] }}</td>
                        <td class="px-3 py-2 text-right font-black tabular-nums text-emerald-400">+{{ number_format($stock['gross_profit'], 2, ',', '.') }} %</td>
                        <td class="px-3 py-2 text-right font-black tabular-nums text-rose-400">{{ number_format($stock['gross_loss'], 2, ',', '.') }} %</td>
                        <td class="px-3 py-2 text-right font-black tabular-nums {{ $stock['total'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ sprintf('%+.2f %%', $stock['total']) }}</td>
                        <td class="px-3 py-2 text-right font-bold tabular-nums {{ $stock['average'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ sprintf('%+.2f %%', $stock['average']) }}</td>
                        <td class="px-4 py-2 text-right font-black tabular-nums {{ $stock['max_drawdown'] < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]' }}">{{ number_format($stock['max_drawdown'], 2, ',', '.') }} %</td>
                    </tr>
                @empty<tr><td colspan="7" class="px-4 py-14 text-center text-[var(--ak-muted)]">{{ __('Keine abgeschlossenen Walk-Forward-Trades im gewählten Zeitraum.') }}</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
    </main>
</x-app-layout>

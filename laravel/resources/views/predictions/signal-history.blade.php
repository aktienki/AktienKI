<x-app-layout>
    <main id="personal-dashboard" class="ak-body min-h-[calc(100dvh-73px)]">
        <div class="ak-container mx-auto max-w-[1800px] py-5">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <h1 class="text-3xl font-black tracking-tight text-[var(--ak-text)]">{{ __('Trade Performance') }}</h1>
                @if($portfolios->isNotEmpty())
                    <form method="get"><select name="portfolio" onchange="this.form.submit()" class="rounded-xl border border-cyan-400/25 bg-[var(--ak-card)] px-4 py-2 text-sm font-black text-[var(--ak-text)]">@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected($selectedPortfolioId === $portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></form>
                @endif
            </div>

            @if($portfolios->isEmpty())
                <section class="ak-card ak-dashboard-card rounded-2xl border border-cyan-400/20 p-10 text-center"><x-heroicon-o-beaker class="mx-auto h-10 w-10 text-cyan-400"/><h2 class="mt-4 text-xl font-black text-[var(--ak-text)]">{{ __('Noch kein Musterdepot vorhanden') }}</h2><p class="mt-2 text-sm text-[var(--ak-muted)]">{{ __('Lege ein Musterdepot an, um deine persönliche Trade Performance auszuwerten.') }}</p><a href="{{ route('paper-depots.index') }}" class="mt-5 inline-flex rounded-xl border border-cyan-400/30 bg-cyan-400/[.08] px-4 py-2 text-sm font-black text-cyan-300">{{ __('Musterdepot anlegen') }}</a></section>
            @else
                <section class="ak-card ak-dashboard-card mb-5 overflow-hidden rounded-2xl border border-cyan-400/20 p-4">
                    <div class="trade-performance-stats grid grid-cols-6 gap-2">
                        @foreach([
                            [__('Trades'), $stats['trades']], [__('Trefferquote'), number_format($stats['hit_rate'],1,',','.').' %'],
                            [__('Ø Profit je Trade'), sprintf('%+.2f %%',$stats['profit_per_trade'])], [__('Performance pro Jahr'), sprintf('%+.2f %%',$stats['annual_performance'])],
                            [__('Realisierter Gewinn'), number_format($stats['realized_profit'],2,',','.').' '.($portfolios->firstWhere('id',$selectedPortfolioId)?->currency ?? '')],
                            [__('Max. Drawdown'), number_format($stats['max_drawdown'],2,',','.').' %'],
                        ] as [$label,$value])<article class="min-w-0 rounded-xl border border-cyan-400/15 bg-cyan-400/[.035] p-3"><p class="truncate text-[8px] font-black uppercase tracking-wider text-cyan-400">{{ $label }}</p><p class="mt-2 truncate text-lg font-black tabular-nums text-white">{{ $value }}</p></article>@endforeach
                    </div>
                </section>

                <section class="ak-card ak-dashboard-card mb-5 rounded-2xl border border-cyan-400/20 p-4">
                    <div class="flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Depotentwicklung') }}</p><h2 class="mt-1 text-sm font-black text-[var(--ak-text)]">{{ __('Realisierte Performance') }}</h2></div><strong class="text-lg font-black {{ $stats['total_performance'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ sprintf('%+.2f %%',$stats['total_performance']) }}</strong></div>
                    @if(count($curve)>1)@php $values=collect($curve)->pluck('value');$min=$values->min();$max=$values->max();$range=max(.0001,$max-$min);$last=count($curve)-1;$points=collect($curve)->map(fn($point,$i)=>round(4+$i/$last*292,1).','.round(82-(($point['value']-$min)/$range)*70,1))->implode(' '); @endphp<svg class="mt-4 h-40 w-full" viewBox="0 0 300 90" preserveAspectRatio="none"><line x1="4" x2="296" y1="82" y2="82" stroke="rgba(148,163,184,.25)"/><polyline points="{{ $points }}" fill="none" stroke="#22d3ee" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>@else<div class="mt-4 grid h-40 place-items-center rounded-xl bg-slate-500/5 text-sm text-[var(--ak-muted)]">{{ __('Noch nicht genügend abgeschlossene Verkäufe für einen Verlauf.') }}</div>@endif
                </section>

                <section class="ak-card ak-dashboard-card overflow-hidden rounded-2xl border border-cyan-400/20">
                    <div class="border-b border-cyan-400/15 px-4 py-3"><p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Aktienperformance') }}</p><h2 class="mt-1 text-sm font-black text-[var(--ak-text)]">{{ __('Realisierte Ergebnisse je Aktie') }}</h2></div>
                    <div class="overflow-x-auto"><table class="ak-mobile-performance-table min-w-[850px] w-full text-left text-xs"><thead class="bg-cyan-400/[.035] text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]"><tr><th class="px-4 py-3">Aktie</th><th class="px-3 py-3 text-right">Trades</th><th class="px-3 py-3 text-right">Gewinne</th><th class="px-3 py-3 text-right">Verluste</th><th class="px-3 py-3 text-right">Gesamt</th><th class="px-3 py-3 text-right">Ø Rendite</th><th class="px-4 py-3 text-right">Max. Drawdown</th></tr></thead><tbody class="divide-y divide-[var(--ak-border)]">@forelse($stockPerformance as $stock)<tr><td class="px-4 py-2"><a href="{{ route('stocks.show',['symbol'=>$stock['symbol'],'return_to'=>'/predictions/signal-history']) }}" class="font-black text-cyan-400">{{ $stock['symbol'] }}</a><small class="ml-2 text-[var(--ak-muted)]">{{ $stock['name'] }}</small></td><td class="px-3 py-2 text-right">{{ $stock['trades'] }}</td><td class="px-3 py-2 text-right font-bold text-emerald-400">{{ sprintf('%+.2f %%',$stock['gross_profit']) }}</td><td class="px-3 py-2 text-right font-bold text-rose-400">{{ number_format($stock['gross_loss'],2,',','.') }} %</td><td class="px-3 py-2 text-right font-black {{ $stock['total']>=0?'text-emerald-400':'text-rose-400' }}">{{ sprintf('%+.2f %%',$stock['total']) }}</td><td class="px-3 py-2 text-right">{{ sprintf('%+.2f %%',$stock['average']) }}</td><td class="px-4 py-2 text-right text-rose-400">{{ number_format($stock['max_drawdown'],2,',','.') }} %</td></tr>@empty<tr><td colspan="7" class="px-4 py-14 text-center text-[var(--ak-muted)]">{{ __('Dieses Musterdepot enthält noch keine abgeschlossenen Verkäufe.') }}</td></tr>@endforelse</tbody></table></div>
                </section>
            @endif
        </div>
    </main>
</x-app-layout>

<x-app-layout>
    @php
        $depotExplorerData = collect($strategyTemplates)->values()->mapWithKeys(function ($template, $index) {
            [$type, $name, $description, $icon, $currency, $stocks, $history] = array_pad($template, 7, []);
            $value = collect($stocks)->sum(fn ($stock) => (float) ($stock['value'] ?? 0));
            $invested = collect($stocks)->sum(function ($stock) {
                $stockValue = (float) ($stock['value'] ?? 0);
                $change = (float) ($stock['change'] ?? 0);
                return $change <= -99.9 ? $stockValue : $stockValue / (1 + ($change / 100));
            });
            $performance = $invested > 0 ? (($value - $invested) / $invested) * 100 : 0;
            $shape = [0, .08, .18, .12, .31, .42, .38, .56, .63, .74, .88, 1];
            $targetScore = [82, 76, 72][$index] ?? 75;

            return ['strategy-'.$index => [
                'name' => $name,
                'currency' => $currency,
                'value' => round($value, 2),
                'performance' => round($performance, 2),
                'stocks' => collect($stocks)->sortByDesc('purchase_date')->values()->all(),
                'chart' => $history ?: collect($shape)->map(fn ($factor, $point) => [
                    'x' => now()->subDays((count($shape) - 1 - $point) * 3)->format('Y-m-d'),
                    'y' => round($performance * $factor + sin($point * 1.7) * .28, 2),
                    'score' => round(58 + (($targetScore - 58) * $factor) + sin($point * 1.25) * 2.2, 1),
                ])->all(),
            ]];
        })->all();
    @endphp
    <div x-data="depotExplorer(@js($depotExplorerData))" class="flex min-h-[calc(100dvh-89px)] flex-col py-3 text-[var(--ak-text)]">
        <div class="mb-3 flex shrink-0 flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300">
                    @if ($paperMode)<x-heroicon-o-beaker class="h-6 w-6" />@else<x-heroicon-o-briefcase class="h-6 w-6" />@endif
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-700">{{ $paperMode ? __('Virtuelle Portfolioübersicht') : __('Portfolioübersicht') }}</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight">{{ $paperMode ? __('Meine Musterdepots') : __('aKI Virtuelle Depots') }}</h1>
                    @if ($paperMode)
                        <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Teste Strategien und KI-Empfehlungen ohne reales Kapital.') }}</p>
                    @endif
                </div>
            </div>
            <button x-cloak x-show="phase === 'detail'" x-transition.opacity.duration.300ms type="button" @click="close()" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl border border-teal-500/30 bg-teal-500/10 px-4 text-xs font-black text-teal-600 shadow-sm transition hover:border-teal-500/50 hover:bg-teal-500/15">
                <x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zurück zu allen Depots') }}
            </button>
        </div>

        @if (session('status') === 'portfolio-created')
            <div class="mb-4 rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-3 text-sm font-bold text-emerald-400">{{ __('Depot wurde angelegt.') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-400/25 bg-rose-400/10 px-4 py-3 text-sm font-bold text-rose-400">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4" :class="['idle', 'fading', 'returning', 'appearing'].includes(phase) ? 'md:grid-cols-2 xl:grid-cols-3' : 'xl:grid-cols-12'">
            @foreach ($portfolios as $portfolio)
                @php
                    $type = match ($portfolio->type) {
                        'strategy' => [__('Strategiedepot'), 'text-sky-400', 'bg-sky-500/10 border-sky-400/25', 'chart-bar-square'],
                        'paper' => [__('Musterdepot'), 'text-amber-400', 'bg-amber-500/10 border-amber-400/25', 'beaker'],
                        default => [__('Hauptdepot'), 'text-teal-600', 'bg-teal-500/10 border-teal-500/25', 'briefcase'],
                    };
                    $performance = (float) $portfolio->performance_percent;
                @endphp
                <article x-show="!selected" x-transition.opacity.duration.1000ms class="flex min-h-72 flex-col overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)] transition hover:-translate-y-0.5 hover:border-teal-500/35">
                    <div class="flex items-start justify-between gap-3 border-b border-[var(--ak-border)] p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border {{ $type[2] }} {{ $type[1] }}">
                                @if ($type[3] === 'beaker')<x-heroicon-o-beaker class="h-5 w-5" />
                                @elseif ($type[3] === 'chart-bar-square')<x-heroicon-o-chart-bar-square class="h-5 w-5" />
                                @else<x-heroicon-o-briefcase class="h-5 w-5" />@endif
                            </span>
                            <div class="min-w-0">
                                @if ($portfolio->type !== 'strategy')
                                    <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $type[0] }}</p>
                                @endif
                                <h2 class="{{ $portfolio->type !== 'strategy' ? 'mt-1' : '' }} truncate text-lg font-black">{{ $portfolio->name }}</h2>
                            </div>
                        </div>
                        @if ($portfolio->is_default)
                            <span class="rounded-md border border-amber-400/30 bg-amber-400/10 px-2 py-1 text-[9px] font-black uppercase text-amber-400">{{ __('Standard') }}</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-4">
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] p-3">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Depotwert') }}</p>
                            <p class="mt-1 text-xl font-black tabular-nums">{{ number_format($portfolio->current_value, 2, ',', '.') }} {{ $portfolio->currency }}</p>
                        </div>
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] p-3">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Performance') }}</p>
                            <p class="mt-1 text-xl font-black tabular-nums {{ $performance > 0 ? 'text-emerald-400' : ($performance < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ $performance > 0 ? '+' : '' }}{{ number_format($performance, 2, ',', '.') }} %</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-4 pb-4 text-xs">
                        <span class="text-[var(--ak-muted)]">{{ __('Positionen') }}</span>
                        <strong>{{ $portfolio->positions->count() }}</strong>
                    </div>
                    <div class="mx-4 h-1.5 overflow-hidden rounded-full bg-[var(--ak-surface-muted)]">
                        <div class="h-full rounded-full bg-teal-600" style="width: {{ min(100, $portfolio->positions->count() * 10) }}%"></div>
                    </div>

                    <div class="mt-auto flex items-center justify-between border-t border-[var(--ak-border)] px-4 py-3">
                        <p class="max-w-[65%] truncate text-[10px] text-[var(--ak-muted)]">{{ $portfolio->description ?: __('Noch keine Beschreibung hinterlegt.') }}</p>
                        <a href="{{ route('depots.show', ['portfolio' => $portfolio, 'return_to' => $paperMode ? 'paper' : 'depots']) }}" class="inline-flex h-9 items-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:text-teal-700">
                            {{ __('Öffnen') }}<x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                    </div>
                </article>
            @endforeach

            @if ($portfolios->isEmpty())
                @php
                    $starterDepots = $paperMode
                        ? [
                            ['paper', __('KI-Musterdepot'), __('Teste die aktuell besten KI-Empfehlungen in einem virtuellen Depot.'), 'sparkles', 'EUR', []],
                            ['paper', __('Chancen-Musterdepot'), __('Beobachte chancenorientierte Aktien mit höherem Renditepotenzial.'), 'chart', 'EUR', []],
                            ['paper', __('Defensives Musterdepot'), __('Simuliere eine ruhigere Auswahl mit Fokus auf Risiko und Stabilität.'), 'shield', 'EUR', []],
                        ]
                        : [
                            ...$strategyTemplates,
                        ];
                @endphp
                @foreach ($starterDepots as [$type, $name, $description, $icon, $currency, $stocks])
                    @php $templateKey = 'strategy-'.$loop->index; @endphp
                    <div
                        x-show="['idle', 'fading', 'returning', 'appearing'].includes(phase) || selected === '{{ $templateKey }}'"
                        :class="selected === '{{ $templateKey }}' && ['moving', 'detail', 'closing-detail'].includes(phase) ? 'xl:col-span-4 xl:col-start-1' : ''"
                        @click="select('{{ $templateKey }}')"
                        data-depot-key="{{ $templateKey }}"
                        data-depot-card
                        class="group min-w-0"
                    >
                        <x-depot-template-card :type="$type" :name="$name" :description="$description" :icon="$icon" :currency="$currency" :stocks="$stocks" :instrument-ids="$stockInstrumentIds" />
                    </div>
                @endforeach
            @else
                @unless ($paperMode)
                    @foreach ($strategyTemplates as [$type, $name, $description, $icon, $currency, $stocks])
                        @continue($portfolios->contains(fn ($portfolio) => mb_strtolower($portfolio->name) === mb_strtolower($name)))
                        @php $templateKey = 'strategy-'.$loop->index; @endphp
                        <div
                            x-show="['idle', 'fading', 'returning', 'appearing'].includes(phase) || selected === '{{ $templateKey }}'"
                            :class="selected === '{{ $templateKey }}' && ['moving', 'detail', 'closing-detail'].includes(phase) ? 'xl:col-span-4 xl:col-start-1' : ''"
                            @click="select('{{ $templateKey }}')"
                            data-depot-key="{{ $templateKey }}"
                            data-depot-card
                            class="group min-w-0"
                        >
                            <x-depot-template-card :type="$type" :name="$name" :description="$description" :icon="$icon" :currency="$currency" :stocks="$stocks" :instrument-ids="$stockInstrumentIds" />
                        </div>
                    @endforeach
                @endunless
                <div x-data="{ open: false }" x-show="!selected" x-transition.opacity.duration.1000ms class="min-h-72 rounded-2xl border border-dashed border-[var(--ak-border)] bg-[var(--ak-card)] p-5">
                    <button type="button" @click="open = !open" class="flex h-full w-full flex-col items-center justify-center text-center text-[var(--ak-muted)] transition hover:text-teal-700" x-show="!open">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]"><x-heroicon-o-plus class="h-6 w-6" /></span>
                        <strong class="mt-4 text-sm">{{ __('Weiteres Depot anlegen') }}</strong>
                    </button>
                    <form x-cloak x-show="open" method="POST" action="{{ route('depots.store') }}" class="grid gap-3">
                        @csrf
                        <input name="name" required maxlength="80" placeholder="{{ __('Name des Depots') }}" class="ak-input h-10 text-sm">
                        @if ($paperMode)
                            <input type="hidden" name="type" value="paper">
                        @else
                            <select name="type" class="ak-input h-10 text-sm"><option value="strategy">{{ __('Strategiedepot') }}</option><option value="paper">{{ __('Musterdepot') }}</option></select>
                        @endif
                        <select name="currency" class="ak-input h-10 text-sm"><option>EUR</option><option>USD</option><option>CHF</option><option>GBP</option></select>
                        <textarea name="description" maxlength="500" rows="3" placeholder="{{ __('Kurze Beschreibung') }}" class="ak-input text-sm"></textarea>
                        <div class="flex gap-2"><button type="button" @click="open = false" class="h-10 flex-1 rounded-xl border border-[var(--ak-border)] text-xs font-bold text-[var(--ak-muted)]">{{ __('Abbrechen') }}</button><button type="submit" class="h-10 flex-1 rounded-xl bg-teal-700 text-xs font-black text-white">{{ __('Anlegen') }}</button></div>
                    </form>
                </div>
            @endif

            <aside x-cloak x-show="phase === 'detail' && active" x-transition.opacity.duration.500ms class="min-w-0 space-y-4 xl:col-span-8">
                <section class="overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                    <div class="border-b border-[var(--ak-border)] px-4 py-3">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[.14em] text-teal-600">{{ __('Performance & durchschnittlicher KI-Score') }}</p>
                            <h2 class="mt-1 text-lg font-black" x-text="active?.name"></h2>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 px-4 pt-3">
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] px-3 py-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Depotwert') }}</p><p class="mt-1 text-base font-black tabular-nums"><span x-text="formatMoney(active?.value)"></span> <span x-text="active?.currency"></span></p></div>
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] px-3 py-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Gesamtperformance') }}</p><p class="mt-1 text-base font-black tabular-nums text-emerald-400" x-text="formatPercent(active?.performance)"></p></div>
                    </div>
                    <div x-ref="performanceChart" class="h-56 w-full px-2"></div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                    <div class="flex items-center gap-2 border-b border-[var(--ak-border)] px-4 py-3">
                        <x-heroicon-o-clock class="h-4 w-4 text-amber-500" />
                        <h3 class="text-sm font-black">{{ __('Historie') }}</h3>
                    </div>
                    <div class="grid max-h-64 gap-1.5 overflow-y-auto p-3">
                        <template x-for="stock in active?.stocks || []" :key="stock.symbol">
                            <div class="grid grid-cols-[minmax(0,1.3fr)_repeat(3,minmax(0,.65fr))] items-center gap-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-black" x-text="stock.name"></p>
                                    <p class="text-[9px] font-bold text-amber-500"><span x-text="stock.symbol"></span> · <span x-text="stock.purchase_date"></span></p>
                                    <p class="truncate text-[8px] font-bold text-[var(--ak-muted)]"><span x-text="stock.country || '—'"></span> · <span x-text="stock.exchange_code || '{{ __('Keine Exchange') }}'"></span></p>
                                </div>
                                <div><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Anzahl') }}</p><p class="mt-0.5 text-[10px] font-bold tabular-nums" x-text="stock.quantity"></p></div>
                                <div><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Kaufpreis') }}</p><p class="mt-0.5 text-[10px] font-bold tabular-nums" x-text="formatMoney(stock.buy_price)"></p></div>
                                <div><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ __('Ergebnis') }}</p><p class="mt-0.5 text-[10px] font-black tabular-nums" :class="stock.change >= 0 ? 'text-emerald-400' : 'text-rose-400'" x-text="formatPercent(stock.change)"></p></div>
                            </div>
                        </template>
                    </div>
                </section>
            </aside>
        </section>
    </div>

        <script>
            function depotExplorer(depots) {
                return {
                    depots,
                    selected: null,
                    phase: 'idle',
                    chart: null,
                    get active() { return this.selected ? this.depots[this.selected] : null },
                    async select(key) {
                        if (!this.depots[key] || this.phase !== 'idle') return;
                        this.selected = key;
                        this.phase = 'fading';

                        await this.$nextTick();
                        const otherCards = [...this.$root.querySelectorAll('[data-depot-card]')]
                            .filter(card => card.dataset.depotKey !== key && getComputedStyle(card).display !== 'none');
                        const fadeAnimations = otherCards.map(card => card.animate(
                            [{ opacity: 1 }, { opacity: 0 }],
                            {
                                duration: 1000,
                                easing: 'ease-in-out',
                                fill: 'forwards',
                            },
                        ));
                        await Promise.all(fadeAnimations.map(animation => animation.finished.catch(() => {})));

                        const card = this.$root.querySelector(`[data-depot-key="${CSS.escape(key)}"]`);
                        const start = card?.getBoundingClientRect();

                        this.phase = 'moving';
                        await this.$nextTick();
                        fadeAnimations.forEach(animation => animation.cancel());

                        if (card && start) {
                            const end = card.getBoundingClientRect();
                            const animation = card.animate([
                                {
                                    transform: `translate(${start.left - end.left}px, ${start.top - end.top}px)`,
                                    transformOrigin: 'top left',
                                },
                                {
                                    transform: 'translate(0, 0)',
                                    transformOrigin: 'top left',
                                },
                            ], {
                                duration: 1000,
                                easing: 'cubic-bezier(.22, 1, .36, 1)',
                                fill: 'both',
                            });
                            await animation.finished.catch(() => {});
                        } else {
                            await this.wait(1000);
                        }

                        this.phase = 'detail';
                        await this.$nextTick();
                        this.renderChart();
                    },
                    async close() {
                        if (this.phase !== 'detail') return;
                        this.phase = 'closing-detail';
                        await this.wait(500);

                        this.chart?.destroy();
                        this.chart = null;

                        const key = this.selected;
                        const card = this.$root.querySelector(`[data-depot-key="${CSS.escape(key)}"]`);
                        const otherCards = [...this.$root.querySelectorAll('[data-depot-card]')]
                            .filter(item => item.dataset.depotKey !== key);
                        const start = card?.getBoundingClientRect();

                        otherCards.forEach(item => { item.style.opacity = '0'; });
                        this.phase = 'returning';
                        await this.$nextTick();

                        if (card && start) {
                            const end = card.getBoundingClientRect();
                            const animation = card.animate([
                                {
                                    transform: `translate(${start.left - end.left}px, ${start.top - end.top}px)`,
                                    transformOrigin: 'top left',
                                },
                                {
                                    transform: 'translate(0, 0)',
                                    transformOrigin: 'top left',
                                },
                            ], {
                                duration: 1000,
                                easing: 'cubic-bezier(.22, 1, .36, 1)',
                                fill: 'both',
                            });
                            await animation.finished.catch(() => {});
                        } else {
                            await this.wait(1000);
                        }

                        this.phase = 'appearing';
                        const appearAnimations = otherCards.map(item => item.animate(
                            [{ opacity: 0 }, { opacity: 1 }],
                            {
                                duration: 1000,
                                easing: 'ease-in-out',
                                fill: 'forwards',
                            },
                        ));
                        await Promise.all(appearAnimations.map(animation => animation.finished.catch(() => {})));
                        appearAnimations.forEach(animation => animation.cancel());
                        otherCards.forEach(item => { item.style.opacity = ''; });

                        this.selected = null;
                        this.phase = 'idle';
                    },
                    wait(milliseconds) {
                        return new Promise(resolve => window.setTimeout(resolve, milliseconds));
                    },
                    formatMoney(value) {
                        return new Intl.NumberFormat(document.documentElement.lang || 'de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
                    },
                    formatPercent(value) {
                        const number = Number(value || 0);
                        return `${number > 0 ? '+' : ''}${new Intl.NumberFormat(document.documentElement.lang || 'de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number)} %`;
                    },
                    renderChart() {
                        if (!window.ApexCharts || !this.$refs.performanceChart || !this.active) return;
                        this.chart?.destroy();
                        const positive = Number(this.active.performance) >= 0;
                        this.chart = new ApexCharts(this.$refs.performanceChart, {
                            chart: { type: 'area', height: 224, toolbar: { show: false }, animations: { enabled: true, speed: 1000 }, background: 'transparent' },
                            series: [
                                { name: @js(__('Performance')), type: 'area', data: this.active.chart.map(point => ({ x: point.x, y: point.y })) },
                                { name: @js(__('Ø KI-Score')), type: 'line', data: this.active.chart.map(point => ({ x: point.x, y: point.score })) },
                            ],
                            colors: [positive ? '#14b8a6' : '#f43f5e', '#f59e0b'],
                            stroke: { width: [2, 3], curve: 'smooth', dashArray: [0, 4] },
                            fill: {
                                type: ['gradient', 'solid'],
                                opacity: [.2, 1],
                                gradient: { shadeIntensity: .2, opacityFrom: .28, opacityTo: .02, stops: [0, 100] },
                            },
                            dataLabels: { enabled: false },
                            grid: { borderColor: 'rgba(148,163,184,.13)', strokeDashArray: 4, padding: { left: 6, right: 12 } },
                            xaxis: { type: 'datetime', labels: { style: { colors: '#94a3b8', fontSize: '9px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
                            yaxis: [
                                {
                                    seriesName: @js(__('Performance')),
                                    labels: { formatter: value => `${value.toFixed(1)} %`, style: { colors: '#94a3b8', fontSize: '9px' } },
                                },
                                {
                                    seriesName: @js(__('Ø KI-Score')),
                                    opposite: true,
                                    min: 0,
                                    max: 100,
                                    tickAmount: 4,
                                    labels: { formatter: value => value.toFixed(0), style: { colors: '#f59e0b', fontSize: '9px' } },
                                },
                            ],
                            tooltip: {
                                theme: 'dark',
                                shared: true,
                                x: { format: 'dd.MM.yyyy' },
                                y: [
                                    { formatter: value => `${value.toFixed(2)} %` },
                                    { formatter: value => `${value.toFixed(1)} / 100` },
                                ],
                            },
                            annotations: { yaxis: [{ y: 0, borderColor: 'rgba(148,163,184,.35)', strokeDashArray: 4 }] },
                            legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '10px', labels: { colors: '#94a3b8' }, markers: { size: 4 } },
                        });
                        this.chart.render();
                    },
                };
            }
        </script>
</x-app-layout>

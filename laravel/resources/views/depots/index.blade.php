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
        @elseif (session('status'))
            <div class="mb-4 rounded-xl border border-teal-400/25 bg-teal-400/10 px-4 py-3 text-sm font-bold text-teal-300">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-400/25 bg-rose-400/10 px-4 py-3 text-sm font-bold text-rose-400">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-3" :class="['idle', 'fading', 'returning', 'appearing'].includes(phase) ? '{{ $paperMode ? 'md:grid-cols-2 xl:grid-cols-4' : 'md:grid-cols-2 xl:grid-cols-3' }}' : 'xl:grid-cols-12'">
            @foreach ($portfolios as $portfolio)
                @php
                    $type = match ($portfolio->type) {
                        'strategy' => [__('Strategiedepot'), 'text-sky-400', 'bg-sky-500/10 border-sky-400/25', 'chart-bar-square'],
                        'paper' => [__('Musterdepot'), 'text-amber-400', 'bg-amber-500/10 border-amber-400/25', 'beaker'],
                        default => [__('Hauptdepot'), 'text-teal-600', 'bg-teal-500/10 border-teal-500/25', 'briefcase'],
                    };
                    $performance = (float) $portfolio->performance_percent;
                    $isStrategyAccount = $paperMode && (bool) data_get($portfolio->meta, 'automation.live_enabled', false);
                @endphp
                <article x-data="{ strategyOpen: false, strategyConfirmOpen: false, capitalOpen: false, resetOpen: false, deleteOpen: false }" x-show="!selected" x-transition.opacity.duration.1000ms class="relative flex flex-col overflow-hidden rounded-2xl border bg-[var(--ak-card)] transition {{ $isStrategyAccount ? 'min-h-[29rem] border-cyan-300/50 shadow-[0_0_0_1px_rgba(34,211,238,.08),0_18px_45px_rgba(8,145,178,.16)] xl:row-span-2' : 'min-h-52 border-[var(--ak-border)] shadow-[var(--ak-shadow)] hover:border-teal-500/35' }}" @if($isStrategyAccount) style="background:linear-gradient(155deg,rgba(8,145,178,.14) 0%,rgba(21,36,58,.96) 34%,rgba(21,36,58,1) 100%);" @endif>
                    @if($isStrategyAccount)<div class="h-1 w-full shrink-0 bg-gradient-to-r from-cyan-300/25 via-cyan-300 to-sky-400/25 shadow-[0_0_12px_rgba(34,211,238,.45)]"></div>@endif
                    <div class="flex items-start justify-between gap-3 border-b border-[var(--ak-border)] {{ $isStrategyAccount ? 'p-4' : 'p-3' }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex shrink-0 items-center justify-center rounded-xl border {{ $isStrategyAccount ? 'h-11 w-11 border-cyan-300/35 bg-cyan-400/[.12] text-cyan-200 shadow-[0_0_18px_rgba(34,211,238,.12)]' : 'h-9 w-9 '.$type[2].' '.$type[1] }}">
                                @if($isStrategyAccount)<x-heroicon-o-bolt class="h-5 w-5" />
                                @elseif ($type[3] === 'beaker')<x-heroicon-o-beaker class="h-5 w-5" />
                                @elseif ($type[3] === 'chart-bar-square')<x-heroicon-o-chart-bar-square class="h-5 w-5" />
                                @else<x-heroicon-o-briefcase class="h-5 w-5" />@endif
                            </span>
                            <div class="min-w-0">
                                @if($isStrategyAccount)
                                    <p class="text-[9px] font-black uppercase tracking-[.14em] text-cyan-300">{{ __('Aktives Strategiedepot') }}</p>
                                @elseif ($portfolio->type !== 'strategy')
                                    <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $type[0] }}</p>
                                @endif
                                <h2 class="{{ $portfolio->type !== 'strategy' ? 'mt-1' : '' }} truncate font-black {{ $isStrategyAccount ? 'text-lg' : 'text-base' }}">{{ $portfolio->name }}</h2>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if(data_get($portfolio->meta, 'automation.live_enabled', false))<span class="inline-flex items-center gap-1.5 rounded-md border border-cyan-200/40 bg-cyan-300/[.16] px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-cyan-100 shadow-[0_0_12px_rgba(34,211,238,.12)]"><span class="h-1.5 w-1.5 rounded-full bg-cyan-200 shadow-[0_0_6px_rgba(165,243,252,.9)]"></span>{{ __('Strategie') }}</span>@endif
                            @if ($portfolio->is_default)<span class="rounded-md border border-amber-400/30 bg-amber-400/10 px-2 py-1 text-[9px] font-black uppercase text-amber-400">{{ __('Standard') }}</span>@endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 {{ $isStrategyAccount ? 'p-4' : 'p-3' }}">
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] {{ $isStrategyAccount ? 'p-3' : 'p-2.5' }}">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Depotwert') }}</p>
                            <p class="mt-1 font-black tabular-nums {{ $isStrategyAccount ? 'text-xl' : 'text-base' }}">{{ number_format($portfolio->current_value, 2, ',', '.') }} {{ $portfolio->currency }}</p>
                        </div>
                        <div class="rounded-xl bg-[var(--ak-surface-muted)] {{ $isStrategyAccount ? 'p-3' : 'p-2.5' }}">
                            <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kapital') }}</p>
                            <p class="mt-1 font-black tabular-nums text-amber-300 {{ $isStrategyAccount ? 'text-xl' : 'text-base' }}">{{ number_format((float) $portfolio->initial_capital, 2, ',', '.') }} {{ $portfolio->currency }}</p>
                        </div>
                    </div>

                    @if($portfolio->type === 'paper')
                        <div class="{{ $isStrategyAccount ? 'px-4 pb-3' : 'px-3 pb-2' }}">
                            <p class="mb-2 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Aktive Strategien') }}</p>
                            @if($portfolio->strategies->isEmpty())
                                <span class="inline-flex rounded-md border border-amber-300/20 bg-amber-300/[.06] px-2.5 py-1 text-[10px] font-bold text-amber-300">{{ __('Keine Strategie zugeordnet') }}</span>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($portfolio->strategies as $strategy)
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-cyan-300/20 bg-cyan-400/[.07] px-2.5 py-1 text-[10px] font-black text-cyan-200" title="{{ __('Priorität') }} {{ $strategy->pivot->priority }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-cyan-300"></span>{{ $strategy->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-between {{ $isStrategyAccount ? 'px-4 pb-4' : 'px-3 pb-2' }} text-xs">
                        <span class="text-[var(--ak-muted)]">{{ __('Positionen') }}</span>
                        <strong>{{ $portfolio->positions->count() }}</strong>
                    </div>
                    <div class="{{ $isStrategyAccount ? 'mx-4 h-1.5' : 'mx-3 h-1' }} overflow-hidden rounded-full bg-[var(--ak-surface-muted)]">
                        <div class="h-full rounded-full bg-teal-600" style="width: {{ min(100, $portfolio->positions->count() * 10) }}%"></div>
                    </div>

                    <div class="mt-auto flex items-center justify-between gap-2 border-t border-[var(--ak-border)] {{ $isStrategyAccount ? 'px-4 py-3' : 'px-3 py-2' }}">
                        <div class="flex min-w-0 items-center gap-1.5">
                        @if($portfolio->type === 'paper')
                            <button type="button" @click="strategyOpen=true" title="{{ __('Strategien verwalten') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-cyan-300/20 bg-cyan-400/[.07] px-2.5 text-[10px] font-black text-cyan-200"><x-heroicon-o-adjustments-horizontal class="h-4 w-4" /><span class="hidden 2xl:inline">{{ __('Strategien') }}</span></button>
                            <button type="button" @click="capitalOpen=true" title="{{ __('Kapital festlegen') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-teal-300/20 bg-teal-400/[.07] px-2.5 text-teal-300"><x-heroicon-o-banknotes class="h-4 w-4" /></button>
                            <button type="button" @click="resetOpen=true" title="{{ __('Depot zurücksetzen') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-amber-300/20 bg-amber-400/[.07] px-2.5 text-amber-300"><x-heroicon-o-arrow-path class="h-4 w-4" /></button>
                            <button type="button" @click="deleteOpen=true" title="{{ __('Musterdepot löschen') }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-rose-300/20 bg-rose-400/[.07] px-2.5 text-rose-300"><x-heroicon-o-trash class="h-4 w-4" /></button>
                        @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                        @if($portfolio->type === 'paper')
                            <a href="{{ route('depots.show', ['portfolio' => $portfolio, 'return_to' => $paperMode ? 'paper' : 'depots', 'test' => 1]) }}" class="inline-flex h-9 items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-3 text-xs font-black text-white shadow-lg shadow-amber-950/20">
                                <x-heroicon-o-play class="h-4 w-4" />{{ __('Depot testen') }}
                            </a>
                        @endif
                        <a href="{{ route('depots.show', ['portfolio' => $portfolio, 'return_to' => $paperMode ? 'paper' : 'depots']) }}" class="inline-flex h-9 items-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:text-teal-700">
                            {{ __('Öffnen') }}<x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                        </div>
                    </div>

                    @if($portfolio->type === 'paper')
                        <div x-show="strategyOpen" x-cloak class="fixed inset-0 z-[130] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="strategyOpen=false">
                            <div class="isolate w-full max-w-xl rounded-2xl border border-cyan-300/25 p-6 shadow-2xl" style="background-color: rgba(22, 37, 58, 0.90);" @click.outside="strategyOpen=false">
                                <div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-300">{{ __('Musterdepot') }}</p><h2 class="mt-1 text-xl font-black text-white">{{ __('Strategien verwalten') }}</h2><p class="mt-2 text-sm text-slate-300">{{ $portfolio->name }}</p></div><button type="button" @click="strategyOpen=false" class="text-slate-400 hover:text-white"><x-heroicon-o-x-mark class="h-6 w-6" /></button></div>
                                <form id="portfolio-strategies-{{ $portfolio->id }}" method="POST" action="{{ route('depots.strategies.update', $portfolio) }}" class="mt-5 grid gap-2 sm:grid-cols-2">@csrf @method('PUT')
                                    @forelse($availableStrategies as $strategy)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-white/10 bg-slate-950/25 p-3 text-sm font-bold text-slate-200"><input type="checkbox" name="strategies[]" value="{{ $strategy->id }}" @checked($portfolio->strategies->contains('id', $strategy->id)) class="h-4 w-4 rounded border-slate-500 bg-slate-900 text-teal-500 focus:ring-teal-500/30"><span>{{ $strategy->name }}</span></label>
                                    @empty
                                        <p class="sm:col-span-2 rounded-xl border border-amber-300/20 bg-amber-400/[.06] p-4 text-sm text-amber-200">{{ __('Es wurden noch keine Strategien gespeichert.') }}</p>
                                    @endforelse
                                </form>
                                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="strategyOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button type="button" @click="strategyOpen=false; strategyConfirmOpen=true" class="h-10 rounded-lg bg-gradient-to-r from-teal-600 to-cyan-600 px-4 text-xs font-black text-white">{{ __('Auswahl übernehmen') }}</button></div>
                            </div>
                        </div>

                        <div x-show="strategyConfirmOpen" x-cloak class="fixed inset-0 z-[131] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="strategyConfirmOpen=false">
                            <div class="isolate w-full max-w-lg rounded-2xl border border-cyan-300/25 p-6 shadow-2xl" style="background-color: rgba(22, 37, 58, 0.80);"><x-heroicon-o-adjustments-horizontal class="h-9 w-9 text-cyan-300" /><h2 class="mt-4 text-xl font-black text-white">{{ __('Strategiezuordnung ändern?') }}</h2><p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Hinzugefügte Strategien steuern dieses Depot künftig parallel. Entfernte Strategien lösen keine neuen Transaktionen mehr aus; bestehende Positionen und Historien bleiben erhalten.') }}</p><div class="mt-5 flex justify-end gap-2"><button type="button" @click="strategyConfirmOpen=false; strategyOpen=true" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Zurück') }}</button><button type="submit" form="portfolio-strategies-{{ $portfolio->id }}" class="h-10 rounded-lg bg-gradient-to-r from-teal-600 to-cyan-600 px-4 text-xs font-black text-white">{{ __('Zuordnung speichern') }}</button></div></div>
                        </div>

                        <div x-show="capitalOpen" x-cloak class="fixed inset-0 z-[132] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="capitalOpen=false">
                            <form method="POST" action="{{ route('depots.capital.update', $portfolio) }}" class="isolate w-full max-w-lg rounded-2xl border border-teal-300/25 p-6 shadow-2xl" style="background-color: rgba(22, 37, 58, 0.90);" @click.outside="capitalOpen=false">@csrf @method('PUT')
                                <x-heroicon-o-banknotes class="h-9 w-9 text-teal-300" />
                                <h2 class="mt-4 text-xl font-black text-white">{{ __('Kapital festlegen') }}</h2>
                                <p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Das Startkapital bestimmt die verfügbare Kapitalbasis für Simulationen und automatische Depottransaktionen. Das Verrechnungskonto wird um die Differenz angepasst.') }}</p>
                                <label class="mt-5 grid gap-2 text-[10px] font-black uppercase tracking-wide text-slate-300">{{ __('Startkapital') }}
                                    <div class="relative"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" required value="{{ number_format((float) data_get($portfolio->meta, 'automation.initial_capital', 10000), 0, '.', '') }}" class="ak-input h-12 w-full pr-14 text-base font-black tabular-nums"><span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 font-black text-teal-300">{{ $portfolio->currency }}</span></div>
                                </label>
                                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="capitalOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button class="h-10 rounded-lg bg-gradient-to-r from-teal-600 to-cyan-600 px-4 text-xs font-black text-white">{{ __('Kapital speichern') }}</button></div>
                            </form>
                        </div>

                        <div x-show="resetOpen" x-cloak class="fixed inset-0 z-[133] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="resetOpen=false">
                            <form method="POST" action="{{ route('depots.reset', $portfolio) }}" class="isolate w-full max-w-lg rounded-2xl border border-amber-300/25 p-6 shadow-2xl" style="background-color: rgba(22, 37, 58, 0.80);">@csrf<x-heroicon-o-arrow-path class="h-9 w-9 text-amber-300" /><h2 class="mt-4 text-xl font-black text-white">{{ __('Musterdepot zurücksetzen?') }}</h2><p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Alle Positionen, Transaktionen, Kontobuchungen und Simulationsergebnisse werden endgültig gelöscht. Strategien und das Depot selbst bleiben erhalten; das Konto wird auf das Startkapital zurückgesetzt.') }}</p><label class="mt-4 flex gap-3 rounded-xl border border-amber-300/20 bg-amber-400/[.06] p-3 text-sm font-bold text-amber-100"><input required type="checkbox" name="confirm_reset" value="1" class="mt-0.5 h-4 w-4 rounded bg-slate-950 text-amber-500"><span>{{ __('Ich bestätige das Löschen der gesamten Depothistorie.') }}</span></label><div class="mt-5 flex justify-end gap-2"><button type="button" @click="resetOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button class="h-10 rounded-lg bg-gradient-to-r from-amber-500 to-orange-600 px-4 text-xs font-black text-white">{{ __('Depot zurücksetzen') }}</button></div></form>
                        </div>

                        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-[134] grid place-items-center bg-slate-950/85 p-4 backdrop-blur-sm" @keydown.escape.window="deleteOpen=false">
                            <form method="POST" action="{{ route('depots.destroy', $portfolio) }}" class="isolate w-full max-w-lg rounded-2xl border border-rose-300/30 p-6 shadow-2xl" style="background-color: rgba(22, 37, 58, 0.80);">@csrf @method('DELETE')<x-heroicon-o-trash class="h-9 w-9 text-rose-300" /><h2 class="mt-4 text-xl font-black text-rose-100">{{ __('Musterdepot endgültig löschen?') }}</h2><p class="mt-3 text-sm leading-6 text-slate-200">{{ __('Das Depot, seine Strategiezuordnungen, Positionen, Transaktionen, Kontobuchungen und Berichte werden unwiderruflich gelöscht. Die Strategien selbst bleiben im Strategie Manager erhalten.') }}</p><label class="mt-4 flex gap-3 rounded-xl border border-rose-300/25 bg-rose-400/[.08] p-3 text-sm font-bold text-rose-100"><input required type="checkbox" name="confirm_delete" value="1" class="mt-0.5 h-4 w-4 rounded bg-slate-950 text-rose-500"><span>{{ __('Ich bestätige die endgültige Löschung dieses Musterdepots.') }}</span></label><div class="mt-5 flex justify-end gap-2"><button type="button" @click="deleteOpen=false" class="h-10 rounded-lg border border-white/10 px-4 text-xs font-black text-slate-300">{{ __('Abbrechen') }}</button><button class="h-10 rounded-lg bg-gradient-to-r from-rose-600 to-red-700 px-4 text-xs font-black text-white">{{ __('Endgültig löschen') }}</button></div></form>
                        </div>
                    @endif
                </article>
            @endforeach

            @if ($portfolios->isEmpty())
                @php
                    $starterDepots = $paperMode
                        ? []
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
                @if ($paperMode)
                    <article class="col-span-full mx-auto w-full max-w-3xl overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
                        <div class="flex items-center gap-3 border-b border-[var(--ak-border)] px-5 py-4">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-amber-400/25 bg-amber-400/10 text-amber-400"><x-heroicon-o-plus class="h-5 w-5" /></span>
                            <div><p class="text-[9px] font-black uppercase tracking-[.14em] text-amber-400">{{ __('Neues Musterdepot') }}</p><h2 class="mt-1 text-lg font-black">{{ __('Virtuelles Depot einrichten') }}</h2></div>
                        </div>
                        <form method="POST" action="{{ route('depots.store') }}" class="grid gap-4 p-5 md:grid-cols-2">
                            @csrf
                            <input type="hidden" name="type" value="paper">
                            <label class="grid gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Name') }}<input name="name" value="{{ old('name') }}" required maxlength="80" placeholder="{{ __('z. B. Momentum Europa') }}" class="ak-input h-11 text-sm normal-case"></label>
                            <label class="grid gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Währung') }}<select name="currency" class="ak-input h-11 text-sm normal-case"><option>EUR</option><option>USD</option><option>CHF</option><option>GBP</option></select></label>
                            <label class="grid gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Startkapital') }}<div class="relative"><input name="initial_capital" type="number" min="1000" max="1000000" step="100" value="{{ old('initial_capital', 10000) }}" required class="ak-input h-11 pr-10 text-sm normal-case tabular-nums"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-black text-amber-400">&euro;</span></div></label>
                            <label class="grid gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kosten je Trade') }}<div class="relative"><input name="trade_cost" type="number" min="0" max="1000" step="0.01" value="{{ old('trade_cost', 10) }}" required class="ak-input h-11 pr-10 text-sm normal-case tabular-nums"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-black text-amber-400">&euro;</span></div></label>
                            <label class="grid gap-1.5 text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)] md:col-span-2">{{ __('Beschreibung') }}<textarea name="description" maxlength="500" rows="2" placeholder="{{ __('Ziel und Regeln dieses Musterdepots') }}" class="ak-input text-sm font-normal normal-case">{{ old('description') }}</textarea></label>
                            <div class="md:col-span-2 flex items-center justify-between gap-4 border-t border-[var(--ak-border)] pt-4"><p class="text-[10px] text-[var(--ak-muted)]">{{ __('Das Musterdepot verwendet ausschließlich virtuelles Kapital.') }}</p><button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-teal-700 px-5 text-xs font-black text-white hover:bg-teal-600"><x-heroicon-o-plus class="h-4 w-4" />{{ __('Musterdepot anlegen') }}</button></div>
                        </form>
                    </article>
                @endif
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
                <div x-data="{ open: false }" x-show="!selected" x-transition.opacity.duration.1000ms class="{{ $paperMode ? 'min-h-52 p-3' : 'min-h-72 p-5' }} rounded-2xl border border-dashed border-[var(--ak-border)] bg-[var(--ak-card)]">
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
                        @if ($paperMode)
                            <div class="grid grid-cols-2 gap-2">
                                <input name="initial_capital" type="number" min="1000" max="1000000" step="100" value="10000" required placeholder="{{ __('Startkapital') }}" class="ak-input h-10 text-sm">
                                <input name="trade_cost" type="number" min="0" max="1000" step="0.01" value="10" required placeholder="{{ __('Kosten') }}" class="ak-input h-10 text-sm">
                            </div>
                        @endif
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

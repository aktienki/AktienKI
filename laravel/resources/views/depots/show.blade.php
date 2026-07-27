<x-app-layout>
    <div class="flex min-h-[calc(100dvh-89px)] flex-col py-4 text-[var(--ak-text)]">
        <div class="mb-4 flex shrink-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-amber-300/25 bg-amber-300/[.08] text-amber-300">
                    <x-heroicon-o-briefcase class="h-6 w-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-teal-700">{{ __('Depot') }}</p>
                    <h1 class="mt-1 truncate text-2xl font-black tracking-tight">{{ $portfolio->name }}</h1>
                    <p class="mt-1 truncate text-sm text-[var(--ak-muted)]">{{ $portfolio->description ?: __('Positionen und Entwicklung deines Depots.') }}</p>
                </div>
            </div>

            <a href="{{ $backUrl }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 text-xs font-black text-[var(--ak-muted)] transition hover:border-teal-500/35 hover:bg-teal-500/10 hover:text-teal-700">
                <x-heroicon-o-arrow-left class="h-4 w-4" />{{ $backLabel }}
            </a>
        </div>

        <section class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                [__('Depotwert'), number_format($currentValue, 2, ',', '.').' '.$portfolio->currency, 'text-[var(--ak-text)]'],
                [__('Investiert'), number_format($invested, 2, ',', '.').' '.$portfolio->currency, 'text-[var(--ak-text)]'],
                [__('Performance'), ($performance > 0 ? '+' : '').number_format($performance, 2, ',', '.').' %', $performance > 0 ? 'text-emerald-400' : ($performance < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]')],
                [__('Positionen'), $portfolio->positions->count(), 'text-teal-700'],
            ] as [$label, $value, $class])
                <div class="rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-4 shadow-[var(--ak-shadow)]">
                    <p class="text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $label }}</p>
                    <p class="mt-2 text-xl font-black tabular-nums {{ $class }}">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <div class="flex items-center justify-between border-b border-[var(--ak-border)] px-4 py-3">
                <div>
                    <h2 class="font-black">{{ __('Depotpositionen') }}</h2>
                    <p class="mt-0.5 text-xs text-[var(--ak-muted)]">{{ __('Aktien, Einstiegskurse und aktuelle Entwicklung.') }}</p>
                </div>
                <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1.5 text-[10px] font-black text-[var(--ak-muted)]">{{ $portfolio->currency }}</span>
            </div>

            @if ($portfolio->positions->isEmpty())
                <div class="grid min-h-64 place-items-center p-8 text-center">
                    <div>
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] text-[var(--ak-muted)]"><x-heroicon-o-chart-bar-square class="h-6 w-6" /></span>
                        <h3 class="mt-4 font-black">{{ __('Noch keine Positionen vorhanden') }}</h3>
                        <p class="mt-2 max-w-md text-sm text-[var(--ak-muted)]">{{ __('Im nächsten Schritt können Aktien mit Stückzahl und Einstiegskurs zu diesem Depot hinzugefügt werden.') }}</p>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ak-stocks-table w-full min-w-[820px] text-left text-xs">
                        <thead><tr>
                            @foreach ([__('Aktie'), __('Stückzahl'), __('Einstieg'), __('Aktueller Kurs'), __('Wert'), __('Performance')] as $heading)
                                <th class="border-b border-[var(--ak-border)] px-4 py-3 text-[10px] font-black uppercase tracking-wide">{{ $heading }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($portfolio->positions as $position)
                                @php
                                    $entry = (float) $position->average_buy_price;
                                    $current = (float) ($position->current_price ?? $entry);
                                    $value = (float) $position->quantity * $current;
                                    $positionPerformance = $entry > 0 ? (($current - $entry) / $entry) * 100 : 0;
                                @endphp
                                <tr>
                                    <td class="px-4 py-3"><a href="{{ route('stocks.show', $position->instrument->symbol) }}" class="font-black text-teal-700">{{ $position->instrument->symbol }}</a><p class="mt-0.5 text-[10px] text-[var(--ak-muted)]">{{ $position->instrument->name }}</p></td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($position->quantity, 4, ',', '.') }}</td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($entry, 2, ',', '.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3 font-bold tabular-nums">{{ number_format($current, 2, ',', '.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3 font-black tabular-nums">{{ number_format($value, 2, ',', '.') }} {{ $portfolio->currency }}</td>
                                    <td class="px-4 py-3 font-black tabular-nums {{ $positionPerformance > 0 ? 'text-emerald-400' : ($positionPerformance < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]') }}">{{ $positionPerformance > 0 ? '+' : '' }}{{ number_format($positionPerformance, 2, ',', '.') }} %</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>

<x-app-layout>
    @php
        $outlook = strtoupper((string) ($analysis?->market_outlook ?? 'NEUTRAL'));
        $outlookClass = match ($outlook) {
            'BULLISH' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-500',
            'BEARISH' => 'border-rose-500/25 bg-rose-500/10 text-rose-500',
            default => 'border-amber-500/25 bg-amber-500/10 text-amber-500',
        };
        $itemText = function (mixed $item): string {
            if (is_string($item) || is_numeric($item)) return (string) $item;
            if (!is_array($item)) return '';
            return (string) ($item['summary'] ?? $item['description'] ?? $item['name'] ?? $item['title'] ?? collect($item)->filter(fn ($value) => is_scalar($value))->implode(' · '));
        };
    @endphp

    <div class="ak-body min-h-[calc(100vh-73px)] pb-8">
        <div class="sticky top-[73px] z-30 border-b border-[var(--ak-border)] bg-[var(--ak-bg)]/95 py-4 backdrop-blur-xl">
            <div class="ak-container flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-teal-500">aKI Market Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Tägliche Marktanalyse') }}</h1>
                </div>
                @if ($analysis)
                    <div class="flex items-center gap-2">
                        <span class="rounded-xl border px-3 py-2 text-xs font-black {{ $outlookClass }}">{{ __($outlook) }}</span>
                        <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                            {{ \Carbon\Carbon::parse($analysis->analysis_date)->format('d.m.Y') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <main class="ak-container mt-5 space-y-4">
            @if ($analysis)
                <section class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        [__('Chancen'), $analysis->opportunities, 'bg-emerald-500', 'text-emerald-500', true],
                        [__('Risiken'), $analysis->risks, 'bg-rose-500', 'text-rose-500', true],
                        [__('Beobachtungsliste'), $analysis->watchlist, 'bg-amber-500', 'text-amber-500', true],
                    ] as [$title, $items, $dotClass, $titleClass, $compactCopy])
                        <article class="ak-card ak-card-static min-h-[320px] p-5">
                            <h2 class="text-lg font-black {{ $titleClass }}">{{ $title }}</h2>
                            <ul class="mt-5 grid gap-3">
                                @forelse ($items as $key => $item)
                                    <li @class([
                                        'ak-analysis-copy flex gap-2.5 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3',
                                        'text-xs leading-[1.35]' => $compactCopy,
                                        'text-sm leading-6' => ! $compactCopy,
                                    ])>
                                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dotClass }}"></span>
                                        <span>
                                            @if (!is_numeric($key))<strong class="text-[var(--ak-text)]">{{ __((string) $key) }}: </strong>@endif
                                            {{ $itemText($item) }}
                                        </span>
                                    </li>
                                @empty
                                    <li class="text-xs text-[var(--ak-muted)]">—</li>
                                @endforelse
                            </ul>
                        </article>
                    @endforeach
                </section>
            @else
                <section class="ak-card grid min-h-[360px] place-items-center text-center">
                    <div>
                        <x-heroicon-o-globe-alt class="mx-auto h-12 w-12 text-teal-500" />
                        <h2 class="mt-4 text-lg font-black text-[var(--ak-text)]">{{ __('Noch keine tägliche Marktanalyse vorhanden') }}</h2>
                    </div>
                </section>
            @endif
        </main>
    </div>

    <style>
        .ak-analysis-copy {
            color: color-mix(in srgb, var(--ak-text) 86%, var(--ak-muted) 14%);
        }
    </style>
</x-app-layout>

<x-app-layout>
    @php
        $outlook = strtoupper((string) ($analysis?->market_outlook ?? 'NEUTRAL'));
        $outlookClasses = match ($outlook) {
            'BULLISH' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-500',
            'BEARISH' => 'border-rose-500/25 bg-rose-500/10 text-rose-500',
            default => 'border-amber-500/25 bg-amber-500/10 text-amber-500',
        };
    @endphp

    <div class="ak-body min-h-[calc(100vh-73px)]">
        <div class="sticky top-[73px] z-30 border-b border-[var(--ak-border)] bg-[var(--ak-bg)]/95 py-4 backdrop-blur-xl">
            <div class="ak-container flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-teal-500">aKI Market Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Markteinschätzung') }}</h1>
                </div>
                @if ($analysis)
                    <span class="rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                        {{ \Carbon\Carbon::parse($analysis->analysis_date)->format('d.m.Y') }}
                    </span>
                @endif
            </div>
        </div>

        <main class="ak-container py-6">
            @if ($analysis)
                <article class="ak-card mx-auto max-w-5xl p-6 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-teal-500/20 bg-teal-500/10 text-teal-500">
                                <x-heroicon-o-sparkles class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-500">{{ __('aKI Tageskommentar') }}</p>
                                <h2 class="mt-2 text-xl font-black leading-tight text-[var(--ak-text)] sm:text-2xl">{{ $analysis->headline }}</h2>
                            </div>
                        </div>
                        <span class="rounded-xl border px-3 py-2 text-xs font-black {{ $outlookClasses }}">{{ __($outlook) }}</span>
                    </div>

                    <div class="mt-6 border-t border-[var(--ak-border)] pt-6">
                        <p class="text-sm leading-7 text-[var(--ak-muted)] sm:text-base">
                            {{ $analysis->executive_summary }}
                        </p>
                    </div>

                    <div class="mt-7 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-[var(--ak-border)] pt-4 text-[10px] font-bold uppercase tracking-wide text-[var(--ak-muted)]">
                        <span>{{ __('Modell') }}: {{ $analysis->model }}</span>
                        <span>{{ __('Konfidenz') }}: {{ $analysis->confidence }} %</span>
                        <span>{{ __('Keine Anlageberatung') }}</span>
                    </div>
                </article>
            @else
                <section class="ak-card mx-auto grid min-h-[320px] max-w-5xl place-items-center text-center">
                    <div>
                        <x-heroicon-o-sparkles class="mx-auto h-11 w-11 text-teal-500" />
                        <h2 class="mt-4 text-lg font-black text-[var(--ak-text)]">{{ __('Noch kein Tageskommentar vorhanden') }}</h2>
                    </div>
                </section>
            @endif
        </main>
    </div>
</x-app-layout>

<x-app-layout>
    <x-detail-page-theme />
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

    <div class="ak-body ak-detail-design min-h-[calc(100vh-73px)] pb-8">
        <div class="ak-container pt-4">
            <div class="ak-detail-hero sticky top-[77px] z-30 flex flex-wrap items-center justify-between gap-4 rounded-[1.5rem] border border-[var(--ak-border)] px-5 py-4 backdrop-blur-xl">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-teal-400/30 bg-teal-400/10 text-teal-500"><x-heroicon-o-scale class="h-6 w-6" /></span>
                    <div>
                    <p class="text-[10px] font-black uppercase tracking-[.2em] text-teal-500">aKI Market Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Chancen & Risiken') }}</h1>
                    <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Das aktuelle globale Chancen-Risiko-Profil kompakt eingeordnet.') }}</p>
                    </div>
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
                <section class="grid items-stretch gap-5 md:grid-cols-3">
                    @foreach ([
                        [__('Chancen'), $analysis->opportunities, 'opportunity', 'bg-emerald-500', 'text-emerald-500', '↗'],
                        [__('Risiken'), $analysis->risks, 'risk', 'bg-rose-500', 'text-rose-500', '!'],
                        [__('Beobachtungsliste'), $analysis->watchlist, 'watch', 'bg-amber-500', 'text-amber-500', '◉'],
                    ] as [$title, $items, $tone, $dotClass, $titleClass, $symbol])
                        <article class="ak-analysis-panel ak-analysis-panel-{{ $tone }} ak-detail-panel ak-standard-card ak-card ak-card-static min-h-[320px] overflow-hidden p-5">
                            <div class="ak-analysis-card-head ak-detail-card-head -mx-5 -mt-5 flex items-center justify-between gap-3 px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="ak-analysis-icon grid h-9 w-9 place-items-center rounded-xl border text-lg font-black {{ $titleClass }}">{{ $symbol }}</span>
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-[.18em] text-[var(--ak-muted)]">{{ __('Markteinschätzung') }}</p>
                                        <h2 class="mt-0.5 text-lg font-black {{ $titleClass }}">{{ $title }}</h2>
                                    </div>
                                </div>
                                <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2.5 py-1 text-[10px] font-black tabular-nums text-[var(--ak-muted)]">{{ collect($items)->count() }}</span>
                            </div>
                            <ul class="mt-4 grid gap-2.5">
                                @forelse ($items as $key => $item)
                                    <li class="ak-analysis-copy flex gap-3 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3 text-xs leading-[1.45]">
                                        <span class="ak-analysis-number grid h-6 w-6 shrink-0 place-items-center rounded-lg text-[10px] font-black {{ $titleClass }}">{{ $loop->iteration }}</span>
                                        <span class="pt-0.5">
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
                <section class="ak-detail-panel ak-standard-card ak-card grid min-h-[360px] place-items-center overflow-hidden text-center">
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

        .ak-analysis-panel {
            border-color: color-mix(in srgb, var(--ak-border) 76%, var(--analysis-accent) 24%) !important;
        }

        .ak-analysis-panel::before {
            background: linear-gradient(90deg, transparent, var(--analysis-accent), transparent) !important;
        }

        .ak-analysis-panel-opportunity { --analysis-accent: #10b981; }
        .ak-analysis-panel-risk { --analysis-accent: #f43f5e; }
        .ak-analysis-panel-watch { --analysis-accent: #eab308; }

        .ak-analysis-panel .ak-analysis-card-head {
            border-bottom-color: color-mix(in srgb, var(--analysis-accent) 30%, var(--ak-border)) !important;
            background:
                radial-gradient(circle at 4% 0, color-mix(in srgb, var(--analysis-accent) 19%, transparent), transparent 42%),
                linear-gradient(108deg, color-mix(in srgb, var(--analysis-accent) 13%, transparent), transparent 76%) !important;
        }

        .ak-analysis-icon,
        .ak-analysis-number {
            border-color: color-mix(in srgb, var(--analysis-accent) 35%, var(--ak-border));
            background: color-mix(in srgb, var(--analysis-accent) 10%, transparent);
        }

        .ak-analysis-panel .ak-analysis-copy {
            box-shadow: inset 3px 0 0 color-mix(in srgb, var(--analysis-accent) 48%, transparent);
        }

        :root[data-theme="light"] .ak-analysis-panel .ak-analysis-copy {
            background: color-mix(in srgb, #fff 92%, var(--analysis-accent) 8%);
        }
    </style>
</x-app-layout>

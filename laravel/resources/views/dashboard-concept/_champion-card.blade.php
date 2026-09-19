@php
    $details = $champion['details'];
    $factors = $champion['factors'];
    $indicators = $champion['indicators'];
    $review = $champion['externalReview'] ?? null;
    $externalTileTone = match ($factors['external_verdict'] ?? null) {
        'NO_OBJECTION' => "text-{$champion['color']}-500",
        'CAUTION' => 'text-amber-500',
        'OBJECTION' => 'text-rose-500',
        default => 'text-[var(--ak-muted)]',
    };
@endphp
<a href="{{ $champion['url'] }}" class="concept-card relative block p-4 hover:border-{{ $champion['color'] }}-400">
    @if($champion['date'])
        <small class="absolute right-4 top-4 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($champion['date'])->format('d.m.Y') }}</small>
    @endif

    <div class="flex items-center gap-2 -mx-4 -mt-4 mb-3 pl-4 pr-14 pt-4 pb-3 rounded-t-[1.25rem] border-b border-[var(--ak-border)] bg-[var(--ak-surface-muted)]">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-{{ $champion['color'] }}-500/10 text-{{ $champion['color'] }}-500">
            <x-dynamic-component :component="$champion['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <b class="block text-[13px] font-black text-[var(--ak-text)]">{{ $champion['label'] }}</b>
            <small class="block text-[9px] text-[var(--ak-muted)]">{{ $champion['subtitle'] }}</small>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div>
            <div class="flex items-center gap-1.5 mb-2">
                <span class="text-lg leading-none shrink-0">{{ $details['country_flag'] }}</span>
                <span class="min-w-0 flex-1 truncate text-[16px] font-black text-[var(--ak-text)]">{{ $champion['name'] }}</span>
            </div>
            @if($details['sector'])
                <div class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)] mb-2">{{ $details['sector'] }}</div>
            @endif

            <div class="mb-3 rounded-lg bg-{{ $champion['color'] }}-500/[.08] px-3 py-2.5">
                <div class="text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $champion['metric_label'] }}</div>
                <div class="flex items-baseline gap-3">
                    <span class="text-[22px] font-black text-{{ $champion['color'] }}-500">{{ $champion['metric_value'] }}</span>
                    @if($details['current_price'])
                        <span class="text-[11px] text-[var(--ak-muted)]">{{ number_format($details['current_price'], 2, ',', '.') }} {{ $details['currency'] }}</span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-3 gap-1.5 text-[9px]">
                <div class="rounded border border-[var(--ak-border)] px-2 py-1.5">
                    <span class="block text-[var(--ak-muted)]">{{ __('Internes Modell') }}</span>
                    <span class="font-black text-{{ $champion['color'] }}-500">{{ $factors['internal_score'] !== null ? number_format($factors['internal_score'], 0, ',', '.') : '–' }}</span>
                </div>
                <div class="rounded border border-[var(--ak-border)] px-2 py-1.5">
                    <span class="block text-[var(--ak-muted)]">{{ __('Externe Bestätigung') }}</span>
                    <span class="font-black {{ $externalTileTone }}">{{ $factors['external_confidence'] !== null ? number_format($factors['external_confidence'], 0, ',', '.').'%' : '–' }}</span>
                </div>
                <div class="rounded border border-[var(--ak-border)] px-2 py-1.5">
                    <span class="block text-[var(--ak-muted)]">{{ __('Panel-Perzentil') }}</span>
                    <span class="font-black text-{{ $champion['color'] }}-500">{{ $factors['panel_percentile'] !== null ? number_format($factors['panel_percentile'], 0, ',', '.').'%' : '–' }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-[var(--ak-border)] p-2.5">
                <div class="mb-1.5 text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Kursverlauf (60 Tage)') }}</div>
                @if($champion['sparkline'])
                    <svg viewBox="0 0 100 32" class="h-14 w-full overflow-visible" preserveAspectRatio="none">
                        <polyline points="{{ $champion['sparkline'] }}" fill="none" stroke="currentColor" stroke-width="1.25" class="text-cyan-500 drop-shadow-[0_0_4px_rgba(34,211,238,0.65)]" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                @else
                    <div class="flex h-14 items-center justify-center text-[9px] italic text-[var(--ak-muted)]">{{ __('Keine Daten') }}</div>
                @endif
            </div>
            <div class="rounded-lg border border-[var(--ak-border)] p-2.5">
                <div class="mb-1.5 text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Technische Indikatoren') }}</div>
                @if($indicators)
                    <div class="grid grid-cols-3 gap-1 text-[9px]">
                        <div>
                            <span class="block text-[var(--ak-muted)]">{{ __('RSI') }}</span>
                            <span class="font-bold text-[var(--ak-text)]">{{ $indicators['rsi'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[var(--ak-muted)]">{{ __('MACD') }}</span>
                            <span class="font-bold {{ $indicators['macd_bullish'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $indicators['macd'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[var(--ak-muted)]">{{ __('Trend') }}</span>
                            <span class="font-bold {{ $indicators['trend_bullish'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $indicators['trend'] }}</span>
                        </div>
                    </div>
                @else
                    <div class="flex h-14 items-center justify-center text-[9px] italic text-[var(--ak-muted)]">{{ __('Keine Daten') }}</div>
                @endif
            </div>

            @if($review)
                @php
                    [$reviewLabel, $reviewTone] = match ($review['verdict']) {
                        'NO_OBJECTION' => [__('Kein Einwand'), 'border-emerald-400/35 bg-emerald-400/10 text-emerald-500'],
                        'CAUTION' => [__('Vorsicht'), 'border-amber-400/35 bg-amber-400/10 text-amber-500'],
                        'OBJECTION' => [__('Einwand'), 'border-rose-400/35 bg-rose-400/10 text-rose-500'],
                        default => [__('Unklar'), 'border-slate-400/25 bg-slate-400/10 text-[var(--ak-muted)]'],
                    };
                @endphp
                <div class="rounded-lg border border-[var(--ak-border)] p-2.5 sm:col-span-2">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <span class="rounded border px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide {{ $reviewTone }}">{{ $reviewLabel }}</span>
                        <span class="text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Externe Bewertung') }}</span>
                    </div>
                    @if($review['summary'])
                        <p class="text-[9px] leading-4 text-[var(--ak-muted)]">{{ $review['summary'] }}</p>
                    @endif
                    <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[9px]">
                        @if(count($review['positive_factors']))
                            <span><span class="font-black text-emerald-500">{{ __('Positiv') }}:</span> {{ implode(' · ', array_slice($review['positive_factors'], 0, 2)) }}</span>
                        @endif
                        @if(count($review['risk_factors']))
                            <span><span class="font-black text-rose-500">{{ __('Risiko') }}:</span> {{ implode(' · ', array_slice($review['risk_factors'], 0, 2)) }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</a>

@php $wrapper = $highlight['url'] ? 'a' : 'div'; $details = $highlight['details'] ?? null; @endphp
<{{ $wrapper }} @if($highlight['url']) href="{{ $highlight['url'] }}" @endif class="concept-card relative flex h-full flex-col p-4 hover:border-{{ $highlight['color'] }}-400">
    @if($highlight['date'] ?? null)
        <small class="absolute right-3 top-3 text-[8px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ \Illuminate\Support\Carbon::parse($highlight['date'])->format('d.m.Y') }}</small>
    @endif
    <div class="flex items-center gap-2 -mx-4 -mt-4 mb-3 pl-4 pr-14 pt-4 pb-3 rounded-t-[1.25rem] border-b border-[var(--ak-border)] bg-[var(--ak-surface-muted)]">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-{{ $highlight['color'] }}-500/10 text-{{ $highlight['color'] }}-500">
            <x-dynamic-component :component="$highlight['icon']" class="h-4.5 w-4.5" />
        </span>
        <div class="min-w-0">
            <b class="block text-[12px] font-black text-[var(--ak-text)]">{{ $highlight['label'] }}</b>
            <small class="block text-[9px] text-[var(--ak-muted)]">{{ $highlight['subtitle'] }}</small>
        </div>
    </div>

    @if(($highlight['kind'] ?? null) === 'upcoming-events')
        @if(count($highlight['events']) === 0)
            <div class="flex flex-1 items-center justify-center text-[9px] text-[var(--ak-muted)] italic">{{ __('Aktuell keine Termine') }}</div>
        @endif
        <div class="grid gap-1.5">
            @foreach($highlight['events'] as $event)
                <div
                    x-data="{
                        open: false,
                        enabled: {{ $event['reminder_enabled'] ? 'true' : 'false' }},
                        saving: false,
                        async toggle() {
                            this.saving = true;
                            try {
                                const response = await fetch('{{ route('calendar-event-reminders.toggle') }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content || '' },
                                    body: JSON.stringify({ event_type: '{{ $event['reminder_type'] }}', reference_id: {{ $event['reference_id'] }}, instrument_id: {{ $event['instrument_id'] ?? 'null' }}, event_date: '{{ $event['date'] }}' }),
                                });
                                if (response.ok) { const payload = await response.json(); this.enabled = payload.enabled; this.open = false; }
                            } finally { this.saving = false; }
                        },
                    }"
                    class="relative flex items-center gap-2 rounded-lg border border-[var(--ak-border)] px-2.5 py-2"
                >
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md {{ $event['type'] === 'earnings' ? 'bg-cyan-400/10 text-cyan-500' : 'bg-amber-400/10 text-amber-500' }}">
                        @if($event['type'] === 'earnings')
                            <x-heroicon-o-chart-bar class="h-3.5 w-3.5" />
                        @else
                            <x-heroicon-o-banknotes class="h-3.5 w-3.5" />
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <b class="block truncate text-[10px] text-[var(--ak-text)]">{{ $event['symbol'] }} · {{ $event['label'] }}</b>
                        <small class="block truncate text-[8px] text-[var(--ak-muted)]">{{ $event['schedule'] }}</small>
                    </span>
                    <button
                        type="button"
                        @click="open = true"
                        :class="enabled ? 'text-cyan-500' : 'text-[var(--ak-muted)]'"
                        class="grid h-6 w-6 shrink-0 place-items-center rounded-md hover:text-cyan-400"
                        :title="enabled ? '{{ __('Erinnerung aktiv') }}' : '{{ __('Erinnerung inaktiv') }}'"
                    >
                        <x-heroicon-o-bell class="h-3.5 w-3.5" x-show="!enabled" x-cloak />
                        <x-heroicon-s-bell class="h-3.5 w-3.5" x-show="enabled" x-cloak />
                    </button>

                    <div x-show="open" x-cloak @click.self="open = false" class="ak-modal-overlay fixed inset-0 z-[190] grid place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
                        <div class="ak-modal-panel w-full max-w-xs rounded-2xl border border-cyan-400/35 bg-[var(--ak-card)] p-4 text-[var(--ak-text)] shadow-2xl" @click.stop>
                            <p class="text-sm font-black">{{ $event['symbol'] }} · {{ $event['label'] }}</p>
                            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ $event['schedule'] }}</p>
                            <p class="mt-2 text-[10px] leading-4 text-[var(--ak-muted)]">
                                @if($event['type'] === 'earnings')
                                    {{ __('E-Mail-Erinnerung am Vortag der Quartalszahlen.') }}
                                @else
                                    {{ __('E-Mail-Erinnerung am Tag des geplanten Verkaufs.') }}
                                @endif
                            </p>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" @click="open = false" class="rounded-lg border border-[var(--ak-border)] px-3 py-1.5 text-[10px] font-black text-[var(--ak-muted)]">{{ __('Schließen') }}</button>
                                <button type="button" @click="toggle()" :disabled="saving" class="rounded-lg px-3 py-1.5 text-[10px] font-black text-slate-950 disabled:opacity-50" :class="enabled ? 'bg-rose-400' : 'bg-cyan-400'">
                                    <span x-show="!saving" x-text="enabled ? '{{ __('Erinnerung deaktivieren') }}' : '{{ __('Erinnerung aktivieren') }}'"></span>
                                    <span x-show="saving" x-cloak>{{ __('Speichert …') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif(($highlight['kind'] ?? null) === 'indicators')
        @php $indicators = $highlight['indicators'] ?? null; @endphp
        @if($indicators)
            @if($highlight['name'] ?? null)
                <div class="mb-2 truncate text-[13px] font-black text-[var(--ak-text)]">{{ $highlight['name'] }}</div>
            @endif
            @if(is_numeric($highlight['rise_probability'] ?? null))
                <div class="mb-2 rounded-lg bg-{{ $highlight['color'] }}-500/10 px-2.5 py-1.5">
                    <span class="block text-[8px] text-{{ $highlight['color'] }}-600">{{ __('Wahrscheinlichkeit steigender Kurs (20T)') }}</span>
                    <span class="font-black text-{{ $highlight['color'] }}-500">{{ number_format((float) $highlight['rise_probability'], 1) }}%</span>
                </div>
            @endif
            <div class="mb-2 text-[8px] text-[var(--ak-muted)]">{{ __('Stand: :date', ['date' => \Illuminate\Support\Carbon::parse($indicators['as_of'])->format('d.m.Y')]) }}</div>
            <div class="grid grid-cols-3 gap-1.5 text-[9px]">
                <div class="rounded border border-[var(--ak-border)] px-2 py-1.5">
                    <span class="block text-[var(--ak-muted)]">{{ __('Relative Stärke (14 Tage)') }}</span>
                    <span class="font-bold text-[var(--ak-text)]">{{ $indicators['rsi'] }}</span>
                    <span class="block text-[8px] text-[var(--ak-muted)]">{{ $indicators['rsi_state'] }}</span>
                </div>
                <div class="rounded border border-[var(--ak-border)] px-2 py-1.5">
                    <span class="block text-[var(--ak-muted)]">{{ __('Trendfolge-Indikator') }}</span>
                    <span class="font-bold {{ $indicators['macd_bullish'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $indicators['macd'] }}</span>
                    <span class="block text-[8px] text-[var(--ak-muted)]">{{ $indicators['macd_bullish'] ? __('Bullisch') : __('Bärisch') }}</span>
                </div>
                <div class="rounded border border-[var(--ak-border)] px-2 py-1.5">
                    <span class="block text-[var(--ak-muted)]">{{ __('Kurstrend') }}</span>
                    <span class="font-bold {{ $indicators['trend_bullish'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $indicators['trend'] }}</span>
                    <span class="block text-[8px] text-[var(--ak-muted)]">{{ __('20T vs. 50T') }}</span>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center text-[9px] text-[var(--ak-muted)] italic">{{ __('Keine Daten heute') }}</div>
        @endif
    @elseif($highlight['data'] && $details)
        <div class="flex items-center gap-1.5 mb-2">
            <span class="text-base leading-none shrink-0">{{ $details['country_flag'] }}</span>
            <span class="min-w-0 flex-1 truncate text-[13px] font-black text-[var(--ak-text)]">{{ $highlight['name'] ?? explode(' ', $highlight['data'])[0] }}</span>
        </div>
        @if($details['sector'])
            <div class="text-[9px] uppercase tracking-wide text-[var(--ak-muted)] mb-2">{{ $details['sector'] }}</div>
        @endif

        <div class="mb-2 rounded-lg bg-{{ $highlight['color'] }}-500/[.08] px-2.5 py-2">
            <div class="flex items-center justify-between">
                <div class="text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $highlight['metric_label'] }}</div>
                @if(($highlight['compositeScore'] ?? null) !== null)
                    <div class="text-[8px] uppercase tracking-wide text-[var(--ak-muted)]">{{ __('Score') }} <span class="font-black text-{{ $highlight['color'] }}-500">{{ number_format($highlight['compositeScore'], 0, ',', '.') }}</span></div>
                @endif
            </div>
            <div class="text-[15px] font-black text-{{ $highlight['color'] }}-500">{{ $highlight['metric_value'] }}</div>
        </div>

        <div class="grid grid-cols-3 gap-1.5 mb-2 text-[9px]">
            @if($details['current_price'])
                <div class="rounded border border-[var(--ak-border)] px-2 py-1">
                    <span class="block text-[var(--ak-muted)]">{{ __('Kurs') }}</span>
                    <span class="font-bold text-[var(--ak-text)]">{{ number_format($details['current_price'], 2, ',', '.') }} {{ $details['currency'] }}</span>
                </div>
            @endif
            @if($details['risk'] !== null)
                <div class="rounded border border-[var(--ak-border)] px-2 py-1">
                    <span class="block text-[var(--ak-muted)]">{{ __('Risiko') }}</span>
                    <span class="font-bold text-[var(--ak-text)]">{{ $details['risk'] }}/10</span>
                </div>
            @endif
            @if($details['confidence'] !== null)
                <div class="rounded border border-[var(--ak-border)] px-2 py-1">
                    <span class="block text-[var(--ak-muted)]">{{ __('Konfidenz') }}</span>
                    <span class="font-bold text-[var(--ak-text)]">{{ $details['confidence'] }}%</span>
                </div>
            @endif
        </div>

        @if($highlight['analog'])
            @php
                $analog = $highlight['analog'];
                $daysAgo = (int) round(\Illuminate\Support\Carbon::parse($analog['signal_date'])->diffInDays(now()));
                $outcomeClass = $analog['outcome_pct'] >= 0 ? 'text-emerald-500' : 'text-rose-500';
            @endphp
            {{-- A <div>, not <a>: this sits inside the card's own link (nested
                 anchors are invalid HTML), and the analog's stock can differ
                 from the card's own, so it isn't safe to just reuse the outer
                 href either. --}}
            <div class="mb-2 flex items-center gap-2 rounded-lg border border-[var(--ak-border)] px-2 py-1.5">
                <span class="min-w-0 flex-1 text-[9px] leading-4 text-[var(--ak-muted)]">
                    <span class="text-[var(--ak-text)]">•</span>
                    @if($analog['type'] === 'same_stock')
                        {{ __('Ähnlicher Fall vor :days Tagen', ['days' => $daysAgo]) }}
                    @else
                        {{ $analog['analog_symbol'] }} {{ __('vor :days Tagen ähnlich', ['days' => $daysAgo]) }}
                    @endif
                    · <span class="font-black {{ $outcomeClass }}">{{ $analog['outcome_pct'] >= 0 ? '+' : '' }}{{ number_format($analog['outcome_pct'], 1, ',', '.') }}%</span>
                </span>
                @if($analog['sparkline'])
                    <svg viewBox="0 0 100 32" class="h-6 w-14 shrink-0" preserveAspectRatio="none">
                        <polyline points="{{ $analog['sparkline'] }}" fill="none" stroke="currentColor" stroke-width="3" class="{{ $outcomeClass }}" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                @endif
            </div>
        @endif

        @if($highlight['historicalStats'] ?? null)
            @php $stats = $highlight['historicalStats']; @endphp
            <div class="mb-2 rounded-lg border border-[var(--ak-border)] px-2 py-1.5 text-[9px] leading-4 text-[var(--ak-muted)]">
                <span class="text-[var(--ak-text)]">•</span>
                {{ __('Historisch (:n ähnliche Signale)', ['n' => $stats['count']]) }}:
                <span class="font-black {{ $stats['win_rate'] >= 50 ? 'text-emerald-500' : 'text-rose-500' }}">{{ number_format($stats['win_rate'], 0, ',', '.') }}%</span> {{ __('positiv') }}
                · Ø <span class="font-black {{ $stats['avg_return'] >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $stats['avg_return'] >= 0 ? '+' : '' }}{{ number_format($stats['avg_return'], 1, ',', '.') }}%</span>
            </div>
        @endif

        @if($highlight['insight'])
            <div class="mt-auto pt-2 border-t border-[var(--ak-border)] text-[9px] leading-4 text-[var(--ak-muted)]">{{ $highlight['insight'] }}</div>
        @endif
    @else
        <div class="flex flex-1 items-center justify-center text-[9px] text-[var(--ak-muted)] italic">{{ __('Keine Daten heute') }}</div>
    @endif
</{{ $wrapper }}>

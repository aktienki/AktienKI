<x-app-layout>
    <style>
        #upcoming-events-page { max-width: 56rem; margin-inline: auto; padding: 1.25rem 1rem 3rem; }
        #upcoming-events-page .events-day { margin-bottom: 1.25rem; }
        #upcoming-events-page .events-day-label {
            margin-bottom: .5rem; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--ak-muted);
        }
        #upcoming-events-page .events-card {
            display: flex; align-items: center; gap: .75rem; border-radius: .85rem; border: 1px solid var(--ak-border);
            background: var(--ak-card); padding: .75rem 1rem; text-decoration: none; color: var(--ak-text);
            transition: border-color .15s ease;
        }
        #upcoming-events-page .events-card:hover { border-color: color-mix(in srgb, #22d3ee 45%, transparent); }
        #upcoming-events-page .events-card + .events-card { margin-top: .5rem; }
        #upcoming-events-page .events-badge {
            flex: none; border-radius: .5rem; border: 1px solid var(--ak-border); padding: .25rem .55rem;
            font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); white-space: nowrap;
        }
        #upcoming-events-page .events-badge--watched { border-color: color-mix(in srgb, #22d3ee 45%, transparent); color: #22d3ee; }
        #upcoming-events-page .events-empty {
            display: grid; place-items: center; min-height: 10rem; padding: 1.5rem; border: 1px dashed var(--ak-border); border-radius: .8rem; text-align: center; font-size: .85rem; font-weight: 700; color: var(--ak-muted);
        }
    </style>

    <div id="upcoming-events-page">
        <header class="mb-5">
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-500">{{ __('Konzept') }}</p>
            <h1 class="mt-1 text-2xl font-black text-[var(--ak-text)]">{{ __('Anstehende News') }}</h1>
            <p class="mt-1 text-xs text-[var(--ak-muted)]">{{ __('Quartalstermine der nächsten :days Tage für unser Aktienuniversum, täglich synchronisiert von Twelve Data.', ['days' => $lookaheadDays]) }}</p>
            <a href="{{ route('dashboard.concept') }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-black text-cyan-500 hover:text-cyan-400">← {{ __('Zurück zum Konzept-Dashboard') }}</a>
        </header>

        @if($groupedByDate->isEmpty())
            <div class="events-empty">{{ __('Aktuell keine anstehenden Termine im Zeitraum.') }}</div>
        @else
            @foreach($groupedByDate as $date => $dayEvents)
                <div class="events-day">
                    <p class="events-day-label">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d.m.Y') }}</p>
                    @foreach($dayEvents as $event)
                        <a href="{{ $event['url'] }}" class="events-card">
                            <span class="min-w-0 flex-1">
                                <b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $event['name'] }}</b>
                                <small class="mt-0.5 block text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $event['symbol'] }}@if($event['time']) · {{ $event['time'] }}@endif</small>
                            </span>
                            @if($event['epsActual'] !== null)
                                <span class="shrink-0 text-right text-[11px] font-black {{ ($event['surprisePercent'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                    EPS {{ number_format((float) $event['epsActual'], 2, ',', '.') }}
                                    @if($event['surprisePercent'] !== null)
                                        <small class="block text-[9px] font-black">{{ $event['surprisePercent'] >= 0 ? '+' : '' }}{{ number_format((float) $event['surprisePercent'], 1, ',', '.') }} %</small>
                                    @endif
                                </span>
                            @elseif($event['epsEstimate'] !== null)
                                <span class="shrink-0 text-right text-[11px] font-black text-[var(--ak-muted)]">{{ __('Erwartung') }} {{ number_format((float) $event['epsEstimate'], 2, ',', '.') }}</span>
                            @endif
                            <span class="events-badge {{ $event['isWatched'] ? 'events-badge--watched' : '' }}">{{ $event['isWatched'] ? __('Watchlist') : __('Quartalszahlen') }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</x-app-layout>

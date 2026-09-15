@props(['date', 'dayEvents'])

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

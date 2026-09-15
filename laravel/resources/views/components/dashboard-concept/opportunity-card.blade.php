@props(['card'])

@php
    $maxHorizon = 30.0; // % - forecasts beyond this just show a full bar
    $barWidth = fn (?float $value): float => $value === null ? 0.0 : min(100.0, (abs($value) / $maxHorizon) * 100.0);
    $barTone = fn (?float $value): string => $value === null ? 'bg-[var(--ak-border)]' : ($value >= 0 ? 'bg-emerald-400' : 'bg-rose-400');
@endphp

<a href="{{ $card['url'] }}" class="concept-opp-card">
    <div class="concept-opp-head">
        <span class="min-w-0">
            <b class="block truncate text-sm font-black text-[var(--ak-text)]">{{ $card['name'] }}</b>
            <small class="mt-0.5 block text-[10px] font-black uppercase tracking-wide text-[var(--ak-muted)]">{{ $card['symbol'] }}</small>
        </span>
        <span class="concept-opp-badge">{{ $card['badge'] }}</span>
    </div>

    <div class="concept-horizon-row">
        @foreach($card['horizons'] as $label => $value)
            <div class="concept-horizon-bar">
                <div class="mb-1 flex items-center justify-between text-[9px] font-black uppercase tracking-wide text-[var(--ak-muted)]">
                    <span>{{ $label }}</span>
                    <span class="{{ $value === null ? '' : ($value >= 0 ? 'text-emerald-400' : 'text-rose-400') }}">{{ $value === null ? '—' : (($value >= 0 ? '+' : '').number_format($value, 1, ',', '.').' %') }}</span>
                </div>
                <div class="bar-track">
                    <div class="bar-fill {{ $barTone($value) }}" style="width: {{ $barWidth($value) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="concept-opp-footer">
        @if($card['externalConfidence'] !== null)
            <span class="font-black text-[var(--ak-text)]">{{ __('Externe Bewertung') }}: {{ $card['externalConfidence'] }}%</span>
            @if($card['externalVerdict'])
                <span class="text-[var(--ak-muted)]"> · {{ $card['externalVerdict'] }}</span>
            @endif
            @if($card['externalSummary'])
                <p class="mt-1 text-[var(--ak-muted)]">{{ \Illuminate\Support\Str::limit($card['externalSummary'], 140) }}</p>
            @endif
        @elseif(($card['score'] ?? null) !== null || ($card['risk'] ?? null) !== null)
            <span class="font-black text-[var(--ak-text)]">
                @if(($card['score'] ?? null) !== null)
                    {{ __('Score') }}: {{ number_format((float) $card['score'], 1, ',', '.') }}/10
                @endif
                @if(($card['risk'] ?? null) !== null)
                    · {{ __('Risiko') }}: {{ number_format((float) $card['risk'], 0, ',', '.') }} %
                @endif
            </span>
        @else
            <span class="text-[var(--ak-muted)]">{{ __('Keine externe Bewertung vorhanden.') }}</span>
        @endif
        @if($card['compositeScore'] !== null)
            <span class="float-right font-black text-cyan-400">{{ number_format($card['compositeScore'], 0, ',', '.') }}</span>
        @endif
    </div>
</a>

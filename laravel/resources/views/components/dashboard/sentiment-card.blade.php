@props([
    'card'
])

@php
$badge = match($card['color']) {
    'green' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    'red' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
    'amber' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
    default => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
};

@endphp

<div class="ak-card">

    <div class="flex items-start justify-between">

        <div>
            <p class="ak-card-label">
                {{ $card['title'] }}
            </p>

            <p class="ak-card-value">
                {{ $card['value'] }}
            </p>
        </div>

        <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $badge }}">
            {{ $card['status'] }}
        </span>

    </div>

    <div class="mt-4">

        <x-dashboard.score-stripes :percent="$card['percent']" />

    </div>

    <div class="mt-3 flex items-center justify-between">

        <span class="text-[10px] text-slate-500">
            Score
        </span>

        <span class="text-xs font-semibold text-white">
            {{ number_format($card['percent'] / 10, 1, ',', '.') }} / 10
        </span>

    </div>

</div>

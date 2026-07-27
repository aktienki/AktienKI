{{-- resources/views/components/dashboard/sentiment-card.blade.php --}}

@props([
    'card'
])

@php
$color = match($card['color']) {
    'green' => 'text-emerald-300 bg-emerald-500/15 border-emerald-500/30',
    'red' => 'text-rose-300 bg-rose-500/15 border-rose-500/30',
    'amber' => 'text-amber-300 bg-amber-500/15 border-amber-500/30',
    default => 'text-violet-300 bg-violet-500/15 border-violet-500/30',
};

$gradient = match($card['color']) {
    'green' => 'from-emerald-500 to-green-300',
    'red' => 'from-rose-500 to-red-300',
    'amber' => 'from-amber-500 to-yellow-300',
    default => 'from-violet-500 via-fuchsia-500 to-indigo-400',
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

        <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $color }}">
            {{ $card['status'] }}
        </span>

    </div>

    <div class="mt-4">

        <div class="h-2 overflow-hidden rounded-full bg-white/10">

            <div
                class="h-full bg-gradient-to-r {{ $gradient }}"
                style="width: {{ $card['percent'] }}%">
            </div>

        </div>

    </div>

    <div class="mt-3 flex items-center justify-between">

        <span class="text-[10px] text-slate-500">
            Score
        </span>

        <span class="text-xs font-semibold text-white">
            {{ $card['percent'] }}%
        </span>

    </div>

</div>
@props(['percent' => 0, 'reverse' => false])

@php
    $normalized = max(0, min(100, (float) $percent));
    $activeSegments = (int) ceil($normalized / 10);
    $colors = [
        '#ef4444', '#f05252', '#f97316', '#fb923c', '#f59e0b',
        '#eab308', '#a3e635', '#84cc16', '#4ade80', '#22c55e',
    ];
    if ($reverse) {
        $colors = array_reverse($colors);
    }
@endphp

<div
    class="flex w-full flex-row items-center gap-1"
    dir="ltr"
    role="meter"
    aria-valuemin="0"
    aria-valuemax="10"
    aria-valuenow="{{ round($normalized / 10, 1) }}">
    @foreach ($colors as $index => $color)
        <span
            class="block h-2 min-w-0 flex-1 rounded-sm transition-opacity"
            style="background-color: {{ $color }}; opacity: {{ $index < $activeSegments ? '1' : '.14' }}">
        </span>
    @endforeach
</div>

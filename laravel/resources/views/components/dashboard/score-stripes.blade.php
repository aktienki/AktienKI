@props(['percent' => 0, 'reverse' => false, 'palette' => 'signal'])

@php
    $normalized = max(0, min(100, (float) $percent));
    $activeSegments = (int) ceil($normalized / 10);
    $colors = match ($palette) {
        'teal' => ['#277c77', '#258983', '#22968f', '#1fa39b', '#1db0a7', '#20bdb3', '#2ccbbf', '#43d7ca', '#65e3d5', '#99f6e4'],
        'cyan' => ['#fb923c', '#fb923c', '#fb923c', '#087f9c', '#fb923c', '#06a6c4', '#fb923c', '#fb923c', '#fb923c', '#fb923c'],
        'violet' => ['#382d59', '#423468', '#4c3b78', '#574387', '#624b97', '#6d54a6', '#7860b4', '#866ec2', '#967fd0', '#a991de'],
        default => [
            '#ef4444', '#f05252', '#f97316', '#fb923c', '#f59e0b',
            '#eab308', '#a3e635', '#84cc16', '#4ade80', '#22c55e',
        ],
    };
    if ($reverse) {
        $colors = array_reverse($colors);
    }
    $paletteBorder = match ($palette) {
        'teal' => 'rgba(153,246,228,.18)',
        'cyan' => 'rgba(251,146,60,.18)',
        'violet' => 'rgba(196,181,253,.18)',
        default => 'transparent',
    };
    $paletteGlow = match ($palette) {
        'teal' => '0 0 7px rgba(45,212,191,.38)',
        'cyan' => '0 0 7px rgba(251,146,60,.36)',
        'violet' => '0 0 7px rgba(139,92,246,.32)',
        default => 'none',
    };
@endphp

<div
    class="flex w-full flex-row items-center gap-1"
    dir="ltr"
    role="meter"
    aria-valuemin="0"
    aria-valuemax="10"
    aria-valuenow="{{ round($normalized / 10, 1) }}">
    @foreach ($colors as $index => $color)
        @php
            $isActive = $index < $activeSegments;
            $isCurrent = $isActive && $index === $activeSegments - 1;
        @endphp
        <span
            class="block h-2 min-w-0 flex-1 rounded-sm border transition-opacity"
            style="background-color: {{ $color }}; border-color: {{ $paletteBorder }}; opacity: {{ $isActive ? ($isCurrent ? '1' : '.62') : '.10' }}; box-shadow: {{ $isCurrent ? $paletteGlow : 'none' }}">
        </span>
    @endforeach
</div>

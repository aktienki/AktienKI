@props(['score' => 0, 'display' => null, 'level' => null, 'type' => 'chance', 'label' => null])
@php
    $value = max(0, min(100, (float) $score));
    $gradeLevel = $level !== null
        ? max(0, min(5, (int) $level))
        : ($value > 0 ? max(1, min(5, (int) ceil($value / 20))) : 0);
    $activeLevel = $type === 'risk' && $gradeLevel > 0
        ? 6 - $gradeLevel
        : $gradeLevel;
    $palette = $type === 'risk'
        ? ['#df4d5f', '#ed8a32', '#e1be32', '#8fca45', '#35b779']
        : ['#df4d5f', '#ed8a32', '#e1be32', '#8fca45', '#35b779'];
@endphp
<div class="segmented-score" role="meter" aria-label="{{ $label ?: ucfirst($type) }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($value, 1, '.', '') }}">
    <svg class="segmented-score-ring" viewBox="0 0 120 120" aria-hidden="true">
        @foreach($palette as $index => $color)
            @php $segment = $index + 1; @endphp
            <circle
                class="segmented-score-sector {{ $segment <= $activeLevel ? 'is-active' : '' }} {{ $segment === $activeLevel ? 'is-end' : '' }}"
                cx="60" cy="60" r="48" pathLength="100"
                stroke="{{ $segment <= $activeLevel ? $color : '#dfe8ea' }}"
                stroke-dasharray="15.5 84.5"
                stroke-dashoffset="{{ -($index * 18.5) }}"
            />
        @endforeach
    </svg>
    <b>{{ $display ?? number_format($value, 0, ',', '.') }}</b>
</div>

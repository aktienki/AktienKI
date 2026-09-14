@props(['score' => 0, 'display' => null, 'level' => null, 'type' => 'chance', 'label' => null, 'segments' => 5])
@php
    $value = max(0, min(100, (float) $score));
    $isBinary = $type === 'binary';
    $isQuad = $type === 'quad';
    // Binary and quad donuts are categorical (pass/fail, or one of several
    // named states) - exactly one segment lights up, never cumulatively.
    $isExclusive = $isBinary || $isQuad;
    $segmentCount = $isBinary ? 2 : ($isQuad ? 4 : ((int) $segments === 10 ? 10 : 5));
    $gradeLevel = $level !== null
        ? max(0, min($segmentCount, (int) $level))
        : ($value > 0 ? max(1, min($segmentCount, (int) ceil($value / (100 / $segmentCount)))) : 0);
    $activeLevel = $type === 'risk' && $gradeLevel > 0
        ? ($segmentCount + 1) - $gradeLevel
        : $gradeLevel;
    $palette = match (true) {
        $isBinary => ['#df4d5f', '#35b779'],
        // Quad order matches severity, not the ring position: 1=objection
        // (red), 2=caution (amber), 3=unclear/pending (gray), 4=confirmed
        // (green) - only ever one of these four is lit at a time.
        $isQuad => ['#df4d5f', '#ed8a32', '#94a3b8', '#35b779'],
        $segmentCount === 10 => ['#df4d5f', '#e45f4d', '#e8753a', '#ed8a32', '#eaa632', '#e1be32', '#bed23b', '#8fca45', '#5fc060', '#35b779'],
        default => ['#df4d5f', '#ed8a32', '#e1be32', '#8fca45', '#35b779'],
    };
    $sectorLength = $isBinary ? 46.0 : ($isQuad ? 21.0 : ($segmentCount === 10 ? 8.0 : 15.5));
    $sectorOffset = $isBinary ? 50.0 : ($isQuad ? 25.0 : ($segmentCount === 10 ? 10.0 : 18.5));
@endphp
<div class="segmented-score" role="meter" aria-label="{{ $label ?: ucfirst($type) }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ number_format($value, 1, '.', '') }}">
    <svg class="segmented-score-ring" viewBox="0 0 120 120" aria-hidden="true">
        @foreach($palette as $index => $color)
            @php
                $segment = $index + 1;
                // Binary/quad donuts show only the one matching segment lit
                // - never cumulatively, unlike the gradient (chance/risk)
                // donuts where "yes" must not also light the "no" segment.
                $isLit = $isExclusive ? $segment === $activeLevel : $segment <= $activeLevel;
            @endphp
            <circle
                class="segmented-score-sector {{ $isLit ? 'is-active' : '' }} {{ $segment === $activeLevel ? 'is-end' : '' }}"
                cx="60" cy="60" r="48" pathLength="100"
                stroke="{{ $isLit ? $color : '#dfe8ea' }}"
                stroke-dasharray="{{ $sectorLength }} {{ 100 - $sectorLength }}"
                stroke-dashoffset="{{ -($index * $sectorOffset) }}"
            />
        @endforeach
    </svg>
    <b>{{ $display ?? number_format($value, 0, ',', '.') }}</b>
</div>

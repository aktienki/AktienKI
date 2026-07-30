@props(['sector' => null])

@php
    $normalizedSector = mb_strtolower(trim((string) $sector));

    [$icon, $color] = match (true) {
        str_contains($normalizedSector, 'technology'),
        str_contains($normalizedSector, 'technologie'),
        str_contains($normalizedSector, 'software'),
        str_contains($normalizedSector, 'semiconductor'),
        str_contains($normalizedSector, 'halbleiter') => ['heroicon-o-cpu-chip', '#7dd3fc'],

        str_contains($normalizedSector, 'health'),
        str_contains($normalizedSector, 'gesundheit'),
        str_contains($normalizedSector, 'pharma'),
        str_contains($normalizedSector, 'biotech') => ['heroicon-o-heart', '#fda4af'],

        str_contains($normalizedSector, 'financial'),
        str_contains($normalizedSector, 'finanz'),
        str_contains($normalizedSector, 'bank'),
        str_contains($normalizedSector, 'insurance'),
        str_contains($normalizedSector, 'versicherung') => ['heroicon-o-banknotes', '#fcd34d'],

        str_contains($normalizedSector, 'energy'),
        str_contains($normalizedSector, 'energie'),
        str_contains($normalizedSector, 'oil'),
        str_contains($normalizedSector, 'gas') => ['heroicon-o-bolt', '#fde047'],

        str_contains($normalizedSector, 'material'),
        str_contains($normalizedSector, 'rohstoff'),
        str_contains($normalizedSector, 'chemical'),
        str_contains($normalizedSector, 'chemie') => ['heroicon-o-beaker', '#fdba74'],

        str_contains($normalizedSector, 'industrial'),
        str_contains($normalizedSector, 'industrie'),
        str_contains($normalizedSector, 'manufactur') => ['heroicon-o-cog-6-tooth', '#93c5fd'],

        str_contains($normalizedSector, 'real estate'),
        str_contains($normalizedSector, 'immobil') => ['heroicon-o-building-office-2', '#a5b4fc'],

        str_contains($normalizedSector, 'communication'),
        str_contains($normalizedSector, 'kommunikation'),
        str_contains($normalizedSector, 'telecom'),
        str_contains($normalizedSector, 'media') => ['heroicon-o-signal', '#d8b4fe'],

        str_contains($normalizedSector, 'utilit'),
        str_contains($normalizedSector, 'versorger') => ['heroicon-o-light-bulb', '#bef264'],

        str_contains($normalizedSector, 'consumer defensive'),
        str_contains($normalizedSector, 'consumer staples'),
        str_contains($normalizedSector, 'basiskonsum') => ['heroicon-o-shopping-cart', '#6ee7b7'],

        str_contains($normalizedSector, 'consumer'),
        str_contains($normalizedSector, 'konsum'),
        str_contains($normalizedSector, 'retail'),
        str_contains($normalizedSector, 'handel') => ['heroicon-o-shopping-bag', '#f9a8d4'],

        default => ['heroicon-o-squares-2x2', '#5eead4'],
    };

    $iconAttributes = $attributes->getAttributes();
    $iconClass = trim('ak-sector-icon '.($iconAttributes['class'] ?? ''));
    $iconAttributes['style'] = trim(
        "--ak-sector-icon-color: {$color}; ".($iconAttributes['style'] ?? '')
    );
    unset($iconAttributes['class']);
@endphp

{!! svg($icon, $iconClass, $iconAttributes)->toHtml() !!}

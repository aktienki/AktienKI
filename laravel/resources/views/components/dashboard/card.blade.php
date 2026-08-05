{{-- resources/views/components/dashboard/card.blade.php --}}

@props([
    'title' => '',
])

<div {{ $attributes->class(['ak-card']) }}>

    {{ $slot }}

</div>

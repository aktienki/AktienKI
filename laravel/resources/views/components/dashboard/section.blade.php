{{-- resources/views/components/dashboard/section.blade.php --}}

@props([
    'title'
])

<section>

    <h2 class="ak-section-title">

        {{ $title }}

    </h2>

    {{ $slot }}

</section>
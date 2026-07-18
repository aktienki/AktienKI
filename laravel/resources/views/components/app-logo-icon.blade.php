@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="AktienKI" {{ $attributes }}>
        <x-slot name="logo">
            <img
                src="{{ asset('images/logo.svg') }}"
                alt="AktienKI"
                class="h-8 w-8 object-contain"
            >
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="AktienKI" {{ $attributes }}>
        <x-slot name="logo">
            <img
                src="{{ asset('images/logo.svg') }}"
                alt="AktienKI"
                class="h-8 w-8 object-contain"
            >
        </x-slot>
    </flux:brand>
@endif

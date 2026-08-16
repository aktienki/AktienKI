<span {{ $attributes->class(['ak-brand-wordmark inline-flex items-center']) }}>
    <img
        src="{{ asset('brand/generated/bull-icon.png') }}?v={{ filemtime(public_path('brand/generated/bull-icon.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-icon h-10 w-10 min-[480px]:hidden"
    >
    <x-welcome-brand-logo class="ak-brand-wordmark-dark hidden h-14 min-[480px]:inline-flex" />
    <img
        src="{{ asset('brand/generated/bull-logo-light-clean.png') }}?v={{ filemtime(public_path('brand/generated/bull-logo-light-clean.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-wordmark-light hidden h-14 w-auto max-w-[190px]"
    >
</span>

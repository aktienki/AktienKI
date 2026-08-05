<span {{ $attributes->class(['ak-brand-wordmark inline-flex items-center']) }}>
    <img
        src="{{ asset('brand/generated/bull-icon.png') }}?v={{ filemtime(public_path('brand/generated/bull-icon.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-icon h-10 w-10 min-[480px]:hidden"
    >
    <img
        src="{{ asset('brand/generated/bull-logo-dark.png') }}?v={{ filemtime(public_path('brand/generated/bull-logo-dark.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-wordmark-dark hidden h-14 w-auto max-w-[190px] min-[480px]:block"
    >
    <img
        src="{{ asset('brand/generated/bull-logo-light-clean.png') }}?v={{ filemtime(public_path('brand/generated/bull-logo-light-clean.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-wordmark-light hidden h-14 w-auto max-w-[190px]"
    >
</span>

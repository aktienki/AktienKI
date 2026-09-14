<span {{ $attributes->class(['ak-brand-wordmark inline-flex items-center']) }}>
    {{-- Cropped to just the icon portion of the same orange artwork used
         below - no separate square asset exists, and object-position keeps
         it transparent (unlike the Instagram export, which has a white
         matte background). Single asset regardless of theme, as before. --}}
    <img
        src="{{ asset('brand/generated/bull-logo-light-clean.png') }}?v={{ filemtime(public_path('brand/generated/bull-logo-light-clean.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-icon h-10 w-10 min-[480px]:hidden"
        style="object-fit: cover; object-position: left center;"
    >
    {{-- Light artwork is the default desktop display (no theme attribute
         needed), since the app defaults every user to the light theme - see
         resources/js/preferences.js's light-default versioning. This avoids
         a flash of the wrong logo before theme JS runs on first paint. The
         dark variant stays hidden unless the theme CSS below explicitly
         reveals it - both use the same orange bull artwork, just suited to
         each background. --}}
    <img
        src="{{ asset('brand/generated/bull-logo-dark.png') }}?v={{ filemtime(public_path('brand/generated/bull-logo-dark.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-wordmark-dark hidden h-14 w-auto max-w-[190px]"
    >
    <img
        src="{{ asset('brand/generated/bull-logo-light-clean.png') }}?v={{ filemtime(public_path('brand/generated/bull-logo-light-clean.png')) }}"
        alt="aktienKI.com"
        class="ak-brand-wordmark-light hidden h-14 w-auto max-w-[190px] min-[480px]:inline-flex"
    >
</span>

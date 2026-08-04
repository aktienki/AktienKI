@props(['continent'])

<svg
    {{ $attributes->merge(['class' => 'shrink-0']) }}
    viewBox="0 0 64 44"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
>
    <g fill="currentColor" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round">
        @switch($continent)
            @case('north-america')
                <path d="M5 9.5 12.5 4l9.5 1 5.5 4.5 6.5-1 5.5 4-2 5.5-5 1.5-2.5 5-4.5 1.5-2.5 5-4-1.5-1-5-5-2L9 18l-4-2.5Z" />
                <path d="m25 31 4 1.5 3.5 4-1.5 4-3-2-2.5-4Z" opacity=".82" />
                <path d="m43 7 6-3 7 2-2 5-7 1Z" opacity=".55" />
                @break
            @case('asia-pacific')
                <path d="M4 10.5 11 5l10 1 5-2 8 3 7-1 8 4.5 7 1.5 3 5-5 3-1 5-5 1-3.5 5-5-2-4 2-4-4-5-1.5-3-5-6-1-2-5.5-5-1.5Z" />
                <path d="m44 34 6-2.5 7 2 2 4.5-5 3-7-1Z" opacity=".82" />
                <path d="m55 24 2-2 2 3-2 3Z" opacity=".62" />
                @break
            @case('africa')
                <path d="m17 7 11-4 11 3 7 7-2 9-5 4-2 8-7 8-5-5-2-7-5-4-4-9-5-4 3-9Z" />
                <path d="m47 31 4 2-1 7-3-2Z" opacity=".58" />
                @break
            @default
                <path d="m8 15 5-7 8 1 4-5 7 3 5-2 4 5 8 1 3 5-5 4-1 6-6-1-4 5-5-3-5 2-4-4-6 1-3-5-6-1Z" />
                <path d="m20 29 4 1-1 5-4-2Z" opacity=".62" />
                <path d="m49 6 4-2 4 3-3 3Z" opacity=".5" />
        @endswitch
    </g>
</svg>

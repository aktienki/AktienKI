@props([
    'logoData' => null,
    'eyebrow' => 'aktienKI.com',
    'title',
    'symbol' => null,
    'meta' => null,
    'donuts' => [],
])

<div class="header">
    @if ($logoData)<img class="logo" src="{{ $logoData }}" alt="aktienKI">@endif
    <div class="brand">{{ $eyebrow }}</div>
    <h1 class="title">{{ $title }} @if($symbol)<span class="symbol">{{ $symbol }}</span>@endif</h1>
    @if($meta)<p class="meta">{{ $meta }}</p>@endif
    @if(count($donuts))
        <table class="header-donuts" role="presentation"><tr>
            @foreach($donuts as $donut)
                <td><img src="{{ $donut['image'] }}" width="54" height="54" alt="{{ $donut['label'] }}"></td>
            @endforeach
        </tr></table>
    @endif
    <div style="clear:both"></div>
</div>

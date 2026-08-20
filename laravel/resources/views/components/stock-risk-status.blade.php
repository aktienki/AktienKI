@props(['status' => null, 'compact' => false])
@php
    $status = strtolower((string) $status);
    $config = match ($status) {
        'sleep' => ['!', __('SLEEP'), 'border-rose-500/55 bg-rose-500/15 text-rose-500'],
        'risk' => ['!', __('Risk'), 'border-orange-500/45 bg-orange-500/12 text-orange-500'],
        'opportunity' => ['↗', __('Chancenorientiert'), 'border-amber-400/45 bg-amber-400/12 text-amber-500'],
        'balanced' => ['◆', __('Ausgewogen'), 'border-cyan-500/40 bg-cyan-500/10 text-cyan-500'],
        'defensive' => ['✓', __('Defensiv'), 'border-emerald-500/40 bg-emerald-500/10 text-emerald-500'],
        default => null,
    };
@endphp
@if($config)
<span {{ $attributes->class(['inline-flex items-center justify-center gap-1 rounded-lg border font-black uppercase tracking-wide', $compact ? 'h-7 min-w-7 px-1.5 text-[10px]' : 'h-8 px-2.5 text-[10px]', $config[2]]) }} title="{{ $config[1] }}">
    <span class="text-sm leading-none">{{ $config[0] }}</span>@unless($compact)<span>{{ $config[1] }}</span>@endunless
</span>
@endif

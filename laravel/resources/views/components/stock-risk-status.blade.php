@props(['status' => null, 'compact' => false, 'interactive' => true, 'profitPerTrade' => null, 'confidence' => null, 'drawdown' => null])
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
    @if($status === 'sleep' && $interactive)
        <span x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative inline-flex">
            <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="dialog" {{ $attributes->class(['inline-flex cursor-pointer items-center justify-center rounded-lg border border-amber-500/55 bg-amber-400/15 font-black text-amber-500 transition hover:bg-amber-400/25 focus:outline-none focus:ring-2 focus:ring-amber-400/40', $compact ? 'h-7 w-7' : 'h-8 w-8']) }} title="{{ __('Begründung für Vorsicht anzeigen') }}" aria-label="{{ __('Begründung für Vorsicht anzeigen') }}">
                <x-heroicon-s-exclamation-triangle class="h-5 w-5" />
            </button>
            <span x-cloak x-show="open" role="dialog" aria-modal="true" class="stock-risk-reason-panel absolute left-0 top-full z-[80] mt-2 w-[min(18rem,calc(100vw-2rem))] rounded-xl border border-amber-400/35 bg-[var(--ak-card)] p-3 text-left normal-case tracking-normal shadow-2xl">
                <span class="flex items-start justify-between gap-3">
                    <span>
                        <strong class="block text-xs font-black text-amber-500">{{ __('Warum Vorsicht?') }}</strong>
                        <span class="mt-1.5 block text-[10px] font-medium leading-4 text-[var(--ak-muted)]">
                            @if(is_numeric($profitPerTrade))
                                {{ __('Der durchschnittliche Nettoertrag je Trade ist mit :value % negativ.', ['value' => number_format((float) $profitPerTrade, 2, ',', '.')]) }}
                            @else
                                {{ __('Der durchschnittliche Nettoertrag je Trade ist negativ.') }}
                            @endif
                        </span>
                    </span>
                    <button type="button" @click="open = false" class="shrink-0 text-base leading-none text-[var(--ak-muted)]" aria-label="{{ __('Schließen') }}">×</button>
                </span>
                <span class="mt-2 block border-t border-[var(--ak-border)] pt-2 text-[9px] font-medium leading-4 text-[var(--ak-muted)]">
                    {{ __('Die Aktie wird weiter trainiert. Ist der durchschnittliche Nettoertrag je Trade beim nächsten vollständigen Walk-Forward-Lauf nicht mehr negativ, wird der Risikostatus neu bewertet.') }}
                </span>
            </span>
        </span>
    @elseif($status === 'sleep')
        <span {{ $attributes->class(['inline-flex items-center justify-center rounded-lg border border-amber-500/55 bg-amber-400/15 font-black text-amber-500', $compact ? 'h-7 w-7' : 'h-8 w-8']) }} title="{{ __('Vorsicht') }}" aria-label="{{ __('Vorsicht') }}">
            <x-heroicon-s-exclamation-triangle class="h-5 w-5" />
        </span>
    @else
        <span {{ $attributes->class(['inline-flex items-center justify-center gap-1 rounded-lg border font-black uppercase tracking-wide', $compact ? 'h-7 min-w-7 px-1.5 text-[10px]' : 'h-8 px-2.5 text-[10px]', $config[2]]) }} title="{{ $config[1] }}">
            <span class="text-sm leading-none">{{ $config[0] }}</span>@unless($compact)<span>{{ $config[1] }}</span>@endunless
        </span>
    @endif
@endif

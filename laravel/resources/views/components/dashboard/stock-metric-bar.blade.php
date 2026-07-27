@props(['percent' => null])

@if ($percent === null)
    <span class="text-xs text-[var(--ak-muted)]">—</span>
@else
    @php
        $score = max(0, min(10, (float) $percent / 10));
        $currentStep = $score > 0 ? (int) ceil($score) : null;
    @endphp
    <div class="flex min-w-36 items-center gap-2">
        <div class="flex flex-1 gap-0.5">
            @for ($step = 1; $step <= 10; $step++)
                @php $fill = max(0, min(100, ($score - ($step - 1)) * 100)); @endphp
                <i class="h-2 flex-1 overflow-hidden rounded-sm bg-slate-300/[.08]">
                    <span
                        class="block h-full"
                        style="width:{{ $fill }}%;background:hsl({{ ($step - 1) * 12 }},72%,52%);opacity:{{ $step === $currentStep ? '1' : '.5' }}">
                    </span>
                </i>
            @endfor
        </div>
        <strong class="w-12 text-right text-xs text-[var(--ak-text)]">{{ number_format($score, 1, ',', '.') }}<small class="text-[9px] font-medium text-[var(--ak-muted)]">/10</small></strong>
    </div>
@endif

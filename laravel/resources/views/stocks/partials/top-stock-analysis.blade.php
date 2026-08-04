@if ($topStockFactorRatings->isNotEmpty())
    <div class="h-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]">
        <div class="flex h-full flex-col justify-between gap-1.5">
            @foreach ($topStockFactorRatings as $topFactor)
                @php
                    $factorRating = $topFactor['rating'];
                    $factorPalette = [
                        '#2dd4bf', '#27c1b1', '#22afa3', '#4ca78c', '#7fa071',
                        '#b39758', '#d68b4d', '#e67855', '#e96868', '#e25569',
                    ];
                    $isDrawdownRisk = $topFactor['key'] === 'drawdown_risk';
                    $factorTone = match (true) {
                        $factorRating === null => 'bg-slate-500/15',
                        $factorRating >= 8 => 'bg-emerald-500',
                        $factorRating >= 6 => 'bg-teal-500',
                        $factorRating >= 4 => 'bg-amber-500',
                        default => 'bg-rose-500',
                    };
                @endphp
                <div class="w-full rounded-md border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-2 py-1">
                    <div class="flex items-center justify-between gap-2 text-[10px] font-bold leading-none">
                        <span class="text-[var(--ak-muted)]">{{ $topFactor['label'] }}</span>
                        <span class="text-[var(--ak-text)]">{{ $factorRating !== null ? $factorRating.'/10' : '—' }}</span>
                    </div>
                    <div class="mt-1 grid grid-cols-10 gap-0.5">
                        @for ($factorStep = 1; $factorStep <= 10; $factorStep++)
                            @php
                                $factorStepActive = $factorRating !== null && $factorStep <= $factorRating;
                                $factorStepCurrent = $factorStepActive && $factorStep === (int) $factorRating;
                            @endphp
                            @if ($isDrawdownRisk)
                                <span
                                    class="h-1 rounded-sm"
                                    style="background-color: {{ $factorStepActive ? $factorPalette[$factorStep - 1] : 'rgba(100,116,139,.15)' }}; opacity: {{ $factorStepCurrent ? '1' : ($factorStepActive ? '.48' : '1') }}"
                                ></span>
                            @else
                                <span class="h-1 rounded-sm {{ $factorStepActive ? $factorTone : 'bg-slate-500/15' }} {{ $factorStepActive && ! $factorStepCurrent ? 'opacity-40' : '' }}"></span>
                            @endif
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

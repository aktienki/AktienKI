@if ($topStockFactorRatings->isNotEmpty())
    <div class="h-full rounded-xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-2 shadow-[var(--ak-shadow)]">
        <div class="flex h-full flex-col justify-between gap-1.5">
            @foreach ($topStockFactorRatings as $topFactor)
                @php
                    $factorRating = $topFactor['rating'];
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
                            <span class="h-1 rounded-sm {{ $factorRating !== null && $factorStep <= $factorRating ? $factorTone : 'bg-slate-500/15' }} {{ $factorRating !== null && $factorStep < $factorRating ? 'opacity-40' : '' }}"></span>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

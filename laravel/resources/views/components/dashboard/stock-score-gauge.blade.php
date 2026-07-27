@props(['percent' => null, 'compact' => false, 'reverse' => false, 'purple' => false, 'percentage' => false])

@if ($percent === null)
    <span class="text-sm text-[var(--ak-muted)]">—</span>
@else
    @php $value = max(0, min(100, (float) $percent)); @endphp
    <div class="{{ $compact ? ($percentage ? 'min-w-24' : 'min-w-32') : 'min-w-36' }}">
        <div class="{{ $compact ? 'mb-1.5' : 'mb-2' }} flex items-end gap-1">
            <strong class="{{ $compact ? 'text-lg' : 'text-2xl' }} font-black leading-none text-[var(--ak-text)]">{{ number_format($percentage ? $value : $value / 10, 1, ',', '.') }}</strong>
            <span class="{{ $compact ? 'text-[10px]' : 'text-xs' }} font-bold text-[var(--ak-muted)]">{{ $percentage ? '%' : '/10' }}</span>
        </div>
        <div class="{{ $compact ? 'h-1.5' : 'h-2' }} overflow-visible rounded-full bg-violet-300/[.10]">
            <div class="relative h-full rounded-full bg-violet-400/55" style="width: {{ $value }}%">
                <i class="absolute right-0 top-1/2 {{ $compact ? 'h-2.5 w-1' : 'h-3 w-1.5' }} -translate-y-1/2 translate-x-1/2 rounded-full bg-amber-300 shadow-[0_0_7px_rgba(251,191,36,.35)]"></i>
            </div>
        </div>
    </div>
@endif

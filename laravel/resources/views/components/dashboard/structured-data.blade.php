@props(['data', 'level' => 0])

@php
    $formatStructuredValue = function (string $key, mixed $value): string {
        if ($value === null) return '—';
        if (is_bool($value)) return $value ? __('Ja') : __('Nein');
        if (! is_numeric($value)) return (string) $value;

        $number = (float) $value;
        if (preg_match('/(Margins|Margin|Growth|returnOn|heldPercent|payoutRatio)$/i', $key)) {
            return number_format($number * 100, 2, ',', '.').' %';
        }
        if (abs($number) >= 1_000_000_000) return number_format($number / 1_000_000_000, 2, ',', '.').' Mrd.';
        if (abs($number) >= 1_000_000) return number_format($number / 1_000_000, 2, ',', '.').' Mio.';
        if (abs($number) >= 1_000) return number_format($number, 0, ',', '.');

        return number_format($number, 4, ',', '.');
    };
    $structuredLabel = fn (string|int $key): string => is_int($key) || ctype_digit((string) $key)
        ? __('Eintrag').' '.((int) $key + 1)
        : \Illuminate\Support\Str::headline(preg_replace('/([a-z])([A-Z])/', '$1 $2', (string) $key));
@endphp

<div class="{{ $level === 0 ? 'grid gap-3 sm:grid-cols-2' : 'space-y-2' }}">
    @foreach ($data as $key => $value)
        @if (is_array($value))
            <details class="{{ $level === 0 ? '' : 'ml-2' }} group rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)]">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5 text-xs font-black text-[var(--ak-text)] marker:hidden">
                    <span class="break-words">{{ $structuredLabel($key) }}</span>
                    <span class="flex shrink-0 items-center gap-2 text-[10px] font-medium text-[var(--ak-muted)]">
                        {{ count($value) }}
                        <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition group-open:rotate-180" />
                    </span>
                </summary>
                <div class="border-t border-[var(--ak-border)] p-2.5">
                    @if ($value === [])
                        <span class="text-xs text-[var(--ak-muted)]">—</span>
                    @else
                        <x-dashboard.structured-data :data="$value" :level="$level + 1" />
                    @endif
                </div>
            </details>
        @else
            <div class="min-w-0 rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] p-3">
                <p class="text-[10px] uppercase tracking-wide text-[var(--ak-muted)]">{{ $structuredLabel($key) }}</p>
                <p class="mt-1.5 break-words text-sm font-bold leading-5 text-[var(--ak-text)]">{{ $formatStructuredValue((string) $key, $value) }}</p>
            </div>
        @endif
    @endforeach
</div>

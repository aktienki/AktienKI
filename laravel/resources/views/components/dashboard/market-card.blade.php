{{-- resources/views/components/dashboard/market-card.blade.php --}}

@props([
    'market'
])

@php
$positive = ($market['change'] ?? 0) >= 0;
$volatilityColor = match ($market['volatility_label'] ?? null) {
    'Hoch' => 'text-rose-300',
    'Mittel' => 'text-amber-300',
    default => 'text-emerald-300',
};
[$marketSymbol, $marketOrigin] = match ($market['name']) {
    'DAX' => ['🇩🇪', __('Deutschland')],
    'NASDAQ', 'S&P 500' => ['🇺🇸', __('USA')],
    'Japan' => ['🇯🇵', __('Nikkei 225')],
    'China' => ['🇨🇳', __('Shanghai Composite')],
    default => ['◈', __('Markt')],
};
@endphp

<div
    class="ak-card ak-dashboard-market-card min-h-[14rem]"
    wire:key="market-card-{{ md5($market['symbol'] ?? $market['name']) }}">

    <div class="grid grid-cols-[minmax(0,1fr)_minmax(88px,42%)] items-center gap-3">

        <div class="min-w-0">

            <div class="flex items-center gap-2">
                <p class="truncate text-xl font-black tracking-tight text-white">
                    {{ $market['name'] }}
                </p>

                <span
                    class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold
                    {{ $positive
                        ? 'bg-emerald-500/15 text-emerald-300'
                        : 'bg-rose-500/15 text-rose-300' }}">

                    {{ $market['change'] !== null
                        ? number_format($market['change'],2,',','.')
                        : '—' }} %

                </span>
            </div>

            <p class="mt-1 flex items-center gap-1.5 text-[10px] font-semibold text-violet-300">
                <span class="inline-flex min-w-6 items-center justify-center rounded-md border border-violet-400/25 bg-violet-500/10 px-1 py-0.5 font-black text-violet-200">{{ $marketSymbol }}</span>
                {{ $marketOrigin }}
            </p>

            <p class="ak-card-value">
                {{ $market['price']
                    ? number_format($market['price'],2,',','.')
                    : '—'
                }}
            </p>

        </div>

        <div
            class="h-24 min-w-0 w-full"
            wire:ignore
            x-data="candlestick(@js($market['candles'] ?? []))">

            <div
                x-ref="chart"
                class="h-full w-full"
                aria-label="{{ __('Kerzenchart für :market', ['market' => $market['name']]) }}">
            </div>

        </div>

    </div>

    <div class="mt-4 grid grid-cols-3 gap-2 border-t border-white/5 pt-3">
        <div class="min-w-0">
            <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Trend') }}</p>
            <p class="mt-1 truncate text-xs font-bold text-slate-200">{{ isset($market['trend']) ? __($market['trend']) : '—' }}</p>
        </div>
        <div class="min-w-0 border-l border-white/5 pl-2">
            <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('Volatilität') }}</p>
            <p class="mt-1 text-xs font-bold {{ $volatilityColor }}">{{ isset($market['volatility_label']) ? __($market['volatility_label']) : '—' }}</p>
        </div>
        <div class="min-w-0 border-l border-white/5 pl-2">
            <p class="text-[9px] uppercase tracking-wide text-slate-500">{{ __('KI-Score') }}</p>
            <p class="mt-1 text-xs font-bold text-violet-300">
                {{ \App\Support\AiScore::toTen($market['ai_score'] ?? null) !== null ? number_format(\App\Support\AiScore::toTen($market['ai_score']), 1, ',', '.').' / 10' : '—' }}
            </p>
        </div>
    </div>

    <div class="mt-3">
        <x-dashboard.score-stripes :percent="\App\Support\AiScore::toPercent($market['ai_score'] ?? null) ?? 0" />
    </div>

    <div class="mt-2 flex items-center justify-between border-t border-white/5 pt-2">

        <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-violet-300">
            <span class="h-1.5 w-1.5 rounded-full bg-violet-400"></span>
            {{ __('1 Std. · Stand beim Aufruf') }}
        </span>

        <span class="text-[10px] text-slate-500">
            {{ $market['currency'] }}
        </span>

    </div>

</div>

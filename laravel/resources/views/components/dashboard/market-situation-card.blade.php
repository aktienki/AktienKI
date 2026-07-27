@props(['card'])

@php
    $positive = ($card['change'] ?? 0) >= 0;
    $volatilityColor = match ($card['volatility_label']) {
        'Hoch' => 'text-rose-300',
        'Mittel' => 'text-amber-300',
        default => 'text-emerald-300',
    };
    [$marketSymbol, $marketOrigin] = match ($card['title']) {
        'DAX' => ['🇩🇪', __('Deutschland')],
        'NASDAQ', 'S&P 500' => ['🇺🇸', __('USA')],
        'Japan' => ['🇯🇵', __('Nikkei 225')],
        'China' => ['🇨🇳', __('Shanghai Composite')],
        default => ['◈', __('Markt')],
    };
@endphp

<div class="ak-card">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xl font-black tracking-tight text-white sm:text-2xl">{{ $card['title'] }}</p>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-400/20 bg-violet-500/10 px-2 py-1 text-[10px] font-bold text-violet-200">
                    <span class="{{ $card['title'] === 'Gold' ? 'text-amber-300' : ($card['title'] === 'Öl' ? 'text-slate-300' : '') }}">{{ $marketSymbol }}</span>
                    {{ $marketOrigin }}
                </span>
            </div>
            <p class="mt-1 text-sm font-semibold text-slate-300">{{ __($card['trend']) }}</p>
        </div>

        <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $positive ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300' : 'border-rose-500/30 bg-rose-500/15 text-rose-300' }}">
            {{ $positive ? '+' : '' }}{{ number_format($card['change'], 2, ',', '.') }} %
        </span>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3">
        <div class="rounded-xl border border-white/5 bg-white/[.025] p-3">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Volatilität / Std.') }}</p>
            <p class="mt-1 text-sm font-bold {{ $volatilityColor }}">{{ __($card['volatility_label']) }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500">Ø {{ number_format($card['volatility'], 2, ',', '.') }} %</p>
        </div>

        <div class="rounded-xl border border-white/5 bg-white/[.025] p-3">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Mittlere KI-Bewertung') }}</p>
            @if ($card['ai_score'] !== null)
                <p class="mt-1 text-sm font-bold text-violet-300">{{ number_format(\App\Support\AiScore::toTen($card['ai_score']), 1, ',', '.') }} / 10</p>
                <p class="mt-0.5 text-[10px] text-slate-500">{{ trans_choice(':count Aktie|:count Aktien', $card['ai_companies'], ['count' => $card['ai_companies']]) }}</p>
            @else
                <p class="mt-1 text-xs font-bold text-slate-400">{{ __('Keine Indexdaten') }}</p>
                <p class="mt-0.5 text-[10px] text-slate-500">{{ __('Mitglieder importieren') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <x-dashboard.score-stripes :percent="\App\Support\AiScore::toPercent($card['ai_score'] ?? null) ?? 0" />
    </div>
</div>

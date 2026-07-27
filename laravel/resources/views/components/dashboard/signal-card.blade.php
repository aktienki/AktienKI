  {{-- resources/views/components/dashboard/signal-card.blade.php --}}

@props([
    'signal'
])

@php

$badgeClass = match (true) {
    str_contains($signal['signal'], 'BUY')  => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    str_contains($signal['signal'], 'SELL') => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
    default                                 => 'bg-violet-500/15 text-violet-200 border-violet-500/30',
};

@endphp

<div class="ak-signal-card">

    <div class="flex items-start justify-between">

        <div class="min-w-0">

            <div class="flex items-center gap-2">

                <h3 class="truncate text-base font-semibold text-white">
                    {{ $signal['symbol'] }}
                </h3>

                @if($signal['index'])
                    <span class="rounded-full border border-violet-400/25 bg-violet-500/10 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-violet-200">
                        {{ $signal['index'] }}
                    </span>
                @endif

            </div>

            <p class="truncate text-xs text-slate-400">
                {{ $signal['company'] }}
            </p>

        </div>

        <span class="rounded-full border px-2 py-1 text-[10px] font-bold uppercase {{ $badgeClass }}">
            {{ $signal['signal'] }}
        </span>

    </div>

    <div class="mt-4 flex items-end justify-between">

        <div>

            <p class="ak-card-label">
                Livekurs
            </p>

            <p class="text-2xl font-bold text-white">
                {{ $signal['price'] ? number_format($signal['price'],2,',','.') : '—' }}
                {{ $signal['currency'] }}
            </p>

            <p class="mt-1 text-xs {{ ($signal['change'] ?? 0) >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">
                {{ $signal['change'] !== null ? number_format($signal['change'],2,',','.') : '—' }} %
            </p>

        </div>

        <div class="text-right">

            <p class="ak-card-label">
                AI
            </p>

            <p class="text-3xl font-bold text-white">
                {{ \App\Support\AiScore::toTen($signal['score'] ?? null) !== null ? number_format(\App\Support\AiScore::toTen($signal['score']), 1, ',', '.') : '—' }}
            </p>

            <p class="text-[10px] text-slate-500">
                /10
            </p>

        </div>

    </div>

    {{-- Sparkline --}}

    <div class="mt-4">

        <div
            class="h-14 w-full"
            x-data="sparkline(@js($signal['sparkline']))">

            <div x-ref="chart" class="h-full w-full"></div>

        </div>

    </div>

    <div class="mt-3 flex justify-between border-t border-white/5 pt-3">

        <div>

            <p class="text-[10px] uppercase tracking-wide text-slate-500">
                Prediction
            </p>

            <p class="text-xs text-slate-300">
                {{ \Carbon\Carbon::parse($signal['prediction_time'])->format('d.m.Y H:i') }}
            </p>

        </div>

        <div class="text-right">

            <p class="text-[10px] uppercase tracking-wide text-slate-500">
                Run
            </p>

            <p class="text-xs text-violet-300">
                {{ $signal['run_type'] }}
            </p>

        </div>

    </div>

    <div class="mt-4 border-t border-white/5 pt-3">

        <div class="mb-3 flex items-center justify-between">

            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-violet-300">
                Top KI Modelle
            </p>

            <span class="text-[10px] text-slate-500">
                Top 3
            </span>

        </div>

        <div class="space-y-3">

            @foreach($signal['models'] as $model)

                <div>

                    <div class="mb-1 flex items-center justify-between">

                        <span class="text-[11px] text-slate-300">
                            {{ $model['name'] }}
                        </span>

                        <span class="text-[11px] font-semibold text-violet-300">
                            {{ number_format($model['score'],1,',','.') }}%
                        </span>

                    </div>

                    <x-dashboard.score-stripes :percent="$model['bar']" />

                </div>

            @endforeach

        </div>

    </div>

</div>

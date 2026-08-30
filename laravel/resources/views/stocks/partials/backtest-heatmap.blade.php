@php
    $currentHeatmapScore = $scorePercent ?? \App\Support\AiScore::toPercent(
        $prediction?->prediction_score ?? $prediction?->ai_score ?? $prediction?->raw_ai_score
    );
    $currentHeatmapScore = is_numeric($currentHeatmapScore)
        ? max(1, min(99, (float) $currentHeatmapScore))
        : null;
    $currentHeatmapConfidence = $confidencePercent
        ?? (is_numeric($prediction?->confidence ?? $prediction?->confidence_score)
            ? ((float) ($prediction?->confidence ?? $prediction?->confidence_score) <= 1
                ? (float) ($prediction?->confidence ?? $prediction?->confidence_score) * 100
                : (float) ($prediction?->confidence ?? $prediction?->confidence_score))
            : null);
    $currentHeatmapConfidence = is_numeric($currentHeatmapConfidence)
        ? max(1, min(99, (float) $currentHeatmapConfidence))
        : null;
    $stockHeatmapMetrics = [
        ['key' => 'hit_rate', 'label' => __('Hitrate'), 'suffix' => '%'],
        ['key' => 'profit_factor', 'label' => __('Profitfaktor'), 'suffix' => ''],
        ['key' => 'drawdown', 'label' => __('Drawdown'), 'suffix' => '%'],
        ['key' => 'samples', 'label' => __('Trades'), 'suffix' => ''],
    ];
@endphp

<section data-stock-collapsible="heatmap" data-stock-collapsible-title="{{ __('Heatmap') }}" class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] px-4 py-3 shadow-[var(--ak-shadow)]">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.16em] text-teal-500">{{ __('Historischer Backtest') }}</p>
            <h2 class="text-base font-black text-[var(--ak-text)]">{{ __('KI-Score und Konfidenz für :symbol', ['symbol' => $instrument->symbol]) }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                {{ __('Trades') }} <strong class="ml-1 text-[var(--ak-text)]">{{ number_format((int) ($stockHeatmapSummary?->trades ?? 0), 0, ',', '.') }}</strong>
            </span>
            <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                {{ __('Hitrate') }} <strong class="ml-1 text-[var(--ak-text)]">{{ is_numeric($stockHeatmapSummary?->hit_rate) ? number_format((float) $stockHeatmapSummary->hit_rate, 1, ',', '.').' %' : '—' }}</strong>
            </span>
            <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                {{ __('Profitfaktor') }} <strong class="ml-1 text-[var(--ak-text)]">{{ is_numeric(data_get($stockHeatmapSummary, 'profit_factor')) ? number_format(\App\Support\ProfitFactor::cap(data_get($stockHeatmapSummary, 'profit_factor')), 2, ',', '.') : '—' }}</strong>
            </span>
            <span class="rounded-lg border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-3 py-2 text-xs font-bold text-[var(--ak-muted)]">
                {{ __('Max. Drawdown') }} <strong class="ml-1 text-[var(--ak-text)]">{{ is_numeric($stockHeatmapSummary?->drawdown) ? number_format((float) $stockHeatmapSummary->drawdown, 1, ',', '.').' %' : '—' }}</strong>
            </span>
        </div>
    </div>

    @if ((int) ($stockHeatmapSummary?->trades ?? 0) === 0)
        <div class="rounded-2xl border border-dashed border-[var(--ak-border)] bg-[var(--ak-card)] p-10 text-center text-sm font-bold text-[var(--ak-muted)]">
            {{ __('Für diese Aktie liegen im aktuellen Backtest noch keine Trades vor.') }}
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stockHeatmapMetrics as $metric)
                <article class="flex aspect-square min-h-0 min-w-0 flex-col rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] p-3 shadow-[var(--ak-shadow)]">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-black text-[var(--ak-text)]">{{ $metric['label'] }}</h3>
                        <div class="flex items-center gap-2">
                            @if ($currentHeatmapScore !== null)
                                <span class="text-[8px] font-bold uppercase tracking-wide text-amber-300/60">{{ __('KI') }} {{ number_format($currentHeatmapScore / 10, 1, ',', '.') }}</span>
                            @endif
                            @if ($currentHeatmapConfidence !== null)
                                <span class="text-[8px] font-bold uppercase tracking-wide text-amber-300/60">{{ __('Konf.') }} {{ number_format($currentHeatmapConfidence, 1, ',', '.') }} %</span>
                            @endif
                        </div>
                    </div>
                    <div class="grid min-h-0 flex-1 grid-cols-[34px_repeat(10,minmax(0,1fr))] grid-rows-[repeat(10,minmax(0,1fr))_14px] gap-1" style="position: relative;">
                        @if ($currentHeatmapScore !== null || $currentHeatmapConfidence !== null)
                            <div
                                aria-hidden="true"
                                style="position: absolute; inset: 0 0 18px 38px; z-index: 20; overflow: hidden; pointer-events: none;"
                            >
                                @if ($currentHeatmapScore !== null)
                                    <span
                                        style="position: absolute; top: 0; bottom: 0; left: {{ $currentHeatmapScore }}%; display: block; width: 1px; background: repeating-linear-gradient(to bottom, rgba(251, 191, 36, .48) 0 4px, transparent 4px 8px);"
                                        title="{{ __('Aktueller KI-Score') }}: {{ number_format($currentHeatmapScore / 10, 1, ',', '.') }}"
                                    ></span>
                                @endif
                                @if ($currentHeatmapConfidence !== null)
                                    <span
                                        style="position: absolute; right: 0; bottom: {{ $currentHeatmapConfidence }}%; left: 0; display: block; height: 1px; background: repeating-linear-gradient(to right, rgba(251, 191, 36, .48) 0 4px, transparent 4px 8px);"
                                        title="{{ __('Aktuelle Konfidenz') }}: {{ number_format($currentHeatmapConfidence, 1, ',', '.') }} %"
                                    ></span>
                                @endif
                            </div>
                        @endif
                        @for ($confidenceBucket = 9; $confidenceBucket >= 0; $confidenceBucket--)
                            <div class="flex items-center justify-end pr-1 text-[8px] font-bold tabular-nums text-[var(--ak-muted)]">
                                {{ $confidenceBucket * 10 }}–{{ ($confidenceBucket + 1) * 10 }}
                            </div>
                            @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                                @php
                                    $cell = $stockHeatmap->get($scoreBucket.'-'.$confidenceBucket);
                                    $samples = (int) ($cell->samples ?? 0);
                                    $rawValue = $metric['key'] === 'samples'
                                        ? $samples
                                        : (is_numeric(data_get($cell, $metric['key'])) ? (float) data_get($cell, $metric['key']) : null);
                                    $hasValue = in_array($metric['key'], ['samples', 'drawdown'], true)
                                        ? $samples > 0
                                        : $samples >= 5 && $rawValue !== null;
                                    $drawdownClass = match (true) {
                                        $rawValue >= 40 => 'border-rose-300/40 bg-rose-500/30 text-rose-50',
                                        $rawValue >= 30 => 'border-orange-300/45 bg-orange-400/30 text-orange-50',
                                        $rawValue >= 20 => 'border-amber-200/45 bg-amber-300/30 text-amber-50',
                                        $rawValue >= 10 => 'border-yellow-200/45 bg-yellow-300/30 text-yellow-50',
                                        $rawValue >= 5 => 'border-lime-300/30 bg-lime-400/17 text-lime-50',
                                        default => 'border-emerald-300/20 bg-emerald-400/10 text-emerald-100',
                                    };
                                    $good = match ($metric['key']) {
                                        'hit_rate' => $rawValue >= 55,
                                        'profit_factor' => $rawValue >= 1.25,
                                        default => false,
                                    };
                                    $weak = match ($metric['key']) {
                                        'hit_rate' => $rawValue < 45,
                                        'profit_factor' => $rawValue < 1,
                                        default => false,
                                    };
                                    $cellClass = ! $hasValue
                                        ? 'border-white/[.05] bg-slate-500/[.07] text-slate-500'
                                        : ($metric['key'] === 'samples'
                                            ? ($rawValue >= 15 ? 'border-orange-400/30 bg-orange-400/20 text-orange-400' : 'border-orange-400/15 bg-orange-400/[.08] text-orange-400')
                                            : ($metric['key'] === 'drawdown'
                                                ? $drawdownClass
                                                : ($good
                                                    ? 'border-emerald-300/25 bg-emerald-400/20 text-emerald-100'
                                                    : ($weak
                                                        ? 'border-rose-400/20 bg-rose-400/15 text-rose-200'
                                                        : 'border-amber-300/20 bg-amber-300/12 text-amber-100'))));
                                    $displayValue = ! $hasValue
                                        ? ($samples ?: '—')
                                        : ($metric['key'] === 'profit_factor'
                                            ? number_format($rawValue, 2, ',', '.')
                                            : ($metric['key'] === 'samples'
                                                ? number_format($rawValue, 0, ',', '.')
                                                : number_format($rawValue, 0, ',', '.').$metric['suffix']));
                                @endphp
                                <a
                                    href="{{ route('predictions.heatmap.trades', [
                                        'instrument_id' => $instrument->id,
                                        'score_bucket' => $scoreBucket,
                                        'confidence_bucket' => $confidenceBucket,
                                    ]) }}"
                                    class="flex min-h-0 min-w-0 items-center justify-center rounded-[4px] border {{ $cellClass }} transition hover:z-10 hover:border-teal-300/60"
                                    title="{{ __('Score :scoreFrom–:scoreTo · Konfidenz :confidenceFrom–:confidenceTo % · :metric: :value · :samples Trades', [
                                        'scoreFrom' => $scoreBucket,
                                        'scoreTo' => $scoreBucket + 1,
                                        'confidenceFrom' => $confidenceBucket * 10,
                                        'confidenceTo' => ($confidenceBucket + 1) * 10,
                                        'metric' => $metric['label'],
                                        'value' => $displayValue,
                                        'samples' => $samples,
                                    ]) }}"
                                >
                                    <span class="text-[8px] font-black tabular-nums">{{ $displayValue }}</span>
                                </a>
                            @endfor
                        @endfor
                        <div></div>
                        @for ($scoreBucket = 0; $scoreBucket <= 9; $scoreBucket++)
                            <div class="text-center text-[8px] font-bold tabular-nums text-[var(--ak-muted)]">{{ $scoreBucket }}–{{ $scoreBucket + 1 }}</div>
                        @endfor
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

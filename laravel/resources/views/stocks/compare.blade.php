<x-app-layout>
    @php
        $flags = ['DE' => '🇩🇪', 'US' => '🇺🇸', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'GB' => '🇬🇧', 'FR' => '🇫🇷', 'CH' => '🇨🇭', 'NL' => '🇳🇱', 'AU' => '🇦🇺', 'CA' => '🇨🇦', 'AT' => '🇦🇹', 'BE' => '🇧🇪', 'DK' => '🇩🇰', 'ES' => '🇪🇸', 'FI' => '🇫🇮', 'IE' => '🇮🇪', 'IT' => '🇮🇹', 'NO' => '🇳🇴', 'SE' => '🇸🇪'];
        $percentage = fn ($value) => is_numeric($value)
            ? number_format((float) $value * (abs((float) $value) <= 1 ? 100 : 1), 2, ',', '.').' %'
            : '—';
        $score = fn ($value) => \App\Support\AiScore::toTen($value) !== null
            ? number_format(\App\Support\AiScore::toTen($value), 1, ',', '.').' / 10'
            : '—';
        $price = fn ($value, $currency) => is_numeric($value)
            ? number_format((float) $value, 2, ',', '.').' '.($currency ?: '')
            : '—';
        $fundamental = function ($row, array $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row->fundamentals) && $row->fundamentals[$key] !== null) {
                    return $row->fundamentals[$key];
                }
            }

            return null;
        };
        $returnFor = fn ($row, string $target) => is_numeric($row->{$target})
            && is_numeric($row->current_price)
            && (float) $row->current_price !== 0.0
                ? (((float) $row->{$target} - (float) $row->current_price) / (float) $row->current_price) * 100
                : null;
    @endphp

    <div class="flex h-[calc(100dvh-89px)] min-h-0 flex-col py-4 text-[var(--ak-text)]">
        <header class="mb-4 flex shrink-0 flex-col justify-between gap-3 border-b border-[var(--ak-border)] pb-3 sm:flex-row sm:items-end">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-violet-300">{{ __('Direktvergleich') }}</p>
                <h1 class="mt-1 text-2xl font-black">{{ __('Aktien vergleichen') }}</h1>
                <p class="mt-1 text-sm text-[var(--ak-muted)]">{{ __('Vergleiche Prognosen, Risiko und Fundamentaldaten von bis zu fünf Aktien.') }}</p>
            </div>
            <a href="{{ route('stocks.index') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl border border-[var(--ak-border)] bg-[var(--ak-surface-muted)] px-4 text-xs font-bold text-[var(--ak-muted)] transition hover:border-violet-400/30 hover:text-[var(--ak-text)] sm:self-auto">
                <x-heroicon-o-arrow-left class="h-4 w-4" />{{ __('Zur Aktienliste') }}
            </a>
        </header>

        <div class="min-h-0 flex-1 overflow-auto rounded-2xl border border-[var(--ak-border)] bg-[var(--ak-card)] shadow-[var(--ak-shadow)]">
            <table class="w-full min-w-[900px] border-separate border-spacing-x-2 border-spacing-y-1.5 px-2 pb-2 text-left">
                <thead class="sticky top-0 z-20 bg-[#111020]/96 backdrop-blur-xl">
                    <tr>
                        <th class="sticky left-0 z-30 w-48 min-w-48 rounded-xl border border-[var(--ak-border)] bg-[#18172a] px-4 py-4 text-[10px] font-black uppercase tracking-[.12em] text-[var(--ak-muted)] shadow-lg shadow-black/15">{{ __('Kennzahl') }}</th>
                        @foreach ($rows as $row)
                            <th class="min-w-52 rounded-2xl border border-violet-400/25 bg-[linear-gradient(145deg,rgba(139,92,246,.18),rgba(255,255,255,.055),var(--ak-surface-muted))] px-4 py-4 shadow-lg shadow-black/15">
                                <a href="{{ route('stocks.show', $row->symbol) }}" class="group flex items-center gap-3">
                                    <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-violet-400/20 bg-white/[.06] text-[10px] font-black text-violet-300">
                                        {{ strtoupper(substr($row->symbol, 0, 2)) }}
                                        <img src="{{ route('stocks.icon', $row->id) }}" alt="" class="absolute inset-1 h-8 w-8 object-contain opacity-0" onload="this.classList.remove('opacity-0'); this.parentElement.classList.add('bg-slate-50')" onerror="this.remove()">
                                    </span>
                                    <span class="min-w-0">
                                        <strong class="block text-sm font-black text-violet-300 group-hover:text-violet-200">{{ $row->symbol }}</strong>
                                        <span class="block truncate text-xs text-[var(--ak-muted)]">{{ $row->name }}</span>
                                    </span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--ak-border)]">
                    @php
                        $metrics = [
                            [__('Aktueller Kurs'), fn ($row) => $price($row->current_price, $row->currency), '', null, null],
                            [__('Land'), fn ($row) => $row->country ? (($flags[$row->country] ?? '🌐').' '.$row->country) : '—', '', null, null],
                            [__('Sektor'), fn ($row) => __($row->sector ?: '—'), '', null, null],
                            [__('Signal'), fn ($row) => strtoupper((string) ($row->signal ?: '—')), 'signal', null, null],
                            [__('KI-Score'), fn ($row) => $score($row->prediction_score), 'ai-score', fn ($row) => \App\Support\AiScore::toTen($row->prediction_score), 'high'],
                            [__('Konfidenz'), fn ($row) => $percentage($row->confidence), 'ai-confidence', fn ($row) => is_numeric($row->confidence) ? (float) $row->confidence : null, 'high'],
                            [__('Risiko'), fn ($row) => $percentage($row->risk_score ?? $row->drawdown_risk_factor), 'ai-risk', fn ($row) => is_numeric($row->risk_score ?? $row->drawdown_risk_factor) ? (float) ($row->risk_score ?? $row->drawdown_risk_factor) : null, 'low'],
                            [__('Ziel 5 Tage'), fn ($row) => $price($row->predicted_price_5d, $row->currency), '', null, null],
                            [__('Rendite 5 Tage'), fn ($row) => (($value = $returnFor($row, 'predicted_price_5d')) !== null ? (($value > 0 ? '+' : '').number_format($value, 2, ',', '.').' %') : '—'), 'return5', fn ($row) => $returnFor($row, 'predicted_price_5d'), 'high'],
                            [__('Ziel 20 Tage'), fn ($row) => $price($row->predicted_price_20d, $row->currency), '', null, null],
                            [__('Rendite 20 Tage'), fn ($row) => (($value = $returnFor($row, 'predicted_price_20d')) !== null ? (($value > 0 ? '+' : '').number_format($value, 2, ',', '.').' %') : '—'), 'return20', fn ($row) => $returnFor($row, 'predicted_price_20d'), 'high'],
                            [__('Marktkapitalisierung'), fn ($row) => (($value = $fundamental($row, ['marketCap', 'market_cap'])) && is_numeric($value) ? number_format((float) $value / 1_000_000_000, 2, ',', '.').' Mrd.' : '—'), '', null, null],
                            [__('KGV'), fn ($row) => (($value = $fundamental($row, ['trailingPE', 'trailing_pe', 'peRatio'])) && is_numeric($value) ? number_format((float) $value, 2, ',', '.') : '—'), '', fn ($row) => (($value = $fundamental($row, ['trailingPE', 'trailing_pe', 'peRatio'])) && is_numeric($value)) ? (float) $value : null, 'low'],
                            [__('Dividendenrendite'), fn ($row) => $percentage($fundamental($row, ['dividendYield', 'dividend_yield'])), '', fn ($row) => is_numeric($value = $fundamental($row, ['dividendYield', 'dividend_yield'])) ? (float) $value : null, 'high'],
                            [__('Gewinnmarge'), fn ($row) => $percentage($fundamental($row, ['profitMargins', 'profit_margin'])), '', fn ($row) => is_numeric($value = $fundamental($row, ['profitMargins', 'profit_margin'])) ? (float) $value : null, 'high'],
                            [__('Umsatzwachstum'), fn ($row) => $percentage($fundamental($row, ['revenueGrowth', 'revenue_growth'])), '', fn ($row) => is_numeric($value = $fundamental($row, ['revenueGrowth', 'revenue_growth'])) ? (float) $value : null, 'high'],
                        ];
                    @endphp
                    @foreach ($metrics as [$label, $value, $type, $rawValue, $preference])
                        @php
                            $comparableValues = $rawValue
                                ? $rows->mapWithKeys(fn ($row) => [$row->id => $rawValue($row)])
                                    ->filter(fn ($value) => is_numeric($value))
                                : collect();
                            $hasComparableRange = $comparableValues->count() >= 2
                                && (float) $comparableValues->min() !== (float) $comparableValues->max();
                            $bestValue = $hasComparableRange
                                ? ($preference === 'low' ? $comparableValues->min() : $comparableValues->max())
                                : null;
                            $worstValue = $hasComparableRange
                                ? ($preference === 'low' ? $comparableValues->max() : $comparableValues->min())
                                : null;
                            $isAiMetricRow = str_starts_with($type, 'ai-');
                        @endphp
                        <tr>
                            <th class="sticky left-0 z-10 rounded-xl border px-4 py-3 text-[10px] font-black uppercase tracking-[.08em] shadow-sm shadow-black/10 {{ $isAiMetricRow ? 'border-violet-300/35 bg-[#292442] text-violet-200' : 'border-[var(--ak-border)] bg-[#18172a] text-[var(--ak-muted)]' }}">{{ $label }}</th>
                            @foreach ($rows as $row)
                                @php
                                    $displayValue = $value($row);
                                    $numericReturn = str_starts_with($type, 'return') ? (float) str_replace(',', '.', $displayValue) : null;
                                    $signalValue = $type === 'signal' ? strtoupper((string) $row->signal) : '';
                                    $raw = $rawValue ? $rawValue($row) : null;
                                    $isBest = $hasComparableRange && is_numeric($raw) && (float) $raw === (float) $bestValue;
                                    $isWorst = $hasComparableRange && is_numeric($raw) && (float) $raw === (float) $worstValue;
                                    $isAiMetric = $isAiMetricRow;
                                @endphp
                                <td class="rounded-xl border px-4 py-3 text-center text-sm font-bold shadow-sm shadow-black/10 transition
                                    {{ $isAiMetric ? 'border-violet-300/35 bg-[linear-gradient(145deg,rgba(167,139,250,.22),rgba(255,255,255,.10),var(--ak-surface-muted))] hover:border-violet-300/55' : 'border-[var(--ak-border)] bg-[var(--ak-surface-muted)] hover:border-violet-400/25 hover:bg-violet-500/[.07]' }}
                                    {{ str_starts_with($type, 'return') ? ($numericReturn > 0 ? 'text-emerald-400' : ($numericReturn < 0 ? 'text-rose-400' : 'text-[var(--ak-muted)]')) : 'text-[var(--ak-text)]' }}">
                                    @if ($type === 'signal')
                                        <span class="inline-flex h-7 w-20 items-center justify-center rounded-lg border px-2 text-[10px] font-black
                                            {{ $signalValue === 'BUY' ? 'border-emerald-300/60 bg-emerald-400/25 text-emerald-100' : ($signalValue === 'SELL' ? 'border-rose-400/25 bg-rose-400/10 text-rose-400' : ($signalValue === 'WATCH' ? 'border-lime-300/25 bg-lime-300/10 text-lime-300' : 'border-amber-400/25 bg-amber-400/10 text-amber-300')) }}">
                                            {{ $displayValue }}
                                        </span>
                                    @else
                                        <span class="{{ $isBest ? 'inline-flex rounded-lg border border-emerald-400/30 bg-emerald-400/12 px-2.5 py-1 text-emerald-300' : ($isWorst ? 'inline-flex rounded-lg border border-rose-400/30 bg-rose-400/12 px-2.5 py-1 text-rose-300' : '') }}">
                                            {{ $displayValue }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <main id="market-deep-analysis" class="mx-auto w-full max-w-[1600px] px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
        <header class="ak-card ak-dashboard-card ak-deep-panel rounded-2xl border px-5 py-5">
            <p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Screener · Marktanalyse') }}</p>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">{{ __('Markt Deep Analysis') }}</h1>
                    <p class="mt-2 max-w-4xl text-sm text-[var(--ak-muted)]">{{ __('Vergleiche Prognoserevisionen mit den tatsächlichen Tagesbewegungen und ordne die erwartete 20-Tage-Rendite über das gemeinsame Q25–Q75-Band ein.') }}</p>
                </div>
                <span class="rounded-xl border border-amber-400/25 bg-amber-400/[.07] px-3 py-2 text-[9px] font-black uppercase tracking-[.1em] text-amber-500">{{ __('DAX · S&P 500 · Global') }}</span>
            </div>
        </header>

        <x-dashboard.monthly-backtest-ai-score :series="$monthlyBacktestAiScores" />

        @php
            $quantileRanksByHorizon = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($macroCards): array {
                $quantileRanks = collect(['q25', 'median', 'q75'])->mapWithKeys(function (string $quantile) use ($macroCards, $days): array {
                    $ranked = collect($macroCards)->mapWithKeys(fn (array $card): array => [
                        $card['key'] => data_get($card, "forecast_band.horizons.{$days}.{$quantile}"),
                    ])->filter(fn ($value) => is_numeric($value))->sortDesc();
                    return [$quantile => $ranked->keys()->mapWithKeys(fn (string $key, int $index): array => [$key => $index + 1])->all()];
                })->all();
                return [$days => $quantileRanks];
            })->all();
        @endphp
        <section x-data="{ expanded: false }" class="ak-card ak-dashboard-card ak-deep-panel mt-4 overflow-hidden rounded-2xl border" aria-labelledby="quantile-heatmap">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-cyan-400/15 px-4 py-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.16em] text-amber-500">{{ __('Quantil-Tabelle') }}</p>
                    <h2 id="quantile-heatmap" class="mt-1 text-base font-black">{{ __('Erwartungsband nach Horizont') }}</h2>
                </div>
                <div class="flex items-center gap-2"><span class="hidden text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)] sm:inline">{{ __('Alle Werte in Prozent') }}</span><button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-400/[.07] text-cyan-400" aria-label="{{ __('Tabelle ein- oder ausklappen') }}"><x-heroicon-o-chevron-down class="h-4 w-4 transition-transform" x-bind:class="expanded && 'rotate-180'" /></button></div>
            </div>
            <div x-show="expanded" x-cloak class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[980px] border-collapse text-right">
                    <thead>
                        <tr class="text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">
                            <th rowspan="2" class="border-b border-r border-[var(--ak-border)] px-4 py-3 text-left">{{ __('Markt') }}</th>
                            @foreach ([5, 10, 15, 20] as $days)<th colspan="3" class="border-b border-r border-[var(--ak-border)] px-2 py-2 text-center {{ $loop->odd ? 'bg-slate-500/[.10]' : 'bg-white/[.045]' }}">{{ $days }} {{ __('Tage') }}</th>@endforeach
                        </tr>
                        <tr class="text-[8px] font-black uppercase text-[var(--ak-muted)]">
                            @foreach ([5, 10, 15, 20] as $days)
                                @php $horizonGroupClass = $loop->odd ? 'bg-slate-500/[.10]' : 'bg-white/[.045]'; @endphp
                                <th class="border-b border-[var(--ak-border)] px-3 py-2 {{ $horizonGroupClass }}">Q25</th><th class="border-b border-[var(--ak-border)] px-3 py-2 text-amber-500 {{ $horizonGroupClass }}">{{ __('Median') }}</th><th class="border-b border-r border-[var(--ak-border)] px-3 py-2 {{ $horizonGroupClass }}">Q75</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ak-border)]">
                        @foreach ($macroCards as $card)
                            @php
                                $heatmapMarket = match ($card['key'] ?? '') { 'dax-ai-score' => 'DAX', 'sp500-ai-score' => 'S&P 500', default => __('Global') };
                            @endphp
                            <tr class="{{ $loop->odd ? 'ak-quantile-row-muted' : 'ak-quantile-row-clear' }} transition hover:bg-cyan-400/[.035]">
                                <th class="whitespace-nowrap border-r border-[var(--ak-border)] px-4 py-3 text-left text-xs font-black">{{ $heatmapMarket }}</th>
                                @foreach ([5, 10, 15, 20] as $days)
                                    @php
                                        $horizonGroupOdd = $loop->odd;
                                    @endphp
                                    @foreach (['q25', 'median', 'q75'] as $quantile)
                                        @php
                                            $heatValue = data_get($card, "forecast_band.horizons.{$days}.{$quantile}");
                                            $heatNumber = is_numeric($heatValue) ? (float) $heatValue : null;
                                            $valueRank = data_get($quantileRanksByHorizon, $days.'.'.$quantile.'.'.($card['key'] ?? ''));
                                            $valueRankClass = $valueRank ? 'ak-median-rank-'.$valueRank : '';
                                            $badgeToneClass = match ($valueRank) {
                                                1 => 'ak-heat-badge-best',
                                                3 => 'ak-heat-badge-worst',
                                                default => 'ak-heat-badge-neutral',
                                            };
                                            $cellToneClass = $valueRank === 1 ? 'ak-table-cell-best' : 'ak-table-cell-default';
                                        @endphp
                                        <td class="{{ $cellToneClass }} {{ $quantile === 'q75' ? 'border-r' : '' }} border-[var(--ak-border)] px-3 py-3 text-xs font-black tabular-nums"><span class="{{ $badgeToneClass }}">{{ $heatNumber === null ? '—' : (($heatNumber > 0 ? '+' : '').number_format($heatNumber, 2, ',', '.').' %') }}</span></td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div x-show="expanded" x-cloak class="grid gap-3 p-3 md:hidden">
                @foreach ([5, 10, 15, 20] as $days)
                    <section class="overflow-hidden rounded-xl border border-[var(--ak-border)]">
                        <h3 class="border-b border-[var(--ak-border)] bg-cyan-400/[.055] px-3 py-2 text-center text-[9px] font-black uppercase tracking-[.12em] text-cyan-400">{{ $days }} {{ __('Tage') }}</h3>
                        <div class="grid grid-cols-[minmax(72px,1fr)_repeat(3,minmax(0,1fr))] text-right text-[9px]">
                            <div class="border-b border-r border-[var(--ak-border)] px-2 py-2 text-left font-black text-[var(--ak-muted)]">{{ __('Markt') }}</div><div class="border-b border-[var(--ak-border)] px-1 py-2 font-black text-[var(--ak-muted)]">Q25</div><div class="border-b border-[var(--ak-border)] px-1 py-2 font-black text-amber-500">{{ __('Median') }}</div><div class="border-b border-[var(--ak-border)] px-1 py-2 font-black text-[var(--ak-muted)]">Q75</div>
                            @foreach ($macroCards as $card)
                                @php $mobileMarket = match ($card['key'] ?? '') { 'dax-ai-score' => 'DAX', 'sp500-ai-score' => 'S&P 500', default => __('Global') }; @endphp
                                <div class="border-r border-t border-[var(--ak-border)] px-2 py-2.5 text-left text-[10px] font-black">{{ $mobileMarket }}</div>
                                @foreach (['q25', 'median', 'q75'] as $quantile)
                                    @php
                                        $mobileValue = data_get($card, "forecast_band.horizons.{$days}.{$quantile}");
                                        $mobileNumber = is_numeric($mobileValue) ? (float) $mobileValue : null;
                                        $mobileRank = data_get($quantileRanksByHorizon, $days.'.'.$quantile.'.'.($card['key'] ?? ''));
                                        $mobileTone = match ($mobileRank) { 1 => 'text-emerald-300 bg-emerald-400/[.10]', 3 => 'text-rose-300', default => 'text-[var(--ak-text)]' };
                                    @endphp
                                    <div class="border-t border-[var(--ak-border)] px-1 py-2.5 font-black tabular-nums {{ $mobileTone }}">{{ $mobileNumber === null ? '—' : (($mobileNumber > 0 ? '+' : '').number_format($mobileNumber, 2, ',', '.').' %') }}</div>
                                @endforeach
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <p x-show="expanded" x-cloak class="border-t border-cyan-400/10 px-4 py-2 text-[9px] text-[var(--ak-muted)]">{{ __('Je Quantil und Horizont ist der höchste Wert grün und der niedrigste Wert rot markiert.') }}</p>
        </section>

        <x-dashboard.macro-indicator-cards :cards="$macroCards" collapsible />

        @php
            $countryQuantileRanksByHorizon = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($countrySummaries, $minimumCountryStocks): array {
                $ranks = collect(['q25', 'median', 'q75'])->mapWithKeys(function (string $quantile) use ($countrySummaries, $minimumCountryStocks, $days): array {
                    $ranked = collect($countrySummaries)->filter(function (array $country) use ($minimumCountryStocks, $days, $quantile): bool {
                        $horizon = data_get($country, "horizons.{$days}");
                        return $horizon && (int) ($horizon['count'] ?? 0) >= $minimumCountryStocks && is_numeric($horizon[$quantile] ?? null);
                    })->mapWithKeys(fn (array $country): array => [(string) $country['country'] => (float) data_get($country, "horizons.{$days}.{$quantile}")])->sortDesc();
                    return [$quantile => $ranked->keys()->mapWithKeys(fn (string $country, int $index): array => [$country => $index + 1])->all()];
                })->all();
                return [$days => $ranks];
            })->all();
        @endphp
        <section x-data="{ expanded: false }" class="ak-card ak-dashboard-card ak-deep-panel mt-4 overflow-hidden rounded-2xl border" aria-labelledby="country-analysis-table">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-cyan-400/15 px-4 py-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Länderanalyse') }}</p>
                    <h2 id="country-analysis-table" class="mt-1 text-base font-black">{{ __('Erwartete Renditen nach Land') }}</h2>
                </div>
                <div class="flex items-center gap-2"><span class="hidden rounded-lg border border-cyan-400/20 bg-cyan-400/[.05] px-2.5 py-1.5 text-[9px] font-black text-[var(--ak-muted)] sm:inline-flex">{{ __('Mindestens :count Aktien', ['count' => $minimumCountryStocks]) }}</span><button type="button" @click="expanded = ! expanded" :aria-expanded="expanded.toString()" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-400/[.07] text-cyan-400" aria-label="{{ __('Länderanalyse ein- oder ausklappen') }}"><x-heroicon-o-chevron-down class="h-4 w-4 transition-transform" x-bind:class="expanded && 'rotate-180'" /></button></div>
            </div>
            <div x-show="expanded" x-cloak class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1100px] border-collapse text-right">
                    <thead>
                        <tr class="text-[8px] font-black uppercase tracking-[.1em] text-[var(--ak-muted)]">
                            <th rowspan="2" class="border-b border-r border-[var(--ak-border)] px-4 py-3 text-left">{{ __('Land') }}</th>
                            <th rowspan="2" class="border-b border-r border-[var(--ak-border)] px-3 py-3">{{ __('Aktien') }}</th>
                            @foreach ([5, 10, 15, 20] as $days)<th colspan="3" class="border-b border-r border-[var(--ak-border)] px-2 py-2 text-center {{ $loop->odd ? 'bg-slate-500/[.10]' : 'bg-white/[.045]' }}">{{ $days }} {{ __('Tage') }}</th>@endforeach
                        </tr>
                        <tr class="text-[8px] font-black uppercase text-[var(--ak-muted)]">
                            @foreach ([5, 10, 15, 20] as $days)
                                @php $countryHeaderClass = $loop->odd ? 'bg-slate-500/[.10]' : 'bg-white/[.045]'; @endphp
                                <th class="border-b border-[var(--ak-border)] px-3 py-2 {{ $countryHeaderClass }}">Q25</th><th class="border-b border-[var(--ak-border)] px-3 py-2 text-amber-500 {{ $countryHeaderClass }}">{{ __('Median') }}</th><th class="border-b border-r border-[var(--ak-border)] px-3 py-2 {{ $countryHeaderClass }}">Q75</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cyan-400/10">
                        @foreach ($countrySummaries as $country)
                            @php
                                $countryPercent = fn (float $value): string => ($value > 0 ? '+' : '').number_format($value, 2, ',', '.').' %';
                                $countryCode = strtoupper(trim((string) $country['country']));
                                $countryFlag = strlen($countryCode) === 2 ? mb_chr(127397 + ord($countryCode[0])).mb_chr(127397 + ord($countryCode[1])) : '🌐';
                            @endphp
                            <tr class="{{ $loop->odd ? 'ak-quantile-row-muted' : 'ak-quantile-row-clear' }} text-xs font-bold text-[var(--ak-text)] transition hover:bg-cyan-400/[.035]">
                                <th class="border-r border-[var(--ak-border)] px-4 py-3 text-sm font-black"><span class="mr-1.5" aria-hidden="true">{{ $countryFlag }}</span>{{ $country['country'] }}</th>
                                <td class="border-r border-[var(--ak-border)] px-3 py-3 text-right tabular-nums text-[var(--ak-muted)]">{{ number_format((int) data_get($country, 'horizons.20.count', 0), 0, ',', '.') }}</td>
                                @foreach ([5, 10, 15, 20] as $days)
                                    @php $countryHorizon = data_get($country, "horizons.{$days}"); @endphp
                                    @foreach (['q25', 'median', 'q75'] as $quantile)
                                        @php
                                            $countryValue = $countryHorizon && (int) ($countryHorizon['count'] ?? 0) >= $minimumCountryStocks && is_numeric($countryHorizon[$quantile] ?? null) ? (float) $countryHorizon[$quantile] : null;
                                            $countryRank = data_get($countryQuantileRanksByHorizon, $days.'.'.$quantile.'.'.(string) $country['country']);
                                            $countryRankCount = count(data_get($countryQuantileRanksByHorizon, $days.'.'.$quantile, []));
                                            $countryCellClass = $countryRank === 1 ? 'ak-table-cell-best' : 'ak-table-cell-default';
                                            $countryTextClass = $countryRank === 1 ? 'ak-heat-badge-best' : (($countryRankCount > 1 && $countryRank === $countryRankCount) ? 'ak-heat-badge-worst' : 'ak-heat-badge-neutral');
                                        @endphp
                                        <td class="{{ $countryCellClass }} {{ $quantile === 'q75' ? 'border-r' : '' }} border-[var(--ak-border)] px-3 py-3 text-xs font-black tabular-nums"><span class="{{ $countryTextClass }}">{{ $countryValue === null ? '—' : $countryPercent($countryValue) }}</span></td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div x-show="expanded" x-cloak class="grid gap-3 p-3 md:hidden">
                @foreach ([5, 10, 15, 20] as $days)
                    <section class="overflow-hidden rounded-xl border border-[var(--ak-border)]">
                        <h3 class="border-b border-[var(--ak-border)] bg-cyan-400/[.055] px-3 py-2 text-center text-[9px] font-black uppercase tracking-[.12em] text-cyan-400">{{ $days }} {{ __('Tage') }}</h3>
                        <div class="grid grid-cols-[minmax(76px,1.1fr)_repeat(3,minmax(0,1fr))] text-right text-[9px]">
                            <div class="border-b border-r border-[var(--ak-border)] px-2 py-2 text-left font-black text-[var(--ak-muted)]">{{ __('Land') }}</div><div class="border-b border-[var(--ak-border)] px-1 py-2 font-black text-[var(--ak-muted)]">Q25</div><div class="border-b border-[var(--ak-border)] px-1 py-2 font-black text-amber-500">{{ __('Median') }}</div><div class="border-b border-[var(--ak-border)] px-1 py-2 font-black text-[var(--ak-muted)]">Q75</div>
                            @foreach ($countrySummaries as $country)
                                @php
                                    $mobileCountryHorizon = data_get($country, "horizons.{$days}");
                                    $mobileCountryCount = (int) data_get($mobileCountryHorizon, 'count', 0);
                                    $mobileCountryCode = strtoupper(trim((string) $country['country']));
                                    $mobileCountryFlag = strlen($mobileCountryCode) === 2 ? mb_chr(127397 + ord($mobileCountryCode[0])).mb_chr(127397 + ord($mobileCountryCode[1])) : '🌐';
                                @endphp
                                <div class="border-r border-t border-[var(--ak-border)] px-2 py-2.5 text-left"><b class="block whitespace-nowrap text-[10px]"><span class="mr-1" aria-hidden="true">{{ $mobileCountryFlag }}</span>{{ $country['country'] }}</b><small class="block text-[7px] text-[var(--ak-muted)]">{{ $mobileCountryCount }} {{ __('Aktien') }}</small></div>
                                @foreach (['q25', 'median', 'q75'] as $quantile)
                                    @php
                                        $mobileCountryValue = $mobileCountryHorizon && $mobileCountryCount >= $minimumCountryStocks && is_numeric($mobileCountryHorizon[$quantile] ?? null) ? (float) $mobileCountryHorizon[$quantile] : null;
                                        $mobileCountryRank = data_get($countryQuantileRanksByHorizon, $days.'.'.$quantile.'.'.(string) $country['country']);
                                        $mobileCountryRankCount = count(data_get($countryQuantileRanksByHorizon, $days.'.'.$quantile, []));
                                        $mobileCountryTone = $mobileCountryRank === 1 ? 'text-emerald-300 bg-emerald-400/[.10]' : (($mobileCountryRankCount > 1 && $mobileCountryRank === $mobileCountryRankCount) ? 'text-rose-300' : 'text-[var(--ak-text)]');
                                    @endphp
                                    <div class="border-t border-[var(--ak-border)] px-1 py-2.5 font-black tabular-nums {{ $mobileCountryTone }}">{{ $mobileCountryValue === null ? '—' : (($mobileCountryValue > 0 ? '+' : '').number_format($mobileCountryValue, 2, ',', '.').' %') }}</div>
                                @endforeach
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <p x-show="expanded" x-cloak class="border-t border-cyan-400/10 px-4 py-2 text-[9px] text-[var(--ak-muted)]">{{ __('Aufgenommen werden Länder mit mindestens :count aktuellen 20T-Aktienprognosen. Auch je Einzelhorizont werden Werte erst ab dieser Mindestabdeckung gezeigt.', ['count' => $minimumCountryStocks]) }}</p>
        </section>

        @if (!empty($countryCards))
            <x-dashboard.macro-indicator-cards :cards="$countryCards" collapsible />
        @endif
    </main>
    <style>
        #market-deep-analysis { --ak-forecast-line: #c6a15b; }
        #market-deep-analysis .ak-deep-panel {
            border-color: rgba(103, 232, 249, .14);
            background: linear-gradient(145deg, rgba(15, 32, 51, .96), rgba(10, 24, 41, .94));
            box-shadow: 0 14px 34px rgba(0, 0, 0, .24), inset 3px 0 0 rgba(34, 211, 238, .58);
        }
        #market-deep-analysis .ak-best-horizon-badge {
            z-index: 2;
            border: 1px solid #fde68a;
            background: #f59e0b;
            color: #111827;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .48), 0 0 12px rgba(245, 158, 11, .45);
        }
        #market-deep-analysis .ak-horizon-rank-badge {
            border: 1px solid #94a3b8;
            background: #475569;
            color: #ffffff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .28);
        }
        #market-deep-analysis .ak-heat-value-badge {
            position: relative;
            display: inline-grid;
            place-items: center;
            width: calc(100% + .5rem);
            min-width: 64px;
            min-height: 56px;
            margin-inline: -.25rem;
            border-radius: .55rem;
            white-space: nowrap;
        }
        #market-deep-analysis .ak-heat-cell {
            border-left: 1px solid rgba(148, 163, 184, .24);
        }
        #market-deep-analysis .ak-heat-cell-start {
            border-left-color: rgba(103, 232, 249, .52);
            border-left-width: 2px;
        }
        #market-deep-analysis .ak-heat-cell-end {
            border-right: 2px solid rgba(103, 232, 249, .52);
        }
        #market-deep-analysis .ak-heat-badge-neutral { background: transparent; color: #e2e8f0; }
        #market-deep-analysis .ak-heat-badge-best { background: transparent; color: #d1fae5; }
        #market-deep-analysis .ak-heat-badge-worst { background: transparent; color: #ffe4e6; }
        #market-deep-analysis .ak-table-cell-default { background: transparent; }
        #market-deep-analysis .ak-quantile-row-muted > th,
        #market-deep-analysis .ak-quantile-row-muted > td { background: rgba(148, 163, 184, .045) !important; }
        #market-deep-analysis .ak-quantile-row-clear > th,
        #market-deep-analysis .ak-quantile-row-clear > td { background: rgba(15, 23, 42, .14) !important; }
        #market-deep-analysis td.ak-table-cell-best { background: rgba(16, 185, 129, .20) !important; }
        #market-deep-analysis .ak-median-rank-1 {
            z-index: 4;
            border: 2px solid #34d399;
            box-shadow: 0 0 12px rgba(16, 185, 129, .42), inset 0 0 8px rgba(16, 185, 129, .08);
        }
        #market-deep-analysis .ak-median-rank-2 {
            z-index: 3;
            transform: scale(.9);
            border: 2px solid #94a3b8;
            box-shadow: none;
        }
        #market-deep-analysis .ak-median-rank-3 {
            z-index: 2;
            transform: scale(.8);
            border: 2px solid #fb7185;
            box-shadow: none;
        }
        html[data-theme="light"] #market-deep-analysis { --ak-forecast-line: #a16207; }
        html[data-theme="light"] #market-deep-analysis .ak-deep-panel {
            border-color: rgba(8, 127, 140, .18);
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 14px 32px rgba(31, 41, 55, .08), inset 3px 0 0 rgba(8, 127, 140, .48);
        }
        html[data-theme="light"] #market-deep-analysis .ak-best-horizon-badge { border-color: #78350f; background: #f59e0b; color: #111827; box-shadow: 0 2px 7px rgba(120, 53, 15, .38); }
        html[data-theme="light"] #market-deep-analysis .ak-horizon-rank-badge { border-color: #334155; background: #64748b; color: #ffffff; box-shadow: 0 2px 5px rgba(51, 65, 85, .28); }
        html[data-theme="light"] #market-deep-analysis .ak-heat-badge-neutral { background: transparent; color: #334155; }
        html[data-theme="light"] #market-deep-analysis .ak-heat-badge-best { background: transparent; color: #065f46; }
        html[data-theme="light"] #market-deep-analysis .ak-heat-badge-worst { background: transparent; color: #9f1239; }
        html[data-theme="light"] #market-deep-analysis .ak-table-cell-default { background: transparent; }
        html[data-theme="light"] #market-deep-analysis .ak-quantile-row-muted > th,
        html[data-theme="light"] #market-deep-analysis .ak-quantile-row-muted > td { background: #fbfcfd !important; }
        html[data-theme="light"] #market-deep-analysis .ak-quantile-row-clear > th,
        html[data-theme="light"] #market-deep-analysis .ak-quantile-row-clear > td { background: #ffffff !important; }
        html[data-theme="light"] #market-deep-analysis td.ak-table-cell-best { background: #dcfce7 !important; }
        html[data-theme="light"] #market-deep-analysis .ak-heat-cell { border-left-color: rgba(71, 85, 105, .28); }
        html[data-theme="light"] #market-deep-analysis .ak-heat-cell-start { border-left-color: rgba(8, 127, 140, .62); }
        html[data-theme="light"] #market-deep-analysis .ak-heat-cell-end { border-right-color: rgba(8, 127, 140, .62); }
        html[data-theme="light"] #market-deep-analysis .ak-median-rank-1 { border: 2px solid #059669; box-shadow: 0 0 11px rgba(5, 150, 105, .30), inset 0 0 7px rgba(5, 150, 105, .06); }
        html[data-theme="light"] #market-deep-analysis .ak-median-rank-2 { border-color: #64748b; box-shadow: none; }
        html[data-theme="light"] #market-deep-analysis .ak-median-rank-3 { border-color: #e11d48; box-shadow: none; }
    </style>
</x-app-layout>

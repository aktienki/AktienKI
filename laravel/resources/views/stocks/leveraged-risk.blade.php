<x-app-layout>
    @php
        $cells = collect($profile['cells']);
        $riskLevel = static fn (float $value): int => match (true) {
            $value <= 20 => 1, $value <= 35 => 2, $value <= 50 => 3, $value <= 65 => 4, default => 5,
        };
        $volatilityLabel = match ($profile['volatility_bucket']) { 'low' => __('Niedrig'), 'medium' => __('Mittel'), 'high' => __('Hoch'), default => '—' };
        $chartWidth = 820;
        $chartHeight = 390;
        $chartPadding = ['left' => 34, 'right' => 78, 'top' => 24, 'bottom' => 34];
        $currentPrice = (float) ($chartBars->last()['close'] ?? 0);
        $forecastReturnPercent = (float) $profile['predicted_return_percent'];
        $forecastTargetPrice = $currentPrice * (1 + $forecastReturnPercent / 100);
        $forecastDirection = $forecastReturnPercent < 0 ? 'short' : 'long';
        $forecastColor = $forecastDirection === 'long' ? '#fde047' : '#fb7185';
        $historicalProbability = static fn (float $value, int $sampleSize): string => $value <= 0
            ? '< '.number_format(min(100, 300 / max(1, $sampleSize)), 1, ',', '.').' %*'
            : number_format($value, 1, ',', '.').' %';
        $simulationProbability = static fn (float $value): string => $value <= 0
            ? '< 0,1 %'
            : number_format($value, 1, ',', '.').' %';
        $mcEndPoint = collect($monteCarlo['quantile_path'])->last();
        $forecastBandLow = $currentPrice * (1 + (float) ($mcEndPoint['p25_percent'] ?? 0) / 100);
        $forecastBandHigh = $currentPrice * (1 + (float) ($mcEndPoint['p75_percent'] ?? 0) / 100);
        $supportPrice = is_numeric($technicalLevels['support'] ?? null) ? (float) $technicalLevels['support'] : null;
        $resistancePrice = is_numeric($technicalLevels['resistance'] ?? null) ? (float) $technicalLevels['resistance'] : null;
        $supportDistance = $supportPrice && $currentPrice > 0 ? 100 * ($currentPrice - $supportPrice) / $currentPrice : null;
        $resistanceDistance = $resistancePrice && $currentPrice > 0 ? 100 * ($resistancePrice - $currentPrice) / $currentPrice : null;
        $allBarrierPrices = collect([5, 10, 15, 20])->flatMap(fn ($distance) => [$currentPrice * (1 - $distance / 100), $currentPrice * (1 + $distance / 100)]);
        $chartPrices = $chartBars->flatMap(fn (array $bar) => [$bar['low'], $bar['high']])->merge($allBarrierPrices)->push($forecastTargetPrice)->push($forecastBandLow)->push($forecastBandHigh)->push($supportPrice)->push($resistancePrice)->filter(fn ($value) => is_numeric($value) && $value > 0);
        $chartMin = (float) $chartPrices->min();
        $chartMax = (float) $chartPrices->max();
        $chartRange = max(.01, $chartMax - $chartMin);
        $plotWidth = $chartWidth - $chartPadding['left'] - $chartPadding['right'];
        $plotHeight = $chartHeight - $chartPadding['top'] - $chartPadding['bottom'];
        $priceY = static fn (float $price): float => $chartPadding['top'] + (($chartMax - $price) / $chartRange) * $plotHeight;
        $historyPlotWidth = $plotWidth * .78;
        $forecastStartX = $chartPadding['left'] + $historyPlotWidth;
        $forecastEndX = $chartPadding['left'] + $plotWidth;
        $candleStep = $historyPlotWidth / max(1, $chartBars->count());
        $candleWidth = max(1.5, min(6, $candleStep * .62));
        $forecastTargetProbability = (float) ($monteCarlo['sides'][$forecastDirection]['forecast_target_probability_percent'] ?? 0);
        $forecastPositiveProbability = 100 - (float) ($monteCarlo['sides'][$forecastDirection]['loss_probability_percent'] ?? 100);
        $initialSide = $profile['direction'] === 'short' ? 'short' : 'long';
        $mcWidth = 1120;
        $mcHeight = 300;
        $mcPadding = ['left' => 52, 'right' => 78, 'top' => 22, 'bottom' => 36];
        $mcPath = collect([['day' => 0, 'p10_percent' => 0, 'p25_percent' => 0, 'median_percent' => 0, 'p75_percent' => 0, 'p90_percent' => 0]])->concat($monteCarlo['quantile_path'])->values();
        $mcValues = $mcPath->flatMap(fn ($point) => [$point['p10_percent'], $point['p90_percent']]);
        $mcMin = min(-1, (float) $mcValues->min());
        $mcMax = max(1, (float) $mcValues->max());
        $mcRange = max(.01, $mcMax - $mcMin);
        $mcPlotWidth = $mcWidth - $mcPadding['left'] - $mcPadding['right'];
        $mcPlotHeight = $mcHeight - $mcPadding['top'] - $mcPadding['bottom'];
        $mcX = static fn (float $day): float => $mcPadding['left'] + ($day / 20) * $mcPlotWidth;
        $mcY = static fn (float $value): float => $mcPadding['top'] + (($mcMax - $value) / $mcRange) * $mcPlotHeight;
        $mcPoints = static fn (string $key) => $mcPath->map(fn ($point) => number_format($mcX((float) $point['day']), 1, '.', '').','.number_format($mcY((float) $point[$key]), 1, '.', ''))->implode(' ');
        $mcOuterArea = $mcPoints('p90_percent').' '.$mcPath->reverse()->map(fn ($point) => number_format($mcX((float) $point['day']), 1, '.', '').','.number_format($mcY((float) $point['p10_percent']), 1, '.', ''))->implode(' ');
        $mcInnerArea = $mcPoints('p75_percent').' '.$mcPath->reverse()->map(fn ($point) => number_format($mcX((float) $point['day']), 1, '.', '').','.number_format($mcY((float) $point['p25_percent']), 1, '.', ''))->implode(' ');
    @endphp
    <main x-data="{
        side: @js($initialSide),
        selectedMatrix: null,
        products: [],
        productCount: 0,
        productMessage: '',
        productError: '',
        productsLoading: false,
        async loadCertificates(matrixSide, leverage, lossThreshold) {
            this.side = matrixSide;
            this.selectedMatrix = { side: matrixSide, leverage, lossThreshold };
            this.productsLoading = true;
            this.productError = '';
            this.products = [];
            try {
                const url = new URL(@js(route('stocks.leveraged-risk.certificates', $instrument->symbol)), window.location.origin);
                url.searchParams.set('side', matrixSide);
                url.searchParams.set('leverage', leverage);
                url.searchParams.set('loss_threshold', lossThreshold);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(@js(__('Die Zertifikate konnten nicht geladen werden.')));
                const payload = await response.json();
                this.products = payload.data || [];
                this.productCount = payload.count || 0;
                this.productMessage = payload.message || '';
            } catch (error) {
                this.productError = error.message;
            } finally {
                this.productsLoading = false;
                this.$nextTick(() => document.getElementById('matrix-certificates')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        }
    }" class="mx-auto w-full max-w-[1500px] space-y-5 px-3 py-5 text-[var(--ak-text)] sm:px-5 lg:py-8">
        <header class="ak-card rounded-2xl border border-cyan-400/25 px-5 py-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[.18em] text-cyan-400">{{ __('Premium · Hebelzertifikate') }}</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight">🇩🇪 {{ $instrument->name }} · {{ __('20T-Risikoanalyse') }}</h1>
                    <p class="mt-2 max-w-4xl text-sm text-[var(--ak-muted)]">{{ __('Empirische Point-in-Time-Auswertung historischer Modell-Scores, Volatilitätszustände und tatsächlicher OHLC-Kursverläufe.') }}</p>
                </div>
                <a href="{{ route('stocks.show', $instrument->symbol) }}" class="inline-flex h-10 items-center rounded-xl border border-[var(--ak-border)] px-4 text-xs font-black text-cyan-400">← {{ __('Zur Aktie') }}</a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ([
                [__('20T-Prognose'), number_format($profile['predicted_return_percent'], 1, ',', '.').' %'],
                [__('Richtung'), strtoupper($profile['direction'])],
                [__('Score-Perzentil'), number_format($profile['point_in_time_score'], 1, ',', '.')],
                [__('Scoreklasse'), $profile['score_bucket'].' / 5'],
                [__('Volatilität'), number_format($profile['volatility_percent'], 1, ',', '.').' %'],
                [__('Volatilitätsklasse'), $volatilityLabel],
            ] as [$label, $value])
                <article class="ak-card rounded-xl border border-[var(--ak-border)] p-4"><p class="text-[9px] font-black uppercase tracking-wider text-[var(--ak-muted)]">{{ $label }}</p><p class="mt-2 text-xl font-black">{{ $value }}</p></article>
            @endforeach
        </section>

        <nav class="ak-card grid grid-cols-2 gap-2 rounded-2xl border border-[var(--ak-border)] p-2" aria-label="{{ __('Positionsrichtung') }}">
            <button type="button" @click="side='long'" :aria-selected="side==='long'" :class="side==='long' ? 'border-yellow-300 bg-yellow-300/10 text-yellow-300 shadow-[0_0_24px_rgba(253,224,71,.10)]' : 'border-transparent text-[var(--ak-muted)] hover:border-[var(--ak-border)]'" class="flex min-h-14 items-center justify-center gap-3 rounded-xl border px-4 text-sm font-black transition"><span class="text-lg">↗</span><span>LONG / CALL</span></button>
            <button type="button" @click="side='short'" :aria-selected="side==='short'" :class="side==='short' ? 'border-rose-400 bg-rose-400/15 text-rose-400 shadow-[0_0_24px_rgba(251,113,133,.12)]' : 'border-transparent text-[var(--ak-muted)] hover:border-[var(--ak-border)]'" class="flex min-h-14 items-center justify-center gap-3 rounded-xl border px-4 text-sm font-black transition"><span class="text-lg">↘</span><span>SHORT / PUT</span></button>
        </nav>

        <section class="grid items-stretch gap-5 xl:grid-cols-[.82fr_1.18fr]">
            <div class="grid gap-3">
            @foreach (['long' => ['LONG / CALL', 'border-yellow-300/25', 'text-yellow-300'], 'short' => ['SHORT / PUT', 'border-rose-400/25', 'text-rose-400']] as $side => [$title, $borderClass, $textClass])
                @php $sideCells = $cells->where('side', $side)->values(); @endphp
                <article x-cloak x-show="side===@js($side)" class="ak-card overflow-hidden rounded-xl border {{ $borderClass }}">
                    <div class="flex items-center justify-between border-b border-[var(--ak-border)] px-4 py-2.5"><div><p class="text-[8px] font-black uppercase tracking-[.16em] {{ $textClass }}">{{ $title }}</p><h2 class="mt-0.5 text-sm font-black">{{ __('Historisches Verlustrisiko') }}</h2></div></div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[500px] text-left text-[10px]">
                            <thead class="bg-[var(--ak-surface-muted)] text-[8px] uppercase text-[var(--ak-muted)]"><tr><th class="px-3 py-2">{{ __('Abstand') }}</th><th class="px-2 py-2 text-right">{{ __('ca. Hebel') }}</th><th class="px-2 py-2 text-right">{{ __('20T-Verlust') }}</th><th class="px-2 py-2 text-right">{{ __('Berührt') }}</th><th class="px-2 py-2 text-right">{{ __('ES 10 %') }}</th><th class="px-2 py-2 text-right">{{ __('Risiko') }}</th><th class="px-3 py-2 text-right">n</th></tr></thead>
                            <tbody>
                                @foreach ($sideCells as $cell)
                                    @php $combined = max($cell['loss_probability_percent'], $cell['barrier_breach_probability_percent']); @endphp
                                    @php $indicativeLeverage = 100 / max(.01, $cell['barrier_distance_percent']); @endphp
                                    <tr class="border-t border-[var(--ak-border)]"><td class="px-3 py-2 font-black">{{ number_format($cell['barrier_distance_percent'], 0) }} %</td><td class="px-2 py-2 text-right font-black text-orange-400">{{ number_format($indicativeLeverage, 1, ',', '.') }}×</td><td class="px-2 py-2 text-right font-black {{ $cell['loss_probability_percent'] >= 50 ? 'text-rose-400' : ($cell['loss_probability_percent'] >= 25 ? 'text-orange-400' : 'text-yellow-300') }}">{{ $historicalProbability($cell['loss_probability_percent'], $cell['sample_size']) }}</td><td class="px-2 py-2 text-right font-black {{ $cell['barrier_breach_probability_percent'] >= 35 ? 'text-rose-400' : ($cell['barrier_breach_probability_percent'] >= 15 ? 'text-orange-400' : 'text-yellow-300') }}">{{ $historicalProbability($cell['barrier_breach_probability_percent'], $cell['sample_size']) }}</td><td class="px-2 py-2 text-right text-rose-400">{{ number_format($cell['expected_shortfall_10_percent'], 1, ',', '.') }} %</td><td class="px-2 py-2 text-right"><span class="rounded-md border {{ $borderClass }} px-1.5 py-0.5 font-black">{{ $riskLevel($combined) }}/5</span></td><td class="px-3 py-2 text-right">{{ $cell['sample_size'] }}{!! $cell['sample_size_sufficient'] ? '' : ' *' !!}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @endforeach
            </div>

            <article class="ak-card min-w-0 overflow-hidden rounded-2xl border border-cyan-400/25">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] px-5 py-4">
                    <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Kurs und Barrieren') }}</p><h2 class="mt-1 text-xl font-black">{{ $instrument->name }}</h2></div>
                    <span x-text="side === 'long' ? 'LONG / CALL' : 'SHORT / PUT'" :class="side === 'long' ? 'border-yellow-300/25 text-yellow-300' : 'border-rose-400/25 text-rose-400'" class="rounded-lg border px-3 py-2 text-[9px] font-black"></span>
                </div>
                <div class="p-4">
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-auto w-full" role="img" aria-label="{{ __('Kursverlauf mit historischen Risikobarrieren') }}">
                        @foreach ([0, .25, .5, .75, 1] as $tick)
                            @php $gridY = $chartPadding['top'] + $tick * $plotHeight; $gridPrice = $chartMax - $tick * $chartRange; @endphp
                            <line x1="{{ $chartPadding['left'] }}" x2="{{ $chartWidth-$chartPadding['right'] }}" y1="{{ $gridY }}" y2="{{ $gridY }}" stroke="currentColor" class="text-slate-400/15" stroke-dasharray="4 6" />
                            <text x="{{ $chartWidth-$chartPadding['right']+8 }}" y="{{ $gridY+4 }}" fill="currentColor" class="text-[10px] text-[var(--ak-muted)]">{{ number_format($gridPrice, 2, ',', '.') }} €</text>
                        @endforeach
                        @foreach ($chartBars as $barIndex => $bar)
                            @php
                                $candleX = $chartPadding['left'] + ($barIndex + .5) * $candleStep;
                                $candleOpenY = $priceY((float) $bar['open']);
                                $candleCloseY = $priceY((float) $bar['close']);
                                $candleHighY = $priceY((float) $bar['high']);
                                $candleLowY = $priceY((float) $bar['low']);
                                $candleTop = min($candleOpenY, $candleCloseY);
                                $candleBodyHeight = max(1.4, abs($candleCloseY - $candleOpenY));
                                $candleColor = (float) $bar['close'] >= (float) $bar['open'] ? '#fde047' : '#fb7185';
                            @endphp
                            <line x1="{{ $candleX }}" x2="{{ $candleX }}" y1="{{ $candleHighY }}" y2="{{ $candleLowY }}" stroke="{{ $candleColor }}" stroke-width="1" opacity=".9" />
                            <rect x="{{ $candleX-$candleWidth/2 }}" y="{{ $candleTop }}" width="{{ $candleWidth }}" height="{{ $candleBodyHeight }}" rx=".7" fill="{{ $candleColor }}" opacity=".95" />
                        @endforeach
                        <polygon points="{{ $forecastStartX }},{{ $priceY($currentPrice) }} {{ $forecastEndX }},{{ $priceY($forecastBandHigh) }} {{ $forecastEndX }},{{ $priceY($forecastBandLow) }}" fill="{{ $forecastColor }}" opacity=".16" />
                        <line x1="{{ $forecastStartX }}" x2="{{ $forecastEndX }}" y1="{{ $priceY($currentPrice) }}" y2="{{ $priceY($forecastTargetPrice) }}" stroke="{{ $forecastColor }}" stroke-width="3" stroke-dasharray="7 5" />
                        <circle cx="{{ $forecastEndX }}" cy="{{ $priceY($forecastTargetPrice) }}" r="5" fill="{{ $forecastColor }}" />
                        <text x="{{ $forecastEndX-6 }}" y="{{ $priceY($forecastTargetPrice)-10 }}" fill="{{ $forecastColor }}" font-size="10" text-anchor="end" font-weight="900">{{ __('Ziel') }} {{ number_format($forecastTargetPrice, 2, ',', '.') }} €</text>
                        <text x="{{ $forecastEndX-6 }}" y="{{ min($chartHeight-$chartPadding['bottom']-5, $priceY($forecastTargetPrice)+18) }}" fill="{{ $forecastColor }}" font-size="9" text-anchor="end" font-weight="800">{{ __('Ziel') }} {{ $simulationProbability($forecastTargetProbability) }} · {{ __('positiv') }} {{ $simulationProbability($forecastPositiveProbability) }}</text>
                        @if ($supportPrice)
                            <g x-cloak x-show="side==='long'">
                                <line x1="{{ $chartPadding['left'] }}" x2="{{ $forecastEndX }}" y1="{{ $priceY($supportPrice) }}" y2="{{ $priceY($supportPrice) }}" stroke="#fde047" stroke-width="2.2" stroke-dasharray="3 4" />
                                <rect x="{{ $chartPadding['left']+5 }}" y="{{ $priceY($supportPrice)-17 }}" width="145" height="16" rx="5" fill="#713f12" opacity=".92" />
                                <text x="{{ $chartPadding['left']+11 }}" y="{{ $priceY($supportPrice)-6 }}" fill="#fde047" font-size="9" font-weight="900">{{ __('Unterstützung') }} {{ number_format($supportPrice, 2, ',', '.') }} € · −{{ number_format($supportDistance, 1, ',', '.') }} %</text>
                            </g>
                        @endif
                        @if ($resistancePrice)
                            <g x-cloak x-show="side==='short'">
                                <line x1="{{ $chartPadding['left'] }}" x2="{{ $forecastEndX }}" y1="{{ $priceY($resistancePrice) }}" y2="{{ $priceY($resistancePrice) }}" stroke="#fb7185" stroke-width="2.2" stroke-dasharray="3 4" />
                                <rect x="{{ $chartPadding['left']+5 }}" y="{{ $priceY($resistancePrice)-17 }}" width="141" height="16" rx="5" fill="#881337" opacity=".92" />
                                <text x="{{ $chartPadding['left']+11 }}" y="{{ $priceY($resistancePrice)-6 }}" fill="#fecdd3" font-size="9" font-weight="900">{{ __('Widerstand') }} {{ number_format($resistancePrice, 2, ',', '.') }} € · +{{ number_format($resistanceDistance, 1, ',', '.') }} %</text>
                            </g>
                        @endif
                        @foreach (['long' => -1, 'short' => 1] as $barrierSide => $direction)
                            <g x-cloak x-show="side===@js($barrierSide)">
                                @foreach ($cells->where('side', $barrierSide)->values() as $cell)
                                    @php
                                        $barrierPrice = $currentPrice * (1 + $direction * $cell['barrier_distance_percent'] / 100);
                                        $barrierY = $priceY($barrierPrice);
                                        $barrierColor = $cell['barrier_breach_probability_percent'] >= 35 ? '#fb7185' : ($cell['barrier_breach_probability_percent'] >= 15 ? '#fb923c' : '#fde047');
                                    @endphp
                                    <line x1="{{ $chartPadding['left'] }}" x2="{{ $chartWidth-$chartPadding['right'] }}" y1="{{ $barrierY }}" y2="{{ $barrierY }}" stroke="{{ $barrierColor }}" stroke-width="2" stroke-dasharray="8 6" opacity=".9" />
                                    <text x="{{ $chartPadding['left']+7 }}" y="{{ $barrierY-5 }}" fill="{{ $barrierColor }}" font-size="10" font-weight="800">{{ number_format($cell['barrier_distance_percent'], 0) }} % · ca. {{ number_format(100 / max(.01, $cell['barrier_distance_percent']), 1, ',', '.') }}× · {{ $historicalProbability($cell['barrier_breach_probability_percent'], $cell['sample_size']) }}</text>
                                @endforeach
                            </g>
                        @endforeach
                        <circle cx="{{ $forecastStartX }}" cy="{{ $priceY($currentPrice) }}" r="5" fill="#22d3ee" />
                        <text x="{{ $forecastStartX-7 }}" y="{{ $priceY($currentPrice)-10 }}" fill="#22d3ee" font-size="11" text-anchor="end" font-weight="900">{{ __('Aktuell') }} {{ number_format($currentPrice, 2, ',', '.') }} €</text>
                    </svg>
                    <div class="mt-2 flex flex-wrap gap-4 text-[9px] font-bold text-[var(--ak-muted)]"><span><i class="mr-1 inline-block h-2 w-3 rounded bg-yellow-300"></i>{{ __('steigende Kerze') }}</span><span><i class="mr-1 inline-block h-2 w-3 rounded bg-rose-400"></i>{{ __('fallende Kerze') }}</span><span><i class="mr-1 inline-block h-0.5 w-5 bg-yellow-300 align-middle"></i>{{ __('geringeres Restrisiko') }}</span><span><i class="mr-1 inline-block h-0.5 w-5 bg-orange-400 align-middle"></i>{{ __('erhöht') }}</span><span><i class="mr-1 inline-block h-0.5 w-5 bg-rose-400 align-middle"></i>{{ __('hoch') }}</span></div>
                </div>
            </article>
        </section>

        <section class="ak-card overflow-hidden rounded-2xl border border-yellow-300/25">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] px-5 py-4">
                <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-yellow-300">{{ __('Historische Gewinnanalyse') }}</p><h2 class="mt-1 text-xl font-black">{{ __('Gewinnchance und erreichbare Kursziele') }}</h2></div>
                <span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ __('Gleiche Score- und Volatilitätsklasse wie die Verlustanalyse') }}</span>
            </div>
            <div class="p-4">
                @foreach (['long' => ['LONG / CALL', 'border-yellow-300/25', 'text-yellow-300'], 'short' => ['SHORT / PUT', 'border-rose-400/25', 'text-rose-400']] as $gainSide => [$gainTitle, $gainBorder, $gainText])
                    @php $gainCells = $cells->where('side', $gainSide)->values(); @endphp
                    <article x-cloak x-show="side===@js($gainSide)" class="overflow-hidden rounded-xl border {{ $gainBorder }}">
                        <div class="flex items-center justify-between border-b border-[var(--ak-border)] px-4 py-3"><h3 class="text-xs font-black {{ $gainText }}">{{ $gainTitle }}</h3><span class="text-[9px] text-[var(--ak-muted)]">n = {{ (int) ($gainCells->first()['sample_size'] ?? 0) }}</span></div>
                        <div class="overflow-x-auto"><table class="w-full min-w-[560px] text-[10px]"><thead class="bg-[var(--ak-surface-muted)] text-[8px] uppercase text-[var(--ak-muted)]"><tr><th class="px-3 py-2 text-left">{{ __('Kursziel') }}</th><th class="px-2 py-2 text-right">{{ __('ca. Hebel') }}</th><th class="px-2 py-2 text-right">{{ __('20T-Gewinn') }}</th><th class="px-2 py-2 text-right">{{ __('Ziel erreicht') }}</th><th class="px-2 py-2 text-right">{{ __('Ø Ergebnis') }}</th><th class="px-3 py-2 text-right">{{ __('Upside Top 10 %') }}</th></tr></thead><tbody>
                            @foreach ($gainCells as $gainCell)
                                <tr class="border-t border-[var(--ak-border)]"><td class="px-3 py-2 font-black">+{{ number_format($gainCell['barrier_distance_percent'], 0) }} %</td><td class="px-2 py-2 text-right font-black text-orange-400">{{ number_format(100 / max(.01, $gainCell['barrier_distance_percent']), 1, ',', '.') }}×</td><td class="px-2 py-2 text-right font-black {{ $gainCell['gain_probability_percent'] >= 60 ? 'text-yellow-300' : ($gainCell['gain_probability_percent'] >= 40 ? 'text-orange-400' : 'text-rose-400') }}">{{ $historicalProbability($gainCell['gain_probability_percent'], $gainCell['sample_size']) }}</td><td class="px-2 py-2 text-right font-black {{ $gainCell['target_hit_probability_percent'] >= 50 ? 'text-yellow-300' : ($gainCell['target_hit_probability_percent'] >= 25 ? 'text-orange-400' : 'text-rose-400') }}">{{ $historicalProbability($gainCell['target_hit_probability_percent'], $gainCell['sample_size']) }}</td><td class="px-2 py-2 text-right {{ $gainCell['average_return_percent'] > 0 ? 'text-yellow-300' : 'text-rose-400' }}">{{ $gainCell['average_return_percent'] > 0 ? '+' : '' }}{{ number_format($gainCell['average_return_percent'], 1, ',', '.') }} %</td><td class="px-3 py-2 text-right font-black text-yellow-300">+{{ number_format($gainCell['expected_upside_10_percent'], 1, ',', '.') }} %</td></tr>
                            @endforeach
                        </tbody></table></div>
                    </article>
                @endforeach
            </div>
            <p class="border-t border-[var(--ak-border)] px-5 py-3 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('20T-Gewinn bezeichnet einen positiven Positionswert am Ende des Horizonts. „Ziel erreicht“ prüft, ob das jeweilige Kursziel innerhalb der 20 Handelstage mindestens einmal erreicht wurde. Upside Top 10 % ist der mittlere Positionsgewinn der besten zehn Prozent der historischen Fälle. * Bei keinem beobachteten Treffer wird statt 0 % die konservative 95-%-Obergrenze nach der Rule of Three gezeigt.') }}</p>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-2">
        <section class="ak-card min-w-0 overflow-hidden rounded-2xl border border-violet-400/25">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] px-5 py-4">
                <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-violet-400">{{ __('Monte-Carlo-Simulation') }}</p><h2 class="mt-1 text-xl font-black">{{ number_format($monteCarlo['simulations'], 0, ',', '.') }} {{ __('historisch simulierte 20T-Pfade') }}</h2></div>
                <span class="rounded-lg border border-violet-400/25 px-3 py-2 text-[9px] font-black text-violet-300">{{ $monteCarlo['history_returns'] }} {{ __('Tagesrenditen') }}</span>
            </div>
            <div class="border-b border-[var(--ak-border)] px-4 py-3">
                <svg viewBox="0 0 {{ $mcWidth }} {{ $mcHeight }}" class="h-auto w-full" role="img" aria-label="{{ __('Monte-Carlo-Fächerchart für die nächsten 20 Handelstage') }}">
                    @foreach ([0, .25, .5, .75, 1] as $tick)
                        @php $mcGridY = $mcPadding['top'] + $tick * $mcPlotHeight; $mcGridValue = $mcMax - $tick * $mcRange; @endphp
                        <line x1="{{ $mcPadding['left'] }}" x2="{{ $mcWidth-$mcPadding['right'] }}" y1="{{ $mcGridY }}" y2="{{ $mcGridY }}" stroke="currentColor" class="text-slate-400/15" stroke-dasharray="4 6" />
                        <text x="{{ $mcPadding['left']-8 }}" y="{{ $mcGridY+4 }}" text-anchor="end" fill="currentColor" class="text-[10px] text-[var(--ak-muted)]">{{ $mcGridValue > 0 ? '+' : '' }}{{ number_format($mcGridValue, 1, ',', '.') }} %</text>
                    @endforeach
                    @foreach ([0, 5, 10, 15, 20] as $day)
                        <text x="{{ $mcX($day) }}" y="{{ $mcHeight-10 }}" text-anchor="middle" fill="currentColor" class="text-[10px] text-[var(--ak-muted)]">T{{ $day }}</text>
                    @endforeach
                    <polygon points="{{ $mcOuterArea }}" fill="#8b5cf6" opacity=".12" />
                    <polygon points="{{ $mcInnerArea }}" fill="#22d3ee" opacity=".18" />
                    <polyline points="{{ $mcPoints('p10_percent') }}" fill="none" stroke="#8b5cf6" stroke-width="1.2" opacity=".65" />
                    <polyline points="{{ $mcPoints('p90_percent') }}" fill="none" stroke="#8b5cf6" stroke-width="1.2" opacity=".65" />
                    <polyline points="{{ $mcPoints('median_percent') }}" fill="none" stroke="#22d3ee" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <line x1="{{ $mcPadding['left'] }}" x2="{{ $mcWidth-$mcPadding['right'] }}" y1="{{ $mcY(0) }}" y2="{{ $mcY(0) }}" stroke="#fbbf24" stroke-width="1.2" stroke-dasharray="6 5" opacity=".8" />
                </svg>
                <div class="flex flex-wrap justify-center gap-5 text-[9px] font-bold text-[var(--ak-muted)]"><span><i class="mr-1 inline-block h-2 w-5 rounded bg-violet-500/40"></i>P10–P90</span><span><i class="mr-1 inline-block h-2 w-5 rounded bg-cyan-400/40"></i>Q25–Q75</span><span><i class="mr-1 inline-block h-0.5 w-5 bg-cyan-400 align-middle"></i>{{ __('Median') }}</span><span><i class="mr-1 inline-block h-0.5 w-5 bg-amber-400 align-middle"></i>{{ __('Break-even') }}</span></div>
            </div>
            <div class="p-4">
                @foreach (['long' => ['LONG / CALL', 'text-yellow-300', 'border-yellow-300/25'], 'short' => ['SHORT / PUT', 'text-rose-400', 'border-rose-400/25']] as $simulationSide => [$simulationTitle, $simulationText, $simulationBorder])
                    @php $simulation = $monteCarlo['sides'][$simulationSide]; @endphp
                    <article x-cloak x-show="side===@js($simulationSide)" class="rounded-xl border {{ $simulationBorder }} p-4">
                        <div class="flex items-center justify-between gap-3"><h3 class="text-xs font-black {{ $simulationText }}">{{ $simulationTitle }}</h3><span class="text-[10px] font-bold text-[var(--ak-muted)]">{{ __('Verlust') }} {{ number_format($simulation['loss_probability_percent'], 1, ',', '.') }} %</span></div>
                        <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                            @foreach ([['P10', $simulation['p10_return_percent']], [__('Median'), $simulation['median_return_percent']], ['P90', $simulation['p90_return_percent']], ['ES 10 %', $simulation['expected_shortfall_10_percent']]] as [$simulationLabel, $simulationValue])
                                <div class="rounded-lg border border-[var(--ak-border)] px-2 py-2"><p class="text-[8px] font-black uppercase text-[var(--ak-muted)]">{{ $simulationLabel }}</p><p class="mt-1 text-xs font-black {{ $simulationValue < 0 ? 'text-rose-400' : 'text-yellow-300' }}">{{ $simulationValue > 0 ? '+' : '' }}{{ abs($simulationValue) < .05 ? '< 0,1' : number_format($simulationValue, 1, ',', '.') }} %</p></div>
                            @endforeach
                        </div>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full min-w-[420px] text-[10px]"><thead class="text-[8px] uppercase text-[var(--ak-muted)]"><tr><th class="py-2 text-left">{{ __('Barriere') }}</th><th class="py-2 text-right">{{ __('ca. Hebel') }}</th><th class="py-2 text-right">{{ __('KO-Wahrsch.') }}</th><th class="py-2 text-right">{{ __('Gewinn-Wahrsch.') }}</th><th class="py-2 text-right">{{ __('Median Produkt') }}</th></tr></thead><tbody>
                                @foreach ($simulation['cells'] as $simulationCell)
                                    <tr class="border-t border-[var(--ak-border)]"><td class="py-2 font-black">{{ number_format($simulationCell['barrier_distance_percent'], 0) }} %</td><td class="py-2 text-right font-black text-orange-400">{{ number_format($simulationCell['indicative_leverage'], 1, ',', '.') }}×</td><td class="py-2 text-right font-black {{ $simulationCell['barrier_breach_probability_percent'] >= 35 ? 'text-rose-400' : ($simulationCell['barrier_breach_probability_percent'] >= 15 ? 'text-orange-400' : 'text-yellow-300') }}">{{ $simulationProbability($simulationCell['barrier_breach_probability_percent']) }}</td><td class="py-2 text-right {{ $simulationCell['profit_probability_percent'] >= 60 ? 'text-yellow-300' : ($simulationCell['profit_probability_percent'] >= 40 ? 'text-orange-400' : 'text-rose-400') }}">{{ $simulationProbability($simulationCell['profit_probability_percent']) }}</td><td class="py-2 text-right {{ $simulationCell['median_product_return_percent'] < 0 ? 'text-rose-400' : 'text-yellow-300' }}">{{ $simulationCell['median_product_return_percent'] > 0 ? '+' : '' }}{{ abs($simulationCell['median_product_return_percent']) < .05 ? '< 0,1' : number_format($simulationCell['median_product_return_percent'], 1, ',', '.') }} %</td></tr>
                                @endforeach
                            </tbody></table>
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="border-t border-[var(--ak-border)] px-5 py-3 text-[9px] leading-4 text-[var(--ak-muted)]">{{ __('Historisches Bootstrap-Verfahren: Die Simulation zieht Tagesrenditen aus der vorhandenen Kurshistorie und unterstellt keine Normalverteilung. KO-Wahrscheinlichkeit basiert auf simulierten Schlusskursen; Intraday-Barrieren, Finanzierungskosten, Aufgeld, Spread und Emittentenrisiko sind nicht enthalten.') }}</p>
        </section>

        <section class="ak-card min-w-0 overflow-hidden rounded-2xl border border-orange-400/25">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] px-5 py-4">
                <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-orange-400">{{ __('Verlustwahrscheinlichkeitsmatrix') }}</p><h2 class="mt-1 text-xl font-black">{{ __('Hebel × Verlustschwelle') }}</h2></div>
                <span class="text-[9px] font-bold text-[var(--ak-muted)]">{{ number_format($monteCarlo['simulations'], 0, ',', '.') }} {{ __('Monte-Carlo-Pfade · 20T') }}</span>
            </div>
            @foreach (['long' => 'LONG / CALL', 'short' => 'SHORT / PUT'] as $matrixSide => $matrixTitle)
                <div x-cloak x-show="side===@js($matrixSide)" class="overflow-x-auto p-4">
                    <div class="mb-3 flex items-center justify-between"><h3 class="text-xs font-black {{ $matrixSide === 'long' ? 'text-yellow-300' : 'text-rose-400' }}">{{ $matrixTitle }}</h3><span class="text-[9px] text-[var(--ak-muted)]">{{ __('Wahrscheinlichkeit für mindestens den angegebenen Verlust') }}</span></div>
                    <table class="w-full min-w-[720px] table-fixed text-center text-[9px]">
                        <thead class="text-[7px] uppercase text-[var(--ak-muted)]"><tr><th class="w-14 px-1 py-2 text-left">{{ __('Hebel') }}</th><th class="w-16 px-1 py-2 text-left">{{ __('impl. KO') }}</th>@foreach ($monteCarlo['loss_thresholds_percent'] as $lossThreshold)<th class="px-0.5 py-2">≥{{ $lossThreshold }}%</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($monteCarlo['loss_probability_matrix'][$matrixSide] as $matrixRow)
                                <tr class="border-t border-[var(--ak-border)]">
                                    <td class="px-1 py-2 text-left text-xs font-black text-orange-400">{{ $matrixRow['leverage'] }}×</td>
                                    <td class="px-1 py-2 text-left font-bold text-[var(--ak-muted)]">{{ number_format($matrixRow['implied_barrier_distance_percent'], 1, ',', '.') }}%</td>
                                    @foreach ($monteCarlo['loss_thresholds_percent'] as $lossThreshold)
                                        @php
                                            $matrixProbability = (float) $matrixRow['probabilities'][(string) $lossThreshold];
                                            $matrixHue = max(0, 52 - min(100, $matrixProbability) * .52);
                                            $matrixTextLightness = 72 - min(100, $matrixProbability) * .20;
                                            $matrixBorderOpacity = .34 + min(100, $matrixProbability) * .0046;
                                            $matrixBackgroundOpacity = .12 + min(100, $matrixProbability) * .0024;
                                            $matrixGlowOpacity = .04 + min(100, $matrixProbability) * .0014;
                                            $matrixCellStyle = "color:hsl({$matrixHue} 100% {$matrixTextLightness}%);border-color:hsl({$matrixHue} 100% 50% / {$matrixBorderOpacity});background:hsl({$matrixHue} 100% 45% / {$matrixBackgroundOpacity});box-shadow:inset 0 0 18px hsl({$matrixHue} 100% 45% / {$matrixGlowOpacity})";
                                        @endphp
                                        <td class="px-0.5 py-1"><button type="button" @click="loadCertificates(@js($matrixSide), {{ $matrixRow['leverage'] }}, {{ $lossThreshold }})" :class="selectedMatrix?.side===@js($matrixSide) && selectedMatrix?.leverage==={{ $matrixRow['leverage'] }} && selectedMatrix?.lossThreshold==={{ $lossThreshold }} ? 'ring-2 ring-cyan-300 ring-offset-1 ring-offset-transparent' : ''" class="block w-full rounded-md border px-0.5 py-1.5 font-black transition hover:scale-[1.04] focus:outline-none" style="{{ $matrixCellStyle }}" title="{{ __('Passende Zertifikate anzeigen') }}">{{ $simulationProbability($matrixProbability) }}</button></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            <div class="flex flex-wrap items-center gap-4 border-t border-[var(--ak-border)] px-5 py-3 text-[9px] font-bold text-[var(--ak-muted)]"><span>{{ __('geringeres Restrisiko') }}</span><span class="h-2 w-44 rounded-full border border-white/10" style="background:linear-gradient(90deg,#fde047 0%,#fb7c22 48%,#e6002d 100%);box-shadow:4px 0 12px rgba(230,0,45,.45)"></span><span>{{ __('hohes Risiko') }}</span><span>{{ __('Die implizite KO-Distanz ist eine Näherung von 1 ÷ Hebel. Intraday-Bewegungen und Produktkosten sind nicht enthalten.') }}</span></div>
        </section>
        </div>

        <section id="matrix-certificates" x-cloak x-show="selectedMatrix" class="ak-card scroll-mt-6 overflow-hidden rounded-2xl border border-cyan-400/25">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ak-border)] px-5 py-4">
                <div><p class="text-[9px] font-black uppercase tracking-[.16em] text-cyan-400">{{ __('Produktauswahl zur Matrixzelle') }}</p><h2 class="mt-1 text-xl font-black"><span x-text="selectedMatrix?.side === 'long' ? 'LONG / CALL' : 'SHORT / PUT'"></span> · <span x-text="selectedMatrix?.leverage + '×'"></span> · <span x-text="'≥ ' + selectedMatrix?.lossThreshold + ' % Verlust'"></span></h2></div>
                <div class="flex items-center gap-3"><span class="text-xs font-black text-[var(--ak-muted)]"><span x-text="productCount.toLocaleString('de-DE')"></span> {{ __('Produkte') }}</span><button type="button" @click="selectedMatrix=null;products=[]" class="rounded-lg border border-[var(--ak-border)] px-3 py-2 text-xs font-black">✕</button></div>
            </div>
            <div x-show="productsLoading" class="p-8 text-center text-sm font-black text-cyan-400">{{ __('Zertifikate werden geladen …') }}</div>
            <div x-show="productError" class="m-4 rounded-xl border border-rose-400/30 bg-rose-400/10 p-4 text-sm font-bold text-rose-300" x-text="productError"></div>
            <div x-show="!productsLoading && !productError">
                <p class="border-b border-[var(--ak-border)] px-5 py-3 text-[9px] leading-4 text-orange-300" x-text="productMessage"></p>
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-[10px]"><thead class="bg-[var(--ak-surface-muted)] text-[8px] uppercase text-[var(--ak-muted)]"><tr><th class="px-4 py-3">{{ __('Produkt') }}</th><th class="px-3 py-3">ISIN</th><th class="px-3 py-3">WKN</th><th class="px-3 py-3">{{ __('Typ') }}</th><th class="px-3 py-3">{{ __('Fälligkeit') }}</th><th class="px-3 py-3">{{ __('Börse') }}</th><th class="px-4 py-3 text-right">{{ __('Details') }}</th></tr></thead><tbody>
                    <template x-for="product in products" :key="product.isin"><tr class="border-t border-[var(--ak-border)]"><td class="max-w-sm px-4 py-3 font-black" x-text="product.name"></td><td class="px-3 py-3 font-mono" x-text="product.isin"></td><td class="px-3 py-3 font-mono" x-text="product.wkn || '—'"></td><td class="px-3 py-3 font-black" :class="product.type === 'CALL' ? 'text-yellow-300' : 'text-rose-400'" x-text="product.type"></td><td class="px-3 py-3" x-text="product.maturity || '—'"></td><td class="px-3 py-3" x-text="product.exchange"></td><td class="px-4 py-3 text-right"><a :href="product.url" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-cyan-400/25 px-3 py-2 font-black text-cyan-400">{{ __('Ansehen') }} ↗</a></td></tr></template>
                    <tr x-show="products.length===0"><td colspan="7" class="px-5 py-8 text-center text-[var(--ak-muted)]">{{ __('Keine passenden Produkte im aktuellen Referenzbestand.') }}</td></tr>
                </tbody></table></div>
                <p class="border-t border-[var(--ak-border)] px-5 py-3 text-[9px] text-[var(--ak-muted)]">{{ __('Es werden maximal 60 aktive Produkte angezeigt. Die Auswahl ist keine Anlageempfehlung. Ein Produkt darf erst nach Prüfung von Livekurs, tatsächlichem Hebel, Knock-out-Barriere, Spread, Bezugsverhältnis und Emittentenrisiko der Matrixzelle verbindlich zugeordnet werden.') }}</p>
            </div>
        </section>

        <section class="ak-card rounded-2xl border border-amber-400/25 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-amber-400">{{ __('Offizieller Produktbestand') }}</p><h2 class="mt-1 text-xl font-black">{{ number_format($productCounts->sum(), 0, ',', '.') }} {{ __('zugeordnete Produkte') }}</h2></div><div class="flex flex-wrap gap-2">@foreach ($productCounts as $type => $count)<span class="rounded-lg border border-[var(--ak-border)] px-3 py-2 text-xs font-black">{{ $type }} {{ number_format($count, 0, ',', '.') }}</span>@endforeach</div></div>
            <p class="mt-4 text-[10px] leading-5 text-[var(--ak-muted)]">{{ __('Die Matrix bewertet Bewegungen des Basiswerts innerhalb von 20 Handelstagen. Der angezeigte Näherungshebel entspricht Kurs geteilt durch Abstand zur hypothetischen Barriere. Für den exakten Produkthebel müssen aktuelle Knock-out- und Finanzierungsschwelle, Produktpreis, Aufgeld, Spread und Bezugsverhältnis ergänzt werden. Die Darstellung ist keine Anlageberatung.') }}</p>
        </section>
    </main>
</x-app-layout>
